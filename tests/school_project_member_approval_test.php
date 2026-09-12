<?php
// tests/school_project_member_approval_test.php
declare(strict_types=1);

require_once __DIR__ . '/../bin/bootstrap.php';

$pdo = (new \TalentHub\Database\Connection(require __DIR__ . '/../config/database.php'))->connect();
$repo = new \TalentHub\Modules\School\Repository\SchoolProjectRepository($pdo);
$service = new \TalentHub\Modules\School\Service\SchoolProjectService($repo);

// Lấy user của trường và dự án
$stmt = $pdo->query("SELECT u.id as userId, p.id as projectId, p.schoolId FROM users u JOIN school_members sm ON sm.userId=u.id JOIN projects p ON p.schoolId=sm.schoolId LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($row)) {
    fwrite(STDERR, "Must find school user and project\n");
    exit(1);
}

$userId = (string) $row['userId'];
$projectId = (string) $row['projectId'];

// Lấy 1 sinh viên trong trường để gán pending
$stmtStudent = $pdo->prepare("SELECT sp.id FROM student_profiles sp JOIN classes c ON c.id=sp.classId WHERE c.schoolId = ? LIMIT 1");
$stmtStudent->execute([(string)$row['schoolId']]);
$studentId = (string) $stmtStudent->fetchColumn();
if ($studentId === '') {
    fwrite(STDERR, "Must find student\n");
    exit(1);
}

// Chuẩn bị bản ghi pending
$pdo->prepare("DELETE FROM project_members WHERE projectId = ? AND studentId = ?")->execute([$projectId, $studentId]);
$pdo->prepare("INSERT INTO project_members (id, projectId, studentId, role, status, joinedAt, createdAt, updatedAt) VALUES (UUID(), ?, ?, 'member', 'pending', NULL, NOW(), NOW())")->execute([$projectId, $studentId]);

// 1. Kiểm tra danh sách
$members = $service->listProjectMembers($userId, $projectId);
$found = false;
foreach ($members as $m) {
    if ($m['studentId'] === $studentId && $m['status'] === 'pending') {
        $found = true;
        break;
    }
}
if (!$found) {
    fwrite(STDERR, "Pending student must be in list\n");
    exit(1);
}

// 2. Duyệt chấp thuận (active)
$service->updateMemberStatus($userId, $projectId, $studentId, 'active');
$checkStatus = $pdo->prepare("SELECT status, joinedAt FROM project_members WHERE projectId=? AND studentId=?");
$checkStatus->execute([$projectId, $studentId]);
$memberRow = $checkStatus->fetch(PDO::FETCH_ASSOC);
if (($memberRow['status'] ?? '') !== 'active') {
    fwrite(STDERR, "Status must be updated to active, got: " . ($memberRow['status'] ?? 'null') . "\n");
    exit(1);
}
if (empty($memberRow['joinedAt'])) {
    fwrite(STDERR, "joinedAt must not be empty when approved\n");
    exit(1);
}

// 3. Từ chối (rejected)
$service->updateMemberStatus($userId, $projectId, $studentId, 'rejected');
$checkStatus->execute([$projectId, $studentId]);
$statusRejected = (string) $checkStatus->fetchColumn();
if ($statusRejected !== 'rejected') {
    fwrite(STDERR, "Status must be updated to rejected, got: {$statusRejected}\n");
    exit(1);
}

echo "Task 3 school project member approval test: PASS\n";
