<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use TalentHub\Learner\Data\Contracts\BadgeRepository;
use TalentHub\Learner\Data\Contracts\StatisticsRepository;

final class BadgeAwardService
{
    public function __construct(
        private readonly BadgeRepository $badgeRepo,
        private readonly StatisticsRepository $statsRepo,
        private readonly BadgeRuleEngine $ruleEngine,
        private readonly NotificationService $notifications,
        private readonly ?DateTimeImmutable $clock = null
    ) {}

    /**
     * Evaluates all active threshold rules for the student and atomically materializes
     * any eligible, not-yet-awarded badges alongside their Phase 8 notification.
     *
     * @return list<array{
     *     badge: array<string,mixed>,
     *     rule: array<string,mixed>,
     *     context: array<string,mixed>
     * }>
     */
    public function evaluateAndAward(string $studentId, string $awardedBy = 'system'): array
    {
        return $this->badgeRepo->withTransaction(function () use ($studentId, $awardedBy): array {
            $user = $this->badgeRepo->userForStudent($studentId);
            if ($user === null) {
                throw new RuntimeException("Student profile not found for ID: {$studentId}");
            }

            $facts = $this->statsRepo->lifetimeFacts($studentId);
            $activeRules = $this->badgeRepo->activeRules();
            $now = ($this->clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));

            $newlyAwarded = [];

            foreach ($activeRules as $item) {
                $badge = $item['badge'];
                $rule = $item['rule'];

                if ($this->badgeRepo->isAwarded($studentId, $badge['id'])) {
                    continue;
                }

                $evalResult = $this->ruleEngine->evaluate($rule['thresholdCriteria'], $facts);
                if (!$evalResult['eligible']) {
                    continue;
                }

                $awardContext = [
                    'ruleDefinitionId' => $rule['id'],
                    'ruleVersion' => $rule['version'],
                    'fact' => $evalResult['fact'],
                    'current' => $evalResult['current'],
                    'target' => $evalResult['target'],
                    'evaluatedAt' => $now->format('Y-m-d\TH:i:s\Z'),
                ];

                $inserted = $this->badgeRepo->insertAward(
                    $studentId,
                    $badge['id'],
                    $rule['id'],
                    $awardedBy,
                    $awardContext,
                    $now
                );

                if ($inserted) {
                    $eventKey = "badge_award:{$studentId}:{$badge['id']}:v{$rule['version']}";
                    $title = "Chúc mừng! Bạn đã đạt huy hiệu {$badge['name']}";
                    $message = "Bạn đã mở khóa thành công huy hiệu {$badge['name']}: {$badge['description']}";
                    $deepLink = '/app/learner/badges.php';

                    $this->notifications->publish(
                        $user['userId'],
                        'badge_awarded',
                        $title,
                        $message,
                        $deepLink,
                        $eventKey,
                        $studentId
                    );

                    $newlyAwarded[] = [
                        'badge' => $badge,
                        'rule' => $rule,
                        'context' => $awardContext,
                    ];
                }
            }

            return $newlyAwarded;
        });
    }
}
