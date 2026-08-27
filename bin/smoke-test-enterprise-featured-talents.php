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
echo " SMOKE TEST: KIỂM THỬ NHÂN TÀI NỔI BẬT TRÊN ENTERPRISE DASHBOARD\n";
echo "======================================================================\n\n";

$authRepo = new AuthRepository($pdo);
$authService = new AuthService($authRepo);

// Step 1: Login as Enterprise (FPT Software)
echo "[Step 1] Logging in as Enterprise (fpt@talenthub.local)...\n";
$entUser = $authService->login(['email' => 'fpt@talenthub.local', 'password' => '123456'], RequestId::make(null));
$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = SessionManager::sessionNameForRole('enterprise');
$entSession = new SessionManager($sessionConfig);
$entSession->start();
$entSession->login($entUser);
echo " -> Login OK: {$entUser['fullName']}\n";

// Step 2: Render Enterprise Dashboard
echo "\n[Step 2] Rendering Enterprise Dashboard (app/enterprise/index.php)...\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/enterprise/index.php';

ob_start();
include dirname(__DIR__) . '/app/enterprise/index.php';
$html = ob_get_clean();

echo " -> Featured talents count loaded: " . count($featuredTalents) . "\n";
if (count($featuredTalents) !== 5) {
    echo " -> FAILED: Expected 5 featured talents, got " . count($featuredTalents) . "!\n";
    exit(1);
}

// Step 3: Verify Candidate Names and School
echo "\n[Step 3] Verifying Featured Candidates Data...\n";
$forbiddenMockNames = ['Nguyễn Văn An', 'Lê Hoàng Nam', 'Lê Hoàng Yến Nhi', 'Hoàng Thị Mai Linh', 'Phạm Quốc Bảo'];
foreach ($forbiddenMockNames as $badName) {
    if (strpos($html, $badName) !== false) {
        echo " -> FAILED: Found old mock student '{$badName}' in Enterprise dashboard HTML!\n";
        exit(1);
    }
}
echo " -> Verified: No old mock candidates found.\n";

$btecFoundCount = 0;
foreach ($featuredTalents as $idx => $t) {
    echo "   [" . ($idx + 1) . "] {$t['name']} ★ {$t['talent_score']} điểm | {$t['meta_description']} (ID: {$t['id']})\n";
    if (strpos($t['school'], 'BTEC') !== false || strpos($t['meta_description'], 'BTEC') !== false) {
        $btecFoundCount++;
    }
    if (empty($t['id'])) {
        echo " -> FAILED: Candidate ID is empty!\n";
        exit(1);
    }
}

if ($btecFoundCount !== 5) {
    echo " -> FAILED: Expected all 5 candidates from BTEC FPT, got {$btecFoundCount}!\n";
    exit(1);
}
echo " -> SUCCESS: All 5 candidates are real BTEC FPT students from Database!\n";

// Step 4: Verify Candidate Detail Page Link for Top Candidate (Vũ Đức Anh)
echo "\n[Step 4] Testing Talent Detail Page for First Candidate ({$featuredTalents[0]['name']})...\n";
$topTalentId = $featuredTalents[0]['id'];
$_GET['id'] = $topTalentId;
$_SERVER['REQUEST_URI'] = '/app/enterprise/talents/detail.php?id=' . urlencode($topTalentId);

ob_start();
include dirname(__DIR__) . '/app/enterprise/talents/detail.php';
$detailHtml = ob_get_clean();

if (strpos($detailHtml, $featuredTalents[0]['name']) === false) {
    echo " -> FAILED: Candidate name not found in Talent Detail page!\n";
    exit(1);
}
if (strpos($detailHtml, 'Cao đẳng Quốc tế BTEC FPT') === false) {
    echo " -> FAILED: School name BTEC FPT not found in Talent Detail page!\n";
    exit(1);
}
echo " -> SUCCESS: Talent Detail page rendered accurately for {$featuredTalents[0]['name']}!\n\n";

echo "======================================================================\n";
echo " ALL ENTERPRISE FEATURED TALENTS TESTS PASSED 100%!\n";
echo "======================================================================\n";
