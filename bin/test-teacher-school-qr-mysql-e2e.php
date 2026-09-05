<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/Contracts/CheckinRepository.php';
require_once dirname(__DIR__) . '/app/learner/data/Exceptions/LearnerDataMappingException.php';
require_once dirname(__DIR__) . '/app/learner/data/Exceptions/LearnerDataQueryException.php';
require_once dirname(__DIR__) . '/app/learner/data/Support/KeyMapper.php';
require_once dirname(__DIR__) . '/app/learner/data/Support/Uuid.php';
require_once dirname(__DIR__) . '/app/learner/data/Database/AbstractDatabaseRepository.php';
require_once dirname(__DIR__) . '/app/learner/data/Database/DatabaseCheckinRepository.php';
require_once dirname(__DIR__) . '/app/learner/data/Service/LearnerCheckinService.php';

use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseCheckinRepository;
use TalentHub\Learner\Data\Service\LearnerCheckinService;
use TalentHub\Modules\School\Repository\SchoolActivityApprovalRepository;
use TalentHub\Modules\School\Service\SchoolActivityApprovalService;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Repository\TeacherQrSessionRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;
use TalentHub\Modules\Teacher\Service\TeacherQrSessionService;
use TalentHub\Support\Uuid;

$failures = 0;
$assert = static function (string $name, bool $condition) use (&$failures): void {
    if ($condition) {
        echo "[PASS] {$name}\n";
        return;
    }
    ++$failures;
    fwrite(STDERR, "[FAIL] {$name}\n");
};

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!str_starts_with($database, 'talenthub_e2e_')) {
    fwrite(STDERR, "REFUSED: test requires a talenthub_e2e_* database.\n");
    exit(2);
}
echo "DATABASE={$database}\n";

$ids = [
    'teacherUser' => Uuid::v4(),
    'teacherProfile' => Uuid::v4(),
    'schoolUser' => Uuid::v4(),
    'otherSchoolUser' => Uuid::v4(),
    'studentUser' => Uuid::v4(),
    'studentProfile' => Uuid::v4(),
    'schoolMember' => Uuid::v4(),
    'otherSchoolMember' => Uuid::v4(),
    'registration' => Uuid::v4(),
];
$activityId = null;
$qrSessionId = null;

try {
    $scope = $pdo->query(
        "SELECT s.id AS schoolId, c.id AS classId
         FROM schools s
         INNER JOIN classes c ON c.schoolId = s.id
         WHERE s.status = 'active'
         ORDER BY s.id
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if (!is_array($scope)) {
        throw new RuntimeException('A disposable school with a class is required.');
    }
    $otherSchoolId = $pdo->prepare(
        "SELECT id FROM schools WHERE status = 'active' AND id <> ? ORDER BY id LIMIT 1"
    );
    $otherSchoolId->execute([(string) $scope['schoolId']]);
    $otherSchool = $otherSchoolId->fetchColumn();
    if (!is_string($otherSchool) || $otherSchool === '') {
        throw new RuntimeException('A second disposable school is required.');
    }

    $role = $pdo->prepare('SELECT id FROM roles WHERE code = ?');
    $roleIds = [];
    foreach (['teacher', 'school', 'student'] as $code) {
        $role->execute([$code]);
        $roleIds[$code] = $role->fetchColumn();
        if (!is_string($roleIds[$code]) || $roleIds[$code] === '') {
            throw new RuntimeException("Missing canonical role: {$code}");
        }
    }

    $insertUser = $pdo->prepare(
        'INSERT INTO users (id, roleId, email, passwordHash, fullName, status)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ([
        [$ids['teacherUser'], $roleIds['teacher'], 'teacher-' . $ids['teacherUser'] . '@example.invalid', 'E2E Teacher'],
        [$ids['schoolUser'], $roleIds['school'], 'school-' . $ids['schoolUser'] . '@example.invalid', 'E2E School'],
        [$ids['otherSchoolUser'], $roleIds['school'], 'school-' . $ids['otherSchoolUser'] . '@example.invalid', 'E2E Other School'],
        [$ids['studentUser'], $roleIds['student'], 'student-' . $ids['studentUser'] . '@example.invalid', 'E2E Student'],
    ] as [$userId, $roleId, $email, $name]) {
        $insertUser->execute([$userId, $roleId, $email, password_hash(Uuid::v4(), PASSWORD_DEFAULT), $name, 'active']);
    }

    $pdo->prepare(
        'INSERT INTO teacher_profiles (id, userId, schoolId, isSchoolAdmin)
         VALUES (?, ?, ?, 0)'
    )->execute([$ids['teacherProfile'], $ids['teacherUser'], $scope['schoolId']]);
    $insertMember = $pdo->prepare(
        'INSERT INTO school_members (id, schoolId, userId, memberRole) VALUES (?, ?, ?, ?)'
    );
    $insertMember->execute([$ids['schoolMember'], $scope['schoolId'], $ids['schoolUser'], 'admin']);
    $insertMember->execute([$ids['otherSchoolMember'], $otherSchool, $ids['otherSchoolUser'], 'admin']);
    $pdo->prepare(
        'INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$ids['studentProfile'], $ids['studentUser'], $scope['classId'], '2008-01-01', '0000000000', 'active']);

    $teacher = new TeacherActivityService(new TeacherActivityRepository($pdo));
    $school = new SchoolActivityApprovalService(new SchoolActivityApprovalRepository($pdo));
    $start = new DateTimeImmutable('+2 days', new DateTimeZone('UTC'));
    $end = $start->modify('+2 hours');
    $activityId = $teacher->create($ids['teacherProfile'], (string) $scope['schoolId'], [
        'title' => 'E2E activity ' . substr($ids['teacherProfile'], 0, 8),
        'category' => 'E2E',
        'startAt' => $start,
        'endAt' => $end,
        'capacity' => 20,
        'summary' => 'Initial E2E activity summary',
        'locationName' => 'E2E room',
    ]);
    $teacher->update($ids['teacherProfile'], $activityId, [
        'title' => 'E2E activity ' . substr($ids['teacherProfile'], 0, 8),
        'category' => 'E2E',
        'startAt' => $start,
        'endAt' => $end,
        'capacity' => 25,
        'summary' => 'Updated E2E activity summary',
        'locationName' => 'Updated E2E room',
    ]);
    $created = $teacher->find($ids['teacherProfile'], $activityId);
    $assert('Teacher creates and edits a draft', is_array($created)
        && $created['status'] === 'draft'
        && $created['approvalStatus'] === 'draft'
        && $created['summary'] === 'Updated E2E activity summary');

    $selfPublishBlocked = false;
    try {
        $teacher->advanceStatus($ids['teacherProfile'], $activityId);
    } catch (ApiException) {
        $selfPublishBlocked = true;
    }
    $assert('Teacher cannot self-publish a draft', $selfPublishBlocked);
    $assert('Student visibility excludes draft', (int) $pdo->query(
        "SELECT COUNT(*) FROM activities WHERE id = " . $pdo->quote($activityId)
        . " AND status IN ('published','ongoing') AND approvalStatus = 'approved'"
    )->fetchColumn() === 0);

    $teacher->submitForApproval($ids['teacherProfile'], $activityId);
    $pending = $teacher->find($ids['teacherProfile'], $activityId);
    $assert('Teacher submission creates pending approval', is_array($pending)
        && $pending['status'] === 'draft'
        && $pending['approvalStatus'] === 'pending_school_review');
    $assert('Owning school sees the pending request', count($school->listPending($ids['schoolUser'])) === 1);
    $assert('Other school does not see the pending request', count(array_filter(
        $school->listPending($ids['otherSchoolUser']),
        static fn (array $row): bool => ($row['id'] ?? null) === $activityId
    )) === 0);

    $otherSchoolBlocked = false;
    try {
        $school->review($ids['otherSchoolUser'], $activityId, 'approve', null, Uuid::v4());
    } catch (ApiException) {
        $otherSchoolBlocked = true;
    }
    $assert('Other school cannot approve the request', $otherSchoolBlocked);

    $school->review($ids['schoolUser'], $activityId, 'reject', 'Please revise the activity.', Uuid::v4());
    $rejected = $teacher->find($ids['teacherProfile'], $activityId);
    $assert('Owning school can reject with a reason', is_array($rejected)
        && $rejected['status'] === 'draft'
        && $rejected['approvalStatus'] === 'rejected');
    $assert('Student visibility excludes rejected activity', (int) $pdo->query(
        "SELECT COUNT(*) FROM activities WHERE id = " . $pdo->quote($activityId)
        . " AND status IN ('published','ongoing') AND approvalStatus = 'approved'"
    )->fetchColumn() === 0);

    $teacher->update($ids['teacherProfile'], $activityId, [
        'title' => 'E2E activity ' . substr($ids['teacherProfile'], 0, 8),
        'category' => 'E2E',
        'startAt' => $start,
        'endAt' => $end,
        'capacity' => 25,
        'summary' => 'Revised E2E activity summary',
        'locationName' => 'Updated E2E room',
    ]);
    $teacher->submitForApproval($ids['teacherProfile'], $activityId);
    $school->review($ids['schoolUser'], $activityId, 'approve', null, Uuid::v4());
    $approved = $teacher->find($ids['teacherProfile'], $activityId);
    $assert('Owning school approval publishes the activity', is_array($approved)
        && $approved['status'] === 'published'
        && $approved['approvalStatus'] === 'approved');
    $assert('Student visibility includes approved activity', (int) $pdo->query(
        "SELECT COUNT(*) FROM activities WHERE id = " . $pdo->quote($activityId)
        . " AND status = 'published' AND approvalStatus = 'approved'"
    )->fetchColumn() === 1);

    $assert('Published advances to ongoing', $teacher->advanceStatus($ids['teacherProfile'], $activityId) === 'ongoing');
    $qr = new TeacherQrSessionService(new TeacherQrSessionRepository($pdo));
    $createdQr = $qr->create($ids['teacherUser'], $activityId, '15', '10', '1.00');
    $qrSessionId = $createdQr['sessionId'];
    $rawToken = $createdQr['rawToken'];
    $firstPage = $qr->pageData($ids['teacherUser']);
    $secondPage = $qr->pageData($ids['teacherUser']);
    $assert('QR reload does not create another session', count($firstPage['sessions']) === 1
        && count($secondPage['sessions']) === 1);

    $pdo->prepare(
        'INSERT INTO activity_registrations (id, activityId, studentId, status)
         VALUES (?, ?, ?, ?)'
    )->execute([$ids['registration'], $activityId, $ids['studentProfile'], 'approved']);
    $checkin = new LearnerCheckinService(new DatabaseCheckinRepository($pdo));
    $result = $checkin->submit($ids['studentProfile'], $ids['studentUser'], Uuid::v4(), $rawToken);
    $assert('Learner QR check-in is confirmed once', ($result['status'] ?? null) === 'confirmed'
        && $rawToken === null
        && (int) $pdo->query(
            "SELECT COUNT(*) FROM checkins WHERE registrationId = " . $pdo->quote($ids['registration'])
        )->fetchColumn() === 1);

    $qr->revoke($ids['teacherUser'], $qrSessionId);
    $assert('Teacher can revoke the active QR session', (string) $pdo->query(
        "SELECT status FROM activity_qr_sessions WHERE id = " . $pdo->quote($qrSessionId)
    )->fetchColumn() === 'revoked');
    $assert('Ongoing advances to completed', $teacher->advanceStatus($ids['teacherProfile'], $activityId) === 'completed');
    $assert('Completed advances to archived', $teacher->advanceStatus($ids['teacherProfile'], $activityId) === 'archived');
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    ++$failures;
} finally {
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "CLEANUP_DATABASE={$database}\n";
    if (!str_starts_with($database, 'talenthub_e2e_')) {
        fwrite(STDERR, "REFUSED cleanup outside disposable database.\n");
        exit(3);
    }
    if ($ids['registration'] !== '') {
        $pdo->prepare(
            'DELETE el FROM experience_logs el
             INNER JOIN checkins c ON c.id = el.checkinId
             WHERE c.registrationId = ?'
        )->execute([$ids['registration']]);
        $pdo->prepare('DELETE FROM checkins WHERE registrationId = ?')->execute([$ids['registration']]);
        $pdo->prepare('DELETE FROM activity_registrations WHERE id = ?')->execute([$ids['registration']]);
    }
    if (is_string($qrSessionId)) {
        $pdo->prepare('DELETE FROM activity_qr_sessions WHERE id = ?')->execute([$qrSessionId]);
    }
    if (is_string($activityId)) {
        $pdo->prepare('DELETE FROM activity_experience_policies WHERE activityId = ?')->execute([$activityId]);
        $pdo->prepare('DELETE FROM activity_details WHERE activityId = ?')->execute([$activityId]);
        $pdo->prepare('DELETE FROM activities WHERE id = ?')->execute([$activityId]);
    }
    $deleteNotification = $pdo->prepare('DELETE FROM notifications WHERE userId = ?');
    foreach (['studentUser', 'teacherUser', 'schoolUser', 'otherSchoolUser'] as $key) {
        $deleteNotification->execute([$ids[$key]]);
    }
    $pdo->prepare('DELETE FROM student_badges WHERE studentId = ?')->execute([$ids['studentProfile']]);
    $deleteUser = $pdo->prepare('DELETE FROM users WHERE id = ?');
    foreach (['studentUser', 'teacherUser', 'schoolUser', 'otherSchoolUser'] as $key) {
        $deleteUser->execute([$ids[$key]]);
    }
    echo "CLEANUP_OK\n";
}

echo "Summary: {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
