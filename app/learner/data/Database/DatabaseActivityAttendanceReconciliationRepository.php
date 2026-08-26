<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use TalentHub\Learner\Data\Contracts\ActivityAttendanceReconciliationRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;
use Throwable;

final class DatabaseActivityAttendanceReconciliationRepository implements ActivityAttendanceReconciliationRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?NotificationService $notifications = null
    ) {}

    /** @return list<array{registration_id:string,student_id:string,activity_id:string}> */
    public function reconcileDueNoShows(DateTimeImmutable $now, int $graceHours, int $limit): array
    {
        [$resolvedAt, $cutoff] = $this->clock($now, $graceHours, $limit);
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('Attendance reconciliation requires ownership of its transaction.');
        }

        $reconciled = [];
        $coreFailure = null;
        $this->pdo->beginTransaction();
        try {
            $candidates = $this->selectCandidates($cutoff, $limit);
            foreach ($candidates as $candidate) {
                $registrationId = (string) $candidate['registration_id'];
                $lockedStatus = $this->lockRegistrationStatus($registrationId);
                if ($lockedStatus !== 'approved') {
                    continue;
                }

                $eligible = $this->eligibleCandidate($registrationId, $cutoff);
                if ($eligible === null) {
                    continue;
                }
                $update = $this->pdo->prepare(<<<'SQL'
                    UPDATE activity_registrations
                    SET status = 'no_show',
                        attendanceResolvedAt = :resolved_at,
                        attendanceResolutionReason = 'no_confirmed_checkin_after_24h',
                        updatedAt = :updated_at
                    WHERE id = :registration_id
                      AND status = 'approved'
                SQL);
                $update->execute([
                    'resolved_at' => $resolvedAt,
                    'updated_at' => $resolvedAt,
                    'registration_id' => $registrationId,
                ]);
                if ($update->rowCount() !== 1) {
                    continue;
                }

                $studentId = (string) $eligible['student_id'];
                $activityId = (string) $eligible['activity_id'];
                $this->insertAudit($registrationId, $studentId, $activityId, $resolvedAt);

                $reconciled[] = [
                    'registration_id' => $registrationId,
                    'student_id' => $studentId,
                    'activity_id' => $activityId,
                ];
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $coreFailure = $exception;
        }

        $deliveryFailure = null;
        try {
            $this->deliverPendingNotifications($limit);
        } catch (Throwable $exception) {
            $deliveryFailure = $exception;
        }
        if ($coreFailure !== null) {
            throw $coreFailure;
        }
        if ($deliveryFailure !== null) {
            throw $deliveryFailure;
        }
        return $reconciled;
    }

    /** @return list<array{registration_id:string,student_id:string,activity_id:string}> */
    public function previewDueNoShows(DateTimeImmutable $now, int $graceHours, int $limit): array
    {
        [, $cutoff] = $this->clock($now, $graceHours, $limit);
        return array_map(
            static fn (array $row): array => [
                'registration_id' => (string) $row['registration_id'],
                'student_id' => (string) $row['student_id'],
                'activity_id' => (string) $row['activity_id'],
            ],
            $this->selectCandidates($cutoff, $limit)
        );
    }

    /** @return array{0:string,1:string} */
    private function clock(DateTimeImmutable $now, int $graceHours, int $limit): array
    {
        if ($graceHours < 1) {
            throw new InvalidArgumentException('Grace hours must be a positive integer.');
        }
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Limit must be between 1 and 1000.');
        }
        $utc = $now->setTimezone(new DateTimeZone('UTC'));
        return [
            $utc->format('Y-m-d H:i:s.u'),
            $utc->sub(new DateInterval('PT' . $graceHours . 'H'))->format('Y-m-d H:i:s.u'),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function selectCandidates(string $cutoff, int $limit): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT registration.id AS registration_id,
                   registration.studentId AS student_id,
                   registration.activityId AS activity_id,
                   student.userId AS recipient_user_id,
                   activity.endAt AS activity_end_at
            FROM activity_registrations registration
            INNER JOIN activities activity ON activity.id = registration.activityId
            LEFT JOIN checkins confirmed
              ON confirmed.registrationId = registration.id
             AND confirmed.status = 'confirmed'
             AND confirmed.confirmedAt IS NOT NULL
            LEFT JOIN student_profiles student ON student.id = registration.studentId
            WHERE registration.status = 'approved'
              AND activity.endAt IS NOT NULL
              AND activity.endAt <= :cutoff_utc
              AND confirmed.id IS NULL
            ORDER BY activity.endAt, registration.id
            LIMIT :candidate_limit
        SQL);
        $statement->bindValue(':cutoff_utc', $cutoff, PDO::PARAM_STR);
        $statement->bindValue(':candidate_limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function lockRegistrationStatus(string $registrationId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT status FROM activity_registrations WHERE id = :registration_id LIMIT 1' . $this->lockSuffix()
        );
        $statement->execute(['registration_id' => $registrationId]);
        $status = $statement->fetchColumn();
        return is_string($status) ? $status : null;
    }

    /** @return array<string,mixed>|null */
    private function eligibleCandidate(string $registrationId, string $cutoff): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT registration.id AS registration_id,
                   registration.studentId AS student_id,
                   registration.activityId AS activity_id,
                   student.userId AS recipient_user_id
            FROM activity_registrations registration
            INNER JOIN activities activity ON activity.id = registration.activityId
            LEFT JOIN checkins confirmed
              ON confirmed.registrationId = registration.id
             AND confirmed.status = 'confirmed'
             AND confirmed.confirmedAt IS NOT NULL
            LEFT JOIN student_profiles student ON student.id = registration.studentId
            WHERE registration.id = :registration_id
              AND registration.status = 'approved'
              AND activity.endAt IS NOT NULL
              AND activity.endAt <= :cutoff_utc
              AND confirmed.id IS NULL
            LIMIT 1
        SQL);
        $statement->execute(['registration_id' => $registrationId, 'cutoff_utc' => $cutoff]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function insertAudit(string $registrationId, string $studentId, string $activityId, string $createdAt): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO audit_logs
                (id,userId,action,entityType,entityId,requestId,ipAddress,metadata,createdAt)
            VALUES
                (:id,NULL,'activity_registration.no_show_reconciled','activity_registration',:entity_id,NULL,NULL,:metadata,:created_at)
        SQL);
        $statement->execute([
            'id' => Uuid::v4(),
            'entity_id' => $registrationId,
            'metadata' => json_encode([
                'actor' => 'system',
                'reason' => 'no_confirmed_checkin_after_24h',
                'studentId' => $studentId,
                'activityId' => $activityId,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'created_at' => $createdAt,
        ]);
    }

    private function deliverPendingNotifications(int $limit): void
    {
        $eventKey = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "'activity_attendance_no_show:' || registration.id"
            : "CONCAT('activity_attendance_no_show:', registration.id)";
        $statement = $this->pdo->prepare(<<<SQL
            SELECT registration.id AS registration_id,
                   registration.studentId AS student_id,
                   registration.activityId AS activity_id,
                   student.userId AS recipient_user_id
            FROM activity_registrations registration
            INNER JOIN student_profiles student ON student.id = registration.studentId
            WHERE registration.status = 'no_show'
              AND registration.attendanceResolvedAt IS NOT NULL
              AND registration.attendanceResolutionReason = 'no_confirmed_checkin_after_24h'
              AND TRIM(COALESCE(student.userId, '')) <> ''
              AND EXISTS (
                  SELECT 1
                  FROM audit_logs audit
                  WHERE audit.entityId = registration.id
                    AND audit.action = 'activity_registration.no_show_reconciled'
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM notifications notification
                  WHERE notification.userId = student.userId
                    AND notification.eventKey = {$eventKey}
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM audit_logs suppression
                  WHERE suppression.entityId = registration.id
                    AND suppression.action = 'activity_registration.no_show_notification_suppressed'
              )
            ORDER BY registration.attendanceResolvedAt, registration.id
            LIMIT :notification_limit
        SQL);
        $statement->bindValue(':notification_limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $firstFailure = null;
        $notifications = $this->notificationService();
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $pending) {
            try {
                $registrationId = (string) $pending['registration_id'];
                $studentId = (string) $pending['student_id'];
                $preferences = $notifications->preferencesForStudent($studentId);
                if (($preferences['activity_attendance_no_show']['inAppEnabled'] ?? true) === false) {
                    $this->insertSuppressionAudit($registrationId, $studentId, (string) $pending['activity_id']);
                    continue;
                }
                $notifications->publish(
                    (string) $pending['recipient_user_id'],
                    'activity_attendance_no_show',
                    'Không tham gia hoạt động',
                    'Hoạt động đã được đối soát do không có check-in xác nhận sau 24 giờ.',
                    '/app/learner/activity-history.php',
                    'activity_attendance_no_show:' . $registrationId,
                    $studentId
                );
            } catch (Throwable $exception) {
                $firstFailure ??= $exception;
            }
        }
        if ($firstFailure !== null) {
            throw $firstFailure;
        }
    }

    private function insertSuppressionAudit(string $registrationId, string $studentId, string $activityId): void
    {
        $id = $this->suppressionAuditId($registrationId);
        try {
            $statement = $this->pdo->prepare(<<<'SQL'
                INSERT INTO audit_logs
                    (id,userId,action,entityType,entityId,requestId,ipAddress,metadata,createdAt)
                VALUES
                    (:id,NULL,'activity_registration.no_show_notification_suppressed','activity_registration',:entity_id,NULL,NULL,:metadata,:created_at)
            SQL);
            $statement->execute([
                'id' => $id,
                'entity_id' => $registrationId,
                'metadata' => json_encode([
                    'actor' => 'system',
                    'reason' => 'learner_notification_preference_disabled',
                    'studentId' => $studentId,
                    'activityId' => $activityId,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'created_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
            ]);
        } catch (Throwable $exception) {
            $check = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE id = :id');
            $check->execute(['id' => $id]);
            if ((int) $check->fetchColumn() !== 1) {
                throw $exception;
            }
        }
    }

    private function suppressionAuditId(string $registrationId): string
    {
        $hex = substr(hash('sha256', 'talenthub:no-show-notification-suppressed:' . $registrationId), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    private function notificationService(): NotificationService
    {
        return $this->notifications ?? new NotificationService(new DatabaseNotificationRepository($this->pdo));
    }

    private function lockSuffix(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }
}
