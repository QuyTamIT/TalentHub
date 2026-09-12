<?php
// tests/learner_project_registration_pending_test.php
declare(strict_types=1);

require_once __DIR__ . '/../bin/bootstrap.php';
require_once __DIR__ . '/../app/learner/data/bootstrap.php';

$pdo = (new \TalentHub\Database\Connection(require __DIR__ . '/../config/database.php'))->connect();
$repo = new \TalentHub\Learner\Data\Database\DatabaseProjectMembershipCommandRepository($pdo);

// Lấy 1 sinh viên và 1 dự án cùng trường để test
$stmt = $pdo->query("SELECT sp.id as studentId, p.id as projectId FROM student_profiles sp JOIN classes c ON c.id=sp.classId JOIN projects p ON p.schoolId=c.schoolId WHERE p.status='in_progress' LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($row)) {
    fwrite(STDERR, "Must find test student and project\n");
    exit(1);
}

$studentId = (string) $row['studentId'];
$projectId = (string) $row['projectId'];

// Xóa thành viên cũ nếu có để kiểm tra đăng ký mới
$del = $pdo->prepare("DELETE FROM project_members WHERE projectId = ? AND studentId = ?");
$del->execute([$projectId, $studentId]);

$result = $repo->registerPendingMember($studentId, $projectId, new DateTimeImmutable('now', new DateTimeZone('UTC')));
if (($result['status'] ?? '') !== 'pending') {
    fwrite(STDERR, "Registration must result in pending status, got: " . ($result['status'] ?? 'null') . "\n");
    exit(1);
}

// Đọc qua DatabaseProjectRepository
$readRepo = new \TalentHub\Learner\Data\Database\DatabaseProjectRepository($pdo);
$proj = $readRepo->findVisibleForStudent($studentId, $projectId);
if (!is_array($proj)) {
    fwrite(STDERR, "Project must be visible\n");
    exit(1);
}
if ($proj['membershipStatus'] !== 'pending') {
    fwrite(STDERR, "membershipStatus must be pending, got: {$proj['membershipStatus']}\n");
    exit(1);
}
if ((int)$proj['isMember'] !== 0) {
    fwrite(STDERR, "isMember must be 0 when pending, got: {$proj['isMember']}\n");
    exit(1);
}

// Kiểm tra qua learner_project_registration_submit (action layer)
require_once __DIR__ . '/../app/learner/actions/register-project.php';

$stmtUser = $pdo->prepare("SELECT u.id as userId FROM users u JOIN student_profiles sp ON sp.userId = u.id WHERE sp.id = ?");
$stmtUser->execute([$studentId]);
$userId = (string) $stmtUser->fetchColumn();

$sessionConfig = require __DIR__ . '/../config/session.php';
$session = new \TalentHub\Auth\Session\SessionManager($sessionConfig);
$_SESSION['user_id'] = $userId;
$_SESSION['role'] = 'student';
$_SESSION['status'] = 'active';
$_SESSION['fullName'] = 'Test Student';
$_SESSION['email'] = 'test@example.com';

$del->execute([$projectId, $studentId]);

$dest = learner_project_registration_submit(
    $pdo,
    $session,
    ['projectId' => $projectId, 'csrfToken' => $session->csrfToken()],
    'POST'
);

if (!str_contains($dest, 'registered=1')) {
    fwrite(STDERR, "Submission must redirect with registered=1, got: {$dest}\n");
    exit(1);
}

$checkMember = $pdo->prepare("SELECT status FROM project_members WHERE projectId = ? AND studentId = ?");
$checkMember->execute([$projectId, $studentId]);
$status = (string) $checkMember->fetchColumn();
if ($status !== 'pending') {
    fwrite(STDERR, "Database status must be pending after action submit, got: {$status}\n");
    exit(1);
}

echo "Task 2 learner registration pending test: PASS\n";
