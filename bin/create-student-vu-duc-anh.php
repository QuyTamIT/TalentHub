<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " TẠO MỚI TÀI KHOẢN & HỒ SƠ SINH VIÊN: VŨ ĐỨC ANH\n";
echo "======================================================================\n\n";

$email = 'vuducanh@student.edu.vn';
$altEmail = 'anhvd@talenthub.local';
$fullName = 'Vũ Đức Anh';
$password = '123456';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$userId = '24000000-0000-4000-8000-000000000019';
$studentId = '24000000-0000-4000-8000-000000000009';
$roleId = 'c8b7001c-6496-5dcf-ab0a-dd384c1ba673'; // Student role

// Step 1: Verify Class BTEC-AI-2026A
$classStmt = $pdo->prepare("SELECT c.*, s.name as schoolName FROM classes c JOIN schools s ON s.id = c.schoolId WHERE c.name = 'BTEC-AI-2026A'");
$classStmt->execute();
$class = $classStmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    echo "ERROR: Class BTEC-AI-2026A not found!\n";
    exit(1);
}
$classId = (string) $class['id'];
echo "[Step 1] Class: {$class['name']} (ID: {$classId}) | School: {$class['schoolName']}\n";

// Step 2: Clean up any old duplicate account with this email if exists
$delUserStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR email = ?");
$delUserStmt->execute([$email, $altEmail]);
$existingUserIds = $delUserStmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($existingUserIds as $oldUid) {
    $oldStId = $pdo->query("SELECT id FROM student_profiles WHERE userId = '{$oldUid}'")->fetchColumn();
    if ($oldStId) {
        $pdo->exec("DELETE FROM notifications WHERE userId = '{$oldUid}'");
        $pdo->exec("DELETE FROM internship_applications WHERE studentId = '{$oldStId}'");
        $pdo->exec("DELETE FROM student_skills WHERE studentId = '{$oldStId}'");
        $pdo->exec("DELETE FROM student_profile_details WHERE studentId = '{$oldStId}'");
        $pdo->exec("DELETE FROM student_profiles WHERE id = '{$oldStId}'");
    }
    $pdo->exec("DELETE FROM users WHERE id = '{$oldUid}'");
}

// Step 3: Insert User Record
$userInsert = $pdo->prepare("
    INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt)
    VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
");
$userInsert->execute([$userId, $roleId, $email, $passwordHash, $fullName]);
echo "[Step 2] Created User Account: {$email} (User ID: {$userId})\n";

// Step 4: Insert Student Profile (talentScore = NULL to wait for teacher grading)
$stInsert = $pdo->prepare("
    INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus, talentScore, createdAt, updatedAt)
    VALUES (?, ?, ?, '2005-08-15', '0938123456', 'active', NULL, NOW(), NOW())
");
$stInsert->execute([$studentId, $userId, $classId]);
echo "[Step 3] Created Student Profile: {$fullName} (Student ID: {$studentId})\n";

// Step 5: Insert Student Profile Details
$headline = 'Kỹ sư Trí tuệ Nhân tạo & Xử lý Ngôn ngữ Tự nhiên (NLP)';
$bio = 'Sinh viên chuyên ngành Trí tuệ Nhân tạo & Xử lý Ngôn ngữ Tự nhiên (NLP) - Cao đẳng Quốc tế BTEC FPT. Đam mê nghiên cứu các mô hình ngôn ngữ lớn (LLM), Prompt Engineering, xây dựng ứng dụng với LangChain và PyTorch.';
$location = 'Hà Nội';
$avatarUrl = '/assets/images/avatars/student-male-2.png';

$detailInsert = $pdo->prepare("
    INSERT INTO student_profile_details (studentId, location, bio, avatarUrl, headline, createdAt, updatedAt)
    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
");
$detailInsert->execute([$studentId, $location, $bio, $avatarUrl, $headline]);
echo "[Step 4] Created Profile Details: {$headline}\n";

// Step 6: Ensure Skills Exist in `skills` Table
$requiredSkills = [
    ['id' => '22000000-952a-427e-8406-1ad950b1f892', 'code' => 'python', 'name' => 'Python', 'category' => 'technical'],
    ['id' => 'fd427644-23fe-425d-aee1-80820baa4b76', 'code' => 'pytorch', 'name' => 'PyTorch', 'category' => 'technical'],
    ['id' => '80000000-0000-4000-8000-000000000051', 'code' => 'nlp', 'name' => 'NLP', 'category' => 'technical'],
    ['id' => '80000000-0000-4000-8000-000000000052', 'code' => 'langchain', 'name' => 'LangChain', 'category' => 'technical'],
    ['id' => '80000000-0000-4000-8000-000000000053', 'code' => 'prompt_engineering', 'name' => 'Prompt Engineering', 'category' => 'technical'],
];

$upsertSkill = $pdo->prepare("
    INSERT INTO skills (id, code, name, category, status, createdAt, updatedAt)
    VALUES (:id, :code, :name, :category, 'active', NOW(), NOW())
    ON DUPLICATE KEY UPDATE name = VALUES(name), code = VALUES(code), category = VALUES(category), status = 'active', updatedAt = NOW()
");

$insertStudentSkill = $pdo->prepare("
    INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, createdAt, updatedAt)
    VALUES (:id, :studentId, :skillId, 0.00, 'self_declared', 'self_declared', NOW(), NOW())
");

foreach ($requiredSkills as $s) {
    $upsertSkill->execute([
        'id' => $s['id'],
        'code' => $s['code'],
        'name' => $s['name'],
        'category' => $s['category']
    ]);

    $insertStudentSkill->execute([
        'id' => Uuid::v4(),
        'studentId' => $studentId,
        'skillId' => $s['id']
    ]);
}
echo "[Step 5] Attached Skills: Python, NLP, PyTorch, LangChain, Prompt Engineering (Status: self_declared / initial 0%)\n";

// Step 7: Verify Authentication Test
$authRep = new TalentHub\Auth\Repository\AuthRepository($pdo);
$authService = new TalentHub\Auth\Service\AuthService($authRep);
$authResult = $authService->login(['email' => $email, 'password' => $password]);

echo "\n[Step 6] Verifying Login Authentication...\n";
if ($authResult['email'] === $email && $authResult['fullName'] === $fullName) {
    echo " -> Login Test SUCCESSFUL!\n";
} else {
    echo " -> Login Test FAILED!\n";
    exit(1);
}

echo "\n======================================================================\n";
echo " THÔNG TIN TÀI KHOẢN ĐÃ TẠO:\n";
echo "======================================================================\n";
echo " - Họ và tên: {$fullName}\n";
echo " - Email đăng nhập: {$email}\n";
echo " - Mật khẩu: {$password}\n";
echo " - Vai trò: student (Học viên / Sinh viên)\n";
echo " - Trường: {$class['schoolName']}\n";
echo " - Lớp: {$class['name']}\n";
echo " - Chuyên ngành: {$headline}\n";
echo " - Kỹ năng: Python, NLP, PyTorch, LangChain, Prompt Engineering\n";
echo " - Điểm đánh giá: Chưa chấm (talentScore = NULL)\n";
echo " - Thông báo thực tập: 0 (Sạch dữ liệu cho Doanh nghiệp gửi lời mời)\n";
echo "======================================================================\n";
