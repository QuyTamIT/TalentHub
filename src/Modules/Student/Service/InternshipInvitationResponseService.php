<?php

declare(strict_types=1);

namespace TalentHub\Modules\Student\Service;

use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;
use Throwable;

final class InternshipInvitationResponseService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{notificationId:string,applicationId:string,postId:string,status:string,enterpriseName:string,postTitle:string,unreadCount:int} */
    public function respond(
        string $studentId,
        string $userId,
        string $notificationId,
        string $decision,
        string $requestId,
    ): array {
        $studentId = $this->uuid($studentId, 'studentId');
        $userId = $this->uuid($userId, 'userId');
        $notificationId = $this->uuid($notificationId, 'notificationId');
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['accept', 'decline'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Phản hồi lời mời không hợp lệ.');
        }
        $targetStatus = $decision === 'accept' ? 'accepted' : 'declined';

        $this->pdo->beginTransaction();
        try {
            $notification = $this->lockNotification($notificationId, $userId);
            if ($notification === null || (string) ($notification['notificationType'] ?? '') !== 'internship_invitation') {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy lời mời thực tập thuộc tài khoản của bạn.');
            }

            $application = $this->lockApplicationForNotification($notification, $studentId, $userId);
            if ($application === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ thực tập của lời mời này.');
            }

            $applicationId = (string) $application['id'];
            $currentStatus = (string) $application['status'];
            if ($currentStatus === $targetStatus) {
                $this->markNotificationRead($notificationId, $userId);
                $this->pdo->commit();
                return $this->result($notificationId, $application, $targetStatus, $userId);
            }
            if ($currentStatus !== 'invited') {
                throw new ApiException(409, 'ILLEGAL_STATUS_TRANSITION', 'Lời mời đã được xử lý hoặc không còn hiệu lực.');
            }
            if ($targetStatus === 'accepted' && $this->hasOtherAcceptedPlacement($studentId, $applicationId)) {
                throw new ApiException(409, 'INTERNSHIP_PLACEMENT_LOCKED', 'Bạn đã xác nhận một vị trí thực tập khác.');
            }

            $now = gmdate('Y-m-d H:i:s.u');
            $update = $this->pdo->prepare(
                'UPDATE internship_applications SET status = :status, updatedAt = :updatedAt '
                . "WHERE id = :id AND (studentId = :studentId OR studentId IN (SELECT id FROM student_profiles WHERE userId = :userId)) AND status = 'invited'"
            );
            $update->execute([
                'status' => $targetStatus,
                'updatedAt' => $now,
                'id' => $applicationId,
                'studentId' => $studentId,
                'userId' => $userId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new ApiException(409, 'CONCURRENT_MODIFICATION', 'Trạng thái lời mời đã thay đổi.');
            }

            $history = $this->pdo->prepare(<<<'SQL'
                INSERT INTO application_status_history
                    (id, applicationId, fromStatus, toStatus, changedByUserId, changedByRole, note, createdAt)
                VALUES
                    (:id, :applicationId, 'invited', :toStatus, :userId, 'student', :note, :createdAt)
            SQL);
            $history->execute([
                'id' => Uuid::v4(),
                'applicationId' => $applicationId,
                'toStatus' => $targetStatus,
                'userId' => $userId,
                'note' => $targetStatus === 'accepted' ? 'Học viên chấp nhận lời mời thực tập' : 'Học viên từ chối lời mời thực tập',
                'createdAt' => $now,
            ]);
            $this->markNotificationRead($notificationId, $userId, $now);
            $this->audit($userId, $applicationId, $requestId, $targetStatus, $now);
            $application['status'] = $targetStatus;

            $this->pdo->commit();
            return $this->result($notificationId, $application, $targetStatus, $userId);
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function lockNotification(string $notificationId, string $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, userId, eventKey, notificationType, deepLink, readAt FROM notifications '
            . 'WHERE id = :id AND userId = :userId LIMIT 1' . $this->lockSuffix()
        );
        $statement->execute(['id' => $notificationId, 'userId' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockApplicationForNotification(array $notification, string $studentId, string $userId): ?array
    {
        $eventKey = (string) ($notification['eventKey'] ?? '');
        $deepLink = (string) ($notification['deepLink'] ?? '');

        // 1. Direct regex match on standard eventKey format: internship_invitation:{appId}
        if (preg_match('/\Ainternship_invitation:([0-9a-f-]{36})(?::[A-Za-z0-9_-]+)?\z/i', $eventKey, $matches) === 1) {
            $app = $this->lockApplicationById($matches[1], $studentId, $userId);
            if ($app !== null) {
                return $app;
            }
        }

        // 2. Extract any candidate UUIDs from eventKey and deepLink
        $candidateUuids = [];
        if (preg_match_all('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $eventKey . ' ' . $deepLink, $matches)) {
            $candidateUuids = array_unique(array_map('strtolower', $matches[1]));
        }

        foreach ($candidateUuids as $uuid) {
            $app = $this->lockApplicationById($uuid, $studentId, $userId);
            if ($app !== null) {
                return $app;
            }
            $app = $this->lockApplicationByPostId($uuid, $studentId, $userId);
            if ($app !== null) {
                return $app;
            }
        }

        // 3. Fallback: match most recent invited application for this student
        return $this->lockLatestInvitedApplication($studentId, $userId);
    }

    private function lockApplicationById(string $applicationId, string $studentId, string $userId): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT ia.id, ia.postId, ia.studentId, ia.status,
                   ip.title AS postTitle, e.name AS enterpriseName
            FROM internship_applications ia
            INNER JOIN internship_posts ip ON ip.id = ia.postId
            INNER JOIN enterprises e ON e.id = ip.enterpriseId
            INNER JOIN student_profiles sp ON sp.id = ia.studentId
            WHERE ia.id = :id AND (ia.studentId = :studentId OR sp.userId = :userId)
            LIMIT 1{$this->lockSuffix()}
        SQL);
        $statement->execute(['id' => $applicationId, 'studentId' => $studentId, 'userId' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockApplicationByPostId(string $postId, string $studentId, string $userId): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT ia.id, ia.postId, ia.studentId, ia.status,
                   ip.title AS postTitle, e.name AS enterpriseName
            FROM internship_applications ia
            INNER JOIN internship_posts ip ON ip.id = ia.postId
            INNER JOIN enterprises e ON e.id = ip.enterpriseId
            INNER JOIN student_profiles sp ON sp.id = ia.studentId
            WHERE ia.postId = :postId AND (ia.studentId = :studentId OR sp.userId = :userId)
            ORDER BY (ia.status = 'invited') DESC, ia.updatedAt DESC
            LIMIT 1{$this->lockSuffix()}
        SQL);
        $statement->execute(['postId' => $postId, 'studentId' => $studentId, 'userId' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockLatestInvitedApplication(string $studentId, string $userId): ?array
    {
        $statement = $this->pdo->prepare(<<<SQL
            SELECT ia.id, ia.postId, ia.studentId, ia.status,
                   ip.title AS postTitle, e.name AS enterpriseName
            FROM internship_applications ia
            INNER JOIN internship_posts ip ON ip.id = ia.postId
            INNER JOIN enterprises e ON e.id = ip.enterpriseId
            INNER JOIN student_profiles sp ON sp.id = ia.studentId
            WHERE (ia.studentId = :studentId OR sp.userId = :userId)
              AND ia.status = 'invited'
            ORDER BY ia.updatedAt DESC
            LIMIT 1{$this->lockSuffix()}
        SQL);
        $statement->execute(['studentId' => $studentId, 'userId' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function hasOtherAcceptedPlacement(string $studentId, string $applicationId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM internship_applications WHERE studentId = :studentId "
            . "AND id <> :applicationId AND status = 'accepted' LIMIT 1" . $this->lockSuffix()
        );
        $statement->execute(['studentId' => $studentId, 'applicationId' => $applicationId]);
        return $statement->fetchColumn() !== false;
    }

    private function markNotificationRead(string $notificationId, string $userId, ?string $now = null): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE notifications SET readAt = COALESCE(readAt, :readAt) WHERE id = :id AND userId = :userId'
        );
        $statement->execute([
            'readAt' => $now ?? gmdate('Y-m-d H:i:s.u'),
            'id' => $notificationId,
            'userId' => $userId,
        ]);
    }

    private function audit(string $userId, string $applicationId, string $requestId, string $status, string $now): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO audit_logs
                (id, userId, action, entityType, entityId, requestId, ipAddress, metadata, createdAt)
            VALUES
                (:id, :userId, 'internship_invitation.responded', 'internship_application', :entityId,
                 :requestId, :ipAddress, :metadata, :createdAt)
        SQL);
        $statement->execute([
            'id' => Uuid::v4(),
            'userId' => $userId,
            'entityId' => $applicationId,
            'requestId' => $requestId,
            'ipAddress' => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
            'metadata' => json_encode(['status' => $status], JSON_THROW_ON_ERROR),
            'createdAt' => $now,
        ]);
    }

    /** @param array<string,mixed> $application */
    private function result(string $notificationId, array $application, string $status, string $userId): array
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE userId = :userId AND readAt IS NULL');
        $statement->execute(['userId' => $userId]);
        return [
            'notificationId' => $notificationId,
            'applicationId' => (string) $application['id'],
            'postId' => (string) $application['postId'],
            'status' => $status,
            'enterpriseName' => (string) $application['enterpriseName'],
            'postTitle' => (string) $application['postTitle'],
            'unreadCount' => (int) $statement->fetchColumn(),
        ];
    }

    private function uuid(string $value, string $field): string
    {
        $value = strtolower(trim($value));
        if (!Uuid::isValid($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không hợp lệ.");
        }
        return $value;
    }

    private function lockSuffix(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }
}