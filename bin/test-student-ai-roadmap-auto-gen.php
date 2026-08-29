<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require dirname(__DIR__) . '/app/learner/api/LearnerApiContext.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Modules\Student\AiRoadmapService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: STUDENT AI ROADMAP AUTO-GENERATION & MATCHING\n";
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
// TEST 1: Page Forwarding & Rendering (/app/student/ai-roadmap.php)
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Page Forwarding & View Rendering ---\n";

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
include dirname(__DIR__) . '/app/student/ai-roadmap.php';
$html = ob_get_clean();

$decodedHtml = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

assertCondition("app/student/ai-roadmap.php renders successfully (> 10KB)", strlen($html) > 10000);
assertCondition("Page contains AI GỢI Ý header", str_contains($decodedHtml, 'AI GỢI Ý'));
assertCondition("Page contains Lộ trình phát triển 90 ngày", str_contains($decodedHtml, 'LỘ TRÌNH PHÁT TRIỂN 90 NGÀY'));
assertCondition("Page contains ĐỘ PHÙ HỢP CÔNG VIỆC TỪ AI widget", str_contains($decodedHtml, 'ĐỘ PHÙ HỢP CÔNG VIỆC TỪ AI'));
assertCondition("Page contains KỸ NĂNG CẦN BỔ SUNG TIẾP THEO widget", str_contains($decodedHtml, 'KỸ NĂNG CẦN BỔ SUNG TIẾP THEO'));

// ----------------------------------------------------------------------
// TEST 2: AiRoadmapService Job Matching & Skill Gap Analysis
// ----------------------------------------------------------------------
echo "\n--- TEST 2: AiRoadmapService Logic & Calculations ---\n";

$aiRoadmapService = new AiRoadmapService($pdo);

$matching = $aiRoadmapService->calculateJobMatching($studentId);
assertCondition("Job matching returns at least 3 roles", count($matching) >= 3, "Total roles: " . count($matching));

$aiEngineer = null;
foreach ($matching as $m) {
    if (str_contains($m['role'], 'AI Engineer')) {
        $aiEngineer = $m;
        break;
    }
}
assertCondition("Job matching includes AI Engineer", $aiEngineer !== null);
assertCondition("AI Engineer match percent >= 80%", ($aiEngineer['match_percent'] ?? 0) >= 80, "Percent: " . ($aiEngineer['match_percent'] ?? 0) . "%");
assertCondition("AI Engineer has rationale points based on tests", count($aiEngineer['reasons'] ?? []) >= 2);

$skillGaps = $aiRoadmapService->calculateSkillGaps($studentId);
assertCondition("Skill gaps return at least 2 categories", count($skillGaps) >= 2);
assertCondition("Skill gaps contain recommended skills to learn", !empty($skillGaps[0]['recommended_skills']));

// ----------------------------------------------------------------------
// TEST 3: Auto-generation for a new student on initial access
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Auto-generation for student without existing roadmap ---\n";

$vuDucAnh = $pdo->query("
    SELECT sp.id, sp.userId, u.email, u.fullName
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    WHERE u.fullName = 'Vũ Đức Anh'
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if ($vuDucAnh) {
    $vdaId = (string) $vuDucAnh['id'];
    $vdaUserId = (string) $vuDucAnh['userId'];

    $_SESSION['user'] = ['id' => $vdaUserId, 'email' => $vuDucAnh['email'], 'role' => 'learner'];
    $_SESSION['user_id'] = $vdaUserId;
    $_SESSION['email'] = $vuDucAnh['email'];

    $context = LearnerApiContext::fromGlobals();
    $roadmapService = $context->roadmapService($vdaId);

    $vdaRoadmap = $roadmapService->latest($vdaId);
    assertCondition("Vũ Đức Anh roadmap latest() returns non-null", $vdaRoadmap !== null);
    assertCondition("Vũ Đức Anh roadmap state is ready_model or fallback_rule", in_array($vdaRoadmap['state'] ?? '', ['ready_model', 'fallback_rule'], true), "State: " . ($vdaRoadmap['state'] ?? ''));
    assertCondition("Vũ Đức Anh roadmap has 3 phases", count($vdaRoadmap['phases'] ?? []) === 3);
    assertCondition("Vũ Đức Anh roadmap has job_matching enriched", !empty($vdaRoadmap['job_matching']));
    assertCondition("Vũ Đức Anh roadmap has skill_gaps enriched", !empty($vdaRoadmap['skill_gaps']));
} else {
    assertCondition("Vũ Đức Anh test skipped (profile not found)", true);
}

// ----------------------------------------------------------------------
// TEST 4: POST /app/learner/api/v1/ai-roadmap.php (Refresh / Regenerate)
// ----------------------------------------------------------------------
echo "\n--- TEST 4: POST API Refresh / Regenerate ---\n";

$_SESSION['user'] = ['id' => $studentUserId, 'email' => $stProfile['email'], 'role' => 'learner'];
$_SESSION['user_id'] = $studentUserId;
$_SESSION['email'] = $stProfile['email'];
$_SESSION['role'] = 'learner';

$context = LearnerApiContext::fromGlobals();
$service = $context->roadmapService($studentId);

$refreshed = $service->generate($studentId, 'req-refresh-test', 'idemp-refresh-' . time(), true);
assertCondition("Refresh generate() returns ready roadmap", in_array($refreshed['state'] ?? '', ['ready_model', 'fallback_rule'], true), "State: " . ($refreshed['state'] ?? ''));
assertCondition("Refreshed roadmap has phases", count($refreshed['phases'] ?? []) >= 1);
assertCondition("Refreshed roadmap has job matching", !empty($refreshed['job_matching']));

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
