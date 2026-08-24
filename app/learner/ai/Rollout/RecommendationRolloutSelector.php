<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Rollout;

use TalentHub\Learner\Ai\Config\RecommendationConfig;

final class RecommendationRolloutSelector
{
    /** @param list<string> $allowedScopes */
    public function canShowModel(string $studentId, RecommendationConfig $config, array $allowedScopes, bool $snapshotCurrent): bool
    {
        $required = ['assessment' => true, 'skills' => true, 'activity' => true, 'evaluation' => true];
        foreach ($allowedScopes as $scope) {
            if (is_string($scope)) {
                unset($required[$scope]);
            }
        }
        return $config->enabled()
            && $config->shadowGateApproved()
            && $config->visiblePercent() > 0
            && !$config->pilotPaused()
            && $config->pilotApprovalReference() !== null
            && $snapshotCurrent
            && $required === []
            && $this->isAssigned($studentId, $config);
    }

    public function isAssigned(string $studentId, RecommendationConfig $config): bool
    {
        if ($config->visiblePercent() <= 0 || trim($studentId) === '') {
            return false;
        }
        if ($config->visiblePercent() >= 100) {
            return true;
        }
        $bucket = hexdec(substr(hash('sha256', strtolower(trim($studentId))), 0, 8)) % 100;
        return $bucket < $config->visiblePercent();
    }

    /** @param list<string> $allowedScopes */
    public function canShowRoadmapModel(string $studentId, RecommendationConfig $config, array $allowedScopes, bool $snapshotCurrent): bool
    {
        return in_array('assessment', $allowedScopes, true)
            && $config->enabled()
            && $config->shadowGateApproved()
            && $config->visiblePercent() > 0
            && !$config->pilotPaused()
            && $config->pilotApprovalReference() !== null
            && $snapshotCurrent
            && $this->isAssigned($studentId, $config);
    }
}
