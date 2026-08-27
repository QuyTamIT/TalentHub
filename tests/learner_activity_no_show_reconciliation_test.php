<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Database\DatabaseActivityAttendanceReconciliationRepository;
use TalentHub\Learner\Data\Service\ActivityAttendanceReconciliationService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$database = static function (): PDO {
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(<<<'SQL'
CREATE TABLE activities (id TEXT PRIMARY KEY, endAt TEXT NULL);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, updatedAt TEXT NOT NULL, attendanceResolvedAt TEXT NULL, attendanceResolutionReason TEXT NULL);
CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NULL);
CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT NULL, action TEXT NOT NULL, entityType TEXT, entityId TEXT, requestId TEXT NULL, ipAddress TEXT NULL, metadata TEXT, createdAt TEXT NOT NULL);
CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT NOT NULL, eventKey TEXT NULL, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT NULL, readAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE(userId,eventKey));
CREATE TABLE learner_notification_preferences (studentId TEXT NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL DEFAULT 1, emailEnabled INTEGER NOT NULL DEFAULT 0, updatedAt TEXT NOT NULL, PRIMARY KEY(studentId,notificationType));
SQL);
    return $pdo;
};

$add = static function (PDO $pdo, string $registrationId, string $status, ?string $endAt, ?string $userId = null): void {
    $activityId = 'activity-' . $registrationId;
    $studentId = 'student-' . $registrationId;
    $pdo->prepare('INSERT INTO activities (id,endAt) VALUES (?,?)')->execute([$activityId, $endAt]);
    $pdo->prepare('INSERT INTO activity_registrations (id,activityId,studentId,status,updatedAt) VALUES (?,?,?,?,?)')
        ->execute([$registrationId, $activityId, $studentId, $status, '2026-08-20 00:00:00.000000']);
    if ($userId !== null) {
        $pdo->prepare('INSERT INTO student_profiles (id,userId) VALUES (?,?)')->execute([$studentId, $userId]);
    }
};

$now = new DateTimeImmutable('2026-08-21 12:00:00', new DateTimeZone('UTC'));
$pdo = $database();
foreach ([
    ['registration-exact', 'approved', '2026-08-20 12:00:00', 'user-exact'],
    ['registration-after', 'approved', '2026-08-20 11:59:59', 'user-after'],
    ['registration-before', 'approved', '2026-08-20 12:00:01', 'user-before'],
    ['registration-pending', 'pending', '2026-08-20 11:00:00', 'user-pending'],
    ['registration-waitlisted', 'waitlisted', '2026-08-20 11:00:00', 'user-waitlisted'],
    ['registration-attended', 'attended', '2026-08-20 11:00:00', 'user-attended'],
    ['registration-cancelled', 'cancelled', '2026-08-20 11:00:00', 'user-cancelled'],
    ['registration-rejected', 'rejected', '2026-08-20 11:00:00', 'user-rejected'],
    ['registration-existing-no-show', 'no_show', '2026-08-20 11:00:00', 'user-no-show'],
    ['registration-null-end', 'approved', null, 'user-null-end'],
    ['registration-confirmed', 'approved', '2026-08-20 11:00:00', 'user-confirmed'],
] as [$id, $status, $endAt, $userId]) {
    $add($pdo, $id, $status, $endAt, $userId);
}
$pdo->exec("UPDATE activity_registrations SET attendanceResolvedAt='2026-08-20 13:00:00', attendanceResolutionReason='existing' WHERE id='registration-existing-no-show'");
$pdo->exec("INSERT INTO checkins VALUES ('checkin-confirmed','registration-confirmed','confirmed','2026-08-20 11:30:00')");

$service = new ActivityAttendanceReconciliationService(new DatabaseActivityAttendanceReconciliationRepository($pdo));
$rows = $service->run($now, 24, 100);
$assert(array_column($rows, 'registration_id') === ['registration-after', 'registration-exact'], 'due records reconcile in stable activity.endAt and registration.id order.');
foreach (['registration-after', 'registration-exact'] as $id) {
    $row = $pdo->query("SELECT * FROM activity_registrations WHERE id='{$id}'")->fetch();
    $assert(($row['status'] ?? null) === 'no_show', "{$id} becomes no_show.");
    $assert(($row['attendanceResolvedAt'] ?? null) === '2026-08-21 12:00:00.000000', "{$id} stores the UTC resolution instant.");
    $assert(($row['attendanceResolutionReason'] ?? null) === 'no_confirmed_checkin_after_24h', "{$id} stores the canonical reason.");
}
foreach (['registration-before','registration-pending','registration-waitlisted','registration-attended','registration-cancelled','registration-rejected','registration-null-end','registration-confirmed'] as $id) {
    $assert((string) $pdo->query("SELECT status FROM activity_registrations WHERE id='{$id}'")->fetchColumn() !== 'no_show', "{$id} is not inferred as no_show.");
}
$assert((string) $pdo->query("SELECT attendanceResolvedAt FROM activity_registrations WHERE id='registration-existing-no-show'")->fetchColumn() === '2026-08-20 13:00:00', 'an existing no_show keeps its original resolution timestamp.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM checkins')->fetchColumn() === 1, 'reconciliation never inserts check-ins.');

$audits = $pdo->query('SELECT userId,action,entityType,entityId,metadata FROM audit_logs ORDER BY entityId')->fetchAll();
$assert(count($audits) === 2, 'each transition writes one audit.');
foreach ($audits as $audit) {
    $metadata = json_decode((string) $audit['metadata'], true, 512, JSON_THROW_ON_ERROR);
    $assert($audit['userId'] === null && ($metadata['actor'] ?? null) === 'system', 'no-show audit uses a null user and system actor metadata.');
    $assert(($audit['action'] ?? null) === 'activity_registration.no_show_reconciled' && ($audit['entityType'] ?? null) === 'activity_registration', 'audit identifies the reconciliation action and entity.');
}
$notifications = $pdo->query('SELECT userId,eventKey,notificationType,deepLink FROM notifications ORDER BY eventKey')->fetchAll();
$assert(count($notifications) === 2, 'each transition publishes one notification.');
foreach ($notifications as $notification) {
    $assert(str_starts_with((string) $notification['userId'], 'user-'), 'notification recipient is student_profiles.userId.');
    $assert(($notification['eventKey'] ?? null) === 'activity_attendance_no_show:registration-' . substr((string) $notification['userId'], 5), 'notification event key is stable per registration.');
    $assert(($notification['notificationType'] ?? null) === 'activity_attendance_no_show', 'notification uses the no-show type.');
    $assert(($notification['deepLink'] ?? null) === '/app/learner/activity-history.php', 'notification links to attendance history.');
}
$counts = [(int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(), (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn()];
$assert($service->run($now, 24, 100) === [], 'a second run is idempotent.');
$assert($counts === [(int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(), (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn()], 'idempotent retry writes no duplicate audit or notification.');

$batch = $database();
foreach ([
    ['registration-b', '2026-08-20 10:00:00'],
    ['registration-a', '2026-08-20 10:00:00'],
    ['registration-c', '2026-08-20 11:00:00'],
] as [$id, $endAt]) {
    $add($batch, $id, 'approved', $endAt, 'user-' . $id);
}
$batchService = new ActivityAttendanceReconciliationService(new DatabaseActivityAttendanceReconciliationRepository($batch));
$assert(array_column($batchService->run($now, 24, 2), 'registration_id') === ['registration-a', 'registration-b'], 'batch limit preserves the stable selection order.');
$assert((string) $batch->query("SELECT status FROM activity_registrations WHERE id='registration-c'")->fetchColumn() === 'approved', 'records beyond the batch limit remain untouched.');

$suppressed = $database();
$add($suppressed, 'registration-suppressed', 'approved', '2026-08-20 10:00:00', 'user-suppressed');
$suppressed->exec("INSERT INTO learner_notification_preferences VALUES ('student-registration-suppressed','activity_attendance_no_show',0,0,'2026-08-20 00:00:00')");
(new ActivityAttendanceReconciliationService(new DatabaseActivityAttendanceReconciliationRepository($suppressed)))->run($now);
$assert((string) $suppressed->query("SELECT status FROM activity_registrations WHERE id='registration-suppressed'")->fetchColumn() === 'no_show', 'disabled notification preference still commits no_show.');
$assert((int) $suppressed->query("SELECT COUNT(*) FROM audit_logs WHERE action='activity_registration.no_show_reconciled'")->fetchColumn() === 1, 'disabled preference keeps the reconciliation audit.');
$assert((int) $suppressed->query("SELECT COUNT(*) FROM audit_logs WHERE action='activity_registration.no_show_notification_suppressed'")->fetchColumn() === 1, 'disabled preference records a durable suppression decision.');
$assert((int) $suppressed->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === 0, 'disabled preference does not create a notification.');
$suppressed->exec("UPDATE learner_notification_preferences SET inAppEnabled=1 WHERE studentId='student-registration-suppressed' AND notificationType='activity_attendance_no_show'");
(new ActivityAttendanceReconciliationService(new DatabaseActivityAttendanceReconciliationRepository($suppressed)))->run($now);
$assert((int) $suppressed->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === 0, 're-enabling preferences does not publish a stale notification that was suppressed when due.');
$assert((int) $suppressed->query("SELECT COUNT(*) FROM audit_logs WHERE action='activity_registration.no_show_notification_suppressed'")->fetchColumn() === 1, 'suppression audit remains idempotent across scheduler retries.');

$auditFailure = $database();
$add($auditFailure, 'registration-audit-failure', 'approved', '2026-08-20 10:00:00', 'user-audit-failure');
$auditFailure->exec("CREATE TRIGGER fail_phase9_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT,'phase9 audit failure'); END");
$failed = false;
try {
    (new ActivityAttendanceReconciliationService(new DatabaseActivityAttendanceReconciliationRepository($auditFailure)))->run($now);
} catch (Throwable) {
    $failed = true;
}
$assert($failed, 'audit failure propagates.');
$assert((string) $auditFailure->query("SELECT status FROM activity_registrations WHERE id='registration-audit-failure'")->fetchColumn() === 'approved', 'audit failure rolls back registration state.');
$assert((int) $auditFailure->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 0 && (int) $auditFailure->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === 0, 'audit failure leaves no partial side effects.');

$coreFailureWithPendingDelivery = $database();
$add($coreFailureWithPendingDelivery, 'registration-existing-pending-delivery', 'approved', '2026-08-20 09:00:00', 'user-existing-pending-delivery');
$coreFailureWithPendingDelivery->exec("UPDATE activity_registrations SET status='no_show',attendanceResolvedAt='2026-08-21 10:00:00.000000',attendanceResolutionReason='no_confirmed_checkin_after_24h' WHERE id='registration-existing-pending-delivery'");
$coreFailureWithPendingDelivery->exec("INSERT INTO audit_logs VALUES ('audit-existing-pending-delivery',NULL,'activity_registration.no_show_reconciled','activity_registration','registration-existing-pending-delivery',NULL,NULL,'{}','2026-08-21 10:00:00.000000')");
$add($coreFailureWithPendingDelivery, 'registration-new-audit-failure', 'approved', '2026-08-20 10:00:00', 'user-new-audit-failure');
$coreFailureWithPendingDelivery->exec("CREATE TRIGGER fail_new_reconciliation_audit BEFORE INSERT ON audit_logs WHEN NEW.entityId='registration-new-audit-failure' BEGIN SELECT RAISE(ABORT,'new audit failure'); END");
$failed = false;
try {
    (new ActivityAttendanceReconciliationService(new DatabaseActivityAttendanceReconciliationRepository($coreFailureWithPendingDelivery)))->run($now);
} catch (Throwable) {
    $failed = true;
}
$assert($failed, 'a core reconciliation failure still propagates for scheduler monitoring.');
$assert((string) $coreFailureWithPendingDelivery->query("SELECT status FROM activity_registrations WHERE id='registration-new-audit-failure'")->fetchColumn() === 'approved', 'the failing core transition remains rolled back.');
$assert((int) $coreFailureWithPendingDelivery->query("SELECT COUNT(*) FROM notifications WHERE eventKey='activity_attendance_no_show:registration-existing-pending-delivery'")->fetchColumn() === 1, 'a core failure does not block delivery already pending from an earlier committed transition.');

$notificationFailure = $database();
$add($notificationFailure, 'registration-notification-failure', 'approved', '2026-08-20 10:00:00', 'user-notification-failure');
$add($notificationFailure, 'registration-notification-healthy', 'approved', '2026-08-20 10:01:00', 'user-notification-healthy');
$notificationFailure->exec("CREATE TRIGGER fail_phase9_notification BEFORE INSERT ON notifications WHEN NEW.eventKey='activity_attendance_no_show:registration-notification-failure' BEGIN SELECT RAISE(ABORT,'phase9 notification failure'); END");
$failed = false;
try {
    (new ActivityAttendanceReconciliationService(new DatabaseActivityAttendanceReconciliationRepository($notificationFailure)))->run($now);
} catch (Throwable) {
    $failed = true;
}
$assert($failed, 'post-commit notification failure propagates for scheduler monitoring.');
$assert((int) $notificationFailure->query("SELECT COUNT(*) FROM activity_registrations WHERE status='no_show'")->fetchColumn() === 2, 'notification failure does not roll back any registration state in the batch.');
$assert((int) $notificationFailure->query("SELECT COUNT(*) FROM audit_logs WHERE action='activity_registration.no_show_reconciled'")->fetchColumn() === 2, 'notification failure does not roll back reconciliation audits.');
$assert((int) $notificationFailure->query("SELECT COUNT(*) FROM notifications WHERE eventKey='activity_attendance_no_show:registration-notification-failure'")->fetchColumn() === 0, 'failed notification is not partially persisted.');
$assert((int) $notificationFailure->query("SELECT COUNT(*) FROM notifications WHERE eventKey='activity_attendance_no_show:registration-notification-healthy'")->fetchColumn() === 1, 'one poison notification does not block later pending deliveries.');

$notificationFailure->exec('DROP TRIGGER fail_phase9_notification');
$retryRows = (new ActivityAttendanceReconciliationService(new DatabaseActivityAttendanceReconciliationRepository($notificationFailure)))->run($now);
$assert($retryRows === [], 'notification retry does not report or repeat the no-show transition.');
$assert((int) $notificationFailure->query("SELECT COUNT(*) FROM audit_logs WHERE action='activity_registration.no_show_reconciled'")->fetchColumn() === 2, 'notification retry does not duplicate reconciliation audit.');
$assert((int) $notificationFailure->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === 2, 'a later scheduler run retries the missing notification exactly once without duplicating the healthy delivery.');

$orphan = $database();
$add($orphan, 'registration-orphan', 'approved', '2026-08-20 10:00:00');
$failed = false;
try {
    (new ActivityAttendanceReconciliationService(new DatabaseActivityAttendanceReconciliationRepository($orphan)))->run($now);
} catch (Throwable) {
    $failed = true;
}
$assert(!$failed, 'missing student user mapping does not block attendance truth.');
$assert((string) $orphan->query("SELECT status FROM activity_registrations WHERE id='registration-orphan'")->fetchColumn() === 'no_show', 'missing recipient still commits the no-show transition.');
$assert((int) $orphan->query("SELECT COUNT(*) FROM audit_logs WHERE action='activity_registration.no_show_reconciled'")->fetchColumn() === 1, 'missing recipient still commits the reconciliation audit.');
$assert((int) $orphan->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === 0, 'missing recipient cannot create an orphan notification.');
$orphan->exec("INSERT INTO student_profiles (id,userId) VALUES ('student-registration-orphan','user-registration-orphan')");
(new ActivityAttendanceReconciliationService(new DatabaseActivityAttendanceReconciliationRepository($orphan)))->run($now);
$assert((int) $orphan->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === 1, 'a later scheduler run delivers after the recipient mapping is repaired.');

foreach ([[0,100],[24,0],[24,1001]] as [$grace, $limit]) {
    $invalid = false;
    try {
        $service->run($now, $grace, $limit);
    } catch (InvalidArgumentException) {
        $invalid = true;
    }
    $assert($invalid, "invalid grace/limit {$grace}/{$limit} is rejected.");
}

echo "learner_activity_no_show_reconciliation_test: OK\n";
