<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class EvaluationReportGenerator
{
    /** @param array<string,mixed> $gate @param array<string,mixed> $metrics @param array<string,mixed> $configuration @return array<string,mixed> */
    public function generate(array $gate, array $metrics, array $configuration): array
    {
        foreach (['studentId', 'api_key', 'authorization', 'raw_response'] as $forbidden) unset($configuration[$forbidden], $metrics[$forbidden]);
        return [
            'decision' => $gate['decision'] ?? 'MODEL_VISIBLE_BLOCKED',
            'eligible_for_visible_rollout' => (bool) ($gate['eligible_for_visible_rollout'] ?? false),
            'reasons' => $gate['reasons'] ?? ['gate_evidence_missing'],
            'metrics' => $metrics, 'configuration' => $configuration,
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }
}
