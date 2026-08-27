<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Support\Id\RequestId;

$config = require dirname(__DIR__) . '/config/database.php';
$pdoConn = (new Connection($config))->connect();

echo "====================================================================\n";
echo "   SECTOR-AWARE TALENT SEARCH & FILTER VERIFICATION TEST\n";
echo "====================================================================\n\n";

$allPassed = true;

function assertCheck(string $title, bool $condition, string $details = ''): void {
    global $allPassed;
    if ($condition) {
        echo "  [PASS] $title\n";
    } else {
        echo "  [FAIL] $title\n";
        if ($details) echo "         -> $details\n";
        $allPassed = false;
    }
}

// 1. Test Session Boot & HTML for Vinamilk
echo "1. Testing Vinamilk Sector-Aware Rendering...\n";

// Login as Vinamilk
$_SESSION = [];
$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = SessionManager::SESSION_ENTERPRISE;
$sessionManager = new SessionManager($sessionConfig);
$sessionManager->start();

$authRepo = new AuthRepository($pdoConn);
$authService = new AuthService($authRepo);
$vnmUser = $authService->login(['email' => 'vinamilk@talenthub.local', 'password' => '123456'], RequestId::make(null));
$sessionManager->login($vnmUser);

// Capture talents.php output for Vinamilk
ob_start();
require dirname(__DIR__) . '/app/enterprise/talents.php';
$vnmHtml = ob_get_clean();

// Assert Quick Filter Pills for Vinamilk
assertCheck("Vinamilk has [Marketing & PR] quick filter", str_contains($vnmHtml, 'data-quick-filter="marketing_pr"'));
assertCheck("Vinamilk has [Quản trị Kinh doanh] quick filter", str_contains($vnmHtml, 'data-quick-filter="biz_mgmt"'));
assertCheck("Vinamilk has [Phân tích Dữ liệu / BI] quick filter", str_contains($vnmHtml, 'data-quick-filter="data_bi"'));
assertCheck("Vinamilk has [Logistics & Chuỗi cung ứng] quick filter", str_contains($vnmHtml, 'data-quick-filter="logistics_sc"'));
assertCheck("Vinamilk has [Tài chính - Kế toán] quick filter", str_contains($vnmHtml, 'data-quick-filter="finance_acc"'));

// Assert Popular Skills Checkboxes for Vinamilk
$expectedVnmSkills = [
    'Digital Marketing',
    'Nghiên cứu thị trường',
    'Phân tích dữ liệu',
    'PowerBI',
    'Excel nâng cao',
    'Quản trị kho vận',
    'Tiếng Anh giao tiếp',
    'Kỹ năng thuyết trình'
];
foreach ($expectedVnmSkills as $sk) {
    assertCheck("Vinamilk sidebar has checkbox '$sk'", str_contains($vnmHtml, "value=\"$sk\""));
}

// Assert Session Boot JSON for Vinamilk
assertCheck("Vinamilk JS boot sets isEconomicSector: true", str_contains($vnmHtml, '"isEconomicSector":true'));
assertCheck("Vinamilk JS boot sets sectorType: economic", str_contains($vnmHtml, '"sectorType":"economic"'));

// 2. Test Session Boot & HTML for FPT Software (IT / Tech)
echo "\n2. Testing FPT Software Sector-Aware Rendering...\n";

// Find FPT user
$fptUserRow = $pdoConn->query("SELECT email FROM users WHERE email = 'fpt@talenthub.local' OR email = 'business@test.talenthub.local' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$fptEmail = $fptUserRow['email'] ?? 'business@test.talenthub.local';

$_SESSION = [];
$sessionManager = new SessionManager($sessionConfig);
$sessionManager->start();

$fptUser = $authService->login(['email' => $fptEmail, 'password' => '123456'], RequestId::make(null));
$sessionManager->login($fptUser);

// Capture talents.php output for FPT Software
ob_start();
require dirname(__DIR__) . '/app/enterprise/talents.php';
$fptHtml = ob_get_clean();

// Assert Quick Filter Pills for FPT Software
assertCheck("FPT Software has [AI / Machine Learning] quick filter", str_contains($fptHtml, 'data-quick-filter="ai_ml"'));
assertCheck("FPT Software has [Lập trình Frontend] quick filter", str_contains($fptHtml, 'data-quick-filter="frontend"'));
assertCheck("FPT Software has [Lập trình Backend] quick filter", str_contains($fptHtml, 'data-quick-filter="backend"'));
assertCheck("FPT Software has [An toàn thông tin] quick filter", str_contains($fptHtml, 'data-quick-filter="security"'));

// Assert Popular Skills Checkboxes for FPT Software
$expectedFptSkills = ['React', 'Node.js', 'Python', 'TypeScript', 'Java', 'Spring Boot', 'Vue.js', 'SQL', 'Docker'];
foreach ($expectedFptSkills as $sk) {
    assertCheck("FPT Software sidebar has checkbox '$sk'", str_contains($fptHtml, "value=\"$sk\""));
}

// Assert Session Boot JSON for FPT Software
assertCheck("FPT Software JS boot sets isEconomicSector: false", str_contains($fptHtml, '"isEconomicSector":false'));
assertCheck("FPT Software JS boot sets sectorType: tech", str_contains($fptHtml, '"sectorType":"tech"'));

echo "\n====================================================================\n";
if ($allPassed) {
    echo ">>> ALL SECTOR-AWARE VERIFICATION ASSERTIONS PASSED! <<<\n";
} else {
    echo ">>> SOME VERIFICATION CHECKS FAILED! <<<\n";
    exit(1);
}
echo "====================================================================\n\n";
