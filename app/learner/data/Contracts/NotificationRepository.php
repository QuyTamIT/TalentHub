<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface NotificationRepository
{
    /**
     * @return array{items: list<array<string, mixed>>, total: int, limit: int, offset: int, hasMore: bool}
     */
    public function listForUser(string $userId, int $limit = 25, int $offset = 0, bool $unreadOnly = false): array;

    public function unreadCount(string $userId): int;

    /**
     * @return array<string, mixed>|null
     */
    public function markRead(string $userId, string $notificationId): ?array;

    public function markAllRead(string $userId): int;

    /**
     * @return array<string, array{inAppEnabled: bool, emailEnabled: bool, updatedAt: ?string}>
     */
    public function preferencesForStudent(string $studentId): array;

    /**
     * @return array{studentId: string, notificationType: string, inAppEnabled: bool, emailEnabled: bool, updatedAt: string}
     */
    public function updatePreference(string $studentId, string $notificationType, bool $inAppEnabled, bool $emailEnabled): array;

    public function insertNotification(
        string $id,
        string $userId,
        ?string $eventKey,
        string $notificationType,
        string $title,
        string $message,
        ?string $deepLink,
        string $createdAt
    ): bool;
}
