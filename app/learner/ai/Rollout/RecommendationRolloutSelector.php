<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Rollout;

use TalentHub\Learner\Ai\Availability\AiAvailabilityDecision;
use TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;

final class RecommendationRolloutSelector
{
    private readonly AiAvailabilityPolicy $policy;
    /** @var array<string,mixed>|null */
    private readonly ?array $rolloutEvidence;

    /** @param array<string,mixed>|null $rolloutEvidence */
    public function __construct(?AiAvailabilityPolicy $policy = null, ?array $rolloutEvidence = null)
    {
        $this->policy = $policy ?? new AiAvailabilityPolicy();
        $this->rolloutEvidence = $rolloutEvidence;
    }

    /** @param list<string> $allowedScopes */
    public function canShowModel(string $studentId, RecommendationConfig $config, array $allowedScopes, bool $snapshotCurrent, ?array $rolloutEvidence = null): bool
    {
        return $this->decision($studentId, $config, $allowedScopes, $snapshotCurrent, false, true, null, $rolloutEvidence ?? $this->rolloutEvidence)->canShowModel();
    }

    public function isAssigned(string $studentId, RecommendationConfig $config): bool
    {
        return $this->policy->isAssigned($studentId, $config);
    }

    /** @return array<string,mixed>|null */
    public function rolloutEvidence(): ?array
    {
        return $this->rolloutEvidence;
    }

    /** @param list<string> $allowedScopes */
    public function canShowRoadmapModel(string $studentId, RecommendationConfig $config, array $allowedScopes, bool $snapshotCurrent, ?array $rolloutEvidence = null): bool
    {
        return $this->decision($studentId, $config, $allowedScopes, $snapshotCurrent, false, true, ['assessment'], $rolloutEvidence ?? $this->rolloutEvidence)->canShowModel();
    }

    /** @param list<string> $allowedScopes @param list<string>|null $requiredScopes */
    public function decision(
        string $studentId,
        RecommendationConfig $config,
        array $allowedScopes,
        bool $snapshotCurrent,
        bool $hasActiveModel,
        bool $ruleFallbackCompleted,
        ?array $requiredScopes = null,
        ?array $rolloutEvidence = null,
    ): AiAvailabilityDecision {
        return $this->policy->decide(
            $studentId,
            $config,
            $allowedScopes,
            $snapshotCurrent,
            $hasActiveModel,
            $ruleFallbackCompleted,
            $requiredScopes ?? ConsentDecision::REQUIRED_SCOPES,
            $rolloutEvidence ?? $this->rolloutEvidence,
        );
    }
}
