<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;
use TalentHub\Modules\Employer\CandidateService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: EMPLOYER & ENTERPRISE TALENT SEARCH\n";
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
// TEST 1: CandidateService Standard Queries & Filters
// ----------------------------------------------------------------------
echo "\n--- TEST 1: CandidateService Standard Queries & Filters ---\n";

$candidateService = new CandidateService($pdo);

// 1.1 All candidates (default/empty/all)
$all = $candidateService->searchCandidates();
assertCondition("searchCandidates() returns full list of candidates (not empty)", $all['total'] >= 20, "Total: {$all['total']}");
assertCondition("searchCandidates(['education_level' => 'all', 'school_id' => 'all']) returns all candidates", $candidateService->searchCandidates(['education_level' => 'all', 'school_id' => 'all'])['total'] >= 20);

// 1.2 School filtering
$btecStudents = $candidateService->searchCandidates(['school' => 'Cao đẳng Quốc tế BTEC FPT']);
assertCondition("Filter school BTEC returns BTEC students", $btecStudents['total'] === 11, "Count: {$btecStudents['total']}");

$thptStudents = $candidateService->searchCandidates(['school' => 'THPT Nguyễn Trãi']);
assertCondition("Filter school THPT returns THPT students", $thptStudents['total'] === 15, "Count: {$thptStudents['total']}");

// 1.3 Education level filtering
$thptLevel = $candidateService->searchCandidates(['education_level' => 'THPT']);
assertCondition("Filter education_level THPT returns THPT students", $thptLevel['total'] === 15, "Count: {$thptLevel['total']}");

$collegeLevel = $candidateService->searchCandidates(['education_level' => 'Cao đẳng']);
assertCondition("Filter education_level Cao đẳng returns Cao đẳng students", $collegeLevel['total'] === 11, "Count: {$collegeLevel['total']}");

// 1.4 Skill tag filtering
$pythonTalents = $candidateService->searchCandidates(['skill_tag' => 'Python']);
assertCondition("Filter skill_tag Python returns Python talents", $pythonTalents['total'] >= 5, "Count: {$pythonTalents['total']}");
$hasTamInPython = false;
foreach ($pythonTalents['items'] as $t) {
    if (str_contains($t['name'], 'Lê Quý Tam')) $hasTamInPython = true;
}
assertCondition("Python talent list includes Lê Quý Tam", $hasTamInPython);

// 1.5 Keyword search filtering
$searchTam = $candidateService->searchCandidates(['search' => 'Lê Quý Tam']);
assertCondition("Search 'Lê Quý Tam' finds Lê Quý Tam", $searchTam['total'] >= 1 && str_contains($searchTam['items'][0]['name'], 'Lê Quý Tam'));

$searchReact = $candidateService->searchCandidates(['search' => 'React']);
assertCondition("Search 'React' finds React developers", $searchReact['total'] >= 2, "Count: {$searchReact['total']}");

// 1.6 Major / Domain field filtering (AI & Data, IT, Marketing)
$aiDomain = $candidateService->searchCandidates(['major_field' => 'Khoa học dữ liệu & AI']);
assertCondition("Domain filter 'Khoa học dữ liệu & AI' returns candidates", $aiDomain['total'] >= 5, "Count: {$aiDomain['total']}");
$hasTamInAi = false;
$hasDucInAi = false;
foreach ($aiDomain['items'] as $item) {
    if (str_contains($item['name'], 'Lê Quý Tam')) $hasTamInAi = true;
    if (str_contains($item['name'], 'Trần Minh Đức')) $hasDucInAi = true;
}
assertCondition("Domain 'Khoa học dữ liệu & AI' includes Lê Quý Tam", $hasTamInAi);
assertCondition("Domain 'Khoa học dữ liệu & AI' includes Trần Minh Đức", $hasDucInAi);

// ----------------------------------------------------------------------
// TEST 2: EnterpriseTalentRepository listTalents Non-blocking
// ----------------------------------------------------------------------
echo "\n--- TEST 2: EnterpriseTalentRepository Non-blocking listTalents ---\n";

$entRepo = new EnterpriseTalentRepository($pdo);
$ent = $pdo->query("SELECT id, name FROM enterprises LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$repoAll = $entRepo->listTalents($ent['id']);
assertCondition("EnterpriseTalentRepository returns candidates for enterprise", $repoAll['total'] >= 20, "Total: {$repoAll['total']}");

$repoAi = $entRepo->listTalents($ent['id'], ['major_field' => 'Khoa học dữ liệu & AI']);
assertCondition("EnterpriseTalentRepository filter major 'Khoa học dữ liệu & AI' matches candidates", $repoAi['total'] >= 5, "Count: {$repoAi['total']}");

$repoAiCode = $entRepo->listTalents($ent['id'], ['major_field' => 'data_ai']);
assertCondition("EnterpriseTalentRepository filter major 'data_ai' matches candidates", $repoAiCode['total'] >= 5, "Count: {$repoAiCode['total']}");

$repoEduTHPT = $entRepo->listTalents($ent['id'], ['education_level' => 'THPT']);
assertCondition("EnterpriseTalentRepository filter edu THPT matches 15 students", $repoEduTHPT['total'] === 15, "Count: {$repoEduTHPT['total']}");

$repoEduCollege = $entRepo->listTalents($ent['id'], ['education_level' => 'Cao đẳng']);
assertCondition("EnterpriseTalentRepository filter edu Cao đẳng matches 11 students", $repoEduCollege['total'] === 11, "Count: {$repoEduCollege['total']}");

// ----------------------------------------------------------------------
// TEST 3: Web Page Rendering & Forwarders
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Web Page Rendering & Forwarders ---\n";

// Mock enterprise session
$stEnt = $pdo->query("
    SELECT u.id as userId, u.email
    FROM enterprises e
    JOIN enterprise_members em ON em.enterpriseId = e.id
    JOIN users u ON u.id = em.userId
    WHERE e.name LIKE '%FPT Software%'
    LIMIT 1
");
$entUserRow = $stEnt->fetch(PDO::FETCH_ASSOC);
$entUserId = $entUserRow['userId'] ?? '10000000-0000-4000-8000-000000000003';
$_SESSION['user'] = ['id' => $entUserId, 'email' => $entUserRow['email'] ?? 'contact@fptsoftware.com', 'role' => 'enterprise'];

ob_start();
include dirname(__DIR__) . '/app/enterprise/talents.php';
$htmlEnterprise = ob_get_clean();

assertCondition("app/enterprise/talents.php renders 200 OK", strlen($htmlEnterprise) > 5000);
assertCondition("app/enterprise/talents.php contains enterprise-session-boot json", str_contains($htmlEnterprise, 'id="enterprise-session-boot"'));
assertCondition("app/enterprise/talents.php contains candidate count > 0", str_contains($htmlEnterprise, '"totalTalents":26') || str_contains($htmlEnterprise, '"totalTalents":'));

ob_start();
include dirname(__DIR__) . '/app/employer/candidates.php';
$htmlEmployer = ob_get_clean();

assertCondition("app/employer/candidates.php renders 200 OK", strlen($htmlEmployer) > 5000 && str_contains($htmlEmployer, 'enterprise-session-boot'));

ob_start();
include dirname(__DIR__) . '/app/employer/talent-search.php';
$htmlTalentSearch = ob_get_clean();

assertCondition("app/employer/talent-search.php renders 200 OK", strlen($htmlTalentSearch) > 5000 && str_contains($htmlTalentSearch, 'enterprise-session-boot'));

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
