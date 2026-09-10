<?php
declare(strict_types=1);

namespace TalentHub\Domain\Internship;

use TalentHub\Domain\ErrorCodes;
use TalentHub\Domain\PolicyViolation;
use TalentHub\Http\ApiException;

/**
 * Internship application state machine and placement-lock rules.
 *
 * State machine (mirror of `src/Modules/Business/Repository/InternshipRepository`):
 *   submitted → reviewing | declined
 *   reviewing → interview | accepted | declined
 *   interview → accepted | declined
 *
 * Placement lock: at most one `accepted` per student; promoting an application
 * to `accepted` must lock every other non-terminal application for the same
 * student (handled by the repository, but verified here).
 */
final class InternshipPolicy
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_INTERVIEW = 'interview';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_DECLINED  = 'declined';

    private const ALLOWED = [
        self::STATUS_SUBMITTED => [self::STATUS_REVIEWING, self::STATUS_DECLINED],
        self::STATUS_REVIEWING => [self::STATUS_INTERVIEW, self::STATUS_ACCEPTED, self::STATUS_DECLINED],
        self::STATUS_INTERVIEW => [self::STATUS_ACCEPTED, self::STATUS_DECLINED],
        self::STATUS_ACCEPTED  => [],
        self::STATUS_DECLINED  => [],
    ];

    public function assertCanTransition(string $current, string $target): void
    {
        $allowed = self::ALLOWED[$current] ?? [];
        if (!in_array($target, $allowed, true)) {
            throw PolicyViolation::fromCode(
                ErrorCodes::INTERNSHIP_INVALID_TRANSITION,
                sprintf('Không thể chuyển trạng thái hồ sơ từ "%s" sang "%s".', $current, $target),
                ['currentStatus' => $current, 'targetStatus' => $target],
                409,
            );
        }
    }

    /**
     * Promotion to `accepted` is blocked if the student already has another
     * accepted application.
     *
     * @param list<array<string, mixed>> $otherApplications excluding the
     *        application being promoted.
     */
    public function assertPlacementAvailable(array $otherApplications, string $studentId): void
    {
        foreach ($otherApplications as $other) {
            if ((string) ($other['studentId'] ?? '') !== $studentId) {
                continue;
            }
            if ((string) ($other['status'] ?? '') === self::STATUS_ACCEPTED) {
                throw PolicyViolation::fromCode(
                    ErrorCodes::INTERNSHIP_PLACEMENT_LOCKED,
                    'Sinh viên đã được tiếp nhận cho một vị trí thực tập khác.',
                    [],
                    409,
                );
            }
        }
    }
}
