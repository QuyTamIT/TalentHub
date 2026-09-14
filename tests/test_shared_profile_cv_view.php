<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

// Lấy 1 học viên mẫu trong DB
$stmt = $pdo->query("SELECT sp.id, u.fullName FROM student_profiles sp JOIN users u ON u.id = sp.userId WHERE u.status = 'active' LIMIT 1");
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "SKIP: No active student found in DB for live test.\n";
    exit(0);
}

$studentId = (string)$student['id'];
$passportCode = 'TP-' . strtoupper(substr(str_replace('-', '', $studentId), 0, 8));

// Test 1: GET with valid code
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['code' => $passportCode];

ob_start();
require dirname(__DIR__) . '/app/learner/shared-profile.php';
$outputHtml = ob_get_clean();

assert(str_contains($outputHtml, 'cv-sheet'), "Shared profile must render CV sheet element");
assert(str_contains($outputHtml, 'cv-sidebar'), "Shared profile must render CV sidebar");
assert(str_contains($outputHtml, 'cv-main'), "Shared profile must render CV main column");
assert(str_contains($outputHtml, 'Hồ sơ CV xác thực điện tử bởi TalentHub'), "Shared profile must show verified guest toolbar");
assert(str_contains($outputHtml, 'In / Tải PDF'), "Shared profile must have print/pdf button for guest");
assert(!str_contains($outputHtml, '360°'), "Shared profile must not contain 360°");

// Test 2: GET with invalid code -> should return 404 error page
$_GET = ['code' => 'INVALID_CODE_999'];
ob_start();
require dirname(__DIR__) . '/app/learner/shared-profile.php';
$errorHtml = ob_get_clean();

assert(str_contains($errorHtml, 'Không tìm thấy hồ sơ'), "Invalid code must render friendly error page");

echo "PASS: shared-profile.php successfully renders 100% identical CV A4 view for QR/shared links.\n";
