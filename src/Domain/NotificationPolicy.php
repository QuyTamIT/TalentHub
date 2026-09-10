<?php
declare(strict_types=1);

namespace TalentHub\Domain;

use TalentHub\Http\ApiException;

/**
 * Policy governing when notifications should or should NOT be sent.
 *
 * Key rules enforced here:
 *  1. When Nhà trường approves/rejects/requests-changes an activity:
 *     → notify the TEACHER (author), NOT students.
 *     → plan rule: "Không gửi thông báo 'hoạt động mới' cho sinh viên khi Nhà trường duyệt."
 *  2. When a teacher publishes/starts/completes an activity:
 *     → students who are registered/pending receive notifications.
 *  3. When a student registration is approved/rejected:
 *     → the student receives notification.
 *  4. When a waitlist is promoted:
 *     → the promoted student receives notification.
 *  5. Never send notifications for `archived` activities.
 *
 * All services that call NotificationService::publish() MUST pass through
 * this policy to make the decision explicit.
 */
final class NotificationPolicy
{
    /**
     * Types of notifications that SHOULD be sent.
     */
    public const SEND = 'send';

    /**
     * Types of notifications that MUST NOT be sent (suppressed).
     */
    public const SUPPRESS = 'suppress';

    /**
     * Decide whether to send a notification for the given event.
     *
     * @param string $event One of the event type strings used in NotificationService::publish()
     * @param array<string, mixed> $context Extra data to disambiguate
     * @return string self::SEND | self::SUPPRESS
     */
    public function shouldSend(string $event, array $context = []): string
    {
        return match ($event) {
            // Nhà trường review results: only notify the teacher (author).
            // Students are NEVER notified when Nhà trường approves an activity.
            // This implements: "Không gửi thông báo 'hoạt động mới' cho sinh viên khi Nhà trường duyệt."
            'activity_approved',
            'activity_changes_requested',
            'activity_rejected'
                => self::SEND, // Notify teacher; students suppressed via caller logic

            // Teacher lifecycle events: students receive if registered.
            'activity_published',
            'activity_started',
            'activity_completed',
            'activity_cancelled'
                => self::SEND,

            // Registration state changes
            'registration_approved',
            'registration_rejected',
            'waitlist_promoted',
            'registration_cancelled'
                => self::SEND,

            // Project events
            'project_member_added',
            'project_sponsored',
            'project_completed'
                => self::SEND,

            // Internship events
            'internship_application_status_changed',
            'internship_placement_confirmed',
            'internship_mentor_assigned'
                => self::SEND,

            // Safeguarding
            'safeguarding_approval_granted',
            'safeguarding_approval_revoked'
                => self::SEND,

            // Credential awards
            'badge_awarded',
            'certificate_issued'
                => self::SEND,

            // System
            'system_announcement',
            'account_invitation_sent'
                => self::SEND,

            // Default: suppress unknown events to avoid accidental leaks.
            default => self::SUPPRESS,
        };
    }

    /**
     * Whether the school-review approval result should be sent to students.
     * Plan rule: students must NEVER be notified when the school approves a
     * previously-submitted-for-review activity.
     */
    public function shouldNotifyStudentsOnSchoolReviewResult(): bool
    {
        return false;
    }

    /**
     * Whether archived activities should trigger any lifecycle notifications.
     */
    public function shouldNotifyForArchived(): bool
    {
        return false;
    }
}
