<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " SMOKE TEST: VERIFY ZERO RESET TRÊN PORTAL DOANH NGHIỆP\n";
echo "======================================================================\n\n";

// Step 1: Check Database is Empty of Applications
echo "[Step 1] Verifying 0 records in database tables...\n";
$appCount = (int) $pdo->query("SELECT COUNT(*) FROM internship_applications")->fetchColumn();
$notifCount = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE notificationType LIKE '%internship%'")->fetchColumn();

echo " -> Total internship_applications: {$appCount}\n";
echo " -> Total invitation notifications: {$notifCount}\n";

if ($appCount !== 0 || $notifCount !== 0) {
    echo " -> FAILED: Database records are not 0!\n";
    exit(1);
}
echo " -> SUCCESS: Database applications and invitations are completely 0.\n\n";

// Step 2: Test Enterprise Dashboard Page Rendering
echo "[Step 2] Testing Enterprise Dashboard Overview Rendering...\n";
$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = TalentHub\Auth\Session\SessionManager::SESSION_ENTERPRISE;
$session = new TalentHub\Auth\Session\SessionManager($sessionConfig);
$session->start();
$session->login([
    'id' => '31000000-0000-4000-8000-000000000015',
    'email' => 'fpt@talenthub.local',
    'fullName' => 'FPT Software',
    'role' => 'enterprise',
    'status' => 'active'
]);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/enterprise/index.php';

ob_start();
include dirname(__DIR__) . '/app/enterprise/index.php';
$dashHtml = ob_get_clean();

// Check KPI Card Values
echo " -> KPI Applicants Value: '{$kpiApplicantsVal}'\n";
echo " -> KPI Pass Rate Value: '{$kpiPassRateVal}'\n";

if ($kpiApplicantsVal !== '0') {
    echo " -> FAILED: KPI Applicants is not '0'!\n";
    exit(1);
}
if ($kpiPassRateVal !== '0%') {
    echo " -> FAILED: KPI Pass Rate is not '0%'!\n";
    exit(1);
}
echo " -> SUCCESS: Dashboard KPI counters are 0 and 0%!\n\n";

// Step 3: Test Enterprise Applicants Management Page Rendering
echo "[Step 3] Testing Enterprise Applicants Management Page...\n";
$activePostId = $pdo->query("SELECT id FROM internship_posts WHERE enterpriseId = '31000000-0000-4000-8000-000000000015' LIMIT 1")->fetchColumn();
$_GET['postId'] = $activePostId;
$_SERVER['REQUEST_URI'] = '/app/enterprise/internships/applicants.php?postId=' . $activePostId;

ob_start();
include dirname(__DIR__) . '/app/enterprise/internships/applicants.php';
$appHtml = ob_get_clean();

echo " -> Applicants Count loaded: " . count($applicants) . "\n";
echo " -> Pipeline Counts: " . json_encode($pipelineCounts) . "\n";

if (count($applicants) !== 0) {
    echo " -> FAILED: Expected 0 applicants, got " . count($applicants) . "!\n";
    exit(1);
}

if ($pipelineCounts['all'] !== 0 || $pipelineCounts['submitted'] !== 0 || $pipelineCounts['accepted'] !== 0) {
    echo " -> FAILED: Pipeline counts are not 0!\n";
    exit(1);
}

$expectedEmptyTitle = 'Chưa có ứng viên nào ứng tuyển hoặc được tiếp nhận cho vị trí này';
if (strpos($appHtml, $expectedEmptyTitle) === false) {
    echo " -> FAILED: Expected empty state text '{$expectedEmptyTitle}' not found in HTML!\n";
    exit(1);
}

echo " -> SUCCESS: Applicants page displays 0 counts for all pipeline tabs and clean professional Empty State!\n\n";

echo "======================================================================\n";
echo " ALL ZERO RESET TESTS PASSED 100%!\n";
echo "======================================================================\n";
