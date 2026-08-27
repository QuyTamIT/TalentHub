<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Http\Request;
use TalentHub\Http\CollectionQuery;
use TalentHub\Modules\Business\Service\BusinessWorkflowService;
use TalentHub\Modules\Business\Repository\BusinessWorkflowRepository;
use TalentHub\Auth\Session\SessionManager;

$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = SessionManager::SESSION_ENTERPRISE;
$sessionManager = new SessionManager($sessionConfig);
$sessionManager->start();

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();
$repo = new BusinessWorkflowRepository($pdo);
$service = new BusinessWorkflowService($repo);

$fptEnterpriseUser = $pdo->query("SELECT * FROM users WHERE (email = 'fpt@talenthub.local' OR email = 'enterprise@talenthub.local') AND status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$fptEnterpriseUser['role'] = 'enterprise';
$sessionManager->login($fptEnterpriseUser);

echo "========================================================\n";
echo "   ENTERPRISE SPONSORSHIP PROJECTS VERIFICATION SUITE\n";
echo "========================================================\n\n";

$testsPassed = 0;
$totalTests = 0;

function assertTest(bool $condition, string $message): void {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] {$message}\n";
    } else {
        echo "  [FAIL] {$message}\n";
    }
}

// 1. Check projects count in database
$query = CollectionQuery::fromRequest(
    new Request('GET', '/api/v1/projects', [], '', [], ['limit' => '100']),
    ['createdAt', 'title', 'fundingGoal']
);
$projects = $service->projects($query);

echo "1. Testing Project List & Data Completeness...\n";
assertTest(count($projects) === 3, "Database returns exactly 3 projects (found " . count($projects) . ")");

// Map by ID
$projMap = [];
foreach ($projects as $p) {
    $projMap[$p['id']] = $p;
}

// 2. Validate Project 1: AI Rác BTEC
$p1 = $projMap['50000000-0000-4000-8000-000000000001'] ?? null;
assertTest($p1 !== null, "Project 1 exists");
if ($p1) {
    assertTest(str_contains($p1['title'], 'AI phân loại rác'), "Project 1 title contains 'AI phân loại rác': {$p1['title']}");
    assertTest(str_contains($p1['schoolName'], 'BTEC FPT'), "Project 1 school is BTEC FPT: {$p1['schoolName']}");
    assertTest((float)$p1['fundingGoal'] == 25000000, "Project 1 funding goal is 25.000.000 VNĐ");
    assertTest((float)$p1['raisedAmount'] == 20000000, "Project 1 raised amount is 20.000.000 VNĐ");
    assertTest($p1['percentage'] === 80, "Project 1 percentage is 80%");
    assertTest($p1['membersCount'] === 3, "Project 1 has 3 members (found {$p1['membersCount']})");
    $memberNames = array_column($p1['members'], 'name');
    assertTest(in_array('Trần Minh Đức', $memberNames, true), "Project 1 contains Trần Minh Đức");
    assertTest(in_array('Võ Đức Anh', $memberNames, true), "Project 1 contains Võ Đức Anh");
    assertTest(in_array('Nguyễn Văn An', $memberNames, true), "Project 1 contains Nguyễn Văn An");
}

// 3. Validate Project 2: Game 3D FPTU
$p2 = $projMap['50000000-0000-4000-8000-000000000002'] ?? null;
assertTest($p2 !== null, "Project 2 exists");
if ($p2) {
    assertTest(str_contains($p2['title'], 'Game Giáo dục 3D'), "Project 2 title contains 'Game Giáo dục 3D': {$p2['title']}");
    assertTest(str_contains($p2['schoolName'], 'Đại học FPT'), "Project 2 school is Đại học FPT: {$p2['schoolName']}");
    assertTest((float)$p2['fundingGoal'] == 35000000, "Project 2 funding goal is 35.000.000 VNĐ");
    assertTest((float)$p2['raisedAmount'] == 35000000, "Project 2 raised amount is 35.000.000 VNĐ");
    assertTest($p2['percentage'] === 100, "Project 2 percentage is 100%");
    assertTest($p2['membersCount'] === 4, "Project 2 has 4 members (found {$p2['membersCount']})");
}

// 4. Validate Project 3: Sàn Nông sản ĐH Cần Thơ
$p3 = $projMap['50000000-0000-4000-8000-000000000003'] ?? null;
assertTest($p3 !== null, "Project 3 exists");
if ($p3) {
    assertTest(str_contains($p3['title'], 'Sàn kết nối Nông sản số'), "Project 3 title contains 'Sàn kết nối Nông sản số': {$p3['title']}");
    assertTest(str_contains($p3['schoolName'], 'Đại học Cần Thơ'), "Project 3 school is Đại học Cần Thơ: {$p3['schoolName']}");
    assertTest((float)$p3['fundingGoal'] == 30000000, "Project 3 funding goal is 30.000.000 VNĐ");
    assertTest((float)$p3['raisedAmount'] == 15000000, "Project 3 raised amount is 15.000.000 VNĐ");
    assertTest($p3['percentage'] === 50, "Project 3 percentage is 50%");
    assertTest($p3['membersCount'] === 3, "Project 3 has 3 members (found {$p3['membersCount']})");
}

// 5. Test Enterprise View Rendering
echo "\n2. Testing Enterprise Sponsorships View Page Rendering...\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/enterprise/sponsorships/index.php';
$_SERVER['SCRIPT_NAME'] = '/app/enterprise/sponsorships/index.php';

ob_start();
include dirname(__DIR__) . '/app/enterprise/sponsorships/index.php';
$html = ob_get_clean();

assertTest(!empty($html), "Sponsorship page rendered output");
assertTest(str_contains($html, htmlspecialchars('Ứng dụng AI phân loại rác & Tái chế thông minh')), "Page contains Project 1");
assertTest(str_contains($html, htmlspecialchars('Game Giáo dục 3D: Hành trình Khám phá Di sản Lịch sử')), "Page contains Project 2");
assertTest(str_contains($html, htmlspecialchars('Nền tảng Sàn kết nối Nông sản số & Truy xuất nguồn gốc')), "Page contains Project 3");
assertTest(str_contains($html, 'Cao đẳng Quốc tế BTEC FPT'), "Page contains BTEC FPT");
assertTest(str_contains($html, 'Đại học Cần Thơ'), "Page contains Đại học Cần Thơ");
assertTest(str_contains($html, '3 thành viên'), "Page contains '3 thành viên'");
assertTest(str_contains($html, '4 thành viên'), "Page contains '4 thành viên'");
assertTest(str_contains($html, 'Tài trợ ngay'), "Page contains 'Tài trợ ngay' CTA button");
assertTest(str_contains($html, 'sponsorship-form-modal'), "Page contains sponsorship modal");
assertTest(str_contains($html, '70.000.000 VNĐ'), "Header displays total 70.000.000 VNĐ");

echo "\n========================================================\n";
echo "   RESULTS: {$testsPassed}/{$totalTests} TESTS PASSED\n";
echo "========================================================\n";

if ($testsPassed === $totalTests) {
    echo "\n>>> ALL ENTERPRISE SPONSORSHIP REQUIREMENTS VERIFIED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo "\n>>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
