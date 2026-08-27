<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Contracts\NotificationRepository;
use TalentHub\Support\Uuid;

final class NotificationService
{
    public const ALLOW_LISTED_TYPES = [
        'activity_registration_created',
        'activity_registration_cancelled',
        'activity_registration_promoted',
        'activity_registration_approved',
        'activity_registration_rejected',
        'activity_checkin_committed',
        'activity_attendance_no_show',
        'activity_submitted_for_review',
        'activity_approved',
        'activity_changes_requested',
        'activity_rejected',
        'activity_checkin_confirmed',
        'assessment_submitted',
        'teacher_assessment_published',
        'internship_application_submitted',
        'internship_application_withdrawn',
        'internship_application_status_changed',
        'badge_awarded',
        'school_badge_awarded',
        'school_certificate_issued',
        'school_certificate_revoked',
        'project_sponsored',
        'project_member_added',
    ];

    public const ALLOW_LISTED_DEEP_LINKS = [
        '/app/learner/my-activities.php',
        '/app/learner/activities.php',
        '/app/learner/checkin.php',
        '/app/learner/activity-history.php',
        '/app/learner/assessment-result.php',
        '/app/learner/evaluation.php',
        '/app/learner/ecosystem.php',
        '/app/learner/badges.php',
        '/app/learner/talent-passport.php',
        '/app/teacher/projects/index.php',
        '/app/teacher/activities/index.php',
        '/app/school/activities.php',
        '/app/enterprise/applications.php',
    ];

    public function __construct(private readonly NotificationRepository $repo) {}

    public function publish(
        string $userId,
        string $notificationType,
        string $title,
        string $message,
        ?string $deepLink = null,
        ?string $eventKey = null,
        ?string $studentId = null
    ): ?array {
        if (!in_array($notificationType, self::ALLOW_LISTED_TYPES, true)) {
            throw new ApiException(422, 'INVALID_NOTIFICATION_TYPE', "Notification type '{$notificationType}' is not allowed.");
        }

        if ($eventKey === null
            || preg_match('/\A[a-z0-9][a-z0-9:_-]{0,190}\z/i', $eventKey) !== 1
        ) {
            throw new ApiException(422, 'INVALID_EVENT_KEY', 'Khóa sự kiện thông báo không hợp lệ.');
        }

        $deepLink = $this->sanitizeAndValidateDeepLink($deepLink);

        // Check preference suppression if studentId is known
        if ($studentId !== null && $studentId !== '') {
            $preferences = $this->preferencesForStudent($studentId);
            $pref = $preferences[$notificationType] ?? null;
            if ($pref !== null && $pref['inAppEnabled'] === false) {
                return null;
            }
        }

        $id = Uuid::v4();
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        $inserted = $this->repo->insertNotification(
            $id,
            $userId,
            $eventKey,
            $notificationType,
            $title,
            $message,
            $deepLink,
            $now
        );

        if (!$inserted) {
            return null;
        }

        return [
            'id' => $id,
            'userId' => $userId,
            'eventKey' => $eventKey,
            'notificationType' => $notificationType,
            'title' => $title,
            'message' => $message,
            'deepLink' => $deepLink,
            'readAt' => null,
            'createdAt' => $now,
        ];
    }

    public function listForUser(string $userId, int $limit = 25, int $offset = 0, bool $unreadOnly = false): array
    {
        return $this->repo->listForUser($userId, $limit, $offset, $unreadOnly);
    }

    public function unreadCount(string $userId): int
    {
        return $this->repo->unreadCount($userId);
    }

    public function markRead(string $userId, string $notificationId): array
    {
        if (preg_match('/^[a-f0-9-]{36}$/i', $notificationId) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'ID thông báo không hợp lệ.');
        }

        $result = $this->repo->markRead($userId, $notificationId);
        if ($result === null) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy thông báo thuộc tài khoản của bạn.');
        }

        return $result;
    }

    public function markAllRead(string $userId): array
    {
        $marked = $this->repo->markAllRead($userId);
        return [
            'markedCount' => $marked,
            'unreadCount' => 0,
        ];
    }

    /**
     * @return array<string, array{inAppEnabled: bool, emailEnabled: bool, updatedAt: ?string}>
     */
    public function preferencesForStudent(string $studentId): array
    {
        $stored = $this->repo->preferencesForStudent($studentId);
        $result = [];

        foreach (self::ALLOW_LISTED_TYPES as $type) {
            if (isset($stored[$type])) {
                $result[$type] = $stored[$type];
            } else {
                $result[$type] = [
                    'inAppEnabled' => true,
                    'emailEnabled' => false,
                    'updatedAt' => null,
                ];
            }
        }

        return $result;
    }

    public function updatePreference(string $studentId, string $notificationType, bool $inAppEnabled, bool $emailEnabled): array
    {
        if (!in_array($notificationType, self::ALLOW_LISTED_TYPES, true)) {
            throw new ApiException(422, 'INVALID_NOTIFICATION_TYPE', "Notification type '{$notificationType}' is not allowed.");
        }

        return $this->repo->updatePreference($studentId, $notificationType, $inAppEnabled, $emailEnabled);
    }

    private function sanitizeAndValidateDeepLink(?string $deepLink): ?string
    {
        if ($deepLink === null || trim($deepLink) === '') {
            return null;
        }

        $deepLink = trim($deepLink);

        // Security check: no scheme, no host, no //, no .., no \, no control characters
        if (
            str_contains($deepLink, '://') ||
            str_starts_with($deepLink, '//') ||
            str_contains($deepLink, '..') ||
            str_contains($deepLink, '\\') ||
            preg_match('/[\x00-\x1F\x7F]/', $deepLink) === 1
        ) {
            throw new ApiException(422, 'INVALID_DEEP_LINK', 'Đường dẫn liên kết không an toàn.');
        }

        if (!in_array($deepLink, self::ALLOW_LISTED_DEEP_LINKS, true)) {
            throw new ApiException(422, 'INVALID_DEEP_LINK', 'Đường dẫn không thuộc danh sách cho phép.');
        }

        return $deepLink;
    }
}
