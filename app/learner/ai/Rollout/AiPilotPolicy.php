<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Rollout;

use TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;

final class AiPilotPolicy
{
    private readonly AiAvailabilityPolicy $availability;

    public function __construct(?AiAvailabilityPolicy $availability = null)
    {
        $this->availability = $availability ?? new AiAvailabilityPolicy();
    }

    public function eligibility(string $studentId, ConsentDecision $consent, bool $snapshotCurrent, bool $ruleFallbackCompleted, RecommendationConfig $config): PilotEligibility
    {
        if (!$config->shadowEnabled()) {
            return new PilotEligibility(false, 'shadow_disabled');
        }
        $decision = $this->availability->decide(
            $studentId,
            $config,
            $consent->allowedScopes(),
            $snapshotCurrent,
            false,
            $ruleFallbackCompleted,
        );
        return new PilotEligibility($decision->canShowModel(), $decision->canShowModel() ? 'eligible' : $decision->reason());
    }
}
