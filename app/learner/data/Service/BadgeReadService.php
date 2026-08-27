<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use TalentHub\Learner\Data\Contracts\BadgeRepository;
use TalentHub\Learner\Data\Contracts\StatisticsRepository;
use TalentHub\Learner\Data\Domain\LevelProgression;

final class BadgeReadService
{
    public function __construct(
        private readonly BadgeRepository $badgeRepo,
        private readonly StatisticsRepository $statsRepo,
        private readonly BadgeRuleEngine $ruleEngine
    ) {}

    /**
     * Read-only view of student's awarded badges, active rule progression, lifetime facts, and level.
     * Note: This method NEVER evaluates or mutates awards.
     *
     * @return array{
     *     badges: list<array<string,mixed>>,
     *     progress: list<array<string,mixed>>,
     *     facts: array{confirmed_experience_hours: float, attended_activity_count: int, submitted_assessment_type_count: int, published_teacher_evaluation_count: int},
     *     level: array<string,mixed>
     * }
     */
    public function forStudent(string $studentId): array
    {
        $awarded = $this->badgeRepo->awardedBadges($studentId);
        $facts = $this->statsRepo->lifetimeFacts($studentId);
        $level = LevelProgression::fromHours((float) $facts['confirmed_experience_hours']);
        // School-owned credentials are rendered by SchoolCredentialService.
        // Keep this legacy collection global-only to avoid duplicate cards.
        $activeRules = $this->badgeRepo->activeRules();

        $awardedMap = [];
        foreach ($awarded as $a) {
            $awardedMap[$a['id']] = $a;
        }

        $progress = [];
        foreach ($activeRules as $item) {
            $badge = $item['badge'];
            $rule = $item['rule'];
            $isAwarded = isset($awardedMap[$badge['id']]);

            $eval = $this->ruleEngine->evaluate($rule['thresholdCriteria'], $facts);

            if ($isAwarded) {
                $status = 'achieved';
                $progressPercent = 100;
            } elseif ($eval['eligible'] || $eval['progressPercent'] > 0) {
                $status = 'in_progress';
                $progressPercent = $eval['progressPercent'];
            } else {
                $status = 'locked';
                $progressPercent = 0;
            }

            $progress[] = [
                'badgeId' => $badge['id'],
                'badgeCode' => $badge['code'],
                'badgeName' => $badge['name'],
                'badgeCategory' => $badge['category'],
                'badgeDescription' => $badge['description'],
                'badgeLevel' => $badge['level'],
                'ruleVersion' => $rule['version'],
                'status' => $status,
                'fact' => $eval['fact'],
                'current' => $eval['current'],
                'target' => $eval['target'],
                'progressPercent' => $progressPercent,
                'awardedAt' => $awardedMap[$badge['id']]['awardedAt'] ?? null,
            ];
        }

        return [
            'badges' => $awarded,
            'progress' => $progress,
            'facts' => $facts,
            'level' => $level,
        ];
    }
}
