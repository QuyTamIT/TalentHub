<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Contracts\CheckinRepository;
use TalentHub\Learner\Data\Service\BadgeAwardService;
use TalentHub\Learner\Data\Service\BadgeRuleEngine;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;
use Throwable;

final class DatabaseCheckinRepository implements CheckinRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?NotificationService $notifications = null,
        private readonly ?BadgeAwardService $badgeAwardService = null
    ) {}


    public function createConfirmed(string $studentId, string $actorUserId, string $requestId, string $tokenHash): array
    {
        $candidate = $this->candidateSession($tokenHash);
        if ($candidate === null) {
            throw new ApiException(404, 'QR_TOKEN_INVALID', 'Ma QR khong hop le hoac khong the su dung.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->lockStudent($studentId);
            $activity = $this->lockActivity((string) $candidate['activityId']);
            if ($activity === null || (string) $activity['status'] !== 'ongoing') {
                throw new ApiException(409, 'ACTIVITY_NOT_CHECKIN_ELIGIBLE', 'Hoat dong chua mo check-in.');
            }
            $registration = $this->lockRegistration($studentId, (string) $candidate['activityId']);
            if ($registration === null) {
                throw new ApiException(409, 'REGISTRATION_NOT_ELIGIBLE', 'Dang ky khong du dieu kien check-in.');
            }
            if ($this->existingCheckinId((string) $registration['id']) !== null) {
                throw new ApiException(409, 'CHECKIN_ALREADY_EXISTS', 'Dang ky nay da check-in.');
            }
            if ((string) $registration['status'] !== 'approved') {
                throw new ApiException(409, 'REGISTRATION_NOT_ELIGIBLE', 'Dang ky khong du dieu kien check-in.');
            }
            $session = $this->lockSession($tokenHash);
            if ($session === null) {
                throw new ApiException(404, 'QR_TOKEN_INVALID', 'Ma QR khong hop le hoac khong the su dung.');
            }
            if ((string) $session['activityId'] !== (string) $candidate['activityId']) {
                throw new ApiException(409, 'CHECKIN_STATE_CONFLICT', 'Trang thai QR da thay doi truoc khi check-in hoan tat.');
            }
            $this->assertSessionUsable($session);
            $policy = $this->lockPolicy((string) $session['activityId']);
            if ($policy === null) {
                throw new ApiException(409, 'EXPERIENCE_POLICY_MISSING', 'Hoat dong chua co chinh sach gio xac nhan.');
            }

            $now = $this->dbNow();
            $checkinId = Uuid::v4();
            $experienceId = Uuid::v4();
            $this->insertCheckin($checkinId, (string) $registration['id'], (string) $session['id'], $now);
            $this->incrementScan((string) $session['id']);
            $this->markAttended((string) $registration['id'], $now);
            $this->insertExperience($experienceId, $studentId, (string) $session['activityId'], $checkinId, (string) $policy['confirmedHours'], $now);
            $this->audit($actorUserId, $requestId, $checkinId, (string) $session['activityId'], (string) $registration['id'], $experienceId, $now);

            $this->getNotificationService()->publish(
                $actorUserId,
                'activity_checkin_committed',
                'Check-in hoạt động thành công',
                'Bạn đã check-in thành công cho hoạt động ' . ($activity['title'] ?? '') . ' và được ghi nhận ' . ($policy['confirmedHours'] ?? '0') . ' giờ trải nghiệm.',
                '/app/learner/checkin.php',
                'activity_checkin:' . $checkinId,
                $studentId
            );

            if ($this->hasBadgesTable()) {
                $this->getBadgeAwardService()->evaluateAndAward($studentId, 'system');
            }

            $result = $this->presentCreated($checkinId);
            $this->pdo->commit();
            return $result;

        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof PDOException && $this->isDuplicate($exception)) {
                throw new ApiException(409, 'CHECKIN_ALREADY_EXISTS', 'Dang ky nay da check-in.');
            }
            throw $exception;
        }
    }

    public function history(string $studentId, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT c.id checkinId, c.status checkinStatus, c.checkedInAt, c.confirmedAt,
                   a.id activityId, a.title activityTitle, a.category, a.startAt, a.endAt,
                   el.id experienceLogId, el.hours, el.status experienceStatus, el.confirmedAt experienceConfirmedAt
            FROM checkins c
            INNER JOIN activity_registrations ar ON ar.id = c.registrationId
            INNER JOIN activities a ON a.id = ar.activityId
            INNER JOIN experience_logs el ON el.checkinId = c.id AND el.studentId = ar.studentId AND el.status = 'confirmed'
            WHERE ar.studentId = :studentId
              AND c.status = 'confirmed'
            ORDER BY c.checkedInAt DESC, c.createdAt DESC
            LIMIT {$limit} OFFSET {$offset}
        SQL);
        $statement->execute(['studentId' => $studentId]);
        return array_map(fn (array $row): array => $this->presentRow($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function candidateSession(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, activityId FROM activity_qr_sessions WHERE tokenHash = :hash LIMIT 1');
        $statement->execute(['hash' => $tokenHash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockStudent(string $studentId): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM student_profiles WHERE id = :id LIMIT 1' . $this->lockSuffix());
        $statement->execute(['id' => $studentId]);
        if ($statement->fetchColumn() === false) {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Khong tim thay ho so hoc vien hop le.');
        }
    }

    private function lockSession(string $tokenHash): ?array
    {
        $expiryProjection = $this->isSqlite()
            ? "datetime(expiresAt) > datetime('now') AS isUnexpired"
            : 'expiresAt > UTC_TIMESTAMP(6) AS isUnexpired';
        $statement = $this->pdo->prepare("SELECT *, {$expiryProjection} FROM activity_qr_sessions WHERE tokenHash = :hash LIMIT 1" . $this->lockSuffix());
        $statement->execute(['hash' => $tokenHash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockActivity(string $activityId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM activities WHERE id = :id LIMIT 1' . $this->lockSuffix());
        $statement->execute(['id' => $activityId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockRegistration(string $studentId, string $activityId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM activity_registrations WHERE studentId = :studentId AND activityId = :activityId LIMIT 1' . $this->lockSuffix());
        $statement->execute(['studentId' => $studentId, 'activityId' => $activityId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockPolicy(string $activityId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM activity_experience_policies WHERE activityId = :activityId LIMIT 1' . $this->lockSuffix());
        $statement->execute(['activityId' => $activityId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function assertSessionUsable(array $session): void
    {
        $status = (string) ($session['status'] ?? '');
        if ($status === 'revoked' || $session['revokedAt'] !== null) {
            throw new ApiException(409, 'QR_SESSION_REVOKED', 'Phien QR da bi thu hoi.');
        }
        if ($status !== 'active') {
            throw new ApiException(409, 'QR_TOKEN_INVALID', 'Ma QR khong hop le hoac khong the su dung.');
        }
        if ((int) ($session['isUnexpired'] ?? 0) !== 1) {
            throw new ApiException(409, 'QR_SESSION_EXPIRED', 'Phien QR da het han.');
        }
        if ((int) $session['usedScans'] >= (int) $session['maxScans']) {
            throw new ApiException(409, 'QR_SESSION_EXHAUSTED', 'Phien QR da het luot quet.');
        }
    }

    private function insertCheckin(string $id, string $registrationId, string $qrSessionId, string $now): void
    {
        $statement = $this->pdo->prepare("INSERT INTO checkins (id, registrationId, qrSessionId, status, checkedInAt, confirmedAt, createdAt) VALUES (:id, :registrationId, :qrSessionId, 'confirmed', :now, :now2, :now3)");
        $statement->execute(['id' => $id, 'registrationId' => $registrationId, 'qrSessionId' => $qrSessionId, 'now' => $now, 'now2' => $now, 'now3' => $now]);
    }

    private function incrementScan(string $sessionId): void
    {
        $now = $this->isSqlite() ? "strftime('%Y-%m-%d %H:%M:%f','now')" : 'UTC_TIMESTAMP(6)';
        $expiry = $this->isSqlite() ? "datetime(expiresAt) > datetime('now')" : 'expiresAt > UTC_TIMESTAMP(6)';
        $statement = $this->pdo->prepare("UPDATE activity_qr_sessions SET usedScans = usedScans + 1, updatedAt = {$now} WHERE id = :id AND status = 'active' AND revokedAt IS NULL AND {$expiry} AND usedScans < maxScans");
        $statement->execute(['id' => $sessionId]);
        if ($statement->rowCount() !== 1) {
            throw new ApiException(409, 'CHECKIN_STATE_CONFLICT', 'Trang thai QR da thay doi truoc khi check-in hoan tat.');
        }
    }

    private function markAttended(string $registrationId, string $now): void
    {
        $statement = $this->pdo->prepare("UPDATE activity_registrations SET status = 'attended', updatedAt = :now WHERE id = :id AND status = 'approved'");
        $statement->execute(['now' => $now, 'id' => $registrationId]);
        if ($statement->rowCount() !== 1) {
            throw new ApiException(409, 'CHECKIN_STATE_CONFLICT', 'Dang ky da thay doi truoc khi check-in hoan tat.');
        }
    }

    private function insertExperience(string $id, string $studentId, string $activityId, string $checkinId, string $hours, string $now): void
    {
        $statement = $this->pdo->prepare("INSERT INTO experience_logs (id, studentId, activityId, checkinId, hours, status, auditReason, confirmedAt, createdAt) VALUES (:id, :studentId, :activityId, :checkinId, :hours, 'confirmed', 'automatic_checkin_policy', :now, :createdAt)");
        $statement->execute(['id' => $id, 'studentId' => $studentId, 'activityId' => $activityId, 'checkinId' => $checkinId, 'hours' => $hours, 'now' => $now, 'createdAt' => $now]);
    }

    private function audit(string $userId, string $requestId, string $checkinId, string $activityId, string $registrationId, string $experienceId, string $now): void
    {
        $statement = $this->pdo->prepare("INSERT INTO audit_logs (id, userId, action, entityType, entityId, requestId, ipAddress, metadata, createdAt) VALUES (:id, :userId, 'checkin.confirmed', 'checkin', :entityId, :requestId, NULL, :metadata, :createdAt)");
        $statement->execute([
            'id' => Uuid::v4(),
            'userId' => $userId,
            'entityId' => $checkinId,
            'requestId' => $requestId,
            'metadata' => json_encode(['activityId' => $activityId, 'registrationId' => $registrationId, 'experienceLogId' => $experienceId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'createdAt' => $now,
        ]);
    }

    private function existingCheckinId(string $registrationId): ?string
    {
        $statement = $this->pdo->prepare('SELECT id FROM checkins WHERE registrationId = :registrationId LIMIT 1');
        $statement->execute(['registrationId' => $registrationId]);
        $id = $statement->fetchColumn();
        return is_string($id) && $id !== '' ? $id : null;
    }

    private function presentCreated(string $checkinId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT c.id checkinId, c.status checkinStatus, c.checkedInAt, c.confirmedAt,
                   a.id activityId, a.title activityTitle, a.category, a.startAt, a.endAt,
                   el.id experienceLogId, el.hours, el.status experienceStatus, el.confirmedAt experienceConfirmedAt
            FROM checkins c
            INNER JOIN activity_registrations ar ON ar.id = c.registrationId
            INNER JOIN activities a ON a.id = ar.activityId
            INNER JOIN experience_logs el ON el.checkinId = c.id
            WHERE c.id = :id
            LIMIT 1
        SQL);
        $statement->execute(['id' => $checkinId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new ApiException(500, 'CHECKIN_STATE_CONFLICT', 'Khong the doc check-in vua tao.');
        }
        return $this->presentRow($row);
    }

    private function presentRow(array $row): array
    {
        return [
            'checkinId' => (string) $row['checkinId'],
            'status' => (string) $row['checkinStatus'],
            'checkedInAt' => $this->iso((string) $row['checkedInAt']),
            'confirmedAt' => $row['confirmedAt'] !== null ? $this->iso((string) $row['confirmedAt']) : null,
            'activity' => [
                'id' => (string) $row['activityId'],
                'title' => (string) $row['activityTitle'],
                'category' => (string) ($row['category'] ?? ''),
                'startAt' => $this->iso((string) $row['startAt']),
                'endAt' => $row['endAt'] !== null ? $this->iso((string) $row['endAt']) : null,
            ],
            'experience' => [
                'id' => (string) ($row['experienceLogId'] ?? ''),
                'hours' => number_format((float) ($row['hours'] ?? 0), 2, '.', ''),
                'status' => (string) ($row['experienceStatus'] ?? ''),
                'confirmedAt' => $row['experienceConfirmedAt'] !== null ? $this->iso((string) $row['experienceConfirmedAt']) : null,
            ],
        ];
    }

    private function dbNow(): string
    {
        $expression = $this->isSqlite()
            ? "strftime('%Y-%m-%d %H:%M:%f','now')"
            : "DATE_FORMAT(UTC_TIMESTAMP(6), '%Y-%m-%d %H:%i:%s.%f')";
        return (string) $this->pdo->query("SELECT {$expression}")->fetchColumn();
    }

    private function iso(string $mysql): string
    {
        $dt = $this->parseUtc($mysql);
        return $dt?->setTimezone(new DateTimeZone('UTC'))->format(DateTimeImmutable::ATOM) ?? gmdate('Y-m-d\TH:i:s\Z');
    }

    private function parseUtc(string $mysql): ?DateTimeImmutable
    {
        if ($mysql === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($mysql, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }
    }

    private function lockSuffix(): string
    {
        return $this->isSqlite() ? '' : ' FOR UPDATE';
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
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

    private function getBadgeAwardService(): BadgeAwardService
    {
        if ($this->badgeAwardService !== null) {
            return $this->badgeAwardService;
        }

        if (!class_exists('TalentHub\Learner\Data\Service\BadgeAwardService', false)) {
            require_once dirname(__DIR__) . '/Contracts/BadgeRepository.php';
            require_once dirname(__DIR__) . '/Contracts/StatisticsRepository.php';
            require_once dirname(__DIR__) . '/Domain/LevelProgression.php';
            require_once dirname(__DIR__) . '/Service/BadgeRuleEngine.php';
            require_once dirname(__DIR__) . '/Service/BadgeAwardService.php';
            require_once dirname(__DIR__) . '/Database/DatabaseBadgeRepository.php';
            require_once dirname(__DIR__) . '/Database/DatabaseStatisticsRepository.php';
        }

        return new BadgeAwardService(
            new DatabaseBadgeRepository($this->pdo),
            new DatabaseStatisticsRepository($this->pdo),
            new BadgeRuleEngine(),
            $this->getNotificationService()
        );
    }

    private function hasBadgesTable(): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'badges' LIMIT 1");
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'badges' LIMIT 1");
        return (bool) $stmt->fetchColumn();
    }
}
