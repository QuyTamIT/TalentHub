<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Availability;

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Rollout\StagedRolloutGate;

final class AiAvailabilityPolicy
{
    /**
     * @param list<string> $allowedScopes
     * @param list<string>|null $requiredScopes Capability-specific consent boundary; defaults to all learner AI scopes.
     */
    public function decide(
        string $studentId,
        RecommendationConfig $config,
        array $allowedScopes,
        bool $snapshotCurrent,
        bool $hasActiveModel,
        bool $ruleFallbackCompleted,
        ?array $requiredScopes = null,
        ?array $rolloutEvidence = null,
    ): AiAvailabilityDecision {
        $requiredScopes ??= ConsentDecision::REQUIRED_SCOPES;
        $consentReady = $this->permits($allowedScopes, $requiredScopes);
        $assigned = $this->isAssigned($studentId, $config);
        $approvalReady = is_string($config->pilotApprovalReference())
            && trim((string) $config->pilotApprovalReference()) !== '';

        $fullVisibilityGate = $this->fullVisibilityGate($config, $rolloutEvidence);
        $visibleEligible = $config->enabled()
            && $config->shadowGateApproved()
            && $config->visiblePercent() > 0
            && !$config->pilotPaused()
            && $approvalReady
            && $consentReady
            && $assigned;
        $visibleEligible = $visibleEligible && $fullVisibilityGate;
        $canRunShadow = $config->enabled()
            && $config->shadowEnabled()
            && $config->shadowGateApproved()
            && $consentReady
            && $snapshotCurrent;
        $visibleRefreshEligible = $visibleEligible && $snapshotCurrent;
        $canShowModel = $visibleRefreshEligible && $ruleFallbackCompleted;
        $canRefresh = $canRunShadow || $canShowModel;
        $canServeActiveModel = $hasActiveModel && $visibleEligible && $snapshotCurrent;
        $canServeStaleModel = $hasActiveModel && $visibleEligible && !$snapshotCurrent;

        [$state, $reason] = match (true) {
            $canServeActiveModel => ['ready_model', 'active_model_ready'],
            $canServeStaleModel => ['stale_model', 'snapshot_stale'],
            $hasActiveModel => ['ai_unavailable', $this->gateReason($studentId, $config, $consentReady, $snapshotCurrent, $assigned, $approvalReady)],
            $ruleFallbackCompleted => ['ready_rule', $this->gateReason($studentId, $config, $consentReady, $snapshotCurrent, $assigned, $approvalReady)],
            $canRefresh => ['pending', $visibleRefreshEligible && !$ruleFallbackCompleted ? 'rule_fallback_missing' : ($canRunShadow && !$canShowModel ? 'shadow_only' : 'refresh_allowed')],
            $visibleRefreshEligible && !$ruleFallbackCompleted => ['ai_unavailable', 'rule_fallback_missing'],
            default => ['ai_unavailable', $this->gateReason($studentId, $config, $consentReady, $snapshotCurrent, $assigned, $approvalReady)],
        };

        return new AiAvailabilityDecision(
            $state,
            $reason,
            $canRefresh,
            $canRunShadow,
            $canShowModel,
            $canServeActiveModel,
            $canServeStaleModel,
        );
    }

    /** A 100% rollout is never inferred from environment alone. */
    private function fullVisibilityGate(RecommendationConfig $config, ?array $evidence): bool
    {
        if (!in_array($config->visiblePercent(), [10, 25, 50, 100], true)) return true;
        if ($evidence === null) return false;
        foreach (['stage', 'error_budget', 'freshness_sla', 'validator_pass_rate', 'privacy_review', 'rollback_drill', 'approval_reference', 'enabled', 'shadow_gate_approved', 'pilot_paused', 'completed_stages', 'visible_percent'] as $key) {
            if (!array_key_exists($key, $evidence)) return false;
        }
        if ($config->visiblePercent() === 100) foreach (['unified_policy_verified', 'last_known_good_verified', 'queue_monitoring_verified'] as $key) if (!array_key_exists($key, $evidence)) return false;
        if ($evidence['visible_percent'] !== $config->visiblePercent() || $evidence['enabled'] !== $config->enabled() || $evidence['shadow_gate_approved'] !== $config->shadowGateApproved() || $evidence['pilot_paused'] !== $config->pilotPaused()) return false;
        if (!is_string($evidence['stage'])) return false;
        $next = (string) $config->visiblePercent();
        return (new StagedRolloutGate())->canAdvance($evidence['stage'], $next, $evidence)['allowed'];
    }

    public function isAssigned(string $studentId, RecommendationConfig $config): bool
    {
        $studentId = strtolower(trim($studentId));
        if ($studentId === '' || $config->visiblePercent() <= 0) return false;
        if ($config->visiblePercent() >= 100) return true;
        return hexdec(substr(hash('sha256', $studentId), 0, 8)) % 100 < $config->visiblePercent();
    }

    /** @param list<string> $allowedScopes @param list<string> $requiredScopes */
    private function permits(array $allowedScopes, array $requiredScopes): bool
    {
        $allowed = [];
        foreach ($allowedScopes as $scope) if (is_string($scope)) $allowed[trim($scope)] = true;
        foreach ($requiredScopes as $scope) {
            if (!is_string($scope) || !isset($allowed[trim($scope)])) return false;
        }
        return $requiredScopes !== [];
    }

    private function gateReason(
        string $studentId,
        RecommendationConfig $config,
        bool $consentReady,
        bool $snapshotCurrent,
        bool $assigned,
        bool $approvalReady,
    ): string {
        if (!$config->enabled()) return 'ai_disabled';
        if (!$config->shadowGateApproved()) return 'shadow_gate_unapproved';
        if ($config->visiblePercent() <= 0) return 'visibility_zero';
        if ($config->pilotPaused()) return 'pilot_paused';
        if (!$approvalReady) return 'approval_missing';
        if (!$consentReady) return 'consent_missing';
        if (!$snapshotCurrent) return 'snapshot_stale';
        if (trim($studentId) === '' || !$assigned) return 'outside_bucket';
        return 'rule_ready';
    }
}
