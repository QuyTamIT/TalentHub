<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Contracts\ActivityCommandRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;
use Throwable;

final class DatabaseActivityCommandRepository implements ActivityCommandRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?NotificationService $notifications = null
    ) {}


    public function register(
        string $studentId,
        string $actorUserId,
        string $requestId,
        string $activityId,
        DateTimeImmutable $now,
    ): array {
        $this->pdo->beginTransaction();
        try {
            $this->lockStudent($studentId);
            $activity = $this->activityForUpdate($activityId);
            if ($activity === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hoạt động đang nhận đăng ký.');
            }
            $this->assertStudentCanJoinActivity($studentId, (string) ($activity['schoolId'] ?? ''));
            $this->assertRegistrationWindow($activity, $now);

            if ($this->registrationExists($activityId, $studentId)) {
                throw new ApiException(409, 'REGISTRATION_EXISTS', 'Bạn đã có đăng ký cho hoạt động này.');
            }
            if ($this->hasScheduleConflict($studentId, $activity)) {
                throw new ApiException(409, 'SCHEDULE_CONFLICT', 'Lịch hoạt động bị trùng với một đăng ký hiện có.');
            }

            $occupied = $this->occupiedCount($activityId);
            $capacity = (int) $activity['capacity'];
            $status = $occupied >= $capacity
                ? 'waitlisted'
                : (($activity['approvalMode'] ?? 'automatic') === 'teacher_review' ? 'pending' : 'approved');
            $registrationId = Uuid::v4();
            $timestamp = $this->timestamp($now);
            $insert = $this->pdo->prepare(<<<'SQL'
                INSERT INTO activity_registrations (
                    id, activityId, studentId, status, registeredAt, updatedAt, cancelledAt, cancellationReason
                ) VALUES (
                    :id, :activityId, :studentId, :status, :registeredAt, :updatedAt, NULL, NULL
                )
            SQL
            );
            $insert->execute([
                'id' => $registrationId,
                'activityId' => $activityId,
                'studentId' => $studentId,
                'status' => $status,
                'registeredAt' => $timestamp,
                'updatedAt' => $timestamp,
            ]);
            $this->audit($actorUserId, $requestId, 'activity_registration.registered', $registrationId, [
                'activityId' => $activityId,
                'status' => $status,
            ], $timestamp);

            $this->getNotificationService()->publish(
                $actorUserId,
                'activity_registration_created',
                'Đăng ký hoạt động thành công',
                'Bạn đã đăng ký tham gia hoạt động ' . ($activity['title'] ?? '') . '.',
                '/app/learner/my-activities.php',
                'activity_registration:' . $registrationId,
                $studentId
            );

            $registration = $this->findRegistration($registrationId)
                ?? throw new ApiException(500, 'REGISTRATION_FAILED', 'Không thể đọc đăng ký vừa tạo.');
            $this->pdo->commit();
            return ['registration' => $registration, 'promotedRegistration' => null];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof PDOException && $this->isDuplicate($exception)) {
                throw new ApiException(409, 'REGISTRATION_EXISTS', 'Bạn đã có đăng ký cho hoạt động này.');
            }
            throw $exception;
        }
    }

    public function cancel(
        string $studentId,
        string $actorUserId,
        string $requestId,
        string $registrationId,
        ?string $reason,
        DateTimeImmutable $now,
    ): array {
        $this->pdo->beginTransaction();
        try {
            $activityId = $this->ownedRegistrationActivityId($studentId, $registrationId);
            if ($activityId === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy đăng ký thuộc hồ sơ của bạn.');
            }
            $this->lockStudent($studentId);
            $activity = $this->activityForUpdate($activityId);
            if ($activity === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hoạt động của đăng ký này.');
            }
            $registration = $this->ownedRegistrationForUpdate($studentId, $registrationId);
            if ($registration === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy đăng ký thuộc hồ sơ của bạn.');
            }
            if (!in_array((string) $registration['status'], ['pending', 'approved', 'waitlisted'], true)) {
                throw new ApiException(409, 'INVALID_REGISTRATION_STATE', 'Đăng ký ở trạng thái không thể hủy.');
            }

            $cancellationClosesAt = new DateTimeImmutable(
                (string) ($registration['cancellationClosesAt'] ?? $registration['startAt']),
                new DateTimeZone('UTC'),
            );
            if ($now > $cancellationClosesAt) {
                throw new ApiException(422, 'REGISTRATION_CANCELLATION_CLOSED', 'Đã quá hạn hủy đăng ký.');
            }

            $previousStatus = (string) $registration['status'];
            $timestamp = $this->timestamp($now);
            $storedReason = $reason ?? 'student_cancelled';
            $update = $this->pdo->prepare(<<<'SQL'
                UPDATE activity_registrations
                SET status = 'cancelled', cancelledAt = :cancelledAt,
                    cancellationReason = :reason, updatedAt = :updatedAt
                WHERE id = :id AND studentId = :studentId AND status = :expectedStatus
            SQL
            );
            $update->execute([
                'cancelledAt' => $timestamp,
                'reason' => $storedReason,
                'updatedAt' => $timestamp,
                'id' => $registrationId,
                'studentId' => $studentId,
                'expectedStatus' => $previousStatus,
            ]);
            if ($update->rowCount() !== 1) {
                throw new ApiException(409, 'REGISTRATION_STATE_CONFLICT', 'Đăng ký đã thay đổi trước khi hủy.');
            }

            $promoted = null;
            if ($previousStatus === 'approved'
                && $this->occupiedCount($activityId) < (int) $activity['capacity']) {
                $promoted = $this->promoteWaitlist(
                    $activityId,
                    (string) ($activity['approvalMode'] ?? 'automatic'),
                    $timestamp,
                );
            }
            $this->audit($actorUserId, $requestId, 'activity_registration.cancelled', $registrationId, [
                'activityId' => (string) $registration['activityId'],
                'previousStatus' => $previousStatus,
                'promotedRegistrationId' => $promoted['id'] ?? null,
            ], $timestamp);
            if ($promoted !== null) {
                $this->audit($actorUserId, $requestId, 'activity_registration.waitlist_promoted', (string) $promoted['id'], [
                    'activityId' => (string) $registration['activityId'],
                    'status' => (string) $promoted['status'],
                ], $timestamp);
            }

            $this->getNotificationService()->publish(
                $actorUserId,
                'activity_registration_cancelled',
                'Hủy đăng ký hoạt động',
                'Bạn đã hủy đăng ký hoạt động ' . ($activity['title'] ?? '') . '.',
                '/app/learner/my-activities.php',
                'activity_registration_cancelled:' . $registrationId,
                $studentId
            );

            if ($promoted !== null) {
                $promotedStudentId = (string) $promoted['studentId'];
                $promotedUserId = $this->userIdForStudent($promotedStudentId);
                $this->getNotificationService()->publish(
                    $promotedUserId,
                    'activity_registration_promoted',
                    'Được duyệt từ danh sách chờ',
                    'Bạn đã được chuyển lên danh sách chính thức cho hoạt động ' . ($activity['title'] ?? '') . '.',
                    '/app/learner/my-activities.php',
                    'activity_registration_promoted:' . $promoted['id'],
                    $promotedStudentId
                );
            }

            $cancelled = $this->findRegistration($registrationId)
                ?? throw new ApiException(500, 'CANCELLATION_FAILED', 'Không thể đọc đăng ký vừa hủy.');
            $this->pdo->commit();
            return ['registration' => $cancelled, 'promotedRegistration' => $promoted];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string,mixed>|null */
    private function activityForUpdate(string $activityId): ?array
    {
        $lock = $this->lockSuffix();
        $statement = $this->pdo->prepare(<<<SQL
            SELECT activity.id, {$this->activitySchoolIdSelect()} activity.title, activity.startAt, activity.endAt, activity.capacity, activity.status,
                   policy.registrationOpensAt, COALESCE(policy.registrationClosesAt, activity.startAt) registrationClosesAt,
                   COALESCE(policy.cancellationClosesAt, activity.startAt) cancellationClosesAt,
                   COALESCE(policy.approvalMode, 'automatic') approvalMode
            FROM activities activity
            LEFT JOIN activity_registration_policies policy ON policy.activityId = activity.id
            WHERE activity.id = :activityId
            LIMIT 1{$lock}
        SQL
        );
        $statement->execute(['activityId' => $activityId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $activity */
    private function assertRegistrationWindow(array $activity, DateTimeImmutable $now): void
    {
        if (($activity['status'] ?? null) !== 'published') {
            throw new ApiException(422, 'REGISTRATION_CLOSED', 'Hoạt động hiện không nhận đăng ký.');
        }
        $closes = new DateTimeImmutable((string) $activity['registrationClosesAt'], new DateTimeZone('UTC'));
        $opensRaw = $activity['registrationOpensAt'] ?? null;
        $opens = is_string($opensRaw) && $opensRaw !== ''
            ? new DateTimeImmutable($opensRaw, new DateTimeZone('UTC'))
            : null;
        if (($opens !== null && $now < $opens) || $now >= $closes) {
            throw new ApiException(422, 'REGISTRATION_CLOSED', 'Hoạt động nằm ngoài thời gian đăng ký.');
        }
    }

    private function assertStudentCanJoinActivity(string $studentId, string $activitySchoolId): void
    {
        // Only legacy SQLite fixtures may predate school ownership. Any other
        // database must fail closed before a write if its scope joins are unavailable.
        if ($this->isSqlite() && $this->hasLegacySqliteSchoolScopeFallback()) {
            return;
        }

        if (!$this->hasSchoolScopeSchema()) {
            throw new ApiException(
                503,
                'ACTIVITY_SCHOOL_SCOPE_UNAVAILABLE',
                'Không thể xác minh phạm vi trường của hoạt động.'
            );
        }
        $statement = $this->pdo->prepare(
            'SELECT classroom.schoolId
             FROM student_profiles student
             INNER JOIN classes classroom ON classroom.id = student.classId
             WHERE student.id = :studentId
             LIMIT 1' . $this->lockSuffix()
        );
        $statement->execute(['studentId' => $studentId]);
        $studentSchoolId = $statement->fetchColumn();
        if (!is_string($studentSchoolId) || $activitySchoolId === '' || !hash_equals($studentSchoolId, $activitySchoolId)) {
            throw new ApiException(403, 'ACTIVITY_SCHOOL_SCOPE_DENIED', 'Bạn chỉ được đăng ký hoạt động của trường mình.');
        }
    }

    private function activitySchoolIdSelect(): string
    {
        return $this->hasColumn('activities', 'schoolId') ? 'activity.schoolId,' : 'NULL AS schoolId,';
    }

    private function hasSchoolScopeSchema(): bool
    {
        return $this->hasTable('classes')
            && $this->hasColumn('student_profiles', 'classId')
            && $this->hasColumn('classes', 'schoolId')
            && $this->hasColumn('activities', 'schoolId');
    }

    private function hasLegacySqliteSchoolScopeFallback(): bool
    {
        return !$this->hasTable('classes')
            && !$this->hasColumn('student_profiles', 'classId')
            && !$this->hasColumn('activities', 'schoolId');
    }

    private function hasTable(string $table): bool
    {
        $statement = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:table")
            : $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        $statement->execute(['table' => $table]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function hasColumn(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $table . ')');
            foreach ($statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                if (($row['name'] ?? null) === $column) {
                    return true;
                }
            }
            return false;
        }
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column');
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function registrationExists(string $activityId, string $studentId): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM activity_registrations WHERE activityId=:activityId AND studentId=:studentId LIMIT 1' . $this->lockSuffix());
        $statement->execute(['activityId' => $activityId, 'studentId' => $studentId]);
        return $statement->fetchColumn() !== false;
    }

    private function lockStudent(string $studentId): void
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM student_profiles WHERE id=:studentId LIMIT 1' . $this->lockSuffix()
        );
        $statement->execute(['studentId' => $studentId]);
        if ($statement->fetchColumn() === false) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ học viên hợp lệ.');
        }
    }

    private function ownedRegistrationActivityId(string $studentId, string $registrationId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT activityId FROM activity_registrations WHERE id=:registrationId AND studentId=:studentId LIMIT 1'
        );
        $statement->execute(['registrationId' => $registrationId, 'studentId' => $studentId]);
        $activityId = $statement->fetchColumn();
        return is_string($activityId) && $activityId !== '' ? $activityId : null;
    }

    /** @param array<string,mixed> $activity */
    private function hasScheduleConflict(string $studentId, array $activity): bool
    {
        $candidateEnd = (string) (($activity['endAt'] ?? null) ?: $activity['startAt']);
        $statement = $this->pdo->prepare(<<<SQL
            SELECT registration.id
            FROM activity_registrations registration
            INNER JOIN activities existing ON existing.id = registration.activityId
            WHERE registration.studentId = :studentId
              AND registration.status IN ('pending','approved','waitlisted','attended')
              AND existing.startAt < :candidateEnd
              AND COALESCE(existing.endAt, existing.startAt) > :candidateStart
            LIMIT 1
        SQL
        );
        $statement->execute([
            'studentId' => $studentId,
            'candidateEnd' => $candidateEnd,
            'candidateStart' => (string) $activity['startAt'],
        ]);
        return $statement->fetchColumn() !== false;
    }

    private function occupiedCount(string $activityId): int
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM activity_registrations WHERE activityId=:activityId AND status IN ('approved','attended')");
        $statement->execute(['activityId' => $activityId]);
        return (int) $statement->fetchColumn();
    }

    /** @return array<string,mixed>|null */
    private function ownedRegistrationForUpdate(string $studentId, string $registrationId): ?array
    {
        $lock = $this->lockSuffix();
        $statement = $this->pdo->prepare(<<<SQL
            SELECT registration.id, registration.activityId, registration.studentId, registration.status,
                   activity.startAt,
                   COALESCE(policy.cancellationClosesAt, activity.startAt) cancellationClosesAt,
                   COALESCE(policy.approvalMode, 'automatic') approvalMode
            FROM activity_registrations registration
            INNER JOIN activities activity ON activity.id = registration.activityId
            LEFT JOIN activity_registration_policies policy ON policy.activityId = activity.id
            WHERE registration.id = :registrationId AND registration.studentId = :studentId
            LIMIT 1{$lock}
        SQL
        );
        $statement->execute(['registrationId' => $registrationId, 'studentId' => $studentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    private function promoteWaitlist(string $activityId, string $approvalMode, string $timestamp): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT id
            FROM activity_registrations
            WHERE activityId = :activityId AND status = 'waitlisted'
            ORDER BY registeredAt, id
            LIMIT 1{$this->lockSuffix()}
        SQL
        );
        $statement->execute(['activityId' => $activityId]);
        $id = $statement->fetchColumn();
        if (!is_string($id) || $id === '') {
            return null;
        }
        $nextStatus = $approvalMode === 'teacher_review' ? 'pending' : 'approved';
        $update = $this->pdo->prepare("UPDATE activity_registrations SET status=:status, updatedAt=:updatedAt WHERE id=:id AND status='waitlisted'");
        $update->execute(['status' => $nextStatus, 'updatedAt' => $timestamp, 'id' => $id]);
        if ($update->rowCount() !== 1) {
            throw new ApiException(409, 'WAITLIST_STATE_CONFLICT', 'Danh sách chờ đã thay đổi.');
        }
        return $this->findRegistration($id);
    }

    /** @return array<string,mixed>|null */
    private function findRegistration(string $registrationId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT id, activityId, studentId, status, registeredAt, updatedAt, cancelledAt, cancellationReason
            FROM activity_registrations WHERE id=:id LIMIT 1
        SQL
        );
        $statement->execute(['id' => $registrationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        return [
            'id' => (string) $row['id'],
            'activityId' => (string) $row['activityId'],
            'studentId' => (string) $row['studentId'],
            'status' => (string) $row['status'],
            'registeredAt' => (string) $row['registeredAt'],
            'updatedAt' => (string) $row['updatedAt'],
            'cancelledAt' => isset($row['cancelledAt']) ? (string) $row['cancelledAt'] : null,
            'cancellationReason' => isset($row['cancellationReason']) ? (string) $row['cancellationReason'] : null,
        ];
    }

    /** @param array<string,mixed> $metadata */
    private function audit(string $userId, string $requestId, string $action, string $entityId, array $metadata, string $timestamp): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO audit_logs (id, userId, action, entityType, entityId, requestId, ipAddress, metadata, createdAt)
            VALUES (:id, :userId, :action, 'activity_registration', :entityId, :requestId, NULL, :metadata, :createdAt)
        SQL
        );
        $statement->execute([
            'id' => Uuid::v4(),
            'userId' => $userId,
            'action' => $action,
            'entityId' => $entityId,
            'requestId' => $requestId,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'createdAt' => $timestamp,
        ]);
    }

    private function timestamp(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function lockSuffix(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }

    private function isDuplicate(PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062
            || str_contains(strtolower($exception->getMessage()), 'unique constraint failed');
    }

    private function getNotificationService(): NotificationService
    {
        if (!class_exists('TalentHub\Learner\Data\Service\NotificationService', false)) {
            require_once dirname(__DIR__) . '/Contracts/NotificationRepository.php';
            require_once dirname(__DIR__) . '/Service/NotificationService.php';
            require_once dirname(__DIR__) . '/Database/DatabaseNotificationRepository.php';
        }
        return $this->notifications ?? new NotificationService(new DatabaseNotificationRepository($this->pdo));
    }


    private function userIdForStudent(string $studentId): string
    {
        $stmt = $this->pdo->prepare('SELECT userId FROM student_profiles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $studentId]);
        $userId = $stmt->fetchColumn();
        if (!is_string($userId) || $userId === '') {
            throw new \RuntimeException('Notification recipient is missing for the promoted registration.');
        }
        return $userId;
    }
}
