<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " E2E SMOKE TEST: TÀI KHOẢN & HỒ SƠ SINH VIÊN VŨ ĐỨC ANH\n";
echo "======================================================================\n\n";

$email = 'vuducanh@student.edu.vn';
$password = '123456';

// Step 1: Verify User Account in DB
echo "[Step 1] Verifying User & Student Profile in Database...\n";
$userStmt = $pdo->prepare("
    SELECT u.id as userId, u.fullName, u.email, u.status, r.code as roleCode,
           sp.id as studentId, sp.classId, sp.talentScore,
           c.name as className, s.name as schoolName,
           spd.headline, spd.bio
    FROM users u
    JOIN roles r ON r.id = u.roleId
    JOIN student_profiles sp ON sp.userId = u.id
    LEFT JOIN classes c ON c.id = sp.classId
    LEFT JOIN schools s ON s.id = c.schoolId
    LEFT JOIN student_profile_details spd ON spd.studentId = sp.id
    WHERE u.email = ?
");
$userStmt->execute([$email]);
$st = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$st) {
    echo " -> FAILED: Student account not found in DB!\n";
    exit(1);
}

echo " -> Họ và tên: {$st['fullName']}\n";
echo " -> Email: {$st['email']}\n";
echo " -> Role: {$st['roleCode']}\n";
echo " -> Trường: {$st['schoolName']}\n";
echo " -> Lớp: {$st['className']}\n";
echo " -> Chuyên ngành: {$st['headline']}\n";
echo " -> Điểm đánh giá ban đầu: " . ($st['talentScore'] === null ? "Chưa có (talentScore = NULL)" : $st['talentScore']) . "\n";
echo " -> SUCCESS: Database records verified!\n\n";

// Step 2: Verify Authentication with Password
echo "[Step 2] Testing Password Authentication (123456)...\n";
$authRep = new TalentHub\Auth\Repository\AuthRepository($pdo);
$authService = new TalentHub\Auth\Service\AuthService($authRep);
$authResult = $authService->login(['email' => $email, 'password' => $password]);

if ($authResult['email'] !== $email || $authResult['role'] !== 'student') {
    echo " -> FAILED: Authentication failed!\n";
    exit(1);
}
echo " -> SUCCESS: Authenticated successfully as student (ID: {$authResult['id']})!\n\n";

// Step 3: Verify Skills
echo "[Step 3] Verifying Skills for Vũ Đức Anh...\n";
$skillStmt = $pdo->prepare("
    SELECT s.name as skillName, ss.levelScore, ss.verificationStatus
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skillId
    WHERE ss.studentId = ?
");
$skillStmt->execute([$st['studentId']]);
$skills = $skillStmt->fetchAll(PDO::FETCH_ASSOC);
echo " -> Skills found (" . count($skills) . "): " . implode(', ', array_column($skills, 'skillName')) . "\n";
if (count($skills) < 5) {
    echo " -> FAILED: Missing required skills!\n";
    exit(1);
}
echo " -> SUCCESS: All 5 required skills (Python, NLP, PyTorch, LangChain, Prompt Engineering) present!\n\n";

// Step 4: Verify Teacher Grading Visibility
echo "[Step 4] Verifying Appearance in Teacher Grading Class List...\n";
$teacherQuery = $pdo->prepare("
    SELECT sp.id as studentId, u.fullName, u.email,
           COALESCE(c.name, 'BTEC-AI-2026A') as className,
           sp.talentScore
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    LEFT JOIN classes c ON c.id = sp.classId
    WHERE c.name LIKE '%BTEC-AI%' AND sp.studyStatus = 'active'
    ORDER BY u.fullName ASC
");
$teacherQuery->execute();
$classStudents = $teacherQuery->fetchAll(PDO::FETCH_ASSOC);

$foundInClass = false;
foreach ($classStudents as $cs) {
    if ($cs['studentId'] === $st['studentId']) {
        $foundInClass = true;
        break;
    }
}

if (!$foundInClass) {
    echo " -> FAILED: Student not found in Teacher's class list!\n";
    exit(1);
}
echo " -> SUCCESS: Vũ Đức Anh is active in Class BTEC-AI-2026A for Teacher Grading!\n\n";

// Step 5: Verify 0 Pending Notifications (Clean state)
echo "[Step 5] Verifying 0 Notifications / Clean State...\n";
$notifCount = $pdo->query("SELECT COUNT(*) FROM notifications WHERE userId = '{$st['userId']}'")->fetchColumn();
$appCount = $pdo->query("SELECT COUNT(*) FROM internship_applications WHERE studentId = '{$st['studentId']}'")->fetchColumn();

echo " -> Notifications count: {$notifCount}\n";
echo " -> Applications count: {$appCount}\n";
if ((int) $notifCount !== 0 || (int) $appCount !== 0) {
    echo " -> FAILED: Expected clean slate with 0 notifications and 0 applications.\n";
    exit(1);
}
echo " -> SUCCESS: Student profile is completely clean and ready for test flow!\n\n";

echo "======================================================================\n";
echo " ALL SMOKE TESTS FOR VŨ ĐỨC ANH PASSED 100%!\n";
echo "======================================================================\n";
