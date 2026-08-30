<?php

declare(strict_types=1);

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseProjectMembershipCommandRepository;
use TalentHub\Learner\Data\Database\DatabaseProjectRepository;

$root = dirname(__DIR__);
require_once $root . '/bin/bootstrap.php';
require_once $root . '/app/learner/data/bootstrap.php';

function learner_project_registration_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

function learner_project_registration_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(<<<'SQL'
CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT NOT NULL, studyStatus TEXT NOT NULL);
CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT NOT NULL);
CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, schoolId TEXT NOT NULL);
CREATE TABLE projects (
    id TEXT PRIMARY KEY, schoolId TEXT, mentorTeacherId TEXT, title TEXT NOT NULL,
    category TEXT NOT NULL, description TEXT, fundingGoal NUMERIC, projectUrl TEXT,
    startAt TEXT, endAt TEXT, status TEXT NOT NULL, createdAt TEXT, updatedAt TEXT
);
CREATE TABLE project_members (
    id TEXT PRIMARY KEY, projectId TEXT NOT NULL, studentId TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'member', status TEXT NOT NULL DEFAULT 'active',
    joinedAt TEXT, leftAt TEXT, createdAt TEXT, updatedAt TEXT
);
CREATE UNIQUE INDEX uq_project_members_student ON project_members (projectId, studentId);
CREATE TABLE learner_ai_data_outbox (
    id TEXT PRIMARY KEY, aggregate_type TEXT NOT NULL, aggregate_id TEXT NOT NULL,
    tenant_id TEXT, event_type TEXT NOT NULL, aggregate_version INTEGER NOT NULL,
    payload_hash TEXT NOT NULL, affected_student_ids TEXT NOT NULL,
    delivery_status TEXT NOT NULL, occurred_at TEXT NOT NULL
);
CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE project_sponsorships (
    id TEXT PRIMARY KEY, enterpriseId TEXT NOT NULL, projectId TEXT NOT NULL,
    amount NUMERIC NOT NULL, currency TEXT NOT NULL, status TEXT NOT NULL,
    note TEXT, createdAt TEXT
);
SQL);

    return $pdo;
}

$pdo = learner_project_registration_fixture();

$schoolId = '22222222-2222-4222-8222-222222222222';
$otherSchoolId = '33333333-3333-4333-8333-333333333333';
$inactiveSchoolId = 'a2222222-2222-4222-8222-222222222222';
$studentId = '11111111-1111-4111-8111-111111111111';
$inactiveStudentId = 'b1111111-1111-4111-8111-111111111111';
$projectId = '44444444-4444-4444-8444-444444444444';
$draftProjectId = '55555555-5555-4555-8555-555555555555';
$completedProjectId = '66666666-6666-4666-8666-666666666666';
$archivedProjectId = '76666666-6666-4666-8666-666666666666';
$crossSchoolProjectId = '77777777-7777-4777-8777-777777777777';
$inactiveSchoolProjectId = '87777777-7777-4777-8777-777777777777';

$pdo->exec("
    INSERT INTO schools VALUES
        ('{$schoolId}', 'FPT Polytechnic', 'active'),
        ('{$otherSchoolId}', 'Trường khác', 'active'),
        ('{$inactiveSchoolId}', 'Trường ngừng hoạt động', 'inactive')
");
$pdo->exec("INSERT INTO classes VALUES ('class-1', '{$schoolId}'), ('class-other', '{$otherSchoolId}'), ('class-inactive', '{$inactiveSchoolId}')");
$pdo->exec("
    INSERT INTO student_profiles VALUES
        ('{$studentId}', 'class-1', 'active'),
        ('{$inactiveStudentId}', 'class-1', 'graduated'),
        ('c1111111-1111-4111-8111-111111111111', 'class-1', 'active'),
        ('d1111111-1111-4111-8111-111111111111', 'class-1', 'active')
");
$insertProject = $pdo->prepare('INSERT INTO projects VALUES (?, ?, NULL, ?, ?, NULL, NULL, NULL, NULL, NULL, ?, ?, ?)');
$insertProject->execute([$projectId, $schoolId, 'EcoSmart AI', 'career_technical', 'in_progress', '2026-08-01', '2026-08-30']);
$insertProject->execute([$draftProjectId, $schoolId, 'Bản nháp', 'career_technical', 'draft', '2026-08-01', '2026-08-29']);
$insertProject->execute([$completedProjectId, $schoolId, 'Đã hoàn thành', 'career_technical', 'completed', '2026-08-01', '2026-08-28']);
$insertProject->execute([$archivedProjectId, $schoolId, 'Đã lưu trữ', 'career_technical', 'archived', '2026-08-01', '2026-08-27']);
$insertProject->execute([$crossSchoolProjectId, $otherSchoolId, 'Dự án trường khác', 'career_business', 'in_progress', '2026-08-01', '2026-08-26']);
$insertProject->execute([$inactiveSchoolProjectId, $inactiveSchoolId, 'Dự án trường ngừng hoạt động', 'career_business', 'in_progress', '2026-08-01', '2026-08-25']);

$now = new DateTimeImmutable('2026-08-30 08:30:00.000000', new DateTimeZone('UTC'));
$repository = new DatabaseProjectMembershipCommandRepository($pdo);

// 1. First-time same-school registration creates exactly one active member row.
$membership = $repository->registerActiveMember($studentId, $projectId, $now);
learner_project_registration_assert(($membership['status'] ?? '') === 'active', 'first registration returns active status');
learner_project_registration_assert(($membership['role'] ?? '') === 'member', 'first registration uses the member role');
learner_project_registration_assert(($membership['projectId'] ?? '') === $projectId, 'membership references the registered project');
learner_project_registration_assert(($membership['studentId'] ?? '') === $studentId, 'membership references the session-resolved student');
learner_project_registration_assert(is_string($membership['id'] ?? null) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $membership['id']) === 1, 'membership id is a UUID');
learner_project_registration_assert(str_starts_with((string) $membership['joinedAt'], '2026-08-30 08:30:00'), 'membership joinedAt uses the request clock');
learner_project_registration_assert(str_starts_with((string) $membership['createdAt'], '2026-08-30 08:30:00'), 'membership createdAt uses the request clock');
learner_project_registration_assert(str_starts_with((string) $membership['updatedAt'], '2026-08-30 08:30:00'), 'membership updatedAt uses the request clock');
learner_project_registration_assert(!array_key_exists('leftAt', $membership) || $membership['leftAt'] === null, 'new membership has no leftAt');

$storedCount = (int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE projectId = '{$projectId}' AND studentId = '{$studentId}'")->fetchColumn();
learner_project_registration_assert($storedCount === 1, 'exactly one membership row is stored');
$outboxRow = $pdo->query("SELECT aggregate_type, aggregate_id, event_type, affected_student_ids, delivery_status FROM learner_ai_data_outbox")->fetch();
learner_project_registration_assert(($outboxRow['aggregate_type'] ?? '') === 'project_membership', 'registration publishes the membership aggregate to the AI outbox');
learner_project_registration_assert(($outboxRow['aggregate_id'] ?? '') === ($membership['id'] ?? ''), 'outbox event references the created membership');
learner_project_registration_assert(($outboxRow['event_type'] ?? '') === 'project.membership_updated', 'registration publishes the canonical membership event type');
learner_project_registration_assert(($outboxRow['delivery_status'] ?? '') === 'pending', 'registration outbox event is pending delivery');
learner_project_registration_assert(json_decode((string) ($outboxRow['affected_student_ids'] ?? ''), true) === [$studentId], 'registration outbox event targets the learner');

// 2. Repeat registration is idempotent and returns the same membership.
$repeat = $repository->registerActiveMember($studentId, $projectId, $now->modify('+1 minute'));
learner_project_registration_assert(($repeat['id'] ?? '') === ($membership['id'] ?? ''), 'repeat registration returns the existing membership id');
learner_project_registration_assert(($repeat['status'] ?? '') === 'active', 'repeat registration stays active');
$repeatCount = (int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE projectId = '{$projectId}' AND studentId = '{$studentId}'")->fetchColumn();
learner_project_registration_assert($repeatCount === 1, 'repeat registration does not duplicate the membership row');
learner_project_registration_assert((int) $pdo->query('SELECT COUNT(*) FROM learner_ai_data_outbox')->fetchColumn() === 1, 'idempotent repeat does not publish a duplicate mutation event');

// 3. A left membership reactivates as an active member.
$pdo->exec("INSERT INTO project_members VALUES ('member-left', '{$projectId}', 'c1111111-1111-4111-8111-111111111111', 'lead', 'left', '2026-07-01 00:00:00', '2026-07-15 00:00:00', '2026-07-01 00:00:00', '2026-07-15 00:00:00')");
$leftStudentId = 'c1111111-1111-4111-8111-111111111111';
$reactivated = $repository->registerActiveMember($leftStudentId, $projectId, $now);
learner_project_registration_assert(($reactivated['id'] ?? '') === 'member-left', 'reactivation reuses the existing membership row');
learner_project_registration_assert(($reactivated['status'] ?? '') === 'active', 'reactivation restores active status');
learner_project_registration_assert(($reactivated['role'] ?? '') === 'member', 'reactivation restores the member role');
learner_project_registration_assert(($reactivated['leftAt'] ?? null) === null, 'reactivation clears leftAt');
learner_project_registration_assert(str_starts_with((string) $reactivated['joinedAt'], '2026-08-30 08:30:00'), 'reactivation refreshes joinedAt');
learner_project_registration_assert(str_starts_with((string) $reactivated['updatedAt'], '2026-08-30 08:30:00'), 'reactivation refreshes updatedAt');
$reactivatedRow = $pdo->query("SELECT * FROM project_members WHERE id = 'member-left'")->fetch();
learner_project_registration_assert(($reactivatedRow['createdAt'] ?? '') === '2026-07-01 00:00:00', 'reactivation preserves createdAt');
learner_project_registration_assert((int) $pdo->query('SELECT COUNT(*) FROM learner_ai_data_outbox')->fetchColumn() === 2, 'left-member reactivation publishes one mutation event');

// 4. A removed membership reactivates as an active member.
$removedStudentId = 'd1111111-1111-4111-8111-111111111111';
$pdo->exec("INSERT INTO project_members VALUES ('member-removed', '{$projectId}', '{$removedStudentId}', 'member', 'removed', '2026-07-02 00:00:00', '2026-07-16 00:00:00', '2026-07-02 00:00:00', '2026-07-16 00:00:00')");
$removedReactivated = $repository->registerActiveMember($removedStudentId, $projectId, $now);
learner_project_registration_assert(($removedReactivated['id'] ?? '') === 'member-removed', 'removed membership reuses the existing row');
learner_project_registration_assert(($removedReactivated['status'] ?? '') === 'active', 'removed membership reactivates to active');
learner_project_registration_assert(($removedReactivated['leftAt'] ?? null) === null, 'removed membership clears leftAt');

// 5. Another school's project cannot be joined.
$denied = false;
try {
    $repository->registerActiveMember($studentId, $crossSchoolProjectId, $now);
} catch (ApiException $exception) {
    $denied = $exception->status === 404;
}
learner_project_registration_assert($denied, "cross-school registration is rejected as unavailable");
$crossCount = (int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE projectId = '{$crossSchoolProjectId}'")->fetchColumn();
learner_project_registration_assert($crossCount === 0, 'cross-school registration creates no membership');

// 6. Non-in_progress projects cannot be joined.
foreach ([$draftProjectId, $completedProjectId, $archivedProjectId] as $unavailableProjectId) {
    $rejected = false;
    try {
        $repository->registerActiveMember($studentId, $unavailableProjectId, $now);
    } catch (ApiException $exception) {
        $rejected = $exception->status === 404;
    }
    learner_project_registration_assert($rejected, "project {$unavailableProjectId} cannot be joined");
    $unavailableCount = (int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE projectId = '{$unavailableProjectId}'")->fetchColumn();
    learner_project_registration_assert($unavailableCount === 0, "project {$unavailableProjectId} creates no membership");
}

// 7. An inactive learner profile cannot join.
$inactiveRejected = false;
try {
    $repository->registerActiveMember($inactiveStudentId, $projectId, $now);
} catch (ApiException $exception) {
    $inactiveRejected = $exception->status === 404;
}
learner_project_registration_assert($inactiveRejected, 'inactive learner profile cannot join');
$inactiveCount = (int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE studentId = '{$inactiveStudentId}'")->fetchColumn();
learner_project_registration_assert($inactiveCount === 0, 'inactive learner profile creates no membership');

// 8. A project at an inactive school cannot be joined.
$inactiveSchoolRejected = false;
try {
    $repository->registerActiveMember($studentId, $inactiveSchoolProjectId, $now);
} catch (ApiException $exception) {
    $inactiveSchoolRejected = $exception->status === 404;
}
learner_project_registration_assert($inactiveSchoolRejected, 'project at inactive school cannot be joined');
$inactiveSchoolCount = (int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE projectId = '{$inactiveSchoolProjectId}'")->fetchColumn();
learner_project_registration_assert($inactiveSchoolCount === 0, 'inactive school project creates no membership');

// 9. Detail read state identifies an existing active member.
$readRepository = new DatabaseProjectRepository($pdo);
$activeMembership = $readRepository->findActiveMembershipForStudent($studentId, $projectId);
learner_project_registration_assert(is_array($activeMembership) && ($activeMembership['status'] ?? '') === 'active', 'detail read state identifies the active member');
learner_project_registration_assert(($activeMembership['project_id'] ?? '') === $projectId, 'active membership read state references the project');
learner_project_registration_assert($readRepository->findActiveMembershipForStudent($studentId, $crossSchoolProjectId) === null, 'cross-school membership read state is null');
learner_project_registration_assert($readRepository->findActiveMembershipForStudent($leftStudentId, $draftProjectId) === null, 'membership for another project read state is null');
$pdo->exec("UPDATE project_members SET status = 'left' WHERE id = 'member-left'");
learner_project_registration_assert($readRepository->findActiveMembershipForStudent($leftStudentId, $projectId) === null, 'left membership is not an active member');

echo "learner_project_registration_test: OK\n";
