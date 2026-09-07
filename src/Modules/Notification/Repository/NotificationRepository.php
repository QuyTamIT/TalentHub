<?php
declare(strict_types=1);
namespace TalentHub\Modules\Notification\Repository;

use PDO;
use TalentHub\Http\CollectionQuery;

final class NotificationRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function list(string $userId, CollectionQuery $q): array
    {
        $where = 'userId=:uid';
        $p = ['uid' => $userId];
        if (isset($q->filters['read'])) {
            $where .= $q->filters['read'] === 'true' ? ' AND readAt IS NOT NULL' : ' AND readAt IS NULL';
        }
        $s = $this->pdo->prepare(
            "SELECT id,notificationType,title,message,eventKey,deepLink,(readAt IS NOT NULL) AS isRead,readAt,createdAt "
            . "FROM notifications WHERE {$where} ORDER BY createdAt " . strtoupper($q->direction)
            . ", id " . strtoupper($q->direction)
            . " LIMIT {$q->limit} OFFSET {$q->offset}"
        );
        $s->execute($p);
        return array_values($s->fetchAll());
    }

    public function count(string $userId, CollectionQuery $q): int
    {
        $where = 'userId=:uid';
        $params = ['uid' => $userId];
        if (isset($q->filters['read'])) {
            $where .= $q->filters['read'] === 'true' ? ' AND readAt IS NOT NULL' : ' AND readAt IS NULL';
        }

        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE {$where}");
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    public function unreadCount(string $userId): int
    {
        $s = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE userId = :uid AND readAt IS NULL');
        $s->execute(['uid' => $userId]);
        return (int) $s->fetchColumn();
    }

    /** @return array<string,mixed>|null */
    public function markRead(string $userId, string $id): ?array
    {
        $update = $this->pdo->prepare(
            'UPDATE notifications SET readAt=UTC_TIMESTAMP(6) WHERE id=:id AND userId=:uid AND readAt IS NULL'
        );
        $update->execute(['id' => $id, 'uid' => $userId]);

        // Fetching after the conditional update makes this operation idempotent:
        // an existing notification that was already read still returns success.
        $fetch = $this->pdo->prepare(
            'SELECT id,notificationType,title,message,eventKey,deepLink,'
            . '(readAt IS NOT NULL) AS isRead,readAt,createdAt '
            . 'FROM notifications WHERE id=:id AND userId=:uid LIMIT 1'
        );
        $fetch->execute(['id' => $id, 'uid' => $userId]);
        $notification = $fetch->fetch(PDO::FETCH_ASSOC);
        return is_array($notification) ? $notification : null;
    }

    public function markAllRead(string $userId): int
    {
        $s = $this->pdo->prepare('UPDATE notifications SET readAt=COALESCE(readAt,UTC_TIMESTAMP(6)) WHERE userId=? AND readAt IS NULL');
        $s->execute([$userId]);
        return (int) $s->rowCount();
    }
}
