<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseActivityCommandRepository;
use TalentHub\Learner\Data\Database\DatabaseActivityRepository;
use TalentHub\Learner\Data\Service\ActivityRegistrationService;

/** @var list<string> $phase1SchoolScopeFailures */
$phase1SchoolScopeFailures = [];
$phase1SchoolScopeAssert = static function (bool $condition, string $message) use (&$phase1SchoolScopeFailures): void {
    if (!$condition) {
        $phase1SchoolScopeFailures[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT NOT NULL)');
$pdo->exec('CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, schoolId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, classId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, createdByTeacherId TEXT NOT NULL, title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT NULL, capacity INTEGER NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, responsibleTeacherId TEXT NULL, audienceScope TEXT NULL, displayCategory TEXT NULL, filterCategory TEXT NULL, summary TEXT NULL, description TEXT NULL, experienceHighlights TEXT NULL, skillTags TEXT NULL, eligibilityRules TEXT NULL, benefitItems TEXT NULL, locationName TEXT NULL, locationAddress TEXT NULL, deliveryMode TEXT NULL, onlineMeetingUrl TEXT NULL, organizerName TEXT NULL, organizerContact TEXT NULL, organizerEmail TEXT NULL, organizerPhone TEXT NULL, coverImageUrl TEXT NULL, feeAmount REAL NULL, currency TEXT NULL, targetAudience TEXT NULL, certificateLabel TEXT NULL, createdAt TEXT NULL, updatedAt TEXT NULL)');
$pdo->exec('CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT NULL, registrationClosesAt TEXT NULL, cancellationClosesAt TEXT NULL, approvalMode TEXT NULL)');
$pdo->exec('CREATE TABLE activity_experience_policies (activityId TEXT PRIMARY KEY, confirmedHours REAL NOT NULL, createdAt TEXT NULL, updatedAt TEXT NULL)');
$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, registeredAt TEXT NOT NULL, updatedAt TEXT NOT NULL, cancelledAt TEXT NULL, cancellationReason TEXT NULL, UNIQUE(activityId, studentId))');
$pdo->exec('CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT NULL, action TEXT NOT NULL, entityType TEXT NOT NULL, entityId TEXT NOT NULL, requestId TEXT NOT NULL, ipAddress TEXT NULL, metadata TEXT NOT NULL, createdAt TEXT NOT NULL)');
$pdo->exec('CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT NOT NULL, eventKey TEXT NULL, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT NULL, readAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE(userId, eventKey))');
$pdo->exec('CREATE TABLE learner_notification_preferences (studentId TEXT NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL DEFAULT 1, emailEnabled INTEGER NOT NULL DEFAULT 0, updatedAt TEXT NOT NULL, PRIMARY KEY(studentId, notificationType))');

$ids = [
    'schoolA' => '11111111-1111-4111-8111-111111111111',
    'schoolB' => '22222222-2222-4222-8222-222222222222',
    'classA' => '33333333-3333-4333-8333-333333333333',
    'classB' => '44444444-4444-4444-8444-444444444444',
    'studentA' => '55555555-5555-4555-8555-555555555555',
    'studentB' => '66666666-6666-4666-8666-666666666666',
    'userA' => '77777777-7777-4777-8777-777777777777',
    'userB' => '88888888-8888-4888-8888-888888888888',
    'teacherA' => '99999999-9999-4999-8999-999999999999',
    'teacherB' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'teacherUserA' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'teacherUserB' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
    'ownActivity' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
    'foreignActivity' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
    'ownSchoolPublicActivity' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
];

$pdo->prepare('INSERT INTO schools (id,name) VALUES (?,?),(?,?)')->execute([$ids['schoolA'], 'Trường A', $ids['schoolB'], 'Trường B']);
$pdo->prepare('INSERT INTO classes (id,schoolId) VALUES (?,?),(?,?)')->execute([$ids['classA'], $ids['schoolA'], $ids['classB'], $ids['schoolB']]);
$pdo->prepare('INSERT INTO users (id,fullName) VALUES (?,?),(?,?),(?,?),(?,?)')->execute([$ids['userA'], 'Sinh viên A', $ids['userB'], 'Sinh viên B', $ids['teacherUserA'], 'Giáo viên A', $ids['teacherUserB'], 'Giáo viên B']);
$pdo->prepare('INSERT INTO teacher_profiles (id,userId,schoolId) VALUES (?,?,?),(?,?,?)')->execute([$ids['teacherA'], $ids['teacherUserA'], $ids['schoolA'], $ids['teacherB'], $ids['teacherUserB'], $ids['schoolB']]);
$pdo->prepare('INSERT INTO student_profiles (id,userId,classId) VALUES (?,?,?),(?,?,?)')->execute([$ids['studentA'], $ids['userA'], $ids['classA'], $ids['studentB'], $ids['userB'], $ids['classB']]);
$insertActivity = $pdo->prepare('INSERT INTO activities (id,schoolId,createdByTeacherId,title,category,startAt,endAt,capacity,status,createdAt) VALUES (?,?,?,?,?,?,?,?,?,?)');
foreach ([
    [$ids['ownActivity'], $ids['schoolA'], $ids['teacherA'], 'Hoạt động trường A', 'career_technical', '2026-09-01 09:00:00', '2026-09-01 11:00:00', 4, 'published', '2026-08-01 00:00:00'],
    [$ids['foreignActivity'], $ids['schoolB'], $ids['teacherB'], 'Hoạt động trường B', 'career_business', '2026-09-02 09:00:00', '2026-09-02 11:00:00', 4, 'published', '2026-08-01 00:00:00'],
    [$ids['ownSchoolPublicActivity'], $ids['schoolA'], $ids['teacherA'], 'Hoạt động public cùng trường', 'career_arts', '2026-09-03 09:00:00', '2026-09-03 11:00:00', 4, 'published', '2026-08-01 00:00:00'],
] as $activity) {
    $insertActivity->execute($activity);
}
$pdo->prepare('INSERT INTO activity_details (activityId,responsibleTeacherId,audienceScope,filterCategory,displayCategory,summary,description,locationName,deliveryMode,organizerName,feeAmount,currency,targetAudience,createdAt,updatedAt) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?),(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?),(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
    $ids['ownActivity'], $ids['teacherA'], 'school_only', 'Kỹ thuật', 'Kỹ thuật', 'Summary', 'Description', 'Phòng A', 'in_person', 'Trường A', 0, 'VND', 'Học sinh Trường A', '2026-08-01 00:00:00', '2026-08-01 00:00:00',
    $ids['foreignActivity'], $ids['teacherB'], 'school_only', 'Kinh doanh', 'Kinh doanh', 'Summary', 'Description', 'Phòng B', 'in_person', 'Trường B', 0, 'VND', 'Học sinh Trường B', '2026-08-01 00:00:00', '2026-08-01 00:00:00',
    $ids['ownSchoolPublicActivity'], $ids['teacherA'], 'public', 'Sáng tạo', 'Sáng tạo', 'Summary', 'Description', 'Phòng A', 'in_person', 'Trường A', 0, 'VND', 'Học sinh Trường A', '2026-08-01 00:00:00', '2026-08-01 00:00:00',
]);
$pdo->prepare('INSERT INTO activity_registration_policies (activityId,registrationOpensAt,registrationClosesAt,cancellationClosesAt,approvalMode) VALUES (?,?,?,?,?),(?,?,?,?,?),(?,?,?,?,?)')->execute([
    $ids['ownActivity'], '2026-08-01 00:00:00', '2026-08-31 00:00:00', '2026-08-31 00:00:00', 'automatic',
    $ids['foreignActivity'], '2026-08-01 00:00:00', '2026-08-31 00:00:00', '2026-08-31 00:00:00', 'automatic',
    $ids['ownSchoolPublicActivity'], '2026-08-01 00:00:00', '2026-08-31 00:00:00', '2026-08-31 00:00:00', 'automatic',
]);
$pdo->prepare('INSERT INTO activity_experience_policies (activityId,confirmedHours,createdAt,updatedAt) VALUES (?,?,?,?),(?,?,?,?),(?,?,?,?)')->execute([
    $ids['ownActivity'], 3.0, '2026-08-01 00:00:00', '2026-08-01 00:00:00',
    $ids['foreignActivity'], 2.5, '2026-08-01 00:00:00', '2026-08-01 00:00:00',
    $ids['ownSchoolPublicActivity'], 2.0, '2026-08-01 00:00:00', '2026-08-01 00:00:00',
]);

$now = new DateTimeImmutable('2026-08-20 10:00:00', new DateTimeZone('UTC'));
$readRepository = new DatabaseActivityRepository($pdo);
$commandService = new ActivityRegistrationService(
    new DatabaseActivityCommandRepository($pdo),
    static fn (): DateTimeImmutable => $now,
);

try {
    $commandService->register($ids['studentA'], $ids['userA'], '01KPHASE1SCHOOLSCOPE00001', ['activityId' => $ids['foreignActivity']]);
    $phase1SchoolScopeFailures[] = 'Foreign-school API registration must throw ApiException 403 ACTIVITY_SCHOOL_SCOPE_DENIED; the current service accepted it despite a complete SQLite audit/notification fixture.';
} catch (ApiException $exception) {
    $phase1SchoolScopeAssert($exception->status === 403, 'Foreign-school API registration must return HTTP 403, not ' . $exception->status . '.');
    $phase1SchoolScopeAssert($exception->errorCode === 'ACTIVITY_SCHOOL_SCOPE_DENIED', 'Foreign-school API registration must return ACTIVITY_SCHOOL_SCOPE_DENIED, not ' . $exception->errorCode . '.');
}

$phase1SchoolScopeAssert(
    (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='activity_registration.registered'")->fetchColumn() === 0,
    'Scope denial must happen before audit and notification writes.'
);
$phase1SchoolScopeAssert(
    (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === 0,
    'Scope denial must happen before notification writes.'
);

if (!method_exists($readRepository, 'discoverForStudent')) {
    $phase1SchoolScopeFailures[] = 'ActivityRepository::discoverForStudent(studentId, now) is missing; Task 8 (Phase 5) must return only published, school_only activities from the student\'s school.';
} else {
    $discovered = $readRepository->discoverForStudent($ids['studentA'], $now);
    $phase1SchoolScopeAssert(array_column($discovered, 'id') === [$ids['ownActivity']], 'discoverForStudent must exclude a same-school published public activity as well as foreign-school activities.');
}

if (!method_exists($readRepository, 'findForStudent')) {
    $phase1SchoolScopeFailures[] = 'ActivityRepository::findForStudent(studentId, activityId) is missing; Task 8 (Phase 5) detail lookup must hide foreign-school activities.';
} else {
    $phase1SchoolScopeAssert($readRepository->findForStudent($ids['studentA'], $ids['foreignActivity']) === null, 'findForStudent must return null for an activity from another school.');
}

if ($phase1SchoolScopeFailures !== []) {
    fwrite(STDERR, "learner_activity_school_scope_test: RED\n- " . implode("\n- ", $phase1SchoolScopeFailures) . "\n");
    exit(1);
}

echo "learner_activity_school_scope_test: OK\n";
