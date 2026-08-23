# Phase 8 Notifications and Preferences Design Specification

- **Date:** 2026-08-23
- **Author:** Antigravity
- **Target Schema:** `talenthub_local`
- **Status:** APPROVED_FOR_PHASE_8_EXECUTION

---

## 1. Overview & Architecture

Phase 8 implements the complete real, database-backed Notification Center and Learner Notification Preferences for the Student Portal, along with atomic domain event producers across the 4-role platform.

Key design principles:
1. **Owner Isolation:** Notifications are bound strictly to `users.id`. A student can only view, count, and mark notifications owned by their authenticated `userId`.
2. **Preference Control:** Students can manage in-app and email preferences per allow-listed notification type in `learner_notification_preferences`. When `inAppEnabled = false`, domain producers safely suppress in-app notification insertion. In v1, `emailEnabled` is stored only; no email worker is triggered.
3. **Atomic Producer Integration:** All notification writes occur within the domain transaction of the originating action (e.g. registration, check-in, assessment submit, application review). If the domain transaction rolls back, all notifications roll back.
4. **Idempotency & Deduplication:** Notifications use unique `(userId, eventKey)` constraints. Retried domain operations or identical event keys do not produce duplicate notifications.
5. **Security & Validation:** Deep links strictly adhere to an internal server allow-list; protocol-relative (`//`), traversal (`..`), backslashes, schemes, and external domains are rejected. Notification rendering uses `textContent` and escaped HTML, with zero untrusted `innerHTML`.

---

## 2. Canonical Database Schema

### 2.1 Table: `notifications`
```sql
CREATE TABLE IF NOT EXISTS notifications (
    id CHAR(36) NOT NULL,
    userId CHAR(36) NOT NULL,
    eventKey VARCHAR(191) NULL,
    notificationType VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    deepLink VARCHAR(500) NULL,
    readAt DATETIME(6) NULL,
    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_notifications_user_event (userId, eventKey),
    KEY idx_notifications_user_timeline (userId, createdAt, id),
    KEY idx_notifications_user_unread (userId, readAt, createdAt),
    CONSTRAINT fk_notifications_user FOREIGN KEY (userId)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Table: `learner_notification_preferences`
```sql
CREATE TABLE IF NOT EXISTS learner_notification_preferences (
    studentId CHAR(36) NOT NULL,
    notificationType VARCHAR(100) NOT NULL,
    inAppEnabled TINYINT(1) NOT NULL DEFAULT 1,
    emailEnabled TINYINT(1) NOT NULL DEFAULT 0,
    updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (studentId, notificationType),
    CONSTRAINT fk_learner_notification_preferences_student FOREIGN KEY (studentId)
        REFERENCES student_profiles (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.3 Permission & RBAC Delta
- Permission `notification.manage_preferences_own` ('Quản lý tùy chọn thông báo của bản thân') added to `permissions` table.
- Mapped in `role_permissions` to all 4 system roles (`student`, `teacher`, `school`, `enterprise`) as declared in `RolePermissionSeeder::COMMON_PERMISSIONS`.

---

## 3. Allow-Listed Domain Event Types & Deep Links

### 3.1 Allow-Listed Notification Types
1. `activity_registration_created`
2. `activity_registration_cancelled`
3. `activity_registration_promoted`
4. `activity_registration_approved`
5. `activity_registration_rejected`
6. `activity_checkin_committed`
7. `assessment_submitted`
8. `internship_application_submitted`
9. `internship_application_withdrawn`
10. `internship_application_status_changed`
11. `badge_awarded` (reserved; not emitted in Phase 8)

### 3.2 Safe Deep Link Mapping
- Activity registration / promotion / approval / rejection: `/app/learner/my-activities.php`
- Activity check-in: `/app/learner/checkin.php`
- Assessment submission: `/app/learner/assessment-result.php`
- Application events: `/app/learner/ecosystem.php`
- Badge award: `/app/learner/badges.php`

Only those five complete strings are valid. Query strings, fragments, prefixes, extra path segments, schemes, traversal, backslashes, and external URLs are rejected.

---

## 4. Service & API Contracts

### 4.1 Service Layer: `NotificationService`
- `publish(string $userId, string $type, string $title, string $message, ?string $deepLink, string $eventKey, ?string $studentId)`:
  - Validates `$type`, the required stable `$eventKey`, and the exact `$deepLink`.
  - Checks preference if `$studentId` provided; suppresses in-app write if `inAppEnabled === 0`.
  - Performs idempotent insert on `(userId, eventKey)`.
- `listForUser(string $userId, int $limit, int $offset, bool $unreadOnly)`: Returns a server-filtered newest-first paginated list with total count and unread count.
- `unreadCount(string $userId)`: Returns count of unread notifications from DB.
- `markRead(string $userId, string $notificationId)`: Marks single notification read (owner scoped).
- `markAllRead(string $userId)`: Marks all unread notifications read (owner scoped).
- `preferencesForStudent(string $studentId)`: Returns default preference map merged with stored DB rows.
- `updatePreference(string $studentId, string $type, bool $inApp, bool $email)`: Upserts single preference.

### 4.2 Endpoint: `app/learner/api/v1/notifications.php`
- `GET ?limit=25&offset=0&filter=all|unread`: Requires `notification.read_own`, rejects unknown query keys, and returns `{ success: true, data: { notifications: [...], unreadCount: N, pagination: { limit, offset, total, hasMore }, preferences: { ... } } }`.
- `PATCH {"action": "mark-read", "notificationId": "<uuid>"}`: Requires CSRF + `notification.mark_read_own`.
- `PATCH {"action": "mark-all-read"}`: Requires CSRF + `notification.mark_read_own`.
- `PATCH {"action": "update-preference", "notificationType": "...", "inAppEnabled": bool, "emailEnabled": bool}`: Requires CSRF + `notification.manage_preferences_own`.

---

## 5. Domain Producer Mapping

1. **Student Registration (`DatabaseActivityCommandRepository`):**
   - Register -> `activity_registration_created`
   - Cancel -> `activity_registration_cancelled` (and `activity_registration_promoted` for promoted waitlisted student if applicable)
2. **Teacher Review (`TeacherActivityRepository::transitionRegistration`):**
   - Approve -> `activity_registration_approved`
   - Reject -> `activity_registration_rejected`
3. **QR Check-in (`DatabaseCheckinRepository::createConfirmed`):**
   - First commit -> `activity_checkin_committed`
4. **Assessment Submit (`DatabaseAssessmentWriteRepository::submitAttempt`):**
   - Submit attempt -> `assessment_submitted`
5. **Student Application (`DatabaseApplicationCommandRepository`):**
   - Submit -> `internship_application_submitted`
   - Withdraw -> `internship_application_withdrawn`
6. **Enterprise Review (`InternshipRepository::review`):**
   - Status transition -> `internship_application_status_changed`
