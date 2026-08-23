<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Evaluation\RecommendationEvaluator;
use TalentHub\Learner\Ai\Evaluation\ShadowRunService;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
use TalentHub\Learner\Ai\Quality\DataQualityGate;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;
use TalentHub\Learner\Ai\Service\RecommendationResponseMapper;
use TalentHub\Learner\Ai\Service\RecommendationService;
use TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder;
use TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseAssessmentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseConsentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;
use TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseStudentProfileSource;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;

require_once dirname(__DIR__, 3) . '/app/learner/ai/bootstrap.php';
require_once __DIR__ . '/CompleteAiDemoDataset.php';

final class CompleteAiDemoAiRunner
{
    /**
     * @param list<string> $studentIds
     * @return array<string,array<string,mixed>>
     */
    public static function run(PDO $pdo, RecommendationEngine $modelEngine, array $studentIds, ?DateTimeImmutable $clock = null): array
    {
        $clock = ($clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
        $consentPolicy = new ConsentPolicy(new DatabaseConsentSource($pdo));
        $snapshotBuilder = new RecommendationSnapshotBuilder(
            new DatabaseStudentProfileSource($pdo),
            new DatabaseSkillSource($pdo),
            new DatabaseAssessmentSource($pdo),
            new DatabaseActivityExperienceSource($pdo),
            new DatabasePublishedEvaluationSource($pdo),
            new DatabaseOpportunitySource($pdo, $clock),
        );
        $qualityGate = new DataQualityGate($clock);
        $ruleEngine = new RuleRecommendationEngine();
        $validator = new RecommendationResultValidator();
        $repository = new DatabaseRecommendationRepository($pdo);
        $shadowService = new ShadowRunService($repository, $modelEngine, new RecommendationEvaluator($validator));
        $report = [];

        foreach ($studentIds as $studentId) {
            if (!is_string($studentId) || trim($studentId) === '') {
                throw new RuntimeException('Demo hero student ID is required.');
            }
            $studentId = trim($studentId);
            if (array_key_exists($studentId, $report)) {
                throw new RuntimeException('Demo hero student IDs must be unique.');
            }

            $owner = str_starts_with($studentId, '20000000-') ? 'thpt' : 'fpt';
            $scopes = $consentPolicy->allowedScopes($studentId);
            $input = $snapshotBuilder->build($studentId, $scopes);
            $quality = $qualityGate->evaluate($input);
            if ($quality->state() !== 'ready') {
                throw new RuntimeException('Demo hero failed quality gate: ' . $quality->state());
            }

            $context = new RecommendationContext(
                $scopes,
                CompleteAiDemoDataset::uuid($owner, 'ai-request', $input->contentHash()),
                'demo-rule-' . substr(hash('sha256', $studentId . ':' . $input->contentHash()), 0, 64),
                $studentId,
            );
            $visibleResult = $ruleEngine->generate($input, $context);

            $service = new RecommendationService(
                $repository,
                $ruleEngine,
                $validator,
                new RecommendationResponseMapper(),
                static fn (string $candidate): bool => hash_equals($studentId, $candidate),
                static fn (string $candidate): array => $consentPolicy->allowedScopes($candidate),
                static fn (string $candidate, array $allowedScopes) => $snapshotBuilder->build($candidate, $allowedScopes),
                static fn ($candidateInput) => $qualityGate->evaluate($candidateInput),
                static fn ($candidateInput): bool => true,
            );
            $visibleRun = $service->generate(
                $studentId,
                (string) $context->requestId(),
                (string) $context->idempotencyKey(),
            );
            self::assertVisibleRun($pdo, $studentId, $visibleRun);

            $visibleReport = [
                'quality_state' => 'ready',
                'visible_engine' => 'rule',
                'visible_item_count' => count($visibleResult->items()),
            ];
            $persistedShadow = self::completedShadowReport($pdo, $studentId, $input->contentHash());
            if ($persistedShadow !== null) {
                $report[$studentId] = $visibleReport + $persistedShadow;
                continue;
            }

            $shadowExecution = $shadowService->run($studentId, $input, $context, $visibleResult);
            $shadowResult = $shadowExecution['shadow_result'];
            $evaluation = $shadowExecution['evaluation'];

            $report[$studentId] = $visibleReport + [
                'shadow_engine' => $shadowResult->engineType(),
                'shadow_valid' => $evaluation['valid'] === true,
                'shadow_violation_codes' => is_array($evaluation['violations'])
                    ? array_values($evaluation['violations'])
                    : [],
            ];
        }

        return $report;
    }

    /** @param array<string,mixed> $run */
    private static function assertVisibleRun(PDO $pdo, string $studentId, array $run): void
    {
        if (($run['state'] ?? null) === 'ready_rule'
            && ($run['status'] ?? null) === 'completed'
            && ($run['engine_type'] ?? null) === 'rule') {
            return;
        }

        if (($run['state'] ?? null) !== 'pending'
            || ($run['reused'] ?? false) !== true
            || ($run['status'] ?? null) !== 'completed') {
            throw new RuntimeException('Demo visible rule run was not completed.');
        }

        $runId = is_string($run['run_id'] ?? null) ? trim($run['run_id']) : '';
        if ($runId === '') {
            throw new RuntimeException('Demo visible completed run could not be loaded.');
        }
        $statement = $pdo->prepare(
            'SELECT engineType, status FROM learner_recommendation_runs WHERE id = :runId AND studentId = :studentId LIMIT 1',
        );
        $statement->execute(['runId' => $runId, 'studentId' => $studentId]);
        $persisted = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($persisted)
            || ($persisted['engineType'] ?? null) !== 'rule'
            || ($persisted['status'] ?? null) !== 'completed') {
            throw new RuntimeException('Demo visible completed run could not be loaded.');
        }
    }

    /** @return array{shadow_engine:string,shadow_valid:bool,shadow_violation_codes:list<string>}|null */
    private static function completedShadowReport(PDO $pdo, string $studentId, string $contentHash): ?array
    {
        $statement = $pdo->prepare(<<<'SQL'
SELECT
    runs.engineType,
    runs.provider,
    runs.modelVersion,
    runs.promptVersion,
    runs.fallbackReason,
    COUNT(DISTINCT items.id) AS itemCount,
    COUNT(DISTINCT CASE WHEN evidence.id IS NOT NULL THEN items.id END) AS evidencedItemCount
FROM learner_recommendation_runs runs
LEFT JOIN learner_recommendation_items items ON items.runId = runs.id
LEFT JOIN learner_recommendation_evidence evidence ON evidence.itemId = items.id
WHERE runs.studentId = :studentId
  AND runs.idempotencyKey = :idempotencyKey
  AND runs.status = 'completed'
  AND runs.engineType = 'model'
GROUP BY runs.id, runs.engineType, runs.provider, runs.modelVersion, runs.promptVersion, runs.fallbackReason
SQL);
        $statement->execute([
            'studentId' => $studentId,
            'idempotencyKey' => 'shadow-' . hash('sha256', $contentHash),
        ]);
        $run = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($run)) {
            return null;
        }

        $itemCount = (int) ($run['itemCount'] ?? 0);
        $valid = ($run['engineType'] ?? null) === 'model'
            && is_string($run['provider'] ?? null) && trim($run['provider']) !== ''
            && is_string($run['modelVersion'] ?? null) && trim($run['modelVersion']) !== ''
            && is_string($run['promptVersion'] ?? null) && trim($run['promptVersion']) !== ''
            && ($run['fallbackReason'] ?? null) === null
            && $itemCount > 0
            && (int) ($run['evidencedItemCount'] ?? 0) === $itemCount;

        return [
            'shadow_engine' => 'model',
            'shadow_valid' => $valid,
            'shadow_violation_codes' => $valid ? [] : ['persisted_shadow_incomplete'],
        ];
    }
}
