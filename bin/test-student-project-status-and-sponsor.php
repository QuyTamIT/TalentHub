<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\Student\ProfileService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: STUDENT PROJECT STATUS & SPONSOR BADGE\n";
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
// TEST 1: Database Talent Passport Repository Project Query & Sponsor Data
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Repository Projects & Sponsor Fetch ---\n";

$stProfile = $pdo->query("
    SELECT sp.id, sp.userId, u.email, u.fullName
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    WHERE u.email = 'tamlangtu2005@gmail.com'
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

assertCondition("Student profile found for Lê Quý Tam", (bool) $stProfile, $stProfile['email'] ?? '');

$studentId = (string) $stProfile['id'];
$studentUserId = (string) $stProfile['userId'];

$dbRepo = new \TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository($pdo);
$passport = $dbRepo->aggregateForStudent($studentId);

assertCondition("Passport has projects key", isset($passport['projects']), "Projects count: " . count($passport['projects'] ?? []));
assertCondition("Lê Quý Tam is member of at least 1 project", count($passport['projects'] ?? []) >= 1);

$hasSmartGarden = false;
$hasSponsor = false;
$sponsorName = '';

foreach ($passport['projects'] as $p) {
    if (str_contains((string)($p['title'] ?? ''), 'Smart Garden')) {
        $hasSmartGarden = true;
        $spName = (string)($p['sponsor_name'] ?? $p['sponsorName'] ?? '');
        if (!empty($spName)) {
            $hasSponsor = true;
            $sponsorName = $spName;
        }
    }
}

assertCondition("Project 'Smart Garden IoT' is present in student projects", $hasSmartGarden);
assertCondition("Project has sponsor name attached from project_sponsorships", $hasSponsor, "Sponsor: {$sponsorName}");

// ----------------------------------------------------------------------
// TEST 2: Student Profile Web Page Rendering (/app/student/profile.php)
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Student Profile Web Page Rendering ---\n";

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
include dirname(__DIR__) . '/app/student/profile.php';
$html = ob_get_clean();

$decodedHtml = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

assertCondition("app/student/profile.php renders successfully (> 10KB)", strlen($html) > 10000);
assertCondition("Profile section 'Dự án đã tham gia' exists", str_contains($decodedHtml, 'Dự án đã tham gia'));
assertCondition("Profile displays project 'Smart Garden IoT'", str_contains($decodedHtml, 'Smart Garden IoT'));

// Verification of status formatting:
assertCondition("Project status is localized to 'Đang thực hiện'", str_contains($decodedHtml, 'Đang thực hiện'));
assertCondition("No raw 'in_progress' string displayed in project cards", !preg_match('/<span[^>]*class="[^"]*learner-badge[^"]*"[^>]*>\s*●?\s*in_progress\s*<\/span>/i', $decodedHtml));

// Verification of enterprise sponsorship tag:
assertCondition("Profile displays 'Được bảo trợ bởi'", str_contains($decodedHtml, 'Được bảo trợ bởi'));
assertCondition("Profile mentions sponsoring enterprise name (FPT Software)", str_contains($decodedHtml, 'FPT Software'));

// ----------------------------------------------------------------------
// TEST 3: Enterprise Candidate Detail View (/app/enterprise/talents/detail.php)
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Enterprise Candidate Detail View ---\n";

$entUser = $pdo->query("SELECT u.id, u.email FROM users u WHERE u.email = 'recruiter@fpt.com' OR u.email LIKE '%enterprise%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($entUser) {
    $_SESSION['user'] = ['id' => $entUser['id'], 'email' => $entUser['email'], 'role' => 'enterprise'];
    $_GET['id'] = $studentId;
    $_SERVER['REQUEST_METHOD'] = 'GET';

    ob_start();
    include dirname(__DIR__) . '/app/enterprise/talents/detail.php';
    $entDetailHtml = ob_get_clean();
    $entDecoded = html_entity_decode($entDetailHtml, ENT_QUOTES, 'UTF-8');

    assertCondition("Enterprise talent detail page renders successfully", strlen($entDetailHtml) > 5000);
    assertCondition("Enterprise view displays project 'Smart Garden IoT'", str_contains($entDecoded, 'Smart Garden IoT'));
    assertCondition("Enterprise view displays 'Được bảo trợ bởi'", str_contains($entDecoded, 'Được bảo trợ bởi'));
} else {
    assertCondition("Enterprise user test skipped", true);
}

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
