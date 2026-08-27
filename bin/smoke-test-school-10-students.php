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
echo " SMOKE TEST: KIỂM THỬ DANH SÁCH 10 SINH VIÊN TẠI SCHOOL PORTAL\n";
echo "======================================================================\n\n";

$authRepo = new AuthRepository($pdo);
$authService = new AuthService($authRepo);

// Step 1: Login as School Admin
echo "[Step 1] Logging in as School Admin (Ban Đào tạo BTEC FPT)...\n";
$schoolUser = $authService->login(['email' => 'school@talenthub.local', 'password' => '123456'], RequestId::make(null));
$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = SessionManager::sessionNameForRole('school');
$schoolSession = new SessionManager($sessionConfig);
$schoolSession->start();
$schoolSession->login($schoolUser);
echo " -> Login OK: {$schoolUser['fullName']}\n";

// Step 2: Render All Students (Page 1)
echo "\n[Step 2] Testing School Students page (All 10 students)...\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/school/students.php';
unset($_GET['classId']);
$_GET['perPage'] = 25;
$_GET['page'] = 1;

ob_start();
include dirname(__DIR__) . '/app/school/students.php';
$html = ob_get_clean();

echo " -> Total loaded in PHP: " . count($students) . " students.\n";
if (count($students) !== 10) {
    echo " -> FAILED: Expected 10 students, got " . count($students) . "!\n";
    exit(1);
}

if (strpos($html, '<strong>10 sinh viên</strong> (trang 1)') === false) {
    echo " -> FAILED: Subtitle '10 sinh viên (trang 1)' not found in HTML!\n";
    exit(1);
}

$expectedNames = [
    'Vũ Đức Anh', 'Trần Minh Đức', 'Nguyễn Hoàng Nam', 'Lê Thị Thu Thảo', 'Phạm Gia Bảo',
    'Đặng Ngọc Mai', 'Bùi Quốc Tuấn', 'Hồ Thanh Trúc', 'Dương Nhật Huy', 'Lê Minh Quân'
];

foreach ($expectedNames as $name) {
    if (strpos($html, $name) === false) {
        echo " -> FAILED: Student '{$name}' not found in rendered table HTML!\n";
        exit(1);
    }
}
echo " -> SUCCESS: All 10 students found in table HTML with subtitle '10 sinh viên (trang 1)'!\n";

// Step 3: Test Filter by Class BTEC-AI-2026A
echo "\n[Step 3] Testing Filter by Class BTEC-AI-2026A...\n";
$_GET['classId'] = 'a1e2894b-2386-5404-9695-78a78f5a60d3';
ob_start();
include dirname(__DIR__) . '/app/school/students.php';
$htmlAi = ob_get_clean();

echo " -> Total loaded in BTEC-AI-2026A: " . count($students) . " students.\n";
if (count($students) !== 5) {
    echo " -> FAILED: Expected 5 students in BTEC-AI-2026A, got " . count($students) . "!\n";
    exit(1);
}
if (strpos($htmlAi, '<strong>5 sinh viên</strong> (trong lớp / chuyên ngành đã chọn)') === false) {
    echo " -> FAILED: Class filter subtitle not found in HTML!\n";
    exit(1);
}
echo " -> SUCCESS: Class BTEC-AI-2026A filtered exactly 5 students!\n";

// Step 4: Test Filter by Class BTEC-SE-2026A
echo "\n[Step 4] Testing Filter by Class BTEC-SE-2026A...\n";
$_GET['classId'] = 'bc0be670-12fd-545b-a70f-3ebf8ce3fad7';
ob_start();
include dirname(__DIR__) . '/app/school/students.php';
$htmlSe = ob_get_clean();

echo " -> Total loaded in BTEC-SE-2026A: " . count($students) . " students.\n";
if (count($students) !== 5) {
    echo " -> FAILED: Expected 5 students in BTEC-SE-2026A, got " . count($students) . "!\n";
    exit(1);
}
echo " -> SUCCESS: Class BTEC-SE-2026A filtered exactly 5 students!\n";

// Step 5: Test Student Login for Vũ Đức Anh
echo "\n[Step 5] Testing Student Login (vuducanh@student.btec.edu.vn / 123456)...\n";
$stLogin = $authService->login(['email' => 'vuducanh@student.btec.edu.vn', 'password' => '123456'], RequestId::make(null));
if (!$stLogin || $stLogin['fullName'] !== 'Vũ Đức Anh') {
    echo " -> FAILED: Student login failed!\n";
    exit(1);
}
echo " -> Login OK: {$stLogin['fullName']} ({$stLogin['email']})\n";

echo "\n======================================================================\n";
echo " ALL 10-STUDENTS VERIFICATION TESTS PASSED 100%!\n";
echo "======================================================================\n";
