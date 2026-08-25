<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Rollout;

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;

final class AiPilotPolicy
{
    public function eligibility(string $studentId, ConsentDecision $consent, bool $snapshotCurrent, bool $ruleFallbackCompleted, RecommendationConfig $config): PilotEligibility
    {
        if (!$config->enabled()) return new PilotEligibility(false, 'ai_disabled');
        if (!$config->shadowEnabled()) return new PilotEligibility(false, 'shadow_disabled');
        if (!$config->shadowGateApproved()) return new PilotEligibility(false, 'shadow_gate_unapproved');
        if ($config->visiblePercent() === 0) return new PilotEligibility(false, 'visibility_zero');
        if ($config->pilotPaused()) return new PilotEligibility(false, 'pilot_paused');
        if ($config->pilotApprovalReference() === null) return new PilotEligibility(false, 'approval_missing');
        if (!$consent->permitsAllRequiredScopes()) return new PilotEligibility(false, $consent->denialReason() ?? 'consent_missing');
        if (!$snapshotCurrent) return new PilotEligibility(false, 'snapshot_stale');
        if (!$ruleFallbackCompleted) return new PilotEligibility(false, 'rule_fallback_missing');
        $assigned = (new RecommendationRolloutSelector())->isAssigned($studentId, $config);
        return new PilotEligibility($assigned, $assigned ? 'eligible' : 'outside_bucket');
    }
}
