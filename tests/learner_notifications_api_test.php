<?php

declare(strict_types=1);

require_once __DIR__ . '/../bin/bootstrap.php';
require_once __DIR__ . '/../app/learner/data/bootstrap.php';

use TalentHub\Http\ApiException;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

echo "Running tests/learner_notifications_api_test.php..." . PHP_EOL;

// 1. Verify existence of required classes/interfaces
$assert(interface_exists('TalentHub\Learner\Data\Contracts\NotificationRepository'), 'NotificationRepository interface exists');
$assert(class_exists('TalentHub\Learner\Data\Database\DatabaseNotificationRepository'), 'DatabaseNotificationRepository class exists');
$assert(class_exists('TalentHub\Learner\Data\Service\NotificationService'), 'NotificationService class exists');

// 2. Verify API file exists
$assert(file_exists(__DIR__ . '/../app/learner/api/v1/notifications.php'), 'app/learner/api/v1/notifications.php exists');
$endpointSource = file_get_contents(__DIR__ . '/../app/learner/api/v1/notifications.php') ?: '';
$assert(str_contains($endpointSource, "queryParam('filter')"), 'Endpoint consumes the server-side all/unread filter');
$assert(str_contains($endpointSource, 'array_keys($request->queryParams())'), 'Endpoint rejects unknown GET query fields');
$assert(str_contains($endpointSource, "is_bool(\$input['inAppEnabled'])"), 'Endpoint requires a real JSON boolean for inAppEnabled');
$assert(str_contains($endpointSource, "is_bool(\$input['emailEnabled'])"), 'Endpoint requires a real JSON boolean for emailEnabled');
$assert(!str_contains($endpointSource, "(bool) (\$input['inAppEnabled']"), 'Endpoint never coerces the string false to true');

// 3. Test NotificationService contract
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
    CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT);
    CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT);
    CREATE TABLE notifications (
        id TEXT PRIMARY KEY,
        userId TEXT NOT NULL,
        eventKey TEXT NULL,
        notificationType TEXT NOT NULL,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        deepLink TEXT NULL,
        readAt TEXT NULL,
        createdAt TEXT NOT NULL
    );
    CREATE UNIQUE INDEX uq_notif_user_event ON notifications(userId, eventKey);
    CREATE TABLE learner_notification_preferences (
        studentId TEXT NOT NULL,
        notificationType TEXT NOT NULL,
        inAppEnabled INTEGER NOT NULL DEFAULT 1,
        emailEnabled INTEGER NOT NULL DEFAULT 0,
        updatedAt TEXT NOT NULL,
        PRIMARY KEY (studentId, notificationType)
    );
SQL);

$repo = new \TalentHub\Learner\Data\Database\DatabaseNotificationRepository($pdo);
$service = new \TalentHub\Learner\Data\Service\NotificationService($repo);

// Test allow-list
$allowed = \TalentHub\Learner\Data\Service\NotificationService::ALLOW_LISTED_TYPES;
$assert(in_array('activity_registration_created', $allowed, true), 'Allow-list contains activity_registration_created');
$assert(in_array('activity_checkin_committed', $allowed, true), 'Allow-list contains activity_checkin_committed');
$assert(in_array('assessment_submitted', $allowed, true), 'Allow-list contains assessment_submitted');
$assert(in_array('internship_application_submitted', $allowed, true), 'Allow-list contains internship_application_submitted');
$assert(in_array('internship_application_status_changed', $allowed, true), 'Allow-list contains internship_application_status_changed');

// Test publish with preference suppression
$student1 = 'student-1';
$user1 = 'user-1';
$pdo->exec("INSERT INTO users VALUES ('{$user1}', 'u1@test.com')");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$student1}', '{$user1}')");

// Initially preference defaults to inAppEnabled = 1
$published = $service->publish(
    $user1,
    'activity_registration_created',
    'Đăng ký thành công',
    'Bạn đã đăng ký tham gia hoạt động.',
    '/app/learner/my-activities.php',
    'act-reg:1',
    $student1
);
$assert($published !== null, 'Published notification when inAppEnabled is default true');
$assert($service->unreadCount($user1) === 1, 'Unread count is 1');

// Test duplicate eventKey idempotency
$publishedDup = $service->publish(
    $user1,
    'activity_registration_created',
    'Đăng ký thành công',
    'Bạn đã đăng ký tham gia hoạt động.',
    '/app/learner/my-activities.php',
    'act-reg:1',
    $student1
);
$assert($service->unreadCount($user1) === 1, 'Duplicate eventKey does not increment unread count');

// Test preference suppression: set inAppEnabled = 0
$service->updatePreference($student1, 'activity_registration_created', false, true);
$publishedSuppressed = $service->publish(
    $user1,
    'activity_registration_created',
    'Đăng ký thành công 2',
    'Bạn đã đăng ký tham gia hoạt động 2.',
    '/app/learner/my-activities.php',
    'act-reg:2',
    $student1
);
$assert($publishedSuppressed === null, 'Publish suppressed when inAppEnabled = false');
$assert($service->unreadCount($user1) === 1, 'Unread count still 1 after suppressed publish');

// Test mark read
$notifs = $service->listForUser($user1);
$assert(count($notifs['items']) === 1, 'List returns 1 item');
$notifId = $notifs['items'][0]['id'];
$service->markRead($user1, $notifId);
$assert($service->unreadCount($user1) === 0, 'Unread count is 0 after markRead');

// Test mark all read
$service->updatePreference($student1, 'activity_checkin_committed', true, false);
$service->publish($user1, 'activity_checkin_committed', 'Check-in thành công', 'Checkin message', '/app/learner/checkin.php', 'chk:1', $student1);
$service->publish($user1, 'assessment_submitted', 'Nộp bài thành công', 'Assessment message', '/app/learner/assessment-result.php', 'ass:1', $student1);
$assert($service->unreadCount($user1) === 2, 'Unread count is 2 before markAllRead');
$service->markAllRead($user1);
$assert($service->unreadCount($user1) === 0, 'Unread count is 0 after markAllRead');

// Test owner isolation
$user2 = 'user-2';
$student2 = 'student-2';
$pdo->exec("INSERT INTO users VALUES ('{$user2}', 'u2@test.com')");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$student2}', '{$user2}')");
$service->publish($user2, 'activity_checkin_committed', 'Checkin U2', 'Msg', '/app/learner/checkin.php', 'chk:u2', $student2);
$assert($service->unreadCount($user1) === 0, 'User 1 unread count not affected by User 2');
$assert($service->unreadCount($user2) === 1, 'User 2 unread count is 1');
$u1List = $service->listForUser($user1);
$assert(count($u1List['items']) === 3, 'User 1 list has 3 items');
foreach ($u1List['items'] as $item) {
    $assert($item['userId'] === $user1, 'User 1 list only contains User 1 notifications');
}

// Test server-side unread filtering and deterministic pagination metadata.
$service->publish($user1, 'activity_checkin_committed', 'Unread filter', 'Unread message', '/app/learner/checkin.php', 'chk:unread-filter', $student1);
$unreadOnly = $service->listForUser($user1, 25, 0, true);
$assert(count($unreadOnly['items']) === 1, 'Unread filter returns only unread notifications');
$assert($unreadOnly['items'][0]['eventKey'] === 'chk:unread-filter', 'Unread filter returns the expected notification');
$assert($unreadOnly['total'] === 1 && $unreadOnly['hasMore'] === false, 'Unread filter pagination metadata is scoped to unread rows');

// Test deep link security: reject external / unsafe links
$invalidLinks = [
    'https://external.com/phishing',
    'http://malicious.org',
    '//protocol-relative.com',
    '/app/learner/../admin/secret.php',
    '/app/learner/checkin.php?next=/app/admin/dashboard.php',
    '/app/learner/checkin.php#javascript:alert(1)',
    '/app/learner/notifications.php',
    'javascript:alert(1)',
    'data:text/html,evil',
    '/unknown/route.php',
];
foreach ($invalidLinks as $badLink) {
    $threw = false;
    try {
        $service->publish($user1, 'activity_checkin_committed', 'Test', 'Msg', $badLink, 'event:' . md5($badLink), $student1);
    } catch (ApiException $e) {
        $threw = true;
        $assert($e->status === 422, 'Invalid deepLink throws 422');
    }
    $assert($threw, "Unsafe deepLink '{$badLink}' rejected");
}


// Test event-key contract: producer keys are mandatory, bounded and non-secret-shaped.
foreach ([null, '', 'contains spaces', str_repeat('a', 192), 'qr/raw/token/value'] as $badEventKey) {
    $threw = false;
    try {
        $service->publish($user1, 'activity_checkin_committed', 'Test', 'Msg', '/app/learner/checkin.php', $badEventKey, $student1);
    } catch (ApiException $e) {
        $threw = true;
        $assert($e->status === 422, 'Invalid event key throws 422');
    }
    $assert($threw, 'Invalid event key is rejected');
}

// Repository persistence must fail closed: only the exact unique event-key conflict is idempotent.
$fkPdo = new PDO('sqlite::memory:');
$fkPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$fkPdo->exec('PRAGMA foreign_keys = ON');
$fkPdo->exec(<<<'SQL'
    CREATE TABLE users (id TEXT PRIMARY KEY);
    CREATE TABLE notifications (
        id TEXT PRIMARY KEY,
        userId TEXT NOT NULL REFERENCES users(id),
        eventKey TEXT NULL,
        notificationType TEXT NOT NULL,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        deepLink TEXT NULL,
        readAt TEXT NULL,
        createdAt TEXT NOT NULL,
        UNIQUE(userId, eventKey)
    );
SQL);
$fkRepo = new \TalentHub\Learner\Data\Database\DatabaseNotificationRepository($fkPdo);
$foreignKeyFailedClosed = false;
try {
    $fkRepo->insertNotification('notif-fk', 'missing-user', 'event:fk', 'activity_checkin_committed', 'Title', 'Message', '/app/learner/checkin.php', '2026-08-23 00:00:00.000000');
} catch (PDOException) {
    $foreignKeyFailedClosed = true;
}
$assert($foreignKeyFailedClosed, 'Foreign-key integrity failure is not suppressed as an idempotent duplicate');

$missingTablePdo = new PDO('sqlite::memory:');
$missingTablePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$missingTableRepo = new \TalentHub\Learner\Data\Database\DatabaseNotificationRepository($missingTablePdo);
$missingTableFailedClosed = false;
try {
    $missingTableRepo->insertNotification('notif-missing', 'user-1', 'event:missing', 'activity_checkin_committed', 'Title', 'Message', '/app/learner/checkin.php', '2026-08-23 00:00:00.000000');
} catch (PDOException) {
    $missingTableFailedClosed = true;
}
$assert($missingTableFailedClosed, 'Missing notifications table fails closed');


echo "All tests in learner_notifications_api_test.php PASSED." . PHP_EOL;
    