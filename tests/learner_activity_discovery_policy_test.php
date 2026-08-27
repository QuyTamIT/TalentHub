<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Database\DatabaseActivityRepository;

/** @var list<string> $phase1DiscoveryFailures */
$phase1DiscoveryFailures = [];
$phase1DiscoveryAssert = static function (bool $condition, string $message) use (&$phase1DiscoveryFailures): void {
    if (!$condition) {
        $phase1DiscoveryFailures[] = $message;
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
$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, registeredAt TEXT NOT NULL, updatedAt TEXT NOT NULL, cancelledAt TEXT NULL, cancellationReason TEXT NULL)');

$schoolA = '11111111-1111-4111-8111-111111111111';
$schoolB = '22222222-2222-4222-8222-222222222222';
$studentA = '33333333-3333-4333-8333-333333333333';
$studentB = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$teacherA = '66666666-6666-4666-8666-666666666666';
$teacherB = '77777777-7777-4777-8777-777777777777';
$teacherUserA = '88888888-8888-4888-8888-888888888888';
$teacherUserB = '99999999-9999-4999-8999-999999999999';
$pdo->prepare('INSERT INTO schools (id,name) VALUES (?,?),(?,?)')->execute([$schoolA, 'Trường A', $schoolB, 'Trường B']);
$pdo->prepare('INSERT INTO classes (id,schoolId) VALUES (?,?),(?,?)')->execute(['44444444-4444-4444-8444-444444444444', $schoolA, '55555555-5555-4555-8555-555555555555', $schoolB]);
$pdo->prepare('INSERT INTO users (id,fullName) VALUES (?,?),(?,?),(?,?),(?,?)')->execute([$teacherUserA, 'Giáo viên A', $teacherUserB, 'Giáo viên B', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Sinh viên A', 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'Sinh viên B']);
$pdo->prepare('INSERT INTO teacher_profiles (id,userId,schoolId) VALUES (?,?,?),(?,?,?)')->execute([$teacherA, $teacherUserA, $schoolA, $teacherB, $teacherUserB, $schoolB]);
$pdo->prepare('INSERT INTO student_profiles (id,userId,classId) VALUES (?,?,?),(?,?,?)')->execute([$studentA, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', '44444444-4444-4444-8444-444444444444', $studentB, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', '55555555-5555-4555-8555-555555555555']);

$activities = [
    ['10000000-0000-4000-8000-000000000010', $schoolA, 'Published first', '2026-08-20 12:00:00', '2026-08-20 13:00:00', 3, 'published', '2026-08-20 09:00:00', '2026-08-20 11:00:01'],
    ['10000000-0000-4000-8000-000000000011', $schoolA, 'Published second', '2026-08-20 12:00:00', '2026-08-20 14:00:00', 2, 'published', '2026-08-20 10:00:00', '2026-08-20 11:00:01'],
    ['10000000-0000-4000-8000-000000000012', $schoolA, 'Pending does not occupy', '2026-08-20 15:00:00', '2026-08-20 16:00:00', 1, 'published', '2026-08-20 10:00:00', '2026-08-20 11:00:01'],
    ['10000000-0000-4000-8000-000000000013', $schoolA, 'Closes exactly now', '2026-08-20 16:00:00', '2026-08-20 17:00:00', 2, 'published', '2026-08-20 09:00:00', '2026-08-20 10:00:00'],
    ['10000000-0000-4000-8000-000000000014', $schoolA, 'Opens after now', '2026-08-20 17:00:00', '2026-08-20 18:00:00', 2, 'published', '2026-08-20 10:00:01', '2026-08-20 11:00:01'],
    ['10000000-0000-4000-8000-000000000015', $schoolA, 'Ends exactly now', '2026-08-20 08:00:00', '2026-08-20 10:00:00', 2, 'published', '2026-08-20 09:00:00', '2026-08-20 11:00:01'],
    ['10000000-0000-4000-8000-000000000016', $schoolA, 'Approved and attended fill capacity', '2026-08-20 18:00:00', '2026-08-20 19:00:00', 2, 'published', '2026-08-20 09:00:00', '2026-08-20 11:00:01'],
    ['10000000-0000-4000-8000-000000000017', $schoolA, 'Ongoing is not discovery', '2026-08-20 18:00:00', '2026-08-20 19:00:00', 2, 'ongoing', '2026-08-20 09:00:00', '2026-08-20 11:00:01'],
    ['10000000-0000-4000-8000-000000000018', $schoolA, 'Completed is not discovery', '2026-08-20 08:00:00', '2026-08-20 09:00:00', 2, 'completed', '2026-08-20 07:00:00', '2026-08-20 08:00:00'],
    ['10000000-0000-4000-8000-000000000019', $schoolB, 'Foreign school', '2026-08-20 12:00:00', '2026-08-20 13:00:00', 2, 'published', '2026-08-20 09:00:00', '2026-08-20 11:00:01'],
    ['10000000-0000-4000-8000-000000000020', $schoolA, 'Same school public', '2026-08-20 14:00:00', '2026-08-20 15:00:00', 2, 'published', '2026-08-20 09:00:00', '2026-08-20 11:00:01'],
    ['10000000-0000-4000-8000-000000000021', $schoolA, 'No details fallback', '2026-08-20 17:00:00', '2026-08-20 18:00:00', 2, 'published', '2026-08-20 09:00:00', '2026-08-20 11:00:01'],
];
$insertActivity = $pdo->prepare('INSERT INTO activities (id,schoolId,createdByTeacherId,title,category,startAt,endAt,capacity,status,createdAt) VALUES (?,?,?,?,?,?,?,?,?,?)');
$insertDetail = $pdo->prepare('INSERT INTO activity_details (activityId,responsibleTeacherId,audienceScope,displayCategory,filterCategory,summary,description,locationName,deliveryMode,organizerName,feeAmount,currency,targetAudience,createdAt,updatedAt) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$insertPolicy = $pdo->prepare('INSERT INTO activity_registration_policies (activityId,registrationOpensAt,registrationClosesAt,cancellationClosesAt,approvalMode) VALUES (?,?,?,?,?)');
$insertExperience = $pdo->prepare('INSERT INTO activity_experience_policies (activityId,confirmedHours,createdAt,updatedAt) VALUES (?,?,?,?)');
foreach ($activities as [$id, $schoolId, $title, $startAt, $endAt, $capacity, $status, $opensAt, $closesAt]) {
    $teacherId = $schoolId === $schoolA ? $teacherA : $teacherB;
    $insertActivity->execute([$id, $schoolId, $teacherId, $title, 'career_technical', $startAt, $endAt, $capacity, $status, '2026-08-01 00:00:00']);
    if ($id !== '10000000-0000-4000-8000-000000000021') {
        $scope = $id === '10000000-0000-4000-8000-000000000020' ? 'public' : 'school_only';
        $insertDetail->execute([$id, $teacherId, $scope, 'Kỹ thuật', 'Kỹ thuật', 'Summary', 'Description', 'Phòng hoạt động', 'in_person', $schoolId === $schoolA ? 'Trường A' : 'Trường B', 0, 'VND', 'Học sinh', '2026-08-01 00:00:00', '2026-08-01 00:00:00']);
    }
    $insertPolicy->execute([$id, $opensAt, $closesAt, $closesAt, 'automatic']);
    $insertExperience->execute([$id, 3.0, '2026-08-01 00:00:00', '2026-08-01 00:00:00']);
}
$insertRegistration = $pdo->prepare('INSERT INTO activity_registrations (id,activityId,studentId,status,registeredAt,updatedAt,cancelledAt,cancellationReason) VALUES (?,?,?,?,?,?,NULL,NULL)');
$insertRegistration->execute(['10000000-0000-4000-8000-000000000001', '10000000-0000-4000-8000-000000000012', '88888888-8888-4888-8888-888888888888', 'pending', '2026-08-19 10:00:00', '2026-08-19 10:00:00']);
$insertRegistration->execute(['10000000-0000-4000-8000-000000000002', '10000000-0000-4000-8000-000000000016', '88888888-8888-4888-8888-888888888888', 'approved', '2026-08-19 10:00:00', '2026-08-19 10:00:00']);
$insertRegistration->execute(['10000000-0000-4000-8000-000000000003', '10000000-0000-4000-8000-000000000016', '99999999-9999-4999-8999-999999999999', 'attended', '2026-08-19 10:00:00', '2026-08-19 10:00:00']);

$repository = new DatabaseActivityRepository($pdo);
$now = new DateTimeImmutable('2026-08-20 10:00:00', new DateTimeZone('UTC'));
$phase1DiscoveryAssert(
    (int) $pdo->query("SELECT COUNT(*) FROM activity_registrations WHERE activityId='10000000-0000-4000-8000-000000000016' AND status IN ('approved','attended')")->fetchColumn() === 2,
    'Fixture sanity: approved plus attended must fill g-full.'
);
$phase1DiscoveryAssert(
    (int) $pdo->query("SELECT COUNT(*) FROM activity_registrations WHERE activityId='10000000-0000-4000-8000-000000000012' AND status IN ('approved','attended')")->fetchColumn() === 0,
    'Fixture sanity: pending must not occupy a published activity.'
);

if (!method_exists($repository, 'discoverForStudent')) {
    $phase1DiscoveryFailures[] = 'ActivityRepository::discoverForStudent(studentId, now) is missing; Task 8 (Phase 5) must enforce published-only, school_only, own-school, opens-inclusive/closes-and-end-exclusive, occupancy, and deterministic ordering.';
} else {
    $discovered = $repository->discoverForStudent($studentA, $now);
    $phase1DiscoveryAssert(
        array_column($discovered, 'id') === ['10000000-0000-4000-8000-000000000010', '10000000-0000-4000-8000-000000000011', '10000000-0000-4000-8000-000000000012', '10000000-0000-4000-8000-000000000021'],
        'Discovery must return only open same-school school_only published activities ordered by startAt then id; pending does not consume capacity, no-details defaults to school_only, and no already-registered exclusion is imposed.'
    );
}

if ($phase1DiscoveryFailures !== []) {
    fwrite(STDERR, "learner_activity_discovery_policy_test: RED\n- " . implode("\n- ", $phase1DiscoveryFailures) . "\n");
    exit(1);
}

echo "learner_activity_discovery_policy_test: OK\n";
