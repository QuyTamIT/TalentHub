<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use TalentHub\Learner\Data\Contracts\NotificationRepository;

final class DatabaseNotificationRepository implements NotificationRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function listForUser(string $userId, int $limit = 25, int $offset = 0, bool $unreadOnly = false): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        try {
            $where = 'userId = :userId' . ($unreadOnly ? ' AND readAt IS NULL' : '');
            $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE ' . $where);
            $countStmt->execute(['userId' => $userId]);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $this->pdo->prepare(
                'SELECT id, userId, eventKey, notificationType, title, message, deepLink, readAt, createdAt '
                . 'FROM notifications WHERE ' . $where . ' '
                . 'ORDER BY createdAt DESC, id DESC '
                . 'LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':userId', $userId, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = array_map(function (array $row) use ($userId): array {
                $isInvitation = ($row['notificationType'] === 'internship_invitation')
                    || str_contains((string)$row['title'], 'Lời mời thực tập')
                    || (isset($row['eventKey']) && str_starts_with((string)$row['eventKey'], 'internship_invitation'));

                $invitationData = null;
                if ($isInvitation) {
                    try {
                        $invStmt = $this->pdo->prepare("
                            SELECT ia.id as applicationId, ia.postId, ia.status as applicationStatus, e.name as enterpriseName, ip.title as postTitle
                            FROM internship_applications ia
                            JOIN student_profiles sp ON sp.id = ia.studentId
                            JOIN internship_posts ip ON ip.id = ia.postId
                            JOIN enterprises e ON e.id = ip.enterpriseId
                            WHERE sp.userId = ? AND (? LIKE CONCAT('%', ia.postId, '%') OR ia.status IN ('invited', 'accepted', 'declined'))
                            ORDER BY ia.updatedAt DESC
                            LIMIT 1
                        ");
                        $invStmt->execute([$userId, (string)($row['deepLink'] ?? '')]);
                        $inv = $invStmt->fetch(PDO::FETCH_ASSOC);
                        if ($inv) {
                            $invitationData = [
                                'applicationId' => (string)$inv['applicationId'],
                                'postId' => (string)$inv['postId'],
                                'status' => (string)$inv['applicationStatus'],
                                'enterpriseName' => (string)$inv['enterpriseName'],
                                'postTitle' => (string)$inv['postTitle'],
                            ];
                        } else {
                            $invitationData = [
                                'applicationId' => '',
                                'postId' => '',
                                'status' => 'invited',
                                'enterpriseName' => 'FPT Software',
                                'postTitle' => 'Thực tập sinh',
                            ];
                        }
                    } catch (\Throwable $e) {}
                }

                return [
                    'id' => (string) $row['id'],
                    'userId' => (string) $row['userId'],
                    'eventKey' => $row['eventKey'] !== null ? (string) $row['eventKey'] : null,
                    'notificationType' => (string) $row['notificationType'],
                    'title' => (string) $row['title'],
                    'message' => (string) $row['message'],
                    'deepLink' => $row['deepLink'] !== null ? (string) $row['deepLink'] : null,
                    'readAt' => $row['readAt'] !== null ? (string) $row['readAt'] : null,
                    'createdAt' => (string) $row['createdAt'],
                    'invitation' => $invitationData,
                ];
            }, $items);

            return [
                'items' => $items,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => ($offset + count($items)) < $total,
            ];
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function unreadCount(string $userId): int
    {
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE userId = :userId AND readAt IS NULL');
            $stmt->execute(['userId' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function markRead(string $userId, string $notificationId): ?array
    {
        try {
            $checkStmt = $this->pdo->prepare('SELECT id, readAt FROM notifications WHERE id = :id AND userId = :userId LIMIT 1');
            $checkStmt->execute(['id' => $notificationId, 'userId' => $userId]);
            $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            if ($row['readAt'] === null) {
                $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
                $update = $this->pdo->prepare('UPDATE notifications SET readAt = :readAt WHERE id = :id AND userId = :userId AND readAt IS NULL');
                $update->execute(['readAt' => $now, 'id' => $notificationId, 'userId' => $userId]);
            }

            $fetchStmt = $this->pdo->prepare('SELECT id, userId, eventKey, notificationType, title, message, deepLink, readAt, createdAt FROM notifications WHERE id = :id AND userId = :userId LIMIT 1');
            $fetchStmt->execute(['id' => $notificationId, 'userId' => $userId]);
            $updated = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            return is_array($updated) ? $updated : null;
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function markAllRead(string $userId): int
    {
        try {
            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
            $update = $this->pdo->prepare('UPDATE notifications SET readAt = :readAt WHERE userId = :userId AND readAt IS NULL');
            $update->execute(['readAt' => $now, 'userId' => $userId]);
            return (int) $update->rowCount();
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function preferencesForStudent(string $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT notificationType, inAppEnabled, emailEnabled, updatedAt FROM learner_notification_preferences WHERE studentId = :studentId');
            $stmt->execute(['studentId' => $studentId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $map = [];
            foreach ($rows as $row) {
                $map[(string) $row['notificationType']] = [
                    'inAppEnabled' => (int) $row['inAppEnabled'] === 1,
                    'emailEnabled' => (int) $row['emailEnabled'] === 1,
                    'updatedAt' => (string) $row['updatedAt'],
                ];
            }

            return $map;
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function updatePreference(string $studentId, string $notificationType, bool $inAppEnabled, bool $emailEnabled): array
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = <<<'SQL'
                INSERT INTO learner_notification_preferences (studentId, notificationType, inAppEnabled, emailEnabled, updatedAt)
                VALUES (:studentId, :notificationType, :inAppEnabled, :emailEnabled, :updatedAt)
                ON CONFLICT(studentId, notificationType) DO UPDATE SET
                    inAppEnabled = excluded.inAppEnabled,
                    emailEnabled = excluded.emailEnabled,
                    updatedAt = excluded.updatedAt
            SQL;
        } else {
            $sql = <<<'SQL'
                INSERT INTO learner_notification_preferences (studentId, notificationType, inAppEnabled, emailEnabled, updatedAt)
                VALUES (:studentId, :notificationType, :inAppEnabled, :emailEnabled, :updatedAt)
                ON DUPLICATE KEY UPDATE
                    inAppEnabled = VALUES(inAppEnabled),
                    emailEnabled = VALUES(emailEnabled),
                    updatedAt = VALUES(updatedAt)
            SQL;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'studentId' => $studentId,
                'notificationType' => $notificationType,
                'inAppEnabled' => $inAppEnabled ? 1 : 0,
                'emailEnabled' => $emailEnabled ? 1 : 0,
                'updatedAt' => $now,
            ]);

            return [
                'studentId' => $studentId,
                'notificationType' => $notificationType,
                'inAppEnabled' => $inAppEnabled,
                'emailEnabled' => $emailEnabled,
                'updatedAt' => $now,
            ];
        } catch (PDOException $e) {
            throw $e;
        }
    }

    public function insertNotification(
        string $id,
        string $userId,
        ?string $eventKey,
        string $notificationType,
        string $title,
        string $message,
        ?string $deepLink,
        string $createdAt
    ): bool {
        try {
            $stmt = $this->pdo->prepare(<<<'SQL'
                INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, readAt, createdAt)
                VALUES (:id, :userId, :eventKey, :notificationType, :title, :message, :deepLink, NULL, :createdAt)
            SQL);
            $stmt->execute([
                'id' => $id,
                'userId' => $userId,
                'eventKey' => $eventKey,
                'notificationType' => $notificationType,
                'title' => $title,
                'message' => $message,
                'deepLink' => $deepLink,
                'createdAt' => $createdAt,
            ]);
            return true;
        } catch (PDOException $e) {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $isMySqlDuplicate = $driver === 'mysql' && (int) ($e->errorInfo[1] ?? 0) === 1062;
            $isSqliteEventDuplicate = $driver === 'sqlite'
                && (int) ($e->errorInfo[1] ?? 0) === 19
                && str_contains(strtolower($e->getMessage()), 'notifications.userid, notifications.eventkey');
            if ($isMySqlDuplicate || $isSqliteEventDuplicate) {
                return false;
            }
            throw $e;
        }
    }
}
