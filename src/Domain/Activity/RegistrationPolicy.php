<?php
declare(strict_types=1);

namespace TalentHub\Domain\Activity;

use TalentHub\Domain\ErrorCodes;
use TalentHub\Domain\PolicyViolation;
use TalentHub\Http\ApiException;

/**
 * Single source of truth for student registration state machine.
 *
 * State machine:
 *   pending   → approved | rejected | cancelled
 *   approved  → cancelled | attended | no_show
 *   waitlisted → approved | cancelled | rejected
 *   rejected  → ∅ (terminal)
 *   cancelled → ∅ (terminal, but allowed to re-register if window open)
 *   attended  → ∅ (terminal, but allows grading)
 *   no_show   → ∅ (terminal, but allows grading)
 *
 * Re-register rules:
 *   - previously rejected: never (cannot re-register after teacher rejection)
 *   - previously cancelled: only if registration window still open
 */
final class RegistrationPolicy
{
    public const STATUS_PENDING     = 'pending';
    public const STATUS_APPROVED    = 'approved';
    public const STATUS_REJECTED    = 'rejected';
    public const STATUS_CANCELLED   = 'cancelled';
    public const STATUS_ATTENDED    = 'attended';
    public const STATUS_WAITLISTED  = 'waitlisted';
    public const STATUS_NO_SHOW     = 'no_show';

    public const TERMINAL_STATUSES = [
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_ATTENDED,
        self::STATUS_NO_SHOW,
    ];

    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT  = 'reject';
    public const ACTION_CANCEL  = 'cancel';

    /**
     * Teacher-side transition (approve / reject) by teacher.
     *
     * @throws ApiException REGISTRATION_NOT_PENDING | REGISTRATION_NOT_FOUND
     */
    public function assertCanTeacherTransition(array $registration, string $action): string
    {
        $current = (string) ($registration['status'] ?? '');
        if ($current === self::STATUS_WAITLISTED) {
            // Promotion from waitlist counts as an approve action.
            if ($action !== self::ACTION_APPROVE) {
                throw PolicyViolation::fromCode(
                    ErrorCodes::REGISTRATION_NOT_PENDING,
                    'Chỉ có thể duyệt học viên trong danh sách chờ; không thể từ chối trực tiếp.',
                    ['status' => $current],
                    409,
                );
            }
            return self::STATUS_APPROVED;
        }
        if ($current !== self::STATUS_PENDING) {
            throw PolicyViolation::fromCode(
                ErrorCodes::REGISTRATION_NOT_PENDING,
                'Chỉ có thể thay đổi trạng thái đăng ký đang chờ duyệt.',
                ['status' => $current],
                409,
            );
        }
        if ($action === self::ACTION_APPROVE) {
            return self::STATUS_APPROVED;
        }
        if ($action === self::ACTION_REJECT) {
            return self::STATUS_REJECTED;
        }
        throw PolicyViolation::validation(sprintf('Hành động "%s" không hợp lệ đối với đăng ký.', $action));
    }

    /**
     * Student-side transition (cancel).
     *
     * @throws ApiException REGISTRATION_CANCEL_WINDOW_CLOSED
     */
    public function assertCanStudentCancel(
        array $registration,
        bool $cancellationWindowOpen,
    ): string {
        $current = (string) ($registration['status'] ?? '');
        if (!in_array($current, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_WAITLISTED], true)) {
            throw PolicyViolation::fromCode(
                ErrorCodes::REGISTRATION_NOT_PENDING,
                'Không thể hủy đăng ký ở trạng thái hiện tại.',
                ['status' => $current],
                409,
            );
        }
        if (!$cancellationWindowOpen) {
            throw PolicyViolation::fromCode(
                ErrorCodes::REGISTRATION_CANCEL_WINDOW_CLOSED,
                'Đã quá hạn hủy đăng ký cho hoạt động này.',
                [],
                409,
            );
        }
        return self::STATUS_CANCELLED;
    }

    /**
     * Decide whether a student may create a fresh registration for the same
     * activity. Returns the new state to insert.
     *
     * Rules:
     *  - never allowed if there is an active (pending/approved/attended/waitlisted)
     *  - never allowed if a `rejected` registration exists (plan rule)
     *  - allowed if previous was `cancelled` or `no_show` AND registration window open
     *  - allowed if no prior registration AND window open
     *
     * @param array<int, array<string, mixed>>|null $previousRegistrations
     * @throws ApiException REGISTRATION_ALREADY_EXISTS | REGISTRATION_REJECTED | REGISTRATION_WINDOW_NOT_OPEN
     */
    public function assertCanRegister(
        ?array $previousRegistrations,
        bool $registrationWindowOpen,
        bool $reachedCapacity,
        bool $approvalModeIsAutomatic,
    ): string {
        $previous = $previousRegistrations ?? [];
        foreach ($previous as $r) {
            $status = (string) ($r['status'] ?? '');
            if (in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_ATTENDED, self::STATUS_WAITLISTED], true)) {
                throw PolicyViolation::fromCode(
                    ErrorCodes::REGISTRATION_ALREADY_EXISTS,
                    'Bạn đã có đăng ký đang hoạt động cho hoạt động này.',
                    ['status' => $status],
                    409,
                );
            }
            if ($status === self::STATUS_REJECTED) {
                throw PolicyViolation::registrationRejected();
            }
        }

        if (!$registrationWindowOpen) {
            throw PolicyViolation::fromCode(
                ErrorCodes::REGISTRATION_WINDOW_NOT_OPEN,
                'Cửa đăng ký hiện không mở.',
                [],
                409,
            );
        }

        if ($reachedCapacity) {
            if ($approvalModeIsAutomatic) {
                throw PolicyViolation::fromCode(
                    ErrorCodes::CAPACITY_REACHED,
                    'Hoạt động đã hết chỗ.',
                    [],
                    409,
                );
            }
            return self::STATUS_WAITLISTED;
        }

        return $approvalModeIsAutomatic ? self::STATUS_APPROVED : self::STATUS_PENDING;
    }

    /**
     * Can a teacher grade this registration right now?
     *
     * Plan rules:
     *  - only `attended` registrations can be graded
     *  - saving draft allowed while activity `ongoing`
     *  - publishing only after activity `completed`
     */
    public function assertCanSaveGradeDraft(string $activityStatus, string $registrationStatus): void
    {
        if ($registrationStatus !== self::STATUS_ATTENDED) {
            throw PolicyViolation::fromCode(
                ErrorCodes::ASSESSMENT_NOT_ATTENDED,
                'Chỉ có thể nhập điểm cho học viên đã điểm danh tham dự.',
                ['status' => $registrationStatus],
                422,
            );
        }
        if ($activityStatus !== ActivityPolicy::STATUS_ONGOING
            && $activityStatus !== ActivityPolicy::STATUS_COMPLETED) {
            throw PolicyViolation::fromCode(
                ErrorCodes::ASSESSMENT_NOT_ONGOING,
                'Chỉ có thể nhập điểm khi hoạt động đang diễn ra hoặc đã hoàn tất.',
                ['activityStatus' => $activityStatus],
                422,
            );
        }
    }

    public function assertCanPublishGrade(string $activityStatus, string $registrationStatus): void
    {
        if ($registrationStatus !== self::STATUS_ATTENDED) {
            throw PolicyViolation::fromCode(
                ErrorCodes::ASSESSMENT_NOT_ATTENDED,
                'Chỉ có thể công bố kết quả cho học viên đã điểm danh tham dự.',
                [],
                422,
            );
        }
        if ($activityStatus !== ActivityPolicy::STATUS_COMPLETED) {
            throw PolicyViolation::fromCode(
                ErrorCodes::ASSESSMENT_NOT_COMPLETED,
                'Chỉ có thể công bố kết quả sau khi hoạt động đã hoàn tất.',
                ['activityStatus' => $activityStatus],
                422,
            );
        }
    }
}
