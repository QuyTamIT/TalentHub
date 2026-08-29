<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING COMPREHENSIVE TEST SUITE: ENTERPRISE PORTAL FIXES\n";
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

$enterpriseEmail = 'fptsoftware@talenthub.local';
$stEnt = $pdo->prepare("
    SELECT e.id as enterpriseId, e.name as enterpriseName, u.id as userId, u.fullName
    FROM enterprises e
    LEFT JOIN enterprise_members em ON em.enterpriseId = e.id
    LEFT JOIN users u ON u.id = em.userId
    WHERE e.name LIKE '%FPT Software%' OR e.email = ?
    LIMIT 1
");
$stEnt->execute([$enterpriseEmail]);
$entRow = $stEnt->fetch(PDO::FETCH_ASSOC);

assertCondition("Enterprise profile found (FPT Software)", (bool)$entRow, $entRow['enterpriseName'] ?? '');

$enterpriseId = (string)($entRow['enterpriseId'] ?? '');
$enterpriseUserId = (string)($entRow['userId'] ?? '');

// ----------------------------------------------------------------------
// Test 1: Project Sponsorships Data & Interactive Sponsoring Flow
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Project Sponsorships Data & Live Sponsoring Flow ---\n";

$workflowService = new \TalentHub\Modules\Business\Service\BusinessWorkflowService(
    new \TalentHub\Modules\Business\Repository\BusinessWorkflowRepository($pdo)
);

$projectsQuery = \TalentHub\Http\CollectionQuery::fromRequest(
    new \TalentHub\Http\Request('GET', '/api/v1/projects', [], '', [], ['limit' => '100']),
    ['createdAt', 'title', 'fundingGoal']
);
$projects = $workflowService->projects($projectsQuery);

assertCondition("Projects list has at least 3 active research projects", count($projects) >= 3, "Total projects: " . count($projects));

$smartGarden = null;
$yolo = null;
$healthcare = null;

foreach ($projects as $p) {
    if (str_contains($p['title'], 'Smart Garden')) $smartGarden = $p;
    if (str_contains($p['title'], 'YOLOv8')) $yolo = $p;
    if (str_contains($p['title'], 'Healthcare')) $healthcare = $p;
}

assertCondition("Project 'Smart Garden IoT' exists", $smartGarden !== null, $smartGarden['title'] ?? 'NOT FOUND');
if ($smartGarden) {
    assertCondition("Smart Garden fundingGoal is 50.000.000 VNĐ", (float)$smartGarden['fundingGoal'] == 50000000.0, "Goal: {$smartGarden['fundingGoal']}");
    assertCondition("Smart Garden raisedAmount >= 38.000.000 VNĐ", (float)$smartGarden['raisedAmount'] >= 38000000.0, "Raised: {$smartGarden['raisedAmount']}");
    assertCondition("Smart Garden has team members", !empty($smartGarden['members']), "Members: " . count($smartGarden['members']));
}

assertCondition("Project 'YOLOv8' exists", $yolo !== null, $yolo['title'] ?? 'NOT FOUND');
if ($yolo) {
    assertCondition("YOLOv8 fundingGoal is 30.000.000 VNĐ", (float)$yolo['fundingGoal'] == 30000000.0, "Goal: {$yolo['fundingGoal']}");
    assertCondition("YOLOv8 raisedAmount >= 15.000.000 VNĐ", (float)$yolo['raisedAmount'] >= 15000000.0, "Raised: {$yolo['raisedAmount']}");
}

assertCondition("Project 'AI for Healthcare' exists", $healthcare !== null, $healthcare['title'] ?? 'NOT FOUND');
if ($healthcare) {
    assertCondition("Healthcare fundingGoal is 40.000.000 VNĐ", (float)$healthcare['fundingGoal'] == 40000000.0, "Goal: {$healthcare['fundingGoal']}");
    assertCondition("Healthcare raisedAmount >= 25.000.000 VNĐ", (float)$healthcare['raisedAmount'] >= 25000000.0, "Raised: {$healthcare['raisedAmount']}");
}

// Test Sponsoring Action (Pledge -> Pay -> Confirm)
$initialRaised = (float)($smartGarden['raisedAmount'] ?? 0);
$testAmount = '5000000'; // 5 million VND

$sponRes = $workflowService->sponsor($enterpriseUserId, [
    'projectId' => $smartGarden['id'],
    'amount' => $testAmount,
    'currency' => 'VND',
    'note' => 'Automated test pledge sponsorship',
], 'req-test-' . time());

assertCondition("Sponsorship pledge created successfully", !empty($sponRes['id']), "Sponsorship ID: " . ($sponRes['id'] ?? ''));

if (!empty($sponRes['id'])) {
    $payRes = $workflowService->createPayment($enterpriseUserId, [
        'sponsorshipId' => $sponRes['id'],
        'provider' => 'vnpay',
    ], 'req-test-pay-' . time());
    assertCondition("Payment order created", !empty($payRes['id']), "Order ID: " . ($payRes['id'] ?? ''));

    if (!empty($payRes['id'])) {
        $paymentConfirmService = new \TalentHub\Modules\Business\Service\PaymentConfirmationService($pdo);
        $confRes = $paymentConfirmService->confirmPayment($enterpriseId, $payRes['id'], ['providerReference' => 'VNPAY_TEST_REF_' . time()], 'req-test-conf-' . time());
        assertCondition("Payment confirmation succeeded", ($confRes['paymentStatus'] ?? '') === 'paid', "Status: " . ($confRes['paymentStatus'] ?? ''));

        // Verify updated raised amount
        $updatedProjects = $workflowService->projects($projectsQuery);
        $updatedSmartGarden = null;
        foreach ($updatedProjects as $up) {
            if ($up['id'] === $smartGarden['id']) {
                $updatedSmartGarden = $up;
                break;
            }
        }
        $newRaised = (float)($updatedSmartGarden['raisedAmount'] ?? 0);
        assertCondition("Smart Garden raised amount increased by 5.000.000 VNĐ", $newRaised >= $initialRaised + 5000000, "New Raised: {$newRaised}");
    }
}

// ----------------------------------------------------------------------
// Test 2: Clean Dummy Teacher "abc"
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Clean Dummy Teacher Records ('abc') ---\n";

$abcUsersCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE fullName = 'abc'")->fetchColumn();
assertCondition("Zero users with fullName 'abc'", $abcUsersCount === 0, "Found: {$abcUsersCount}");

$hungUser = $pdo->query("SELECT fullName FROM users WHERE email = 'teacher1@talenthub.local'")->fetch(PDO::FETCH_ASSOC);
assertCondition("teacher1@talenthub.local is 'ThS. Nguyễn Văn Hùng'", ($hungUser['fullName'] ?? '') === 'ThS. Nguyễn Văn Hùng', $hungUser['fullName'] ?? '');

// ----------------------------------------------------------------------
// Test 3: Sync Skills & Experience Hours in Candidate Profiles
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Candidate Profiles Skills & Experience Sync ---\n";

$talentRepo = new \TalentHub\Modules\Business\Repository\EnterpriseTalentRepository($pdo);

// 1. Lê Quý Tam
$tamId = '9f9b3e8c-0f72-4b8d-90d9-53ca6ce0a69d';
$tamDetail = $talentRepo->getTalentDetail($enterpriseId, $tamId);
assertCondition("Talent detail loaded for Lê Quý Tam", $tamDetail !== null, $tamDetail['displayName'] ?? '');
if ($tamDetail) {
    $tamSkills = array_column($tamDetail['skills'] ?? [], 'name');
    assertCondition("Lê Quý Tam has Python skill", in_array('Python', $tamSkills), implode(', ', $tamSkills));
    assertCondition("Lê Quý Tam has PyTorch skill", in_array('PyTorch', $tamSkills));
    assertCondition("Lê Quý Tam has Machine Learning skill", in_array('Machine Learning', $tamSkills));
    assertCondition("Lê Quý Tam experience hours > 0", ($tamDetail['experience']['confirmed_hours'] ?? 0) > 0, "Hours: " . ($tamDetail['experience']['confirmed_hours'] ?? 0));
}

// 2. Vũ Đức Anh
$anhId = 'f3150ce0-7a99-4d5f-8b03-c293b91e37e5';
$anhDetail = $talentRepo->getTalentDetail($enterpriseId, $anhId);
assertCondition("Talent detail loaded for Vũ Đức Anh", $anhDetail !== null, $anhDetail['displayName'] ?? '');
if ($anhDetail) {
    $anhSkills = array_column($anhDetail['skills'] ?? [], 'name');
    assertCondition("Vũ Đức Anh has Machine Learning skill", in_array('Machine Learning', $anhSkills), implode(', ', $anhSkills));
    assertCondition("Vũ Đức Anh has Computer Vision skill", in_array('Computer Vision', $anhSkills));
}

// 3. Trần Minh Đức
$ducId = '1a1dddd2-b913-49bd-96eb-08b610642a8a';
$ducDetail = $talentRepo->getTalentDetail($enterpriseId, $ducId);
assertCondition("Talent detail loaded for Trần Minh Đức", $ducDetail !== null, $ducDetail['displayName'] ?? '');
if ($ducDetail) {
    $ducSkills = array_column($ducDetail['skills'] ?? [], 'name');
    assertCondition("Trần Minh Đức has React skill", in_array('React', $ducSkills), implode(', ', $ducSkills));
    assertCondition("Trần Minh Đức has TypeScript skill", in_array('TypeScript', $ducSkills));
}

// ----------------------------------------------------------------------
// Test 4: Page Renders (Enterprise Sponsorships & Candidate Detail)
// ----------------------------------------------------------------------
echo "\n--- TEST 4: Page Renders Verification ---\n";

$sessionConfig = array_merge(require dirname(__DIR__) . '/config/session.php', ['name' => \TalentHub\Auth\Session\SessionManager::SESSION_ENTERPRISE]);
$session = new \TalentHub\Auth\Session\SessionManager($sessionConfig);
$session->start();
$session->login([
    'id' => $enterpriseUserId,
    'email' => $enterpriseEmail,
    'role' => 'enterprise',
    'fullName' => $entRow['fullName'],
]);

$_SERVER['REQUEST_METHOD'] = 'GET';

// Render app/enterprise/sponsorships/index.php
ob_start();
require dirname(__DIR__) . '/app/enterprise/sponsorships/index.php';
$sponHtml = ob_get_clean();

assertCondition("Sponsorships page renders Smart Garden IoT", str_contains($sponHtml, 'Smart Garden IoT'));
assertCondition("Sponsorships page renders YOLOv8", str_contains($sponHtml, 'YOLOv8'));
assertCondition("Sponsorships page renders AI for Healthcare", str_contains($sponHtml, 'AI for Healthcare'));
assertCondition("Sponsorships page has modal sponsor form", str_contains($sponHtml, 'id="sponsorship-form-modal"'));

// Render app/enterprise/talents/detail.php for Lê Quý Tam
$_GET['id'] = $tamId;
ob_start();
require dirname(__DIR__) . '/app/enterprise/talents/detail.php';
$talentHtml = ob_get_clean();

assertCondition("Talent detail renders Lê Quý Tam", str_contains($talentHtml, 'Lê Quý Tam'));
assertCondition("Talent detail renders Python skill tag", str_contains($talentHtml, 'Python'));
assertCondition("Talent detail renders PyTorch skill tag", str_contains($talentHtml, 'PyTorch'));
assertCondition("Talent detail renders experience hours", str_contains($talentHtml, 'trải nghiệm'));

// Check candidate-detail.php forwarder
assertCondition("candidate-detail.php exists", file_exists(dirname(__DIR__) . '/app/enterprise/candidate-detail.php'));

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
