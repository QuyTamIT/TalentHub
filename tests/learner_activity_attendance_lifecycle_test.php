<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Enums\ActivityRegistrationStatus;
use TalentHub\Learner\Data\Enums\StudentPortalStatusContract;
use TalentHub\Learner\Data\ReadModel\ActivityReadModel;

/** @var list<string> $phase1LifecycleFailures */
$phase1LifecycleFailures = [];
$phase1LifecycleAssert = static function (bool $condition, string $message) use (&$phase1LifecycleFailures): void {
    if (!$condition) {
        $phase1LifecycleFailures[] = $message;
    }
};

foreach (['pending', 'approved', 'waitlisted', 'attended'] as $status) {
    $registration = ActivityReadModel::registration(['id' => 'registration-' . $status, 'status' => $status]);
    $phase1LifecycleAssert($registration['status'] === $status, "ActivityReadModel must preserve canonical {$status} registration status.");
    $phase1LifecycleAssert(ActivityRegistrationStatus::normalize($status)->value === $status, "ActivityRegistrationStatus must normalize {$status} canonically.");
}
$phase1LifecycleAssert(
    ActivityRegistrationStatus::normalize('no_show')->value === 'no_show',
    'ActivityRegistrationStatus::NoShow and no_show normalization are required by Task 4 in Phase 2.'
);
$phase1LifecycleAssert(
    in_array('no_show', StudentPortalStatusContract::canonicalActivityRegistrationStatuses(), true),
    'StudentPortalStatusContract must expose no_show as a canonical registration status.'
);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, endAt TEXT NULL)');
$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, updatedAt TEXT NOT NULL, attendanceResolvedAt TEXT NULL, attendanceResolutionReason TEXT NULL)');
$pdo->exec('CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL)');
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT NULL, action TEXT NOT NULL, entityType TEXT NOT NULL, entityId TEXT NOT NULL, requestId TEXT NULL, ipAddress TEXT NULL, metadata TEXT NOT NULL, createdAt TEXT NOT NULL)');
$pdo->exec('CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT NOT NULL, eventKey TEXT NULL, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT NULL, readAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE(userId, eventKey))');
$pdo->exec('CREATE TABLE learner_notification_preferences (studentId TEXT NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL DEFAULT 1, emailEnabled INTEGER NOT NULL DEFAULT 0, updatedAt TEXT NOT NULL, PRIMARY KEY(studentId, notificationType))');

$activityRows = [
    ['activity-due', '2026-08-20 12:00:00'], ['activity-confirmed', '2026-08-20 12:00:00'], ['activity-pending', '2026-08-20 12:00:00'],
    ['activity-waitlisted', '2026-08-20 12:00:00'], ['activity-rejected', '2026-08-20 12:00:00'], ['activity-cancelled', '2026-08-20 12:00:00'],
    ['activity-attended', '2026-08-20 12:00:00'], ['activity-null-end', null], ['activity-due-after', '2026-08-20 12:00:01'],
];
$insertActivity = $pdo->prepare('INSERT INTO activities (id,endAt) VALUES (?,?)');
foreach ($activityRows as $row) {
    $insertActivity->execute($row);
}
$insertRegistration = $pdo->prepare('INSERT INTO activity_registrations (id,activityId,studentId,status,updatedAt,attendanceResolvedAt,attendanceResolutionReason) VALUES (?,?,?,?,?,NULL,NULL)');
foreach ([
    ['registration-due', 'activity-due', 'student-a', 'approved'], ['registration-confirmed', 'activity-confirmed', 'student-b', 'approved'],
    ['registration-pending', 'activity-pending', 'student-c', 'pending'], ['registration-waitlisted', 'activity-waitlisted', 'student-d', 'waitlisted'],
    ['registration-rejected', 'activity-rejected', 'student-e', 'rejected'], ['registration-cancelled', 'activity-cancelled', 'student-f', 'cancelled'],
    ['registration-attended', 'activity-attended', 'student-g', 'attended'], ['registration-null-end', 'activity-null-end', 'student-h', 'approved'],
    ['registration-due-after', 'activity-due-after', 'student-i', 'approved'],
] as [$id, $activityId, $studentId, $status]) {
    $insertRegistration->execute([$id, $activityId, $studentId, $status, '2026-08-20 12:00:00']);
    $pdo->prepare('INSERT INTO student_profiles (id,userId) VALUES (?,?)')->execute([$studentId, $studentId . '-user']);
}
$pdo->prepare('INSERT INTO checkins (id,registrationId,status,confirmedAt) VALUES (?,?,?,?)')->execute(['checkin-confirmed', 'registration-confirmed', 'confirmed', '2026-08-20 12:30:00']);

$repositoryClass = 'TalentHub\\Learner\\Data\\Database\\DatabaseActivityAttendanceReconciliationRepository';
$serviceClass = 'TalentHub\\Learner\\Data\\Service\\ActivityAttendanceReconciliationService';
if (!class_exists($repositoryClass) || !class_exists($serviceClass)) {
    $phase1LifecycleFailures[] = 'ActivityAttendanceReconciliationRepository/Service is missing; Task 18 (Phase 9) must reconcile only approved registrations with no confirmed check-in at endAt + 24 hours.';
} else {
    /** @var object $repository */
    $repository = new $repositoryClass($pdo);
    /** @var object $service */
    $service = new $serviceClass($repository);
    $service->run(new DateTimeImmutable('2026-08-21 11:59:59', new DateTimeZone('UTC')), 24, 100);
    $phase1LifecycleAssert(
        (string) $pdo->query("SELECT status FROM activity_registrations WHERE id='registration-due'")->fetchColumn() === 'approved',
        'At endAt + 23:59:59 an approved registration must not become no_show.'
    );
    $service->run(new DateTimeImmutable('2026-08-21 12:00:00', new DateTimeZone('UTC')), 24, 100);
    $phase1LifecycleAssert(
        (string) $pdo->query("SELECT status FROM activity_registrations WHERE id='registration-due'")->fetchColumn() === 'no_show',
        'At exactly endAt + 24 hours an approved registration without confirmed check-in must become no_show.'
    );
    $phase1LifecycleAssert(
        (string) $pdo->query("SELECT status FROM activity_registrations WHERE id='registration-due-after'")->fetchColumn() === 'approved',
        'Before its own endAt + 24 hours, a separate approved registration must remain approved.'
    );
    $service->run(new DateTimeImmutable('2026-08-21 12:00:02', new DateTimeZone('UTC')), 24, 100);
    $phase1LifecycleAssert(
        (string) $pdo->query("SELECT status FROM activity_registrations WHERE id='registration-due-after'")->fetchColumn() === 'no_show',
        'Strictly after 24 hours, the separate approved registration without confirmed check-in must become no_show.'
    );
    $auditCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entityId IN ('registration-due','registration-due-after')")->fetchColumn();
    $notificationCount = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE eventKey IN ('activity_attendance_no_show:registration-due','activity_attendance_no_show:registration-due-after')")->fetchColumn();
    $service->run(new DateTimeImmutable('2026-08-21 12:00:02', new DateTimeZone('UTC')), 24, 100);
    $phase1LifecycleAssert(
        (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entityId IN ('registration-due','registration-due-after')")->fetchColumn() === $auditCount,
        'A second reconciliation run must not write duplicate no_show audits.'
    );
    $phase1LifecycleAssert(
        (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE eventKey IN ('activity_attendance_no_show:registration-due','activity_attendance_no_show:registration-due-after')")->fetchColumn() === $notificationCount,
        'A second reconciliation run must not write duplicate no_show notifications.'
    );
    foreach (['registration-confirmed', 'registration-pending', 'registration-waitlisted', 'registration-rejected', 'registration-cancelled', 'registration-attended', 'registration-null-end'] as $registrationId) {
        $phase1LifecycleAssert(
            (string) $pdo->query("SELECT status FROM activity_registrations WHERE id='{$registrationId}'")->fetchColumn() !== 'no_show',
            "{$registrationId} must never be inferred as no_show by reconciliation."
        );
    }
}

if ($phase1LifecycleFailures !== []) {
    fwrite(STDERR, "learner_activity_attendance_lifecycle_test: RED\n- " . implode("\n- ", $phase1LifecycleFailures) . "\n");
    exit(1);
}

echo "learner_activity_attendance_lifecycle_test: OK\n";
