<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$expectedExistingTypes = [
    'activity_registration_created', 'activity_registration_cancelled', 'activity_registration_promoted',
    'activity_registration_approved', 'activity_registration_rejected', 'activity_checkin_committed',
    'assessment_submitted', 'internship_application_submitted', 'internship_application_withdrawn',
    'internship_application_status_changed', 'badge_awarded', 'project_sponsored', 'project_member_added',
];
foreach ($expectedExistingTypes as $type) $assert(in_array($type, NotificationService::ALLOW_LISTED_TYPES, true), "Existing notification type {$type} is preserved.");
$assert(in_array('activity_attendance_no_show', NotificationService::ALLOW_LISTED_TYPES, true), 'No-show notification type is allow-listed.');

$expectedExistingLinks = [
    '/app/learner/my-activities.php', '/app/learner/checkin.php', '/app/learner/assessment-result.php',
    '/app/learner/ecosystem.php', '/app/learner/badges.php', '/app/learner/talent-passport.php',
    '/app/teacher/projects/index.php',
];
foreach ($expectedExistingLinks as $link) $assert(in_array($link, NotificationService::ALLOW_LISTED_DEEP_LINKS, true), "Existing deep link {$link} is preserved.");
$assert(in_array('/app/learner/activity-history.php', NotificationService::ALLOW_LISTED_DEEP_LINKS, true), 'History deep link is safe on the backend.');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE users (id TEXT PRIMARY KEY);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL);
CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT NOT NULL, eventKey TEXT NULL, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT NULL, readAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE(userId, eventKey));
CREATE TABLE learner_notification_preferences (studentId TEXT NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL DEFAULT 1, emailEnabled INTEGER NOT NULL DEFAULT 0, updatedAt TEXT NOT NULL, PRIMARY KEY(studentId, notificationType));
SQL);
$userId = '11111111-1111-4111-8111-111111111111';
$studentId = '22222222-2222-4222-8222-222222222222';
$registrationId = '33333333-3333-4333-8333-333333333333';
$pdo->prepare('INSERT INTO users (id) VALUES (?)')->execute([$userId]);
$pdo->prepare('INSERT INTO student_profiles (id, userId) VALUES (?, ?)')->execute([$studentId, $userId]);
$recipient = (string) $pdo->query("SELECT userId FROM student_profiles WHERE id = '{$studentId}'")->fetchColumn();
$service = new NotificationService(new DatabaseNotificationRepository($pdo));
$eventKey = 'activity_attendance_no_show:' . $registrationId;

$first = $service->publish($recipient, 'activity_attendance_no_show', 'Không tham gia hoạt động', 'Hoạt động đã được đối soát.', '/app/learner/activity-history.php', $eventKey, $studentId);
$second = $service->publish($recipient, 'activity_attendance_no_show', 'Không tham gia hoạt động', 'Hoạt động đã được đối soát.', '/app/learner/activity-history.php', $eventKey, $studentId);
$rows = $pdo->query('SELECT userId, eventKey, notificationType, deepLink FROM notifications')->fetchAll(PDO::FETCH_ASSOC);
$assert(is_array($first) && $second === null, 'Publishing the same no-show event twice is idempotent.');
$assert(count($rows) === 1, 'Idempotency leaves exactly one notification.');
$assert(($rows[0]['userId'] ?? null) === $userId, 'Recipient is student_profiles.userId.');
$assert(($rows[0]['eventKey'] ?? null) === $eventKey, 'Event key uses the stable no-show registration contract.');
$assert(($rows[0]['notificationType'] ?? null) === 'activity_attendance_no_show', 'Persisted type is no-show attendance.');
$assert(($rows[0]['deepLink'] ?? null) === '/app/learner/activity-history.php', 'Persisted deep link opens attendance history.');

$lifecycleSource = (string) file_get_contents(__DIR__ . '/learner_activity_attendance_lifecycle_test.php');
$assert(!str_contains((string) file_get_contents(dirname(__DIR__) . '/app/learner/data/Service/NotificationService.php'), '24 hour'), 'Phase 8 does not add the Phase 9 reconciler.');

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_notification_contract_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "learner_activity_notification_contract_test: OK\n";
