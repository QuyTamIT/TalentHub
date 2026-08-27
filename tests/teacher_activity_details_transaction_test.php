<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec(<<<'SQL'
CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT NOT NULL, email TEXT);
CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, schoolId TEXT NOT NULL);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT NOT NULL);
CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, createdByTeacherId TEXT NOT NULL, title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT, capacity INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'draft', visibility TEXT NOT NULL DEFAULT 'school_only');
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, responsibleTeacherId TEXT, audienceScope TEXT NOT NULL, displayCategory TEXT NOT NULL, filterCategory TEXT NOT NULL, summary TEXT NOT NULL, description TEXT NOT NULL, experienceHighlights TEXT NOT NULL, skillTags TEXT NOT NULL, eligibilityRules TEXT NOT NULL, benefitItems TEXT NOT NULL, locationName TEXT NOT NULL, locationAddress TEXT, deliveryMode TEXT NOT NULL, onlineMeetingUrl TEXT, organizerName TEXT NOT NULL, organizerContact TEXT, organizerEmail TEXT, organizerPhone TEXT, coverImageUrl TEXT, coverImageAlt TEXT, feeAmount NUMERIC NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT 'VND', targetAudience TEXT NOT NULL, certificateLabel TEXT, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL);
CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT NOT NULL, registrationClosesAt TEXT NOT NULL, cancellationClosesAt TEXT NOT NULL, approvalMode TEXT NOT NULL DEFAULT 'automatic', createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL);
CREATE TABLE activity_experience_policies (activityId TEXT PRIMARY KEY, confirmedHours NUMERIC NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL);
CREATE TABLE learner_ai_data_outbox (id TEXT PRIMARY KEY, aggregate_type TEXT NOT NULL, aggregate_id TEXT NOT NULL, tenant_id TEXT, event_type TEXT NOT NULL, aggregate_version INTEGER NOT NULL, payload_hash TEXT NOT NULL, affected_student_ids TEXT NOT NULL, delivery_status TEXT NOT NULL, occurred_at TEXT NOT NULL);
INSERT INTO users VALUES ('21111111-1111-4111-8111-111111111111','Giáo viên Một','one@example.test'),('22222222-2222-4222-8222-222222222222','Giáo viên Khác','two@example.test');
INSERT INTO teacher_profiles VALUES ('11111111-1111-4111-8111-111111111111','21111111-1111-4111-8111-111111111111','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),('12222222-2222-4222-8222-222222222222','22222222-2222-4222-8222-222222222222','bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
INSERT INTO classes VALUES ('class-ai-001','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
INSERT INTO student_profiles VALUES ('student-ai-001','class-ai-001');
SQL
);

$service = new TeacherActivityService(new TeacherActivityRepository($pdo));
$teacherId = '11111111-1111-4111-8111-111111111111';
$schoolId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$activityInput = [
    'title' => 'Robotics Lab', 'category' => 'Kỹ thuật', 'displayCategory' => 'Robotics', 'filterCategory' => 'Kỹ thuật',
    'summary' => 'Tóm tắt hoạt động', 'description' => 'Mô tả hoạt động đầy đủ',
    'experienceHighlights' => ['Lắp ráp', 'Lập trình'], 'skillTags' => ['Robotics'], 'eligibilityRules' => ['Học viên của trường'], 'benefitItems' => ['Giờ trải nghiệm'],
    'locationName' => 'Phòng B305', 'locationAddress' => 'Địa chỉ thật', 'deliveryMode' => 'in_person',
    'organizerName' => 'Đơn vị tổ chức', 'organizerContact' => 'Liên hệ thật', 'organizerEmail' => 'contact@example.test',
    'coverImageUrl' => 'assets/activities/covers/robotics.webp', 'coverImageAlt' => 'Học viên lắp ráp robot', 'feeAmount' => '0', 'currency' => 'VND',
    'targetAudience' => 'Học viên của trường', 'responsibleTeacherId' => $teacherId,
    'startAt' => new DateTimeImmutable('2026-09-01 09:00', new DateTimeZone('Asia/Ho_Chi_Minh')),
    'endAt' => new DateTimeImmutable('2026-09-01 12:00', new DateTimeZone('Asia/Ho_Chi_Minh')), 'capacity' => 2,
    'registrationOpensAt' => new DateTimeImmutable('2026-08-20 09:00', new DateTimeZone('Asia/Ho_Chi_Minh')),
    'registrationClosesAt' => new DateTimeImmutable('2026-08-31 09:00', new DateTimeZone('Asia/Ho_Chi_Minh')),
    'cancellationClosesAt' => new DateTimeImmutable('2026-08-31 12:00', new DateTimeZone('Asia/Ho_Chi_Minh')),
    'approvalMode' => 'automatic', 'confirmedHours' => '2.50',
];
$service->create($teacherId, 'forged-school-is-ignored', $activityInput);
$activityId = (string) $pdo->query('SELECT id FROM activities LIMIT 1')->fetchColumn();
$assert($activityId !== '', 'Create writes an activity using the teacher profile school, not submitted schoolId.');
$assert($pdo->query('SELECT schoolId FROM activities LIMIT 1')->fetchColumn() === $schoolId, 'Activity ownership school comes from teacher profile.');
$detail = $pdo->query('SELECT * FROM activity_details LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$policy = $pdo->query('SELECT * FROM activity_registration_policies LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$experience = $pdo->query('SELECT * FROM activity_experience_policies LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$assert(is_array($detail) && $detail['locationName'] === 'Phòng B305' && json_decode($detail['skillTags'], true) === ['Robotics'], 'Create persists detail metadata and JSON lists.');
$assert(is_array($policy) && $policy['approvalMode'] === 'automatic' && $policy['registrationOpensAt'] === '2026-08-20 02:00:00.000000', 'Create converts local policy time to UTC once.');
$assert(is_array($experience) && (float) $experience['confirmedHours'] === 2.5, 'Create persists confirmed hours policy separately.');

$before = $service->find($teacherId, $activityId);
$service->update($teacherId, $activityId, ['title' => 'Robotics Lab Updated']);
$after = $service->find($teacherId, $activityId);
$assert(($after['title'] ?? '') === 'Robotics Lab Updated', 'Edit updates submitted metadata.');
$assert(($after['locationName'] ?? '') === ($before['locationName'] ?? '') && ($after['summary'] ?? '') === ($before['summary'] ?? ''), 'Edit does not erase metadata omitted from payload.');
$assert(($after['approvalMode'] ?? '') === 'automatic' && (float) ($after['confirmedHours'] ?? 0) === 2.5, 'Edit preserves omitted policy and experience fields.');

$service->update($teacherId, $activityId, [
    'deliveryMode' => 'online', 'locationName' => '', 'locationAddress' => '',
    'onlineMeetingUrl' => 'https://meet.example.test/robotics', 'feeAmount' => '125000',
]);
$online = $service->find($teacherId, $activityId);
$assert(($online['deliveryMode'] ?? '') === 'online' && ($online['locationName'] ?? '') === '' && ($online['onlineMeetingUrl'] ?? '') === 'https://meet.example.test/robotics', 'Edit supports online delivery and clears location explicitly.');
$assert((float) ($online['feeAmount'] ?? 0) === 125000.0, 'Edit preserves a paid fee.');

$service->update($teacherId, $activityId, [
    'deliveryMode' => 'hybrid', 'locationName' => 'Phòng B305', 'locationAddress' => 'Địa chỉ thật',
    'onlineMeetingUrl' => 'https://meet.example.test/robotics',
]);
$hybrid = $service->find($teacherId, $activityId);
$assert(($hybrid['deliveryMode'] ?? '') === 'hybrid' && ($hybrid['locationName'] ?? '') === 'Phòng B305' && ($hybrid['onlineMeetingUrl'] ?? '') !== '', 'Edit supports hybrid delivery with both contexts.');

$service->update($teacherId, $activityId, [
    'deliveryMode' => 'in_person', 'onlineMeetingUrl' => '', 'feeAmount' => '0',
]);
$inPerson = $service->find($teacherId, $activityId);
$assert(($inPerson['deliveryMode'] ?? '') === 'in_person' && ($inPerson['onlineMeetingUrl'] ?? null) === null, 'Edit returns to in-person delivery and clears the online link explicitly.');
$assert((float) ($inPerson['feeAmount'] ?? -1) === 0.0, 'Edit supports switching back to a free activity.');

$pdo->exec("INSERT INTO activity_registrations VALUES ('40000000-0000-4000-8000-000000000001','{$activityId}','50000000-0000-4000-8000-000000000001','approved'),('40000000-0000-4000-8000-000000000002','{$activityId}','50000000-0000-4000-8000-000000000002','attended')");
try {
    $service->update($teacherId, $activityId, ['capacity' => 1]);
    $assert(false, 'Capacity reduction below occupied registrations is rejected.');
} catch (ApiException $exception) {
    $assert($exception->errorCode === 'CAPACITY_REACHED', 'Capacity guard returns the canonical error.');
}
$assert((int) $pdo->query("SELECT capacity FROM activities WHERE id='{$activityId}'")->fetchColumn() === 2, 'Rejected capacity update leaves parent unchanged.');

try {
    $service->update($teacherId, $activityId, ['title' => 'Must Roll Back', 'responsibleTeacherId' => '12222222-2222-4222-8222-222222222222']);
    $assert(false, 'Cross-school responsible teacher is rejected.');
} catch (ApiException $exception) {
    $assert($exception->errorCode === 'VALIDATION_FAILED', 'Cross-school responsible teacher is a validation failure.');
}
$actualTitle = $pdo->query("SELECT title FROM activities WHERE id='{$activityId}'")->fetchColumn();
$actualResponsible = $pdo->query("SELECT responsibleTeacherId FROM activity_details WHERE activityId='{$activityId}'")->fetchColumn();
$assert($actualTitle === 'Robotics Lab Updated' && $actualResponsible === $teacherId, 'Child-table failure rolls back the parent update.');

$service->advanceStatus($teacherId, $activityId);
$assert($pdo->query("SELECT status FROM activities WHERE id='{$activityId}'")->fetchColumn() === 'published', 'A fully configured draft can be published without changing lifecycle during edit.');
$publishedOutbox = $pdo->query("SELECT event_type, affected_student_ids FROM learner_ai_data_outbox WHERE event_type='activity.published' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$assert(is_array($publishedOutbox) && json_decode((string) $publishedOutbox['affected_student_ids'], true) === ['student-ai-001'], 'Publishing an activity queues one learner AI refresh event for the school audience.');

$service->update($teacherId, $activityId, ['title' => 'Robotics Lab Published']);
$updatedOutbox = $pdo->query("SELECT event_type FROM learner_ai_data_outbox WHERE event_type='activity.updated' LIMIT 1")->fetchColumn();
$assert($updatedOutbox === 'activity.updated', 'Updating a published activity queues an AI refresh event in the same mutation flow.');

echo "teacher_activity_details_transaction_test: OK\n";
