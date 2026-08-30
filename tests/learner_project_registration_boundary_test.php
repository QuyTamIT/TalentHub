<?php

declare(strict_types=1);

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Http\ApiException;

$root = dirname(__DIR__);
require_once $root . '/bin/bootstrap.php';
require_once $root . '/app/learner/actions/register-project.php';

function learner_project_boundary_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

function learner_project_boundary_fixture(): array
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(<<<'SQL'
CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT NOT NULL);
CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, classId TEXT NOT NULL, studyStatus TEXT NOT NULL);
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
SQL);

    $schoolId = '22222222-2222-4222-8222-222222222222';
    $otherSchoolId = '33333333-3333-4333-8333-333333333333';
    $userId = '99999999-9999-4999-8999-999999999999';
    $otherUserId = '99999999-9999-4999-8999-999999999998';
    $studentId = '11111111-1111-4111-8111-111111111111';
    $otherStudentId = '11111111-1111-4111-8111-111111111112';
    $projectId = '44444444-4444-4444-8444-444444444444';
    $crossSchoolProjectId = '77777777-7777-4777-8777-777777777777';

    $pdo->exec("INSERT INTO schools VALUES ('{$schoolId}', 'FPT Polytechnic', 'active'), ('{$otherSchoolId}', 'Trường khác', 'active')");
    $pdo->exec("INSERT INTO classes VALUES ('class-1', '{$schoolId}'), ('class-other', '{$otherSchoolId}')");
    $pdo->exec("INSERT INTO users VALUES ('{$userId}', 'Nguyễn Văn A'), ('{$otherUserId}', 'Nguyễn Văn B')");
    $pdo->exec("INSERT INTO student_profiles VALUES ('{$studentId}', '{$userId}', 'class-1', 'active'), ('{$otherStudentId}', '{$otherUserId}', 'class-1', 'active')");
    $pdo->exec("INSERT INTO projects VALUES ('{$projectId}', '{$schoolId}', NULL, 'EcoSmart AI', 'career_technical', NULL, NULL, NULL, NULL, NULL, 'in_progress', '2026-08-01', '2026-08-30')");
    $pdo->exec("INSERT INTO projects VALUES ('{$crossSchoolProjectId}', '{$otherSchoolId}', NULL, 'Dự án trường khác', 'career_business', NULL, NULL, NULL, NULL, NULL, 'in_progress', '2026-08-01', '2026-08-29')");

    return [
        'pdo' => $pdo,
        'school_id' => $schoolId,
        'user_id' => $userId,
        'student_id' => $studentId,
        'other_student_id' => $otherStudentId,
        'project_id' => $projectId,
        'cross_school_project_id' => $crossSchoolProjectId,
    ];
}

function learner_project_boundary_session(string $role = 'student', string $userId = '99999999-9999-4999-8999-999999999999', ?string $csrfToken = 'boundary-csrf-token'): SessionManager
{
    $session = new SessionManager(['name' => SessionManager::SESSION_STUDENT, 'lifetime' => 3600, 'secure' => false, 'sameSite' => 'Lax', 'savePath' => '']);
    $_SESSION = [
        'user' => ['id' => $userId, 'role' => $role, 'email' => 'student@talenthub.local', 'fullName' => 'Nguyễn Văn A', 'status' => 'active'],
        'csrfToken' => $csrfToken ?? 'boundary-csrf-token',
        'csrf_token' => $csrfToken ?? 'boundary-csrf-token',
    ];
    return $session;
}

$fixture = learner_project_boundary_fixture();
$pdo = $fixture['pdo'];
$now = new DateTimeImmutable('2026-08-30 09:00:00', new DateTimeZone('UTC'));

// 1. Non-POST methods are rejected at the page boundary.
$getRejected = false;
try {
    learner_project_registration_submit($pdo, learner_project_boundary_session(), ['projectId' => $fixture['project_id'], 'csrfToken' => 'boundary-csrf-token'], 'GET', $now);
} catch (ApiException $exception) {
    $getRejected = $exception->status === 405;
}
learner_project_boundary_assert($getRejected, 'GET requests are rejected with 405');

// 2. A missing CSRF token is rejected and creates no membership.
$csrfRejected = false;
try {
    learner_project_registration_submit($pdo, learner_project_boundary_session(), ['projectId' => $fixture['project_id']], 'POST', $now);
} catch (ApiException $exception) {
    $csrfRejected = $exception->status === 403;
}
learner_project_boundary_assert($csrfRejected, 'missing CSRF token is rejected with 403');

// 3. A forged CSRF token is rejected and creates no membership.
$forgedRejected = false;
try {
    learner_project_registration_submit($pdo, learner_project_boundary_session(), ['projectId' => $fixture['project_id'], 'csrfToken' => 'attacker-token'], 'POST', $now);
} catch (ApiException $exception) {
    $forgedRejected = $exception->status === 403;
}
learner_project_boundary_assert($forgedRejected, 'forged CSRF token is rejected with 403');

// 4. Non-student roles cannot register.
$roleRejected = false;
try {
    learner_project_registration_submit($pdo, learner_project_boundary_session(role: 'enterprise'), ['projectId' => $fixture['project_id'], 'csrfToken' => 'boundary-csrf-token'], 'POST', $now);
} catch (ApiException $exception) {
    $roleRejected = $exception->status === 403;
}
learner_project_boundary_assert($roleRejected, 'non-student roles are rejected with 403');

// 5. A student session without a learner profile cannot register.
$profileMissing = false;
try {
    learner_project_registration_submit($pdo, learner_project_boundary_session(userId: '88888888-8888-4888-8888-888888888888'), ['projectId' => $fixture['project_id'], 'csrfToken' => 'boundary-csrf-token'], 'POST', $now);
} catch (ApiException $exception) {
    $profileMissing = $exception->status === 403;
}
learner_project_boundary_assert($profileMissing, 'session without a learner profile is rejected with 403');

// 6. A successful POST returns a PRG destination back to the internal detail URL.
$destination = learner_project_registration_submit($pdo, learner_project_boundary_session(), ['projectId' => $fixture['project_id'], 'csrfToken' => 'boundary-csrf-token'], 'POST', $now);
learner_project_boundary_assert(str_contains($destination, '/app/learner/project.php?id=' . $fixture['project_id']), 'success redirects back to the same internal detail URL');
learner_project_boundary_assert(str_contains($destination, 'registered=1'), 'success destination carries the registered flag');
$memberCount = (int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE projectId = '{$fixture['project_id']}' AND studentId = '{$fixture['student_id']}'")->fetchColumn();
learner_project_boundary_assert($memberCount === 1, 'successful boundary POST creates one membership');

// 7. Learner identity is always resolved from the session, never from input.
$destination = learner_project_registration_submit($pdo, learner_project_boundary_session(), [
    'projectId' => $fixture['project_id'],
    'csrfToken' => 'boundary-csrf-token',
    'studentId' => $fixture['other_student_id'],
], 'POST', $now);
$forgedStudentCount = (int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE studentId = '{$fixture['other_student_id']}'")->fetchColumn();
learner_project_boundary_assert($forgedStudentCount === 0, 'a posted studentId is never used as the membership owner');
$sessionStudentCount = (int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE projectId = '{$fixture['project_id']}' AND studentId = '{$fixture['student_id']}'")->fetchColumn();
learner_project_boundary_assert($sessionStudentCount === 1, 'the session student owns the single membership');

// 8. A forged project id redirects back with a failure flag and creates nothing.
$destination = learner_project_registration_submit($pdo, learner_project_boundary_session(), ['projectId' => '50000000-0000-4000-8000-000000000001', 'csrfToken' => 'boundary-csrf-token'], 'POST', $now);
learner_project_boundary_assert(str_contains($destination, 'project.php?id=50000000-0000-4000-8000-000000000001'), 'forged project id redirects back to its detail URL');
learner_project_boundary_assert(str_contains($destination, 'register=failed'), 'forged project id destination carries the failure flag');
$forgedCount = (int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE projectId = '50000000-0000-4000-8000-000000000001'")->fetchColumn();
learner_project_boundary_assert($forgedCount === 0, 'forged project id creates no membership');

// 9. Another school's project redirects with a failure flag and creates nothing.
$destination = learner_project_registration_submit($pdo, learner_project_boundary_session(), ['projectId' => $fixture['cross_school_project_id'], 'csrfToken' => 'boundary-csrf-token'], 'POST', $now);
learner_project_boundary_assert(str_contains($destination, 'register=failed'), 'cross-school project destination carries the failure flag');
$crossCount = (int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE projectId = '{$fixture['cross_school_project_id']}'")->fetchColumn();
learner_project_boundary_assert($crossCount === 0, 'cross-school project creates no membership');

// 10. An invalid project identifier redirects with a failure flag and creates nothing.
$destination = learner_project_registration_submit($pdo, learner_project_boundary_session(), ['projectId' => 'not-a-uuid', 'csrfToken' => 'boundary-csrf-token'], 'POST', $now);
learner_project_boundary_assert(str_contains($destination, 'register=failed'), 'invalid project identifier destination carries the failure flag');

// 11. CSRF token may arrive via the standard header.
$headerDestination = learner_project_registration_submit($pdo, learner_project_boundary_session(), ['projectId' => $fixture['project_id']], 'POST', $now, 'boundary-csrf-token');
learner_project_boundary_assert(str_contains($headerDestination, 'registered=1'), 'header CSRF token is accepted at the boundary');

// 12. Production bootstrap failures retain the readable PRG error path.
$actionSource = (string) file_get_contents($root . '/app/learner/actions/register-project.php');
learner_project_boundary_assert(
    preg_match('/catch \(ApiException \$exception\).*?catch \(Throwable\).*?learner_project_registration_failure_destination/s', $actionSource) === 1,
    'unexpected production failures redirect through the safe registration failure destination'
);

echo "learner_project_registration_boundary_test: OK\n";
