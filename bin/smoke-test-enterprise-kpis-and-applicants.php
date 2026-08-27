<?php
declare(strict_types=1);

define('TEST_MODE', true);

require __DIR__ . '/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Support\Id\RequestId;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " SMOKE TEST: KIỂM THỬ KPI DASHBOARD DOANH NGHIỆP & QUẢN LÝ ỨNG VIÊN\n";
echo "======================================================================\n\n";

$authRepo = new AuthRepository($pdo);
$authService = new AuthService($authRepo);

// Step 1: Login as Enterprise (fpt@talenthub.local)
echo "[Step 1] Logging in as Enterprise (fpt@talenthub.local)...\n";
$entUser = $authService->login(['email' => 'fpt@talenthub.local', 'password' => '123456'], RequestId::make(null));
$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = SessionManager::sessionNameForRole('enterprise');
$entSession = new SessionManager($sessionConfig);
$entSession->start();
$entSession->login($entUser);
echo " -> Login OK: {$entUser['fullName']}\n";

// Step 2: Render Enterprise Dashboard (app/enterprise/index.php)
echo "\n[Step 2] Testing Dashboard Enterprise (app/enterprise/index.php)...\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/enterprise/index.php';

ob_start();
include dirname(__DIR__) . '/app/enterprise/index.php';
$dashHtml = ob_get_clean();

$kpiApplicants = null;
$kpiPassRate = null;
foreach ($kpis as $kpi) {
    if ($kpi['id'] === 'talents') {
        $kpiApplicants = $kpi;
    } elseif ($kpi['id'] === 'pass_rate') {
        $kpiPassRate = $kpi;
    }
}

echo " -> KPI 'Hồ sơ ứng tuyển': Value = '{$kpiApplicants['value']}', Change = '{$kpiApplicants['change']}'\n";
echo " -> KPI 'Tỷ lệ tuyển dụng': Value = '{$kpiPassRate['value']}', Change = '{$kpiPassRate['change']}'\n";

if ((int)$kpiApplicants['value'] < 1) {
    echo " -> FAILED: KPI 'Hồ sơ ứng tuyển' must be >= 1, got {$kpiApplicants['value']}!\n";
    exit(1);
}
if ($kpiPassRate['value'] !== '100%') {
    echo " -> FAILED: KPI 'Tỷ lệ tuyển dụng' must be '100%', got {$kpiPassRate['value']}!\n";
    exit(1);
}
echo " -> SUCCESS: Dashboard KPIs reflect accepted candidate accurately (1 hồ sơ, 100% tuyển dụng)!\n";

// Step 3: Render Applicants Management without query param (app/enterprise/internships/applicants.php)
echo "\n[Step 3] Testing Applicants Management (app/enterprise/internships/applicants.php)...\n";
unset($_GET['postId']);
unset($_GET['post_id']);
$_SERVER['REQUEST_URI'] = '/app/enterprise/internships/applicants.php';

ob_start();
include dirname(__DIR__) . '/app/enterprise/internships/applicants.php';
$applicantsHtml = ob_get_clean();

echo " -> Tab 'Đã nhận' count: {$pipelineCounts['accepted']}\n";
echo " -> Total applicants loaded: " . count($applicants) . "\n";

if ($pipelineCounts['accepted'] < 1) {
    echo " -> FAILED: Tab 'Đã nhận' must be >= 1, got {$pipelineCounts['accepted']}!\n";
    exit(1);
}

$foundVuDucAnh = false;
foreach ($applicants as $app) {
    echo "   Candidate: {$app['name']} | Status: {$app['status']} ({$app['status_label']}) | Match: {$app['match_score']}%\n";
    if (strpos($app['name'], 'Vũ Đức Anh') !== false && $app['status'] === 'accepted') {
        $foundVuDucAnh = true;
    }
}

if (!$foundVuDucAnh) {
    echo " -> FAILED: Candidate Vũ Đức Anh with status 'accepted' not found in applicants list!\n";
    exit(1);
}
echo " -> SUCCESS: Candidate Vũ Đức Anh is in 'accepted' (Đã nhận) state and Tab 'Đã nhận' = 1!\n";

// Step 4: Render Candidates Route Alias (app/enterprise/candidates.php)
echo "\n[Step 4] Testing Candidates Route Alias (app/enterprise/candidates.php)...\n";
$_SERVER['REQUEST_URI'] = '/app/enterprise/candidates.php';

ob_start();
include dirname(__DIR__) . '/app/enterprise/candidates.php';
$candidatesHtml = ob_get_clean();

if (strpos($candidatesHtml, 'applicants-raw-data') === false) {
    echo " -> FAILED: Candidates alias did not render applicant raw data!\n";
    exit(1);
}
echo " -> SUCCESS: Candidates route alias rendered correctly!\n\n";

echo "======================================================================\n";
echo " ALL ENTERPRISE KPI & APPLICANTS MANAGEMENT TESTS PASSED 100%!\n";
echo "======================================================================\n";
