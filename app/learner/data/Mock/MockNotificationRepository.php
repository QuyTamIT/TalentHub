<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Mock;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Learner\Data\Contracts\NotificationRepository;

final class MockNotificationRepository implements NotificationRepository
{
    /** @var list<array<string, mixed>> */
    private array $notifications = [];

    /** @var array<string, array<string, array{inAppEnabled: bool, emailEnabled: bool, updatedAt: string}>> */
    private array $preferences = [];

    /**
     * @param list<array<string, mixed>> $notifications
     */
    public function __construct(array $notifications = [])
    {
        $this->notifications = $notifications;
    }

    public function listForUser(string $userId, int $limit = 25, int $offset = 0, bool $unreadOnly = false): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $filtered = array_values(array_filter(
            $this->notifications,
            static fn (array $item): bool => ($item['userId'] ?? '') === $userId
                && (!$unreadOnly || empty($item['readAt']))
        ));

        // Sort newest first
        usort($filtered, static function (array $a, array $b): int {
            return strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? ''));
        });

        $total = count($filtered);
        $slice = array_slice($filtered, $offset, $limit);

        return [
            'items' => $slice,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'hasMore' => ($offset + count($slice)) < $total,
        ];
    }

    public function unreadCount(string $userId): int
    {
        $count = 0;
        foreach ($this->notifications as $item) {
            if (($item['userId'] ?? '') === $userId && empty($item['readAt'])) {
                $count++;
            }
        }
        return $count;
    }

    public function markRead(string $userId, string $notificationId): ?array
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        foreach ($this->notifications as &$item) {
            if (($item['userId'] ?? '') === $userId && ($item['id'] ?? '') === $notificationId) {
                if (empty($item['readAt'])) {
                    $item['readAt'] = $now;
                }
                return $item;
            }
        }
        return null;
    }

    public function markAllRead(string $userId): int
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $count = 0;
        foreach ($this->notifications as &$item) {
            if (($item['userId'] ?? '') === $userId && empty($item['readAt'])) {
                $item['readAt'] = $now;
                $count++;
            }
        }
        return $count;
    }

    public function preferencesForStudent(string $studentId): array
    {
        return $this->preferences[$studentId] ?? [];
    }

    public function updatePreference(string $studentId, string $notificationType, bool $inAppEnabled, bool $emailEnabled): array
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $pref = [
            'studentId' => $studentId,
            'notificationType' => $notificationType,
            'inAppEnabled' => $inAppEnabled,
            'emailEnabled' => $emailEnabled,
            'updatedAt' => $now,
        ];
        $this->preferences[$studentId][$notificationType] = [
            'inAppEnabled' => $inAppEnabled,
            'emailEnabled' => $emailEnabled,
            'updatedAt' => $now,
        ];
        return $pref;
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
        if ($eventKey !== null) {
            foreach ($this->notifications as $item) {
                if (($item['userId'] ?? '') === $userId && ($item['eventKey'] ?? '') === $eventKey) {
                    return false;
                }
            }
        }

        $this->notifications[] = [
            'id' => $id,
            'userId' => $userId,
            'eventKey' => $eventKey,
            'notificationType' => $notificationType,
            'title' => $title,
            'message' => $message,
            'deepLink' => $deepLink,
            'readAt' => null,
            'createdAt' => $createdAt,
        ];

        return true;
    }
}
