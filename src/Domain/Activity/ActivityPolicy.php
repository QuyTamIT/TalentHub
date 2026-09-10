<?php
declare(strict_types=1);

namespace TalentHub\Domain\Activity;

use TalentHub\Domain\ErrorCodes;
use TalentHub\Domain\PolicyViolation;
use TalentHub\Http\ApiException;
use TalentHub\Support\Clock\ClockInterface;

/**
 * Single source of truth for activity lifecycle, editability and grading
 * rules. Pure functions - no DB, no globals - so it can be exercised
 * deterministically by policy tests.
 *
 * Replaces duplicated checks that previously lived in
 *   - TeacherActivityService (advanceStatus, publish, submitForSchoolReview)
 *   - TeacherActivityRepository (assertPublishable)
 *   - TeacherQrSessionService (QR availability)
 *   - StudentActivityService (catalog visibility)
 *   - app/teacher/activities/index.php (UI lifecycleAction)
 *   - app/school/activities.php (school review label)
 *
 * All services MUST consult this policy instead of re-implementing rules.
 */
final class ActivityPolicy
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ONGOING   = 'ongoing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED  = 'archived';

    public const APPROVAL_DRAFT                = 'draft';
    public const APPROVAL_PENDING              = 'pending_school_review';
    public const APPROVAL_CHANGES_REQUESTED    = 'changes_requested';
    public const APPROVAL_APPROVED             = 'approved';
    public const APPROVAL_REJECTED             = 'rejected';

    public const DELIVERY_IN_PERSON = 'in_person';
    public const DELIVERY_ONLINE    = 'online';
    public const DELIVERY_HYBRID    = 'hybrid';

    public const APPROVAL_AUTOMATIC      = 'automatic';
    public const APPROVAL_TEACHER_REVIEW = 'teacher_review';

    /**
     * Fields that are locked once the activity has been published AND any
     * registration exists (pending/approved/attended/waitlisted/no_show).
     */
    public const LOCKED_FIELDS_AFTER_OPEN = [
        'title',
        'startAt',
        'endAt',
        'registrationOpensAt',
        'registrationClosesAt',
        'cancellationClosesAt',
        'confirmedHours',
        'scope',
        'createdByTeacherId',
        'approvalMode',
        'deliveryMode',
    ];

    /**
     * Fields editable while activity is `ongoing` (late corrections only).
     */
    public const EDITABLE_FIELDS_WHILE_ONGOING = [
        'contactInfo',
        'location',
        'joinUrl',
    ];

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    // ---------------------------------------------------------------------
    // Configuration validation (used by publish + edit)
    // ---------------------------------------------------------------------

    /**
     * @param array<string, mixed> $activity   Activities row
     * @param array<string, mixed> $details    Activity details row (may be empty for legacy)
     * @param array<string, mixed> $regPolicy  Registration policy row
     * @param array<string, mixed> $expPolicy  Experience policy row
     * @return list<string>                   List of missing/invalid configuration keys (empty if ok)
     */
    public function missingPublishConfiguration(
        array $activity,
        array $details,
        array $regPolicy,
        array $expPolicy,
    ): array {
        $missing = [];

        if (trim((string) ($details['title'] ?? '')) === '') {
            $missing[] = 'thông tin chi tiết';
        }
        if ($regPolicy === []) {
            $missing[] = 'cấu hình đăng ký';
        }
        if ($expPolicy === [] || !isset($expPolicy['confirmedHours'])) {
            $missing[] = 'số giờ trải nghiệm';
        }

        // Time ordering
        try {
            $startAt = $this->parseDate($activity['startAt'] ?? null);
            $endAt = $this->parseDate($activity['endAt'] ?? null);
            $regOpens = $this->parseDate($regPolicy['registrationOpensAt'] ?? null);
            $regCloses = $this->parseDate($regPolicy['registrationClosesAt'] ?? null);
            $cancelCloses = $this->parseDate($regPolicy['cancellationClosesAt'] ?? null);

            if ($endAt <= $startAt) {
                $missing[] = 'thời gian kết thúc phải sau thời gian bắt đầu';
            }
            if ($regOpens > $regCloses) {
                $missing[] = 'thời gian mở đăng ký không hợp lệ';
            }
            if ($regCloses >= $startAt) {
                $missing[] = 'cửa đăng ký phải đóng trước giờ bắt đầu';
            }
            if ($cancelCloses > $startAt) {
                $missing[] = 'hạn hủy đăng ký phải trước giờ bắt đầu';
            }
        } catch (\Throwable) {
            $missing[] = 'thời gian hoạt động không hợp lệ';
        }

        $approvalMode = (string) ($regPolicy['approvalMode'] ?? '');
        if (!in_array($approvalMode, [self::APPROVAL_AUTOMATIC, self::APPROVAL_TEACHER_REVIEW], true)) {
            $missing[] = 'chính sách duyệt đăng ký';
        }

        $deliveryMode = (string) ($activity['deliveryMode'] ?? '');
        if (!in_array($deliveryMode, [self::DELIVERY_IN_PERSON, self::DELIVERY_ONLINE, self::DELIVERY_HYBRID], true)) {
            $missing[] = 'hình thức tổ chức';
        }

        return array_values(array_unique($missing));
    }

    // ---------------------------------------------------------------------
    // Lifecycle transitions
    // ---------------------------------------------------------------------

    /**
     * Validate a transition from $current to $next.
     *
     * @throws ApiException ACTIVITY_INVALID_TRANSITION
     */
    public function assertCanTransition(string $current, string $next): void
    {
        $allowed = [
            self::STATUS_DRAFT     => [self::STATUS_PUBLISHED, self::STATUS_ARCHIVED],
            self::STATUS_PUBLISHED => [self::STATUS_ONGOING, self::STATUS_ARCHIVED, self::STATUS_DRAFT],
            self::STATUS_ONGOING   => [self::STATUS_COMPLETED],
            self::STATUS_COMPLETED => [self::STATUS_ARCHIVED],
            self::STATUS_ARCHIVED  => [],
        ];
        if (!in_array($next, $allowed[$current] ?? [], true)) {
            throw PolicyViolation::fromCode(
                ErrorCodes::ACTIVITY_INVALID_TRANSITION,
                sprintf('Không thể chuyển trạng thái từ "%s" sang "%s".', $current, $next),
                ['currentStatus' => $current, 'targetStatus' => $next],
                409,
            );
        }
    }

    /**
     * Assert activity is ready to start (`published → ongoing`).
     *
     * Plan rules:
     *  - `now >= startAt`
     *  - no `pending` registrations left
     *  - if past `endAt`, still allowed with a warning flag (handled by caller)
     *
     * @param int $pendingCount
     * @throws ApiException ACTIVITY_NOT_STARTED | PENDING_REGISTRATIONS_EXIST
     */
    public function assertCanStart(array $activity, int $pendingCount): void
    {
        $now = $this->clock->now();
        $startAt = $this->parseDateOrThrow($activity['startAt'] ?? null, ErrorCodes::INVALID_ACTIVITY, 'Thời gian bắt đầu của hoạt động không hợp lệ.');
        if ($now < $startAt) {
            throw PolicyViolation::activityNotStarted();
        }
        if ($pendingCount > 0) {
            throw PolicyViolation::pendingRegistrationsExist($pendingCount);
        }
    }

    /**
     * True if the activity is overdue (now > endAt) so caller may surface a
     * confirmation prompt before letting the teacher start late.
     */
    public function isOverdueStart(array $activity): bool
    {
        try {
            $endAt = $this->parseDate($activity['endAt'] ?? null);
        } catch (\Throwable) {
            return false;
        }
        return $endAt !== null && $this->clock->now() > $endAt;
    }

    /**
     * Assert activity is ready to complete (`ongoing → completed`).
     *
     * @param int $pendingCount
     * @throws ApiException ACTIVITY_NOT_ENDED | PENDING_REGISTRATIONS_EXIST
     */
    public function assertCanComplete(array $activity, int $pendingCount): void
    {
        $now = $this->clock->now();
        $endAt = $this->parseDateOrThrow($activity['endAt'] ?? null, ErrorCodes::INVALID_ACTIVITY, 'Thời gian kết thúc của hoạt động không hợp lệ.');
        if ($now < $endAt) {
            throw PolicyViolation::activityNotEnded();
        }
        if ($pendingCount > 0) {
            throw PolicyViolation::pendingRegistrationsExist($pendingCount);
        }
    }

    /**
     * @param int $unresolvedResultCount Number of `attended` registrations
     *                                    without published assessment and
     *                                    without a "skip" reason.
     * @throws ApiException ARCHIVE_BLOCKED_PENDING_RESULTS
     */
    public function assertCanArchive(array $activity, int $unresolvedResultCount): void
    {
        $this->assertCanTransition((string) ($activity['status'] ?? ''), self::STATUS_ARCHIVED);
        if ($unresolvedResultCount > 0) {
            throw PolicyViolation::fromCode(
                ErrorCodes::ARCHIVE_BLOCKED_PENDING_RESULTS,
                'Vẫn còn học viên chưa được chấm công bố kết quả.',
                ['unresolvedResultCount' => $unresolvedResultCount],
                409,
            );
        }
    }

    // ---------------------------------------------------------------------
    // Editability (teacher-side)
    // ---------------------------------------------------------------------

    /**
     * Validate that the requested payload respects the editability matrix
     * defined by the plan. Mutates nothing; caller decides how to react.
     *
     * @param array<string, mixed> $activity
     * @param array<string, mixed> $regPolicy
     * @param array<string, mixed> $patch
     * @param bool $hasAnyRegistration Has the activity at least one registration
     *                                  in {pending, approved, attended, waitlisted, no_show}?
     * @return list<string>             List of locked field names
     */
    public function lockedFieldsForPatch(
        array $activity,
        array $regPolicy,
        array $patch,
        bool $hasAnyRegistration,
    ): array {
        $status = (string) ($activity['status'] ?? '');
        $locked = [];

        if ($status === self::STATUS_DRAFT) {
            return $locked;
        }

        if ($status === self::STATUS_COMPLETED || $status === self::STATUS_ARCHIVED) {
            // Whole configuration is locked, only deny-level fields - caller
            // should never attempt a patch here, so we report every field
            // that came in.
            return array_values(array_intersect(self::LOCKED_FIELDS_AFTER_OPEN, array_keys($patch)));
        }

        if ($status === self::STATUS_ONGOING) {
            $forbidden = array_diff(array_keys($patch), self::EDITABLE_FIELDS_WHILE_ONGOING);
            return array_values(array_intersect(self::LOCKED_FIELDS_AFTER_OPEN, $forbidden));
        }

        // status === published
        $windowOpen = $this->registrationWindowIsOpen($activity, $regPolicy);
        if (!$windowOpen && !$hasAnyRegistration) {
            // Published but not yet open, no registrations: full edit allowed.
            return $locked;
        }
        $forbidden = array_intersect(array_keys($patch), self::LOCKED_FIELDS_AFTER_OPEN);
        return array_values($forbidden);
    }

    public function assertPatchAllowed(
        array $activity,
        array $regPolicy,
        array $patch,
        bool $hasAnyRegistration,
    ): void {
        $locked = $this->lockedFieldsForPatch($activity, $regPolicy, $patch, $hasAnyRegistration);
        if ($locked !== []) {
            throw PolicyViolation::activityEditLocked($locked);
        }
    }

    // ---------------------------------------------------------------------
    // Registration window / availability
    // ---------------------------------------------------------------------

    /**
     * True if `now ∈ [registrationOpensAt, registrationClosesAt)`.
     */
    public function registrationWindowIsOpen(array $activity, array $regPolicy): bool
    {
        if ($regPolicy === []) {
            return false;
        }
        try {
            $opens = $this->parseDateOrThrow($regPolicy['registrationOpensAt'] ?? null, ErrorCodes::INVALID_ACTIVITY, '');
            $closes = $this->parseDateOrThrow($regPolicy['registrationClosesAt'] ?? null, ErrorCodes::INVALID_ACTIVITY, '');
        } catch (\Throwable) {
            return false;
        }
        $now = $this->clock->now();
        return $now >= $opens && $now < $closes;
    }

    /**
     * True if cancellation is still allowed (`now < cancellationClosesAt`).
     */
    public function cancellationWindowIsOpen(array $regPolicy): bool
    {
        if ($regPolicy === []) {
            return false;
        }
        try {
            $closes = $this->parseDateOrThrow($regPolicy['cancellationClosesAt'] ?? null, ErrorCodes::INVALID_ACTIVITY, '');
        } catch (\Throwable) {
            return false;
        }
        return $this->clock->now() < $closes;
    }

    /**
     * Compute the student-visible catalog bucket for an activity given the
     * student's current registration (if any) and current capacity.
     *
     * @param array<string, mixed>|null $studentRegistration
     */
    public function catalogBucket(
        array $activity,
        array $regPolicy,
        ?array $studentRegistration,
        int $approvedCount,
        ?int $capacity,
    ): string {
        $status = (string) ($activity['status'] ?? '');

        // Not in catalog if status isn't published/ongoing.
        if (!in_array($status, [self::STATUS_PUBLISHED, self::STATUS_ONGOING], true)) {
            return CatalogBucket::HIDDEN;
        }

        if ($studentRegistration !== null) {
            $regStatus = (string) ($studentRegistration['status'] ?? '');
            if (in_array($regStatus, ['approved', 'attended'], true)) {
                return CatalogBucket::ENROLLED;
            }
            if ($regStatus === 'pending') {
                return CatalogBucket::PENDING;
            }
            if ($regStatus === 'waitlisted') {
                return CatalogBucket::WAITLISTED;
            }
            if ($regStatus === 'cancelled' || $regStatus === 'no_show') {
                return CatalogBucket::CANCELLED;
            }
            if ($regStatus === 'rejected') {
                return CatalogBucket::REJECTED;
            }
        }

        // No registration yet (or removed): classify by window + capacity.
        if (!$this->registrationWindowIsOpen($activity, $regPolicy)) {
            $now = $this->clock->now();
            try {
                $opens = $this->parseDate($regPolicy['registrationOpensAt'] ?? null);
                if ($opens !== null && $now < $opens) {
                    return CatalogBucket::UPCOMING;
                }
            } catch (\Throwable) {
                // Fall through to closed.
            }
            return CatalogBucket::CLOSED;
        }

        if ($capacity !== null && $approvedCount >= $capacity) {
            return CatalogBucket::FULL;
        }

        return CatalogBucket::OPEN;
    }

    // ---------------------------------------------------------------------
    // QR availability
    // ---------------------------------------------------------------------

    /**
     * Can a QR session be created right now?
     *
     * Plan rules:
     *  - activity must be `ongoing`
     *  - now must be < endAt
     *  - caller passes durationMinutes (1-120); max qr expiry must not
     *    overshoot endAt.
     *
     * @return \DateTimeImmutable Max expiry timestamp allowed for the session.
     */
    public function assertQrCanBeCreated(array $activity, int $durationMinutes): \DateTimeImmutable
    {
        if ((string) ($activity['status'] ?? '') !== self::STATUS_ONGOING) {
            throw PolicyViolation::fromCode(
                ErrorCodes::QR_SESSION_NOT_AVAILABLE,
                'Chỉ có thể tạo QR cho hoạt động đang diễn ra.',
                [],
                422,
            );
        }
        $now = $this->clock->now();
        try {
            $endAt = $this->parseDateOrThrow($activity['endAt'] ?? null, ErrorCodes::INVALID_ACTIVITY, 'Thời gian kết thúc của hoạt động không hợp lệ.');
        } catch (ApiException $e) {
            throw PolicyViolation::fromCode(
                ErrorCodes::QR_SESSION_NOT_AVAILABLE,
                'Không thể tạo QR khi thời gian kết thúc hoạt động không xác định.',
                [],
                422,
            );
        }
        if ($now >= $endAt) {
            throw PolicyViolation::fromCode(
                ErrorCodes::QR_SESSION_NOT_AVAILABLE,
                'Hoạt động đã qua thời gian kết thúc; không thể tạo thêm phiên QR.',
                [],
                422,
            );
        }
        $expiresAt = $now->modify(sprintf('+%d minutes', $durationMinutes));
        if ($expiresAt > $endAt) {
            // Clamp to endAt - never let QR outlive activity.
            return $endAt;
        }
        return $expiresAt;
    }

    /**
     * When activity completes, all live QR sessions must be invalidated.
     *
     * @return bool True if caller must revoke remaining active sessions.
     */
    public function mustInvalidateQrSessionsOnComplete(): bool
    {
        return true;
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        $string = (string) $value;
        try {
            return new \DateTimeImmutable($string, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            throw new \RuntimeException(sprintf('Cannot parse date "%s".', $string));
        }
    }

    private function parseDateOrThrow(mixed $value, string $code, string $message): \DateTimeImmutable
    {
        $parsed = $this->parseDate($value);
        if ($parsed === null) {
            throw PolicyViolation::fromCode($code !== '' ? $code : ErrorCodes::INVALID_ACTIVITY, $message !== '' ? $message : 'Ngày không hợp lệ.');
        }
        return $parsed;
    }
}
