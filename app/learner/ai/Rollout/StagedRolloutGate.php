<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Rollout;

/** Fail-closed advancement gate for the approved staged rollout sequence. */
final class StagedRolloutGate
{
    /** @var list<string> */
    public const STAGES = ['shadow', 'pilot', '10', '25', '50', '100'];

    /** @param array<string,mixed> $checks @return array{allowed:bool,stage:string,next_stage:string,reasons:list<string>} */
    public function canAdvance(string $stage, string $nextStage, array $checks): array
    {
        $stage = strtolower(trim($stage));
        $nextStage = strtolower(trim($nextStage));
        $reasons = [];
        $from = array_search($stage, self::STAGES, true);
        $to = array_search($nextStage, self::STAGES, true);
        if ($from === false || $to === false || $to !== $from + 1) $reasons[] = 'invalid_stage_transition';
        foreach (['error_budget', 'freshness_sla', 'validator_pass_rate', 'privacy_review', 'rollback_drill'] as $check) {
            if (($checks[$check] ?? false) !== true) $reasons[] = "{$check}_missing";
        }
        if ($nextStage !== 'shadow') {
            $approval = $checks['approval_reference'] ?? null;
            if (!is_string($approval) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{2,127}\z/', $approval) !== 1) {
                $reasons[] = 'approval_reference_missing';
            }
        }
        if ($nextStage === '100') {
            foreach (['enabled', 'shadow_gate_approved'] as $check) if (($checks[$check] ?? false) !== true) $reasons[] = "{$check}_missing";
            if (($checks['pilot_paused'] ?? true) !== false) $reasons[] = 'pilot_paused';
            foreach (['pilot', '10', '25', '50'] as $completed) if (!in_array($completed, (array) ($checks['completed_stages'] ?? []), true)) $reasons[] = "stage_{$completed}_missing";
            if (!array_key_exists('visible_percent', $checks) || !is_int($checks['visible_percent']) || $checks['visible_percent'] !== 100) $reasons[] = 'visible_percent_missing';
            foreach (['unified_policy_verified', 'last_known_good_verified', 'queue_monitoring_verified'] as $check) {
                if (($checks[$check] ?? false) !== true) $reasons[] = "{$check}_missing";
            }
        }
        return ['allowed' => $reasons === [], 'stage' => $stage, 'next_stage' => $nextStage, 'reasons' => array_values(array_unique($reasons))];
    }

    /** @return list<string> */
    public function stages(): array { return self::STAGES; }
}
