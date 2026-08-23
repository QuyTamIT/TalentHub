<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

use Closure;
use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Persistence\EvaluationRepository;
use TalentHub\Learner\Ai\Persistence\ModelAttemptRepository;

final class ShadowEvaluationOrchestrator
{
    /** @var Closure(EvaluationSubject):array{rule:PersistedRecommendationRun,context:\TalentHub\Learner\Ai\Domain\RecommendationContext} */
    private readonly Closure $ruleLoader;
    /** @var Closure(EvaluationSubject,string):EvaluationExecutionMetadata */
    private readonly Closure $metadataFactory;

    /**
     * @param callable(EvaluationSubject):array{rule:PersistedRecommendationRun,context:\TalentHub\Learner\Ai\Domain\RecommendationContext} $ruleLoader
     * @param callable(EvaluationSubject,string):EvaluationExecutionMetadata $metadataFactory
     * @param array{provider:string,model_version:string,prompt_version:string} $modelVersions
     */
    public function __construct(
        callable $ruleLoader,
        private readonly RecommendationEngine $modelEngine,
        private readonly ModelAttemptRepository $modelAttempts,
        private readonly EvaluationRepository $evaluations,
        private readonly EvaluationService $evaluationService,
        callable $metadataFactory,
        private readonly array $modelVersions,
    ) {
        $this->ruleLoader = Closure::fromCallable($ruleLoader);
        $this->metadataFactory = Closure::fromCallable($metadataFactory);
        foreach (['provider','model_version','prompt_version'] as $field) if (trim((string)($modelVersions[$field]??''))==='') throw new \InvalidArgumentException('Reviewed model versions are required.');
    }

    public function evaluate(EvaluationSubject $subject, EvaluationManifest $manifest): EvaluationRecord
    {
        $approved = false;
        foreach ($manifest->subjects() as $candidate) if (hash_equals($candidate->subjectRef(), $subject->subjectRef())) { $approved = true; break; }
        if (!$approved) throw new \InvalidArgumentException('Evaluation subject is not present in the approved manifest.');

        $loaded = ($this->ruleLoader)($subject);
        $rule = $loaded['rule'] ?? null; $context = $loaded['context'] ?? null;
        if (!$rule instanceof PersistedRecommendationRun || !$context instanceof \TalentHub\Learner\Ai\Domain\RecommendationContext) throw new \RuntimeException('Rule run loader returned an invalid contract.');
        if ($rule->studentId() !== $subject->studentId() || $rule->runId() !== $subject->ruleRunId()
            || $rule->snapshotId() !== $subject->snapshotId() || !hash_equals($rule->input()->contentHash(), $subject->snapshotHash())) {
            throw new \InvalidArgumentException('Rule run does not match the approved subject snapshot.');
        }

        $pending = $this->modelAttempts->createPendingModelAttempt($subject->studentId(), $rule->input(), $context, $this->modelVersions);
        $runId = trim((string)($pending['runId'] ?? ''));
        if ($runId === '' || (string)($pending['snapshotId'] ?? '') !== $subject->snapshotId()) throw new \RuntimeException('Model attempt does not match the approved snapshot.');
        if (($pending['reused'] ?? false) === true) {
            $existing = $this->evaluations->latestByModelRun($subject->studentId(), $runId);
            if (is_array($existing)) return new EvaluationRecord($existing);
            throw new \RuntimeException('reconciliation_required');
        }

        $result = $this->modelEngine->generate($rule->input(), $context);
        $modelRun = new PersistedRecommendationRun($subject->studentId(), $runId, $subject->snapshotId(), $result->fallbackReason() === null ? 'completed' : 'fallback', $result, $rule->input());
        $metadata = ($this->metadataFactory)($subject, $runId);
        $evaluation = $this->evaluationService->compare($rule, $modelRun, $metadata);
        $terminal = $result->fallbackReason() === null
            ? $this->modelAttempts->completeModelAttemptWithEvaluation($subject->studentId(), $runId, $result, $evaluation)
            : $this->modelAttempts->failModelAttemptWithEvaluation($subject->studentId(), $runId, (string)$result->fallbackReason(), $evaluation);
        $row = $terminal['record'] ?? null;
        return is_array($row) ? new EvaluationRecord($row) : $evaluation;
    }
}
