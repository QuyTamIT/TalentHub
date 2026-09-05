<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Database\DatabaseActivityRepository;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL);
CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT NOT NULL);
CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, schoolId TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, classId TEXT NOT NULL);
CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, createdByTeacherId TEXT NOT NULL, title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT NULL, capacity INTEGER NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL);
CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, responsibleTeacherId TEXT NULL, audienceScope TEXT NULL, displayCategory TEXT NULL, filterCategory TEXT NULL, summary TEXT NULL, description TEXT NULL, experienceHighlights TEXT NULL, skillTags TEXT NULL, eligibilityRules TEXT NULL, benefitItems TEXT NULL, locationName TEXT NULL, locationAddress TEXT NULL, deliveryMode TEXT NULL, onlineMeetingUrl TEXT NULL, organizerName TEXT NULL, organizerContact TEXT NULL, organizerEmail TEXT NULL, organizerPhone TEXT NULL, coverImageUrl TEXT NULL, coverImageAlt TEXT NULL, feeAmount REAL NULL, currency TEXT NULL, targetAudience TEXT NULL, certificateLabel TEXT NULL, createdAt TEXT NULL, updatedAt TEXT NULL);
CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT NULL, registrationClosesAt TEXT NULL, cancellationClosesAt TEXT NULL, approvalMode TEXT NULL);
CREATE TABLE activity_experience_policies (activityId TEXT PRIMARY KEY, confirmedHours REAL NOT NULL, createdAt TEXT NULL, updatedAt TEXT NULL);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, registeredAt TEXT NOT NULL, updatedAt TEXT NOT NULL, cancelledAt TEXT NULL, cancellationReason TEXT NULL, attendanceResolvedAt TEXT NULL, attendanceResolutionReason TEXT NULL);
CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT NOT NULL UNIQUE, status TEXT NOT NULL, checkedInAt TEXT NULL, confirmedAt TEXT NULL, createdAt TEXT NULL);
CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, activityId TEXT NOT NULL, checkinId TEXT NOT NULL, hours REAL NOT NULL, status TEXT NOT NULL, auditReason TEXT NULL, confirmedAt TEXT NULL, createdAt TEXT NULL);
SQL
);

$ids = [
    'schoolA' => '11111111-1111-4111-8111-111111111111',
    'schoolB' => '22222222-2222-4222-8222-222222222222',
    'classA' => '33333333-3333-4333-8333-333333333333',
    'studentA' => '44444444-4444-4444-8444-444444444444',
    'studentUser' => '55555555-5555-4555-8555-555555555555',
    'teacherA' => '66666666-6666-4666-8666-666666666666',
    'teacherUserA' => '77777777-7777-4777-8777-777777777777',
    'ownCompleted' => '88888888-8888-4888-8888-888888888888',
    'ownNoShow' => '99999999-9999-4999-8999-999999999999',
    'foreign' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'attendedRegistration' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'noShowRegistration' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
    'checkin' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
];
$pdo->prepare('INSERT INTO schools VALUES (?,?),(?,?)')->execute([$ids['schoolA'], 'School A', $ids['schoolB'], 'School B']);
$pdo->prepare('INSERT INTO classes VALUES (?,?)')->execute([$ids['classA'], $ids['schoolA']]);
$pdo->prepare('INSERT INTO users VALUES (?,?),(?,?)')->execute([$ids['studentUser'], 'Student A', $ids['teacherUserA'], 'Teacher A']);
$pdo->prepare('INSERT INTO teacher_profiles VALUES (?,?,?)')->execute([$ids['teacherA'], $ids['teacherUserA'], $ids['schoolA']]);
$pdo->prepare('INSERT INTO student_profiles VALUES (?,?,?)')->execute([$ids['studentA'], $ids['studentUser'], $ids['classA']]);
$activity = $pdo->prepare('INSERT INTO activities VALUES (?,?,?,?,?,?,?,?,?,?)');
foreach ([
    [$ids['ownCompleted'], $ids['schoolA'], $ids['teacherA'], 'Own completed', 'career_technical', '2026-08-10 09:00:00', '2026-08-10 11:00:00', 10, 'completed', '2026-08-01 00:00:00'],
    [$ids['ownNoShow'], $ids['schoolA'], $ids['teacherA'], 'Own no show', 'career_business', '2026-08-11 09:00:00', '2026-08-11 11:00:00', 10, 'completed', '2026-08-01 00:00:00'],
    [$ids['foreign'], $ids['schoolB'], $ids['teacherA'], 'Foreign activity', 'career_arts', '2026-08-12 09:00:00', '2026-08-12 11:00:00', 10, 'published', '2026-08-01 00:00:00'],
] as $row) {
    $activity->execute($row);
}
$detail = $pdo->prepare('INSERT INTO activity_details VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
foreach ([$ids['ownCompleted'], $ids['ownNoShow'], $ids['foreign']] as $activityId) {
    $detail->execute([$activityId, $ids['teacherA'], 'school_only', 'Kỹ thuật', 'Kỹ thuật', 'Summary', 'Description', '["Thực hành"]', 'not-valid-json', '["Học sinh"]', '["Chứng nhận"]', 'Room', null, 'in_person', null, 'School A', null, null, null, '/local-cover.webp', 'Ảnh minh họa', 0, 'VND', 'Học sinh', null, '2026-08-01 00:00:00', '2026-08-01 00:00:00']);
}
$pdo->prepare('INSERT INTO activity_registration_policies VALUES (?,?,?,?,?)')->execute([$ids['ownCompleted'], '2026-08-01 00:00:00', '2026-08-09 00:00:00', '2026-08-09 00:00:00', 'automatic']);
$pdo->prepare('INSERT INTO activity_experience_policies VALUES (?,?,?,?)')->execute([$ids['ownCompleted'], 3.5, '2026-08-01 00:00:00', '2026-08-01 00:00:00']);
$pdo->prepare('INSERT INTO activity_registrations VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$ids['attendedRegistration'], $ids['ownCompleted'], $ids['studentA'], 'attended', '2026-08-01 10:00:00', '2026-08-10 11:05:00', null, null, null, null]);
$pdo->prepare('INSERT INTO activity_registrations VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$ids['noShowRegistration'], $ids['ownNoShow'], $ids['studentA'], 'no_show', '2026-08-02 10:00:00', '2026-08-12 11:00:00', null, null, '2026-08-12 11:00:00', 'absence_reconciled']);
$pdo->prepare('INSERT INTO checkins VALUES (?,?,?,?,?,?)')->execute([$ids['checkin'], $ids['attendedRegistration'], 'confirmed', '2026-08-25 09:00:00', '2026-08-25 09:05:00', '2026-08-25 09:00:00']);
$pdo->prepare('INSERT INTO experience_logs VALUES (?,?,?,?,?,?,?,?,?)')->execute(['eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', $ids['studentA'], $ids['ownCompleted'], $ids['checkin'], 3.5, 'confirmed', 'automatic_checkin_policy', '2026-08-25 09:05:00', '2026-08-25 09:05:00']);

$repository = new DatabaseActivityRepository($pdo);
$assert(method_exists($repository, 'findForStudent'), 'findForStudent is part of the repository contract');
$assert(method_exists($repository, 'registrationTimelineFor'), 'registrationTimelineFor is part of the repository contract');

$own = $repository->findForStudent($ids['studentA'], $ids['ownCompleted']);
$assert(is_array($own), 'same-school completed activity resolves for detail/history');
$assert(($own['school_name'] ?? null) === 'School A', 'scoped detail includes the real school name');
$assert(($own['responsible_teacher_name'] ?? null) === 'Teacher A', 'scoped detail includes the real responsible teacher name');
$assert(($own['skills'] ?? null) === [], 'invalid JSON metadata fails safely to an empty list');
$assert($repository->findForStudent($ids['studentA'], $ids['foreign']) === null, 'cross-school detail is non-enumerating');

$timeline = $repository->registrationTimelineFor($ids['studentA']);
$assert(count($timeline) === 2, 'timeline retains all own registration statuses even after discovery closes');
$byRegistration = [];
foreach ($timeline as $row) {
    $byRegistration[$row['id']] = $row;
}
$attended = $byRegistration[$ids['attendedRegistration']] ?? null;
$noShow = $byRegistration[$ids['noShowRegistration']] ?? null;
$assert(is_array($attended) && ($attended['checked_in_at'] ?? null) === '2026-08-25 09:00:00', 'timeline exposes the student scan timestamp, not the later confirmation timestamp');
$assert(is_array($attended) && ($attended['checked_in_at'] ?? null) !== '2026-08-25 09:05:00', 'timeline never substitutes confirmedAt for checkedInAt');
$assert(is_array($attended) && (float) ($attended['experience_hours'] ?? 0) === 3.5, 'timeline uses a confirmed experience log only');
$assert(is_array($noShow) && array_key_exists('checked_in_at', $noShow) && $noShow['checked_in_at'] === null, 'timeline never infers check-in from registration status');
$assert(is_array($noShow) && ($noShow['attendance_resolved_at'] ?? null) === '2026-08-12 11:00:00', 'timeline exposes resolved attendance timestamp');

echo "learner_activity_registration_timeline_test: OK\n";
