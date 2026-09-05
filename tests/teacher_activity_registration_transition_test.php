<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec(<<<'SQL'
CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL);
CREATE TABLE activities (id TEXT PRIMARY KEY, title TEXT NOT NULL, createdByTeacherId TEXT NOT NULL, capacity INTEGER NOT NULL, status TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, registeredAt TEXT, updatedAt TEXT, cancelledAt TEXT, cancellationReason TEXT);
CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, requestId TEXT, ipAddress TEXT, metadata TEXT, createdAt TEXT);
CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT NOT NULL, eventKey TEXT NULL, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT NULL, readAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE(userId,eventKey));
CREATE TABLE learner_notification_preferences (studentId TEXT NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL DEFAULT 1, emailEnabled INTEGER NOT NULL DEFAULT 0, updatedAt TEXT NOT NULL, PRIMARY KEY(studentId,notificationType));
INSERT INTO teacher_profiles VALUES
 ('11111111-1111-4111-8111-111111111111','21111111-1111-4111-8111-111111111111'),
 ('12222222-2222-4222-8222-222222222222','22222222-2222-4222-8222-222222222222');
INSERT INTO activities VALUES
 ('33333333-3333-4333-8333-333333333333','Activity A','11111111-1111-4111-8111-111111111111',2,'published'),
 ('34444444-4444-4444-8444-444444444444','Activity B','12222222-2222-4222-8222-222222222222',2,'published'),
 ('35555555-5555-4555-8555-555555555555','Activity C','11111111-1111-4111-8111-111111111111',1,'published');
INSERT INTO student_profiles VALUES
 ('50000000-0000-4000-8000-000000000001','60000000-0000-4000-8000-000000000001'),
 ('50000000-0000-4000-8000-000000000002','60000000-0000-4000-8000-000000000002'),
 ('50000000-0000-4000-8000-000000000003','60000000-0000-4000-8000-000000000003'),
 ('50000000-0000-4000-8000-000000000004','60000000-0000-4000-8000-000000000004'),
 ('50000000-0000-4000-8000-000000000005','60000000-0000-4000-8000-000000000005');
INSERT INTO activity_registrations VALUES
 ('40000000-0000-4000-8000-000000000001','33333333-3333-4333-8333-333333333333','50000000-0000-4000-8000-000000000001','pending','2026-08-01','2026-08-01',NULL,NULL),
 ('40000000-0000-4000-8000-000000000002','33333333-3333-4333-8333-333333333333','50000000-0000-4000-8000-000000000002','pending','2026-08-02','2026-08-02',NULL,NULL),
 ('40000000-0000-4000-8000-000000000003','34444444-4444-4444-8444-444444444444','50000000-0000-4000-8000-000000000003','pending','2026-08-03','2026-08-03',NULL,NULL),
 ('40000000-0000-4000-8000-000000000004','35555555-5555-4555-8555-555555555555','50000000-0000-4000-8000-000000000004','approved','2026-08-04','2026-08-04',NULL,NULL),
 ('40000000-0000-4000-8000-000000000005','35555555-5555-4555-8555-555555555555','50000000-0000-4000-8000-000000000005','pending','2026-08-05','2026-08-05',NULL,NULL);
SQL
);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};
$expectApi = static function (string $code, callable $operation) use ($assert): void {
    try {
        $operation();
        $assert(false, "Expected {$code}.");
    } catch (ApiException $exception) {
        $assert($exception->errorCode === $code, "Expected {$code}, got {$exception->errorCode}.");
    }
};

$service = new TeacherActivityService(new TeacherActivityRepository($pdo));
$teacherId = $service->teacherIdForUser('21111111-1111-4111-8111-111111111111');
$assert($teacherId === '11111111-1111-4111-8111-111111111111', 'Teacher profile resolves from authenticated user.');

$approved = $service->transitionRegistration(
    $teacherId,
    '21111111-1111-4111-8111-111111111111',
    '01J5PHASE4APPROVE00000001',
    '33333333-3333-4333-8333-333333333333',
    '40000000-0000-4000-8000-000000000001',
    ['expectedStatus' => 'pending', 'action' => 'approve'],
);
$assert(($approved['status'] ?? null) === 'approved', 'Teacher approves pending registration.');

$rejected = $service->transitionRegistration(
    $teacherId,
    '21111111-1111-4111-8111-111111111111',
    '01J5PHASE4REJECT000000002',
    '33333333-3333-4333-8333-333333333333',
    '40000000-0000-4000-8000-000000000002',
    ['expectedStatus' => 'pending', 'action' => 'reject'],
);
$assert(($rejected['status'] ?? null) === 'rejected', 'Teacher rejects pending registration.');

$expectApi('STATUS_CONFLICT', static fn () => $service->transitionRegistration(
    $teacherId,
    '21111111-1111-4111-8111-111111111111',
    '01J5PHASE4STALE0000000003',
    '33333333-3333-4333-8333-333333333333',
    '40000000-0000-4000-8000-000000000001',
    ['expectedStatus' => 'pending', 'action' => 'reject'],
));
$expectApi('RESOURCE_NOT_FOUND', static fn () => $service->transitionRegistration(
    $teacherId,
    '21111111-1111-4111-8111-111111111111',
    '01J5PHASE4CROSS0000000004',
    '34444444-4444-4444-8444-444444444444',
    '40000000-0000-4000-8000-000000000003',
    ['expectedStatus' => 'pending', 'action' => 'approve'],
));
$expectApi('CAPACITY_REACHED', static fn () => $service->transitionRegistration(
    $teacherId,
    '21111111-1111-4111-8111-111111111111',
    '01J5PHASE4FULL00000000005',
    '35555555-5555-4555-8555-555555555555',
    '40000000-0000-4000-8000-000000000005',
    ['expectedStatus' => 'pending', 'action' => 'approve'],
));

$auditCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('activity_registration.approved','activity_registration.rejected')")->fetchColumn();
$assert($auditCount === 2, 'Successful teacher transitions create audit records only once.');

echo "teacher_activity_registration_transition_test: OK\n";
