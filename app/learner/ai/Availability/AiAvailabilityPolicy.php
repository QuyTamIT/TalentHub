<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Availability;

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;

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

        // Authenticated learner product access is an application entitlement.
        // Deployment experiments remain useful for telemetry, but do not decide
        // whether an individual learner may use an enabled capability.
        $canShowModel = $config->enabled() && $consentReady && $snapshotCurrent;
        $canRefresh = $config->enabled() && $consentReady;
        $canRunShadow = $config->enabled()
            && $config->shadowEnabled()
            && $consentReady
            && $snapshotCurrent;
        $canServeActiveModel = $hasActiveModel && $canShowModel;
        $canServeStaleModel = $hasActiveModel && $config->enabled() && $consentReady && !$snapshotCurrent;

        [$state, $reason] = match (true) {
            $canServeActiveModel => ['ready_model', 'active_model_ready'],
            $canServeStaleModel => ['stale_model', 'snapshot_stale'],
            $canShowModel => ['pending', 'refresh_allowed'],
            !$config->enabled() => ['ai_unavailable', 'ai_disabled'],
            !$consentReady => ['ai_unavailable', 'consent_missing'],
            default => ['ai_unavailable', 'snapshot_stale'],
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

}
