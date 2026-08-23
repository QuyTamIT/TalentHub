<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class EvaluationGateService
{
    /** @param array<string,mixed> $metrics @param array<string,array<string,mixed>> $cohorts @return array<string,mixed> */
    public function decide(array $metrics, array $cohorts, bool $independentReviewApproved): array
    {
        $reasons = [];
        if (($metrics['status'] ?? '') !== 'measured') $reasons[] = (string) ($metrics['reason'] ?? 'metrics_unavailable');
        foreach ($cohorts as $band => $cohort) if (($cohort['status'] ?? '') !== 'scored') $reasons[] = "{$band}_insufficient_sample";
        if (!$independentReviewApproved) $reasons[] = 'independent_review_missing';
        return [
            'decision' => $reasons === [] ? 'GATE_EVIDENCE_READY' : 'MODEL_VISIBLE_BLOCKED',
            'eligible_for_visible_rollout' => false,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }
}
