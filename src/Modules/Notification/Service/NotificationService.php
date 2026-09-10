<?php
declare(strict_types=1);

namespace TalentHub\Modules\Notification\Service;

use TalentHub\Http\ApiException;
use TalentHub\Http\CollectionQuery;
use TalentHub\Modules\Notification\Repository\NotificationRepository;

final class NotificationService
{
    public function __construct(private readonly NotificationRepository $repo) {}

    public function list(string $userId, CollectionQuery $q): array
    {
        return $this->repo->list($userId, $q);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,limit:int,offset:int,hasMore:bool} */
    public function inbox(string $userId, CollectionQuery $query): array
    {
        $items = $this->repo->list($userId, $query);
        $total = $this->repo->count($userId, $query);

        return [
            'items' => $items,
            'total' => $total,
            'limit' => $query->limit,
            'offset' => $query->offset,
            'hasMore' => $query->offset + count($items) < $total,
        ];
    }

    public function unreadCount(string $userId): int
    {
        return $this->repo->unreadCount($userId);
    }

    public function markRead(string $userId, string $id): array
    {
        $notification = $this->repo->markRead($userId, $id);
        if ($notification === null) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy thông báo.');
        }
        return $notification;
    }

    public function markAllRead(string $userId): array
    {
        $marked = $this->repo->markAllRead($userId);
        return ['markedCount' => $marked, 'unreadCount' => 0];
    }
}
