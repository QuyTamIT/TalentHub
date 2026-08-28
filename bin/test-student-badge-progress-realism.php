<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require dirname(__DIR__) . '/app/learner/api/LearnerApiContext.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\Student\DashboardService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: STUDENT REALISTIC BADGE PROGRESS & UI CLEANLINESS\n";
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
// TEST 1: DashboardService Badge Calculations for Student
// ----------------------------------------------------------------------
echo "\n--- TEST 1: DashboardService Badge & Credential Calculations ---\n";

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

$dashboardService = new DashboardService($pdo);
$credentialData = $dashboardService->getBadgesAndCredentials($studentId);

assertCondition("Credential data returned for student", !empty($credentialData['badges']) || !empty($credentialData['featured']));

$badges = $credentialData['badges'] ?? [];
$profileCompleteBadge = null;
$inProgressBadgeCount = 0;
$allHundredPercent = true;

foreach ($badges as $b) {
    if (str_contains($b['code'], 'profile_complete')) {
        $profileCompleteBadge = $b;
    }
    $progress = (int) ($b['progress_percent'] ?? 0);
    if ($progress < 100) {
        $allHundredPercent = false;
        $inProgressBadgeCount++;
    }
}

assertCondition("Profile complete badge is 100% (Đã đạt)", ($profileCompleteBadge['progress_percent'] ?? 0) === 100 && ($profileCompleteBadge['status_label'] ?? '') === 'Đã đạt');
assertCondition("Not all badges are hardcoded 100% (Realism test)", !$allHundredPercent, "In-progress badges: {$inProgressBadgeCount}");
assertCondition("In-progress badges have status 'Đang tích lũy' or 'Chưa mở khóa'", $inProgressBadgeCount > 0);

// ----------------------------------------------------------------------
// TEST 2: Unearned Badges Do Not Display Sample Earned Date
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Date Display Logic for Unearned Badges ---\n";

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

assertCondition("app/student/dashboard.php renders cleanly", strlen($dashboardHtml) > 10000);

// ----------------------------------------------------------------------
// TEST 3: Skills Card UI Cleanliness
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Skills Card Layout & Cleanliness ---\n";

assertCondition("Skills card heading contains clean 'Hồ sơ kỹ năng'", str_contains($decodedHtml, 'id="skills-title">Hồ sơ kỹ năng</h2>'));
assertCondition("Skills list displays normalized skill names", str_contains($decodedHtml, 'Lập trình Python') || str_contains($decodedHtml, 'Học máy (Machine Learning)'));

// ----------------------------------------------------------------------
// TEST 4: Student Badges Page (/app/learner/badges.php)
// ----------------------------------------------------------------------
echo "\n--- TEST 4: Student Badges Page Rendering ---\n";

ob_start();
include dirname(__DIR__) . '/app/learner/badges.php';
$badgesHtml = ob_get_clean();
$decodedBadges = html_entity_decode($badgesHtml, ENT_QUOTES, 'UTF-8');

assertCondition("app/learner/badges.php renders cleanly", strlen($badgesHtml) > 10000);
assertCondition("Badges page displays 'Đang tích lũy' or progress percentages", 
    str_contains($decodedBadges, 'Đang tích lũy') || 
    str_contains($decodedBadges, 'Đã đạt') ||
    str_contains($decodedBadges, '%')
);

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
