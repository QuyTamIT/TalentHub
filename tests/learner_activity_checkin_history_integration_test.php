<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Database\DatabaseActivityRepository;
use TalentHub\Learner\Data\Database\DatabaseCheckinRepository;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$page = (string) file_get_contents(dirname(__DIR__) . '/app/learner/checkin.php');
$assert(str_contains($page, 'learner_activity_find'), 'QR deep link resolves its activity through the scoped learner helper.');
$assert(str_contains($page, 'learner_activity_registration_history'), 'QR deep link derives its status from the current learner registration timeline.');
$assert(str_contains($page, "'approved'"), 'Approved registrations have an explicit linked-card state.');
$assert(str_contains($page, "'pending'"), 'Pending registrations have an explicit linked-card state.');
$assert(str_contains($page, "'waitlisted'"), 'Waitlisted registrations have an explicit linked-card state.');
$assert(str_contains($page, "'attended'"), 'Attended registrations do not imply a second check-in.');
$assert(str_contains($page, 'activity-history.php'), 'The QR page exposes the post-success activity history destination.');

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec(<<<'SQL'
CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT);
CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, createdByTeacherId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT, registeredAt TEXT, updatedAt TEXT, cancelledAt TEXT, cancellationReason TEXT);
CREATE TABLE activity_qr_sessions (id TEXT PRIMARY KEY, activityId TEXT, tokenHash TEXT UNIQUE, status TEXT, expiresAt TEXT, maxScans INTEGER, usedScans INTEGER, revokedAt TEXT, updatedAt TEXT);
CREATE TABLE activity_experience_policies (activityId TEXT PRIMARY KEY, confirmedHours NUMERIC NOT NULL);
CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT UNIQUE, qrSessionId TEXT, status TEXT, checkedInAt TEXT, confirmedAt TEXT, createdAt TEXT);
CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, checkinId TEXT UNIQUE, hours NUMERIC, status TEXT, auditReason TEXT, confirmedAt TEXT, createdAt TEXT);
CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, requestId TEXT, ipAddress TEXT, metadata TEXT, createdAt TEXT);
CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT, eventKey TEXT, notificationType TEXT, title TEXT, message TEXT, deepLink TEXT, readAt TEXT, createdAt TEXT, UNIQUE(userId, eventKey));
CREATE TABLE learner_notification_preferences (studentId TEXT, notificationType TEXT, inAppEnabled INTEGER, emailEnabled INTEGER, updatedAt TEXT);
INSERT INTO schools VALUES ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'Trường cùng scope'), ('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'Trường khác scope');
INSERT INTO classes VALUES ('cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'), ('dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
INSERT INTO student_profiles VALUES ('11111111-1111-4111-8111-111111111111', 'cccccccc-cccc-4ccc-8ccc-cccccccccccc'), ('99999999-9999-4999-8999-999999999999', 'dddddddd-dddd-4ddd-8ddd-dddddddddddd');
INSERT INTO activities VALUES ('22222222-2222-4222-8222-222222222222', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', 'Hoạt động đã check-in', 'technical', '2026-08-22 08:00:00', '2026-08-22 10:00:00', 10, 'ongoing');
INSERT INTO activity_registrations VALUES ('33333333-3333-4333-8333-333333333333', '22222222-2222-4222-8222-222222222222', '11111111-1111-4111-8111-111111111111', 'approved', '2026-08-22 07:00:00', '2026-08-22 07:00:00', NULL, NULL);
INSERT INTO activity_qr_sessions VALUES ('44444444-4444-4444-8444-444444444444', '22222222-2222-4222-8222-222222222222', 'TOKEN_HASH', 'active', '2099-08-22 12:00:00', 5, 0, NULL, '2026-08-22 07:00:00');
INSERT INTO activity_experience_policies VALUES ('22222222-2222-4222-8222-222222222222', 2.50);
SQL);
$pdo->prepare('UPDATE activity_qr_sessions SET tokenHash = :hash')->execute(['hash' => hash('sha256', 'phase9-history-token')]);

(new DatabaseCheckinRepository($pdo))->createConfirmed(
    '11111111-1111-4111-8111-111111111111',
    '55555555-5555-4555-8555-555555555555',
    '01JPHASE9HISTORY0000001',
    hash('sha256', 'phase9-history-token'),
);

$timeline = (new DatabaseActivityRepository($pdo))->registrationTimelineFor('11111111-1111-4111-8111-111111111111');
$active = array_values(array_filter($timeline, static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), ['pending', 'approved', 'waitlisted'], true)));
$history = array_values(array_filter($timeline, static fn (array $row): bool => (string) ($row['status'] ?? '') === 'attended'));
$assert($active === [], 'attended registration no longer appears in the registered activity states.');
$assert(count($history) === 1, 'attended registration appears once in the scoped attendance history.');
$assert(($history[0]['id'] ?? null) === '33333333-3333-4333-8333-333333333333', 'history keeps the original registration identity.');
$assert(!empty($history[0]['checked_in_at']), 'history uses the actual check-in timestamp.');
$assert((float) ($history[0]['experience_hours'] ?? 0) === 2.5, 'history exposes only the confirmed policy hours.');
$assert((new DatabaseActivityRepository($pdo))->registrationTimelineFor('99999999-9999-4999-8999-999999999999') === [], 'a learner from another school cannot read the attended registration.');

echo "learner_activity_checkin_history_integration_test: OK\n";
