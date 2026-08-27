<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Modules\Business\Repository\BusinessRepository;
use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;
use TalentHub\Modules\Business\Service\EnterpriseTalentService;

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
$_SESSION['user_id'] = '31000000-0000-4000-8000-000000000015';
$_SESSION['user_email'] = 'fpt@talenthub.local';
$_SESSION['user_role'] = 'enterprise';
$_SESSION['role'] = 'enterprise';

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "========================================================\n";
echo "   SMOKE TEST: FPT SOFTWARE & IT TALENTS DISCOVERY\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        if ($details) echo "         -> {$details}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$name}\n";
        if ($details) echo "         -> {$details}\n";
        $failCount++;
    }
}

// 1. Test Auth Lookup for fpt@talenthub.local & enterprise@talenthub.local
$authRepo = new AuthRepository($pdo);

$userFpt = $authRepo->findByEmail('fpt@talenthub.local');
assertTest(
    'Auth lookup: fpt@talenthub.local',
    $userFpt !== null && password_verify('123456', $userFpt['passwordHash']),
    "User ID: " . ($userFpt['id'] ?? 'N/A') . " | Name: " . ($userFpt['fullName'] ?? 'N/A')
);

$userEnt = $authRepo->findByEmail('enterprise@talenthub.local');
assertTest(
    'Auth lookup: enterprise@talenthub.local',
    $userEnt !== null && password_verify('123456', $userEnt['passwordHash']),
    "User ID: " . ($userEnt['id'] ?? 'N/A') . " | Name: " . ($userEnt['fullName'] ?? 'N/A')
);

// 2. Test Business Repository resolves to FPT Software
$bizRepo = new BusinessRepository($pdo);
$ent = $bizRepo->findByUserId($userFpt['id']);
assertTest(
    'Enterprise profile: Company name is FPT Software',
    $ent !== null && (stripos($ent['name'], 'FPT') !== false),
    "Company: " . ($ent['name'] ?? 'N/A') . " | Industry: " . ($ent['industry'] ?? 'N/A')
);

assertTest(
    'Enterprise profile: Industry is IT & AI',
    $ent !== null && (stripos($ent['industry'], 'Công nghệ thông tin') !== false || stripos($ent['industry'], 'IT & AI') !== false),
    "Industry: " . ($ent['industry'] ?? 'N/A')
);

// 3. Test Talent Service lists 4 IT candidates
$talentRepo = new EnterpriseTalentRepository($pdo);
$talentService = new EnterpriseTalentService($talentRepo);

$talents = $talentService->listTalents($userFpt['id']);
assertTest(
    'Talent Discovery: Total IT candidates >= 4',
    $talents['total'] >= 4,
    "Found: {$talents['total']} candidates"
);

$candidateNames = array_column($talents['items'], 'displayName');
$requiredCandidates = [
    'Nguyễn Văn An',
    'Trần Minh Đức',
    'Lê Hoàng Nam',
    'Võ Đức Anh'
];

foreach ($requiredCandidates as $rc) {
    $found = in_array($rc, $candidateNames, true);
    assertTest(
        "Candidate presence: {$rc}",
        $found,
        $found ? "Present with verified skills" : "Missing from query result"
    );
}

// 4. Test Talent Detail retrieval
echo "\n--- Verifying Detailed Profiles ---\n";
foreach ($talents['items'] as $item) {
    $detail = $talentService->getTalent($userFpt['id'], $item['studentId']);
    assertTest(
        "Talent detail for {$item['displayName']}",
        $detail !== null,
        "Skills count: " . count($detail['skills'] ?? []) . " | School: " . ($detail['schoolName'] ?? 'N/A')
    );
}

// 5. Test Filters (School, Skills, Search)
echo "\n--- Verifying Filter Queries ---\n";

$fptFilter = $talentService->listTalents($userFpt['id'], ['school' => 'Đại học FPT']);
assertTest(
    "Filter by school 'Đại học FPT'",
    $fptFilter['total'] >= 1 && in_array('Nguyễn Văn An', array_column($fptFilter['items'], 'displayName'), true),
    "Found " . $fptFilter['total'] . " candidate(s)"
);

$btecFilter = $talentService->listTalents($userFpt['id'], ['school' => 'BTEC']);
assertTest(
    "Filter by school 'BTEC'",
    $btecFilter['total'] >= 2,
    "Found " . $btecFilter['total'] . " candidate(s)"
);

$searchFilter = $talentService->listTalents($userFpt['id'], ['search' => 'Backend']);
assertTest(
    "Search keyword 'Backend'",
    $searchFilter['total'] >= 1,
    "Found " . $searchFilter['total'] . " candidate(s)"
);

// 6. Test rendering of talents page output
echo "\n--- Verifying Web Page Output ---\n";
ob_start();
require dirname(__DIR__) . '/app/enterprise/talents.php';
$html = ob_get_clean();

assertTest(
    "Talents page HTML renders successfully",
    strlen($html) > 5000 && (stripos($html, 'FPT') !== false || stripos($html, 'Phần mềm FPT') !== false),
    "HTML length: " . strlen($html) . " bytes"
);

assertTest(
    "No legacy Viettel Cyber Security string in page",
    stripos($html, 'Viettel Cyber Security') === false && stripos($html, 'Công ty An ninh mạng Viettel') === false,
    "Verified clean output"
);

echo "\n========================================================\n";
echo "   RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
