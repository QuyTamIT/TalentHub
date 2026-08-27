<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Support\Id\RequestId;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " SMOKE TEST: KIỂM THỬ XÓA SẠCH VỀ 0 VÀ THÊM MỚI VŨ ĐỨC ANH TẠI SCHOOL\n";
echo "======================================================================\n\n";

$btecSchoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783';

// Step 1: Ensure BTEC FPT has 0 students in database
echo "[Step 1] Cleaning BTEC FPT students to test 0 state...\n";
$btecClasses = $pdo->query("SELECT id FROM classes WHERE schoolId = '{$btecSchoolId}'")->fetchAll(PDO::FETCH_COLUMN);
if (!empty($btecClasses)) {
    $classIdsStr = "'" . implode("','", $btecClasses) . "'";
    $pdo->exec("DELETE FROM student_profile_details WHERE studentId IN (SELECT id FROM student_profiles WHERE classId IN ({$classIdsStr}))");
    $pdo->exec("DELETE FROM student_profiles WHERE classId IN ({$classIdsStr})");
    $pdo->exec("DELETE FROM users WHERE email = 'vuducanh@student.edu.vn' OR email LIKE '%@student.btec%'");
}

$cnt = (int) $pdo->query("
    SELECT COUNT(*) 
    FROM student_profiles sp 
    JOIN classes c ON c.id = sp.classId 
    WHERE c.schoolId = '{$btecSchoolId}'
")->fetchColumn();
echo " -> Current student count in DB: {$cnt}\n";

if ($cnt !== 0) {
    echo " -> FAILED: Student count in DB is not 0!\n";
    exit(1);
}

// Step 2: Test School Students Page Rendering in Zero State
echo "\n[Step 2] Testing School Students Page in Zero State...\n";
$authRepo = new AuthRepository($pdo);
$authService = new AuthService($authRepo);
$schoolUser = $authService->login(['email' => 'school@talenthub.local', 'password' => '123456'], RequestId::make(null));

$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = SessionManager::sessionNameForRole('school');
$schoolSession = new SessionManager($sessionConfig);
$schoolSession->start();
$schoolSession->login($schoolUser);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/school/students.php';
unset($_GET['classId']);

ob_start();
include dirname(__DIR__) . '/app/school/students.php';
$studentsHtml = ob_get_clean();

echo " -> Students count loaded in PHP: " . count($students) . "\n";
if (count($students) !== 0) {
    echo " -> FAILED: Expected 0 students, got " . count($students) . "!\n";
    exit(1);
}

$expectedEmptyTitle = 'Chưa có sinh viên nào trong danh sách. Vui lòng thêm mới hoặc Import file Excel.';
if (strpos($studentsHtml, $expectedEmptyTitle) === false) {
    echo " -> FAILED: Expected empty state text '{$expectedEmptyTitle}' not found!\n";
    exit(1);
}

if (strpos($studentsHtml, '<strong>0 sinh viên</strong>') === false) {
    echo " -> FAILED: Subtitle '0 sinh viên' not found!\n";
    exit(1);
}
echo " -> SUCCESS: School Students page displays '0 sinh viên' and professional empty state!\n";

// Step 3: Test Adding New Student "Vũ Đức Anh" via School Dashboard Service
echo "\n[Step 3] Testing Adding Student 'Vũ Đức Anh'...\n";
$classId = $pdo->query("SELECT id FROM classes WHERE schoolId = '{$btecSchoolId}' AND name = 'BTEC-AI-2026A' LIMIT 1")->fetchColumn();
if (!$classId) {
    echo " -> FAILED: Class BTEC-AI-2026A not found!\n";
    exit(1);
}

$schoolContext = (new TalentHub\Bootstrap\SchoolAppContext())->boot();
$schoolService = $schoolContext['service'];

$created = $schoolService->createStudent($schoolUser['id'], [
    'fullName' => 'Vũ Đức Anh',
    'email' => 'vuducanh@student.edu.vn',
    'classId' => $classId,
    'phone' => '0912345678',
    'dateOfBirth' => '2004-05-15',
]);

echo " -> Successfully created student: Vũ Đức Anh (Profile ID: {$created['id']})\n";

// Set password for Vũ Đức Anh and activate account for full testing flow
$pwdHash = password_hash('123456', PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET passwordHash = ?, status = 'active' WHERE email = 'vuducanh@student.edu.vn'")->execute([$pwdHash]);
$pdo->prepare("UPDATE student_profiles SET studyStatus = 'active' WHERE id = ?")->execute([$created['id']]);

// Step 4: Re-render School Students page and verify exactly 1 student
echo "\n[Step 4] Re-rendering School Students Page with 1 Student...\n";
ob_start();
include dirname(__DIR__) . '/app/school/students.php';
$studentsHtml1 = ob_get_clean();

echo " -> Students count loaded in PHP: " . count($students) . "\n";
if (count($students) !== 1) {
    echo " -> FAILED: Expected exactly 1 student, got " . count($students) . "!\n";
    exit(1);
}

if ($students[0]['fullName'] !== 'Vũ Đức Anh' || $students[0]['email'] !== 'vuducanh@student.edu.vn') {
    echo " -> FAILED: Student data mismatch!\n";
    exit(1);
}

if (strpos($studentsHtml1, '<strong>1 sinh viên</strong>') === false) {
    echo " -> FAILED: Subtitle '1 sinh viên' not found!\n";
    exit(1);
}
echo " -> SUCCESS: School Students page correctly displays exactly 1 student 'Vũ Đức Anh'!\n\n";

echo "======================================================================\n";
echo " ALL ZERO-STATE AND ADD-STUDENT TESTS PASSED 100%!\n";
echo "======================================================================\n";
