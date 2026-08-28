<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolDashboardService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: TEACHER SEEDER & MENTOR DROPDOWN FIX\n";
echo "======================================================================\n\n";

$passed = 0;
$failed = 0;

function assertCondition(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}" . ($detail ? " ({$detail})" : '') . "\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " ({$detail})" : '') . "\n";
        $failed++;
    }
}

// ----------------------------------------------------------------------
// TEST 1: Database Seed Validation (5 Diverse Teachers)
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Database Seed Validation (5 Diverse Teachers) ---\n";

$expectedTeachers = [
    'ThS. Nguyễn Văn Hùng' => 'Kỹ thuật phần mềm & Trí tuệ nhân tạo (AI)',
    'TS. Trần Hoàng Nam' => 'Khoa học dữ liệu & Học máy (Machine Learning)',
    'ThS. Lê Thị Mai Lan' => 'Phát triển Ứng dụng Web & Điện toán đám mây',
    'TS. Phạm Quốc Bảo' => 'An toàn thông tin & Mạng máy tính',
    'ThS. Đỗ Phương Thảo' => 'Thiết kế trải nghiệm người dùng (UI/UX) & Đồ họa',
];

$btecSchoolId = $pdo->query("SELECT id FROM schools WHERE name LIKE '%BTEC%' LIMIT 1")->fetchColumn();
assertCondition("BTEC FPT school found", !empty($btecSchoolId), "School ID: {$btecSchoolId}");

$repo = new SchoolRepository($pdo);
$teachers = $repo->listTeachers($btecSchoolId);

assertCondition("listTeachers returns at least 5 teachers for BTEC FPT", count($teachers) >= 5, "Count: " . count($teachers));

$foundTeachers = [];
foreach ($teachers as $t) {
    $foundTeachers[$t['fullName']] = $t['specialization'];
}

foreach ($expectedTeachers as $name => $spec) {
    $exists = isset($foundTeachers[$name]);
    $specMatches = $exists && (stripos($foundTeachers[$name], explode('&', $spec)[0]) !== false || $foundTeachers[$name] === $spec);
    assertCondition("Teacher '{$name}' exists in BTEC FPT", $exists);
    assertCondition("Teacher '{$name}' specialization matches '{$spec}'", $specMatches, "Actual: " . ($foundTeachers[$name] ?? 'N/A'));
}

// ----------------------------------------------------------------------
// TEST 2: Deduplication Validation (0 Duplicate Names in listTeachers)
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Deduplication Validation ---\n";

$nameCounts = [];
foreach ($teachers as $t) {
    $n = $t['fullName'];
    $nameCounts[$n] = ($nameCounts[$n] ?? 0) + 1;
}

$duplicateNames = [];
foreach ($nameCounts as $n => $cnt) {
    if ($cnt > 1) {
        $duplicateNames[] = "{$n} ({$cnt} times)";
    }
}

assertCondition("Zero duplicate teacher names in listTeachers()", empty($duplicateNames), !empty($duplicateNames) ? implode(', ', $duplicateNames) : 'All unique');

// ----------------------------------------------------------------------
// TEST 3: Web Page Rendering & Mentor Dropdown Formatting
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Page Rendering & Mentor Dropdown Formatting ---\n";

$schoolUserId = '31000000-0000-4000-8000-000000000001';
$schoolUserEmail = 'btec@school.edu.vn';

if (!defined('TEST_MODE')) {
    define('TEST_MODE', true);
}

$_SESSION['user'] = ['id' => $schoolUserId, 'email' => $schoolUserEmail, 'role' => 'school'];
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
include dirname(__DIR__) . '/app/school/internships.php';
$html = ob_get_clean();

$decodedHtml = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

assertCondition("app/school/internships.php renders 200 OK", strlen($html) > 5000);
assertCondition("Dropdown contains 'ThS. Nguyễn Văn Hùng - Kỹ thuật phần mềm & Trí tuệ nhân tạo (AI)'", str_contains($decodedHtml, 'ThS. Nguyễn Văn Hùng - Kỹ thuật phần mềm & Trí tuệ nhân tạo (AI)'));
assertCondition("Dropdown contains 'TS. Trần Hoàng Nam - Khoa học dữ liệu & Học máy (Machine Learning)'", str_contains($decodedHtml, 'TS. Trần Hoàng Nam - Khoa học dữ liệu & Học máy (Machine Learning)'));
assertCondition("Dropdown contains 'ThS. Lê Thị Mai Lan - Phát triển Ứng dụng Web & Điện toán đám mây'", str_contains($decodedHtml, 'ThS. Lê Thị Mai Lan - Phát triển Ứng dụng Web & Điện toán đám mây'));
assertCondition("Dropdown contains 'TS. Phạm Quốc Bảo - An toàn thông tin & Mạng máy tính'", str_contains($decodedHtml, 'TS. Phạm Quốc Bảo - An toàn thông tin & Mạng máy tính'));
assertCondition("Dropdown contains 'ThS. Đỗ Phương Thảo - Thiết kế trải nghiệm người dùng (UI/UX) & Đồ họa'", str_contains($decodedHtml, 'ThS. Đỗ Phương Thảo - Thiết kế trải nghiệm người dùng (UI/UX) & Đồ họa'));

// Count occurrences of option containing ThS. Nguyễn Văn Hùng per select
preg_match_all('/<option[^>]*>\s*ThS\.\s*Nguyễn Văn Hùng[^<]*<\/option>/u', $html, $hungMatches);
$hungCount = count($hungMatches[0]);

// Each application row has 1 select dropdown. So if there are N application selects, hung appears N times (1 per select)
$selectCount = substr_count($html, 'class="school-mentor-select"');
assertCondition("Each dropdown contains exactly 1 option for ThS. Nguyễn Văn Hùng", $selectCount === 0 || $hungCount === $selectCount, "Hung options: {$hungCount}, Selects: {$selectCount}");

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
