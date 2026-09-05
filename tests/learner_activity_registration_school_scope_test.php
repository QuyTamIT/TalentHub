<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseActivityCommandRepository;
use TalentHub\Learner\Data\Service\ActivityRegistrationService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$expect = static function (callable $callback, int $status, string $code, string $message) use ($assert): void {
    try {
        $callback();
    } catch (ApiException $exception) {
        $assert($exception->status === $status, "{$message}: HTTP status");
        $assert($exception->errorCode === $code, "{$message}: error code");
        return;
    }

    $assert(false, "{$message}: expected ApiException");
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, classId TEXT NOT NULL);
CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, title TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT NULL, capacity INTEGER NOT NULL, status TEXT NOT NULL);
CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT, cancellationClosesAt TEXT, approvalMode TEXT);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, registeredAt TEXT NOT NULL, updatedAt TEXT NOT NULL, cancelledAt TEXT NULL, cancellationReason TEXT NULL, UNIQUE(activityId,studentId));
CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, requestId TEXT, ipAddress TEXT, metadata TEXT, createdAt TEXT);
CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT NOT NULL, eventKey TEXT NULL, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT NULL, readAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE(userId,eventKey));
CREATE TABLE learner_notification_preferences (studentId TEXT NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL DEFAULT 1, emailEnabled INTEGER NOT NULL DEFAULT 0, updatedAt TEXT NOT NULL, PRIMARY KEY(studentId,notificationType));
SQL
);

$ids = [
    'schoolA' => '11111111-1111-4111-8111-111111111111',
    'schoolB' => '22222222-2222-4222-8222-222222222222',
    'classA' => '33333333-3333-4333-8333-333333333333',
    'classB' => '44444444-4444-4444-8444-444444444444',
    'studentA' => '55555555-5555-4555-8555-555555555555',
    'studentB' => '66666666-6666-4666-8666-666666666666',
    'userA' => '77777777-7777-4777-8777-777777777777',
    'sameSchool' => '88888888-8888-4888-8888-888888888888',
    'foreignSchool' => '99999999-9999-4999-8999-999999999999',
    'closesNow' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
];
$pdo->prepare('INSERT INTO schools (id,name) VALUES (?,?),(?,?)')->execute([$ids['schoolA'], 'School A', $ids['schoolB'], 'School B']);
$pdo->prepare('INSERT INTO classes (id,schoolId) VALUES (?,?),(?,?)')->execute([$ids['classA'], $ids['schoolA'], $ids['classB'], $ids['schoolB']]);
$pdo->prepare('INSERT INTO student_profiles (id,userId,classId) VALUES (?,?,?),(?,?,?)')->execute([
    $ids['studentA'], $ids['userA'], $ids['classA'],
    $ids['studentB'], 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', $ids['classB'],
]);
$activity = $pdo->prepare('INSERT INTO activities (id,schoolId,title,startAt,endAt,capacity,status) VALUES (?,?,?,?,?,?,?)');
foreach ([
    [$ids['sameSchool'], $ids['schoolA'], 'Same school', '2026-09-10 09:00:00', '2026-09-10 11:00:00', 2, 'published'],
    [$ids['foreignSchool'], $ids['schoolB'], 'Foreign school', '2026-09-11 09:00:00', '2026-09-11 11:00:00', 2, 'published'],
    [$ids['closesNow'], $ids['schoolA'], 'Closes now', '2026-09-12 09:00:00', '2026-09-12 11:00:00', 2, 'published'],
] as $row) {
    $activity->execute($row);
}
$policy = $pdo->prepare('INSERT INTO activity_registration_policies (activityId,registrationOpensAt,registrationClosesAt,cancellationClosesAt,approvalMode) VALUES (?,?,?,?,?)');
$policy->execute([$ids['sameSchool'], '2026-08-20 10:00:00', '2026-09-10 08:00:00', '2026-09-10 08:30:00', 'automatic']);
$policy->execute([$ids['foreignSchool'], '2026-08-01 00:00:00', '2026-09-11 08:00:00', '2026-09-11 08:30:00', 'teacher_review']);
$policy->execute([$ids['closesNow'], '2026-08-01 00:00:00', '2026-08-20 10:00:00', '2026-08-20 10:00:00', 'automatic']);

$now = new DateTimeImmutable('2026-08-20 10:00:00', new DateTimeZone('UTC'));
$service = new ActivityRegistrationService(
    new DatabaseActivityCommandRepository($pdo),
    static fn (): DateTimeImmutable => $now,
);

$sameSchool = $service->register($ids['studentA'], $ids['userA'], '01KPHASE5SAMESCHOOL0000001', ['activityId' => $ids['sameSchool']]);
$assert(($sameSchool['registration']['status'] ?? null) === 'approved', 'same-school registration at opening boundary remains automatic and approved');

$before = [
    'registrations' => (int) $pdo->query('SELECT COUNT(*) FROM activity_registrations')->fetchColumn(),
    'audits' => (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(),
    'notifications' => (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn(),
];
$expect(
    fn () => $service->register($ids['studentA'], $ids['userA'], '01KPHASE5FOREIGNSCHOOL00001', ['activityId' => $ids['foreignSchool']]),
    403,
    'ACTIVITY_SCHOOL_SCOPE_DENIED',
    'foreign-school registration is denied before every mutation',
);
$after = [
    'registrations' => (int) $pdo->query('SELECT COUNT(*) FROM activity_registrations')->fetchColumn(),
    'audits' => (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(),
    'notifications' => (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn(),
];
$assert($after === $before, 'foreign-school registration creates no registration, audit, or notification');

$expect(
    fn () => $service->register($ids['studentA'], $ids['userA'], '01KPHASE5CLOSESEXCLUSIVE001', ['activityId' => $ids['closesNow']]),
    422,
    'REGISTRATION_CLOSED',
    'registration close boundary is exclusive in UTC',
);

echo "learner_activity_registration_school_scope_test: OK\n";
