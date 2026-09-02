<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require dirname(__DIR__) . '/app/learner/api/LearnerApiContext.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: STUDENT SKILL PROGRESS BAR & LABEL NORMALIZATION\n";
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
// TEST 1: Page Forwarding & Rendering (/app/student/dashboard.php)
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Dashboard Page Rendering & Skills Widget ---\n";

$stProfile = $pdo->query("
    SELECT sp.id, sp.userId, u.email, u.fullName
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    WHERE u.email = 'tamlangtu2005@gmail.com'
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

assertCondition("Student profile found (Lê Quý Tam)", (bool) $stProfile, $stProfile['email'] ?? '');

$studentId = (string) $stProfile['id'];
$studentUserId = (string) $stProfile['userId'];

if (!defined('TEST_MODE')) {
    define('TEST_MODE', true);
}

$_SESSION['user'] = ['id' => $studentUserId, 'email' => $stProfile['email'], 'role' => 'learner'];
$_SESSION['user_id'] = $studentUserId;
$_SESSION['email'] = $stProfile['email'];
$_SESSION['role'] = 'learner';
$_SESSION['logged_in'] = true;
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
include dirname(__DIR__) . '/app/student/dashboard.php';
$dashboardHtml = ob_get_clean();
$decodedHtml = html_entity_decode($dashboardHtml, ENT_QUOTES, 'UTF-8');

assertCondition("app/student/dashboard.php renders successfully (> 10KB)", strlen($dashboardHtml) > 10000);
assertCondition("Dashboard contains 'Hồ sơ kỹ năng' widget", str_contains($decodedHtml, 'Hồ sơ kỹ năng'));

// ----------------------------------------------------------------------
// TEST 2: Skill Names Normalization (No raw database slugs)
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Skill Names Formatting & Slug Elimination ---\n";

assertCondition("Zero raw 'machine_learning' in skill widget", !str_contains($decodedHtml, '<strong>machine_learning</strong>'));
assertCondition("Zero raw 'ai_machine_learning' in skill widget", !str_contains($decodedHtml, '<strong>ai_machine_learning</strong>'));
assertCondition("Zero raw 'data_analysis' in skill widget", !str_contains($decodedHtml, '<strong>data_analysis</strong>'));
assertCondition("Zero raw 'teamwork' in skill widget", !str_contains($decodedHtml, '<strong>teamwork</strong>'));

// Verify human-readable Vietnamese labels
assertCondition("Displays human-readable skill name (e.g. Lập trình Python / Kỹ năng làm việc nhóm / Trí tuệ Nhân tạo)",
    str_contains($decodedHtml, 'Lập trình Python') ||
    str_contains($decodedHtml, 'Kỹ năng làm việc nhóm') ||
    str_contains($decodedHtml, 'Trí tuệ Nhân tạo') ||
    str_contains($decodedHtml, 'AI / Machine Learning') ||
    str_contains($decodedHtml, 'Học máy')
);

// ----------------------------------------------------------------------
// TEST 3: Teamwork Skill Progress Bar & Green Color Fill (85/100)
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Skill Progress Bar Width & Color Fill ---\n";

// Check if progress bar has width style and background color
assertCondition("Progress bar markup includes explicit width: % style", str_contains($decodedHtml, 'width: ') && str_contains($decodedHtml, '%'));
assertCondition("Progress bar markup includes background-color", str_contains($decodedHtml, 'background-color: #') || str_contains($decodedHtml, 'learner-progress--'));

// ----------------------------------------------------------------------
// TEST 4: Student Profile Skills Rendering (/app/student/profile.php)
// ----------------------------------------------------------------------
echo "\n--- TEST 4: Student Profile Page Skills Section ---\n";

ob_start();
include dirname(__DIR__) . '/app/student/profile.php';
$profileHtml = ob_get_clean();
$decodedProfile = html_entity_decode($profileHtml, ENT_QUOTES, 'UTF-8');

assertCondition("app/student/profile.php renders successfully", strlen($profileHtml) > 10000);
assertCondition("Profile page skills progress bars have width & background color", str_contains($decodedProfile, 'width: ') && str_contains($decodedProfile, 'background-color:'));
assertCondition("Success/Soft skill progress bars have green color (#10B981) on profile",
    str_contains($decodedProfile, '#10B981') ||
    str_contains($decodedProfile, '#10b981') ||
    str_contains($decodedProfile, 'learner-progress--success')
);
assertCondition("assets/css/student.css exists", file_exists(dirname(__DIR__) . '/assets/css/student.css'));

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
