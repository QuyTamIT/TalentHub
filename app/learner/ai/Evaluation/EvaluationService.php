<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;

final class EvaluationService
{
    public function __construct(
        private readonly RecommendationResultValidator $validator = new RecommendationResultValidator(),
        private readonly UnsupportedClaimDetector $unsupportedClaims = new UnsupportedClaimDetector(),
        private readonly UnsafeOutputDetector $unsafeOutput = new UnsafeOutputDetector(),
    ) {}

    public function compare(PersistedRecommendationRun $rule, PersistedRecommendationRun $modelAttempt, EvaluationExecutionMetadata $execution): EvaluationRecord
    {
        if ($rule->studentId() !== $modelAttempt->studentId() || $rule->snapshotId() !== $modelAttempt->snapshotId()) {
            throw new \InvalidArgumentException('Evaluation runs must have the same owner and snapshot.');
        }
        $providerFallback = $modelAttempt->result()->engineType() === 'rule' && $modelAttempt->result()->fallbackReason() !== null;
        if ($rule->result()->engineType() !== 'rule' || ($modelAttempt->result()->engineType() !== 'model' && !$providerFallback)) {
            throw new \InvalidArgumentException('Evaluation requires one Rule run and one model run.');
        }

        $schemaValid = !$providerFallback;
        if (!$providerFallback) {
            try { $this->validator->validate($modelAttempt->result()); } catch (\Throwable) { $schemaValid = false; }
        }
        $allowed = [];
        foreach ($modelAttempt->input()->evidenceReferences() as $evidence) {
            $allowed[$evidence['source_type'] . ':' . $evidence['source_id']] = true;
        }
        $required = 0; $matched = 0;
        foreach ($providerFallback ? [] : $modelAttempt->result()->items() as $item) {
            foreach ($item->evidence() as $evidence) {
                $required++;
                if (isset($allowed[$evidence->sourceType() . ':' . $evidence->sourceId()])) $matched++;
            }
        }
        $unsupported = $providerFallback ? [] : $this->unsupportedClaims->detect($modelAttempt->result(), $modelAttempt->input());
        $unsafe = $providerFallback ? [] : $this->unsafeOutput->detect($modelAttempt->result());
        $fallbackReason = $execution->get('fallbackReason', $modelAttempt->result()->fallbackReason());
        $resultType = $fallbackReason === null ? 'model' : 'rule_fallback';
        $status = $fallbackReason === null
            ? 'completed'
            : ($providerFallback ? 'fallback' : (($unsupported !== [] || $unsafe !== [] || !$schemaValid) ? 'gate_failed' : 'fallback'));

        return new EvaluationRecord([
            'id' => $execution->get('evaluationId'), 'studentId' => $rule->studentId(),
            'subjectRef' => $execution->get('subjectRef'), 'subjectRefVersion' => $execution->get('subjectRefVersion'),
            'attemptKey' => $execution->get('attemptKey'), 'ruleRunId' => $rule->runId(), 'modelRunId' => $modelAttempt->runId(),
            'snapshotId' => $rule->snapshotId(), 'educationBand' => $execution->get('educationBand'), 'cohortTags' => [],
            'provider' => (string) ($modelAttempt->result()->provider() ?? $execution->get('provider')),
            'modelVersion' => (string) ($modelAttempt->result()->modelVersion() ?? $execution->get('modelVersion')),
            'promptVersion' => (string) ($modelAttempt->result()->promptVersion() ?? $execution->get('promptVersion')),
            'ruleVersion' => (string) $rule->result()->ruleVersion(),
            'evaluatorVersion' => $execution->get('evaluatorVersion'), 'evaluationRevision' => $execution->get('evaluationRevision'),
            'supersedesEvaluationId' => $execution->get('supersedesEvaluationId'), 'inputSnapshotHash' => $modelAttempt->input()->contentHash(),
            'consentPolicyVersion' => $execution->get('consentPolicyVersion'), 'consentDecisionHash' => $execution->get('consentDecisionHash'),
            'consentEvaluatedAt' => $execution->get('consentEvaluatedAt'), 'schemaValid' => $schemaValid,
            'evidenceCoverage' => $required === 0 ? 0.0 : round($matched / $required, 6), 'evidenceMatched' => $matched, 'evidenceRequired' => $required,
            'unsupportedClaimCount' => count($unsupported), 'unsafeOutputCount' => count($unsafe), 'resultType' => $resultType,
            'fallbackReason' => $fallbackReason, 'providerErrorCategory' => $execution->get('providerErrorCategory'),
            'latencyMs' => $execution->get('latencyMs'), 'inputTokens' => $execution->get('inputTokens'), 'outputTokens' => $execution->get('outputTokens'),
            'estimatedCost' => $execution->get('estimatedCost'), 'costCurrency' => $execution->get('costCurrency'), 'status' => $status,
            'retentionClass' => $execution->get('retentionClass'), 'evaluatedAt' => $execution->get('evaluatedAt'), 'createdAt' => $execution->get('createdAt'),
        ]);
    }
}
