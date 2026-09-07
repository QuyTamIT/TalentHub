<?php

declare(strict_types=1);

/**
 * Integration regression for teacher submission, school review and publication.
 * Uses SQLite memory and mock notifications only: never connects to the app DB.
 * Run: php bin/test-activity-school-approval.php
 */
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/Contracts/NotificationRepository.php';
require_once dirname(__DIR__) . '/app/learner/data/Mock/MockNotificationRepository.php';
require_once dirname(__DIR__) . '/app/learner/data/Service/NotificationService.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Mock\MockNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Modules\School\Repository\SchoolActivityApprovalRepository;
use TalentHub\Modules\School\Service\SchoolActivityApprovalService;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;
use TalentHub\Support\Uuid;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "This isolated regression requires pdo_sqlite.\n");
    exit(1);
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Only the schema used by these repositories is needed in the isolated fixture.
$pdo->exec(<<<'SQL'
CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT, email TEXT);
CREATE TABLE school_members (userId TEXT PRIMARY KEY, schoolId TEXT, memberRole TEXT);
CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT, schoolId TEXT);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, studyStatus TEXT);
CREATE TABLE activities (
    id TEXT PRIMARY KEY, schoolId TEXT, createdByTeacherId TEXT, title TEXT, category TEXT,
    startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT, visibility TEXT,
    approvalStatus TEXT, approvalRequestedAt TEXT, approvedAt TEXT, approvedBy TEXT, approvalReason TEXT
);
CREATE TABLE activity_details (
    activityId TEXT PRIMARY KEY, responsibleTeacherId TEXT, audienceScope TEXT,
    displayCategory TEXT, filterCategory TEXT, summary TEXT, description TEXT,
    experienceHighlights TEXT, skillTags TEXT, eligibilityRules TEXT, benefitItems TEXT,
    locationName TEXT, locationAddress TEXT, deliveryMode TEXT, onlineMeetingUrl TEXT,
    organizerName TEXT, organizerContact TEXT, organizerEmail TEXT, organizerPhone TEXT,
    coverImageUrl TEXT, coverImageAlt TEXT, feeAmount TEXT, currency TEXT,
    targetAudience TEXT, certificateLabel TEXT, createdAt TEXT, updatedAt TEXT
);
CREATE TABLE activity_registration_policies (
    activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT,
    cancellationClosesAt TEXT, approvalMode TEXT, createdAt TEXT, updatedAt TEXT
);
CREATE TABLE activity_experience_policies (
    activityId TEXT PRIMARY KEY, confirmedHours TEXT, createdAt TEXT, updatedAt TEXT
);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT);
CREATE TABLE audit_logs (
    id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT,
    requestId TEXT, ipAddress TEXT, metadata TEXT, createdAt TEXT
);
SQL);

$ids = [];
foreach (['school', 'otherSchool', 'schoolUser', 'otherSchoolUser', 'outsider', 'teacher', 'teacherUser', 'otherTeacher', 'otherTeacherUser', 'student', 'studentUser', 'otherStudent', 'otherStudentUser', 'class', 'otherClass'] as $name) {
    $ids[$name] = Uuid::v4();
}
$insert = static function (string $table, array $row) use ($pdo): void {
    $columns = implode(',', array_keys($row));
    $placeholders = implode(',', array_fill(0, count($row), '?'));
    $pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})")->execute(array_values($row));
};
foreach (['schoolUser', 'otherSchoolUser', 'outsider', 'teacherUser', 'otherTeacherUser', 'studentUser', 'otherStudentUser'] as $name) {
    $insert('users', ['id' => $ids[$name], 'fullName' => 'Fixture ' . $name, 'email' => $name . '@example.invalid']);
}
foreach (['' => 'school', 'other' => 'otherSchool'] as $prefix => $schoolKey) {
    $teacherKey = $prefix === '' ? 'teacher' : 'otherTeacher';
    $studentKey = $prefix === '' ? 'student' : 'otherStudent';
    $classKey = $prefix === '' ? 'class' : 'otherClass';
    $insert('school_members', ['userId' => $ids[$schoolKey . 'User'], 'schoolId' => $ids[$schoolKey], 'memberRole' => 'admin']);
    $insert('teacher_profiles', ['id' => $ids[$teacherKey], 'userId' => $ids[$teacherKey . 'User'], 'schoolId' => $ids[$schoolKey]]);
    $insert('classes', ['id' => $ids[$classKey], 'schoolId' => $ids[$schoolKey]]);
    $insert('student_profiles', ['id' => $ids[$studentKey], 'userId' => $ids[$studentKey . 'User'], 'classId' => $ids[$classKey], 'studyStatus' => 'active']);
}

$notificationRepository = new MockNotificationRepository();
$notifications = new NotificationService($notificationRepository);
$teacher = new TeacherActivityService(new TeacherActivityRepository($pdo, $notifications));
$school = new SchoolActivityApprovalService(new SchoolActivityApprovalRepository($pdo, $notifications));
$passed = 0;
$failed = 0;
$assert = static function (string $name, bool $condition) use (&$passed, &$failed): void {
    if ($condition) {
        ++$passed;
        echo "[PASS] {$name}\n";
    } else {
        ++$failed;
        echo "[FAIL] {$name}\n";
    }
};
$expectError = static function (string $name, int $status, string $code, callable $action) use ($assert, $pdo): void {
    try {
        $action();
        $assert($name . ' rejects the action', false);
    } catch (ApiException $exception) {
        $assert($name . " ({$exception->status}/{$exception->errorCode})", $exception->status === $status && $exception->errorCode === $code);
    }
    $assert($name . ' leaves no open transaction', !$pdo->inTransaction());
};
$requestId = static fn (): string => strtoupper(bin2hex(random_bytes(13)));
$readActivity = static function (string $activityId) use ($pdo): array {
    $statement = $pdo->prepare('SELECT * FROM activities WHERE id=?');
    $statement->execute([$activityId]);
    return $statement->fetch() ?: [];
};
$auditCount = static function (string $activityId) use ($pdo): int {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE entityId=?');
    $statement->execute([$activityId]);
    return (int) $statement->fetchColumn();
};
$create = static function (string $title, bool $otherSchool = false) use ($teacher, $ids): string {
    $start = new DateTimeImmutable('+10 days 09:00:00', new DateTimeZone('UTC'));
    return $teacher->create($ids[$otherSchool ? 'otherTeacher' : 'teacher'], $ids[$otherSchool ? 'otherSchool' : 'school'], [
        'title' => $title, 'category' => 'Kỹ năng', 'startAt' => $start, 'endAt' => $start->modify('+3 hours'),
        'capacity' => 30, 'summary' => 'Tóm tắt hoạt động cần Nhà trường phê duyệt.',
        'description' => 'Sinh viên tham gia thực hành kỹ năng làm việc nhóm.',
        'locationName' => 'Hội trường A', 'organizerName' => 'Phòng công tác sinh viên',
        'registrationOpensAt' => $start->modify('-7 days'),
        'registrationClosesAt' => $start->modify('-1 day'),
        'cancellationClosesAt' => $start->modify('-1 day'), 'confirmedHours' => '3',
    ]);
};

echo "School activity approval integration regression (SQLite memory, mock notifications)\n";
try {
    $activityId = $create('Workshop duyệt hoạt động');
    $otherActivityId = $create('Hoạt động riêng trường khác', true);
    $assert('Teacher creates a draft awaiting submission', $readActivity($activityId)['approvalStatus'] === 'draft');
    $expectError('Publication requires school approval', 409, 'SCHOOL_APPROVAL_REQUIRED', fn () => $teacher->publish($ids['teacher'], $activityId, $requestId()));
    $expectError('Legacy advanceStatus cannot bypass school approval', 422, 'INVALID_ACTIVITY_CONFIGURATION', fn () => $teacher->advanceStatus($ids['teacher'], $activityId, $requestId()));
    $expectError('School cannot approve an unsubmitted draft', 409, 'APPROVAL_STATUS_CONFLICT', fn () => $school->review($ids['schoolUser'], $activityId, 'approve', null, $requestId()));
    $expectError('Another teacher cannot submit this activity', 404, 'RESOURCE_NOT_FOUND', fn () => $teacher->submitForSchoolReview($ids['otherTeacher'], $activityId, $requestId()));

    $submitted = $teacher->submitForSchoolReview($ids['teacher'], $activityId, $requestId());
    $assert('Submission returns the new pending status', $submitted['approvalStatus'] === 'pending_school_review');
    $assert('Submission saves request time and remains unpublished', !empty($readActivity($activityId)['approvalRequestedAt']) && $readActivity($activityId)['status'] === 'draft');
    $assert('Submission notifies its school administrator', $notifications->unreadCount($ids['schoolUser']) === 1);
    $assert('Submission does not notify another school', $notifications->unreadCount($ids['otherSchoolUser']) === 0);
    $assert('Submission does not notify students', $notifications->unreadCount($ids['studentUser']) === 0);
    $pending = $school->listPending($ids['schoolUser']);
    $assert('School sees its pending activity and teacher details', count($pending) === 1 && $pending[0]['id'] === $activityId && $pending[0]['teacherName'] === 'Fixture teacherUser');
    $assert('Other school cannot list this pending activity', $school->listPending($ids['otherSchoolUser']) === []);
    $assert('School search filters pending activities', count($school->listPending($ids['schoolUser'], 'workshop')) === 1 && $school->listPending($ids['schoolUser'], 'does not exist') === []);

    $expectError('Account without school membership cannot review', 403, 'PERMISSION_DENIED', fn () => $school->review($ids['outsider'], $activityId, 'approve', null, $requestId()));
    $expectError('Other school cannot approve this activity', 404, 'RESOURCE_NOT_FOUND', fn () => $school->review($ids['otherSchoolUser'], $activityId, 'approve', null, $requestId()));
    $expectError('Unsupported decision is rejected', 422, 'VALIDATION_FAILED', fn () => $school->review($ids['schoolUser'], $activityId, 'publish', null, $requestId()));
    $expectError('Review request ID is validated', 422, 'VALIDATION_FAILED', fn () => $school->review($ids['schoolUser'], $activityId, 'approve', null, 'invalid'));
    foreach (['request_changes', 'reject'] as $decision) {
        $expectError($decision . ' requires a nonblank reason', 422, 'VALIDATION_FAILED', fn () => $school->review($ids['schoolUser'], $activityId, $decision, '   ', $requestId()));
    }
    $expectError('Review reason length is limited', 422, 'VALIDATION_FAILED', fn () => $school->review($ids['schoolUser'], $activityId, 'reject', str_repeat('ấ', 1001), $requestId()));
    $assert('Invalid reviews preserve pending state and audit history', $readActivity($activityId)['approvalStatus'] === 'pending_school_review' && $auditCount($activityId) === 1);
    $expectError('Pending activity cannot be edited', 409, 'APPROVAL_STATUS_CONFLICT', fn () => $teacher->update($ids['teacher'], $activityId, ['title' => 'Changed while pending']));
    $expectError('Pending activity cannot be submitted again', 409, 'APPROVAL_STATUS_CONFLICT', fn () => $teacher->submitForSchoolReview($ids['teacher'], $activityId, $requestId()));
    $expectError('Pending activity cannot be published', 409, 'SCHOOL_APPROVAL_REQUIRED', fn () => $teacher->publish($ids['teacher'], $activityId, $requestId()));

    $changes = $school->review($ids['schoolUser'], $activityId, 'request_changes', '  Bổ sung kế hoạch chi tiết.  ', $requestId());
    $assert('Changes request returns its new status and trimmed reason', $changes['approvalStatus'] === 'changes_requested' && $changes['approvalReason'] === 'Bổ sung kế hoạch chi tiết.');
    $assert('Teacher receives the requested changes', $notifications->unreadCount($ids['teacherUser']) === 1 && $readActivity($activityId)['approvalReason'] === 'Bổ sung kế hoạch chi tiết.');
    $expectError('School cannot approve before teacher resubmits', 409, 'APPROVAL_STATUS_CONFLICT', fn () => $school->review($ids['schoolUser'], $activityId, 'approve', null, $requestId()));
    $teacher->update($ids['teacher'], $activityId, ['description' => 'Kế hoạch chi tiết đã được bổ sung theo yêu cầu.']);
    $assert('Teacher can edit after changes are requested', $teacher->find($ids['teacher'], $activityId)['description'] === 'Kế hoạch chi tiết đã được bổ sung theo yêu cầu.');
    $resubmitted = $teacher->submitForSchoolReview($ids['teacher'], $activityId, $requestId());
    $assert('Resubmission returns pending and clears the previous reason', $resubmitted['approvalStatus'] === 'pending_school_review' && $readActivity($activityId)['approvalReason'] === null);

    $approved = $school->review($ids['schoolUser'], $activityId, 'approve', null, $requestId());
    $assert('Approval returns the new approved status', $approved['approvalStatus'] === 'approved');
    $assert('Approval persists its reviewer and time', $readActivity($activityId)['approvedBy'] === $ids['schoolUser'] && !empty($readActivity($activityId)['approvedAt']));
    $assert('Approval clears requested changes and stays draft until teacher publishes', $readActivity($activityId)['approvalReason'] === null && $readActivity($activityId)['status'] === 'draft');
    $assert('Approval notifies the owning teacher', $notifications->unreadCount($ids['teacherUser']) === 2);
    $assert('No student notification is sent before actual publication', $notifications->unreadCount($ids['studentUser']) === 0 && $notifications->unreadCount($ids['otherStudentUser']) === 0);
    $assert('Approval moves the activity out of the pending queue', $school->listPending($ids['schoolUser']) === [] && count($school->list($ids['schoolUser'], 'approved')) === 1);
    $expectError('Approved content cannot change without review', 409, 'APPROVAL_STATUS_CONFLICT', fn () => $teacher->update($ids['teacher'], $activityId, ['title' => 'Changed after approval']));
    $expectError('Conflicting decision cannot overwrite approval', 409, 'APPROVAL_STATUS_CONFLICT', fn () => $school->review($ids['schoolUser'], $activityId, 'reject', 'Decision changed', $requestId()));
    $auditsBeforeRetry = $auditCount($activityId);
    $approvalRetry = $school->review($ids['schoolUser'], $activityId, 'approve', null, $requestId());
    $assert('Repeated approval is idempotent', $approvalRetry['approvalStatus'] === 'approved' && $auditCount($activityId) === $auditsBeforeRetry && $notifications->unreadCount($ids['teacherUser']) === 2);

    $published = $teacher->publish($ids['teacher'], $activityId, $requestId());
    $assert('Teacher can publish an approved activity', $published['status'] === 'published' && $readActivity($activityId)['status'] === 'published');
    $outbox = $pdo->query('SELECT * FROM learner_ai_data_outbox')->fetchAll();
    $assert('Publication queues learner updates only for the correct school', count($outbox) === 1 && $outbox[0]['event_type'] === 'activity.published' && json_decode($outbox[0]['affected_student_ids'], true, 512, JSON_THROW_ON_ERROR) === [$ids['student']]);
    $expectError('Repeated publication cannot advance to ongoing', 409, 'STATUS_CONFLICT', fn () => $teacher->publish($ids['teacher'], $activityId, $requestId()));

    $rejectedId = $create('Hoạt động bị từ chối');
    $teacher->submitForSchoolReview($ids['teacher'], $rejectedId, $requestId());
    $rejected = $school->review($ids['schoolUser'], $rejectedId, 'reject', 'Nội dung chưa phù hợp với kế hoạch của trường.', $requestId());
    $assert('Rejection returns and persists its decision with reason', $rejected['approvalStatus'] === 'rejected' && $readActivity($rejectedId)['approvalReason'] === 'Nội dung chưa phù hợp với kế hoạch của trường.');
    $assert('Rejected activity has no approval metadata', $readActivity($rejectedId)['approvedAt'] === null && $readActivity($rejectedId)['approvedBy'] === null);
    $expectError('Rejected activity cannot be edited', 409, 'APPROVAL_STATUS_CONFLICT', fn () => $teacher->update($ids['teacher'], $rejectedId, ['title' => 'Retry rejected content']));
    $expectError('Rejected activity cannot be resubmitted', 409, 'APPROVAL_STATUS_CONFLICT', fn () => $teacher->submitForSchoolReview($ids['teacher'], $rejectedId, $requestId()));
    $expectError('Rejected activity cannot be published', 409, 'SCHOOL_APPROVAL_REQUIRED', fn () => $teacher->publish($ids['teacher'], $rejectedId, $requestId()));
    $expectError('Rejected decision cannot be replaced by approval', 409, 'APPROVAL_STATUS_CONFLICT', fn () => $school->review($ids['schoolUser'], $rejectedId, 'approve', null, $requestId()));
    $assert('Other school activity was never changed', $readActivity($otherActivityId)['approvalStatus'] === 'draft' && $readActivity($otherActivityId)['status'] === 'draft' && $auditCount($otherActivityId) === 0);
    $assert('Every successful lifecycle transition is audited', $auditCount($activityId) === 5 && $auditCount($rejectedId) === 2);
} catch (Throwable $exception) {
    ++$failed;
    fwrite(STDERR, '[FAIL] Unexpected ' . get_class($exception) . ': ' . $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
}

echo "\nPassed: {$passed}; Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
