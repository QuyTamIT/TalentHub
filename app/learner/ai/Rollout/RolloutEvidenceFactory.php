<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Rollout;

use TalentHub\Learner\Ai\Config\RecommendationConfig;

/**
 * Builds the canonical rollout-evidence array consumed by `AiAvailabilityPolicy`
 * and `StagedRolloutGate`.
 *
 * The evidence map is the single contract that runtime and diagnostics share
 * for the staged 100% rollout. Centralising the parsing keeps `LearnerApiContext`
 * declarative and makes the gate behaviour reproducible from the
 * `tests/learner_ai_team_100_rollout_test.php` contract.
 */
final class RolloutEvidenceFactory
{
    /** @var list<string> */
    private const STAGES = ['shadow', 'pilot', '10', '25', '50', '100'];

    /**
     * @param array<string,string> $environment
     * @return array<string,mixed>|null
     */
    public static function fromEnvironment(RecommendationConfig $config, array $environment): ?array
    {
        $lookup = static function (string $key) use ($environment): string {
            if (array_key_exists($key, $environment)) return trim((string) $environment[$key]);
            $raw = getenv($key);
            return is_string($raw) ? trim($raw) : '';
        };

        $stage = strtolower($lookup('TALENTHUB_AI_ROLLOUT_STAGE'));
        if (!in_array($stage, self::STAGES, true)) {
            return null;
        }

        $verified = static fn (string $key): bool => strtolower($lookup($key)) === 'true';
        $completedStages = array_values(array_filter(array_map('trim', explode(',', $lookup('TALENTHUB_AI_COMPLETED_STAGES'))), static fn (string $value): bool => $value !== ''));

        $pilotPaused = $config->pilotPaused();

        return [
            'stage' => $stage,
            'error_budget' => $verified('TALENTHUB_AI_ERROR_BUDGET_VERIFIED'),
            'freshness_sla' => $verified('TALENTHUB_AI_FRESHNESS_SLA_VERIFIED'),
            'validator_pass_rate' => $verified('TALENTHUB_AI_VALIDATOR_PASS_RATE_VERIFIED'),
            'privacy_review' => $verified('TALENTHUB_AI_PRIVACY_REVIEW_VERIFIED'),
            'rollback_drill' => $verified('TALENTHUB_AI_ROLLBACK_DRILL_VERIFIED'),
            'approval_reference' => $config->pilotApprovalReference(),
            'enabled' => $config->enabled(),
            'shadow_gate_approved' => $config->shadowGateApproved(),
            'pilot_paused' => $pilotPaused,
            'visible_percent' => $config->visiblePercent(),
            'completed_stages' => $completedStages,
            'unified_policy_verified' => $verified('TALENTHUB_AI_UNIFIED_POLICY_VERIFIED'),
            'last_known_good_verified' => $verified('TALENTHUB_AI_LAST_KNOWN_GOOD_VERIFIED'),
            'queue_monitoring_verified' => $verified('TALENTHUB_AI_QUEUE_MONITORING_VERIFIED'),
        ];
    }
}
