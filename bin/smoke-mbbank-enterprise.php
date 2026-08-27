<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Modules\Business\Repository\BusinessRepository;
use TalentHub\Modules\Business\Service\BusinessProfileService;
use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Modules\Business\Service\InternshipService;
use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;
use TalentHub\Modules\Business\Service\EnterpriseTalentService;
use TalentHub\Rbac\RoleCodes;

$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = SessionManager::SESSION_ENTERPRISE;
$sessionManager = new SessionManager($sessionConfig);
$sessionManager->start();

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

$authRepo = new AuthRepository($pdo);
$authService = new AuthService($authRepo);
$bizRepo = new BusinessRepository($pdo);
$bizService = new BusinessProfileService($bizRepo);
$internRepo = new InternshipRepository($pdo);
$internService = new InternshipService($internRepo);
$talentRepo = new EnterpriseTalentRepository($pdo);
$talentService = new EnterpriseTalentService($talentRepo);

echo "========================================================\n";
echo "   SMOKE TEST: MB BANK ENTERPRISE & ECONOMIC TALENTS\n";
echo "========================================================\n\n";

$testsPassed = 0;
$totalTests = 0;

function assertTest(bool $condition, string $message, string $detail = ''): void {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo "  [PASS] {$message}\n";
        if ($detail !== '') {
            echo "         -> {$detail}\n";
        }
    } else {
        echo "  [FAIL] {$message}\n";
        if ($detail !== '') {
            echo "         -> ERROR: {$detail}\n";
        }
    }
}

// --------------------------------------------------------------------------
// 1. Test Auth & User Accounts
// --------------------------------------------------------------------------
echo "1. Testing MB Bank Auth & Enterprise Profile...\n";

$mbUser1 = $authRepo->findByEmail('mbbank@talenthub.local');
assertTest($mbUser1 !== null, "User lookup: mbbank@talenthub.local", "User ID: " . ($mbUser1['id'] ?? 'null') . " | Name: " . ($mbUser1['fullName'] ?? ''));
if ($mbUser1) {
    assertTest(password_verify('123456', $mbUser1['passwordHash'] ?? ''), "Password verify for '123456'");
    assertTest(($mbUser1['fullName'] ?? '') === 'Ban Tuyển Dụng MB Bank', "Full name is 'Ban Tuyển Dụng MB Bank'");
}

$mbUser2 = $authRepo->findByEmail('biz@talenthub.local');
assertTest($mbUser2 !== null, "Alias user lookup: biz@talenthub.local", "User ID: " . ($mbUser2['id'] ?? 'null'));

$mbEnterprise = $bizRepo->findByUserId((string)$mbUser1['id']);
assertTest($mbEnterprise !== null, "Enterprise profile resolved for MB Bank user");
if ($mbEnterprise) {
    assertTest(str_contains($mbEnterprise['name'], 'MB Bank'), "Company name contains 'MB Bank': {$mbEnterprise['name']}");
    assertTest(str_contains($mbEnterprise['industry'], 'Tài chính'), "Industry contains 'Tài chính': {$mbEnterprise['industry']}");
    assertTest(str_contains($mbEnterprise['address'], 'MB Grand Tower'), "Address contains 'MB Grand Tower': {$mbEnterprise['address']}");
    assertTest($mbEnterprise['verificationStatus'] === 'verified', "Verification status is 'verified'");
}

// --------------------------------------------------------------------------
// 2. Test MB Bank 2 Internship Posts
// --------------------------------------------------------------------------
echo "\n2. Testing MB Bank Internship Posts...\n";
$posts = $pdo->prepare("SELECT * FROM internship_posts WHERE enterpriseId = ? ORDER BY createdAt ASC");
$posts->execute([$mbEnterprise['id']]);
$mbPosts = $posts->fetchAll(PDO::FETCH_ASSOC);

assertTest(count($mbPosts) === 2, "MB Bank has exactly 2 internship posts (found " . count($mbPosts) . ")");

$titles = array_column($mbPosts, 'title');
$hasBiPost = false;
$hasMktPost = false;

foreach ($mbPosts as $p) {
    echo "     * [{$p['status']}] {$p['title']} ({$p['location']}) - Slots: {$p['slots']}\n";
    if (str_contains($p['title'], 'Business Intelligence') || str_contains($p['title'], 'Phân tích Dữ liệu')) {
        $hasBiPost = true;
        assertTest(str_contains($p['location'], 'Cần Thơ') && str_contains($p['location'], 'TP.HCM'), "BI post location has Cần Thơ / TP.HCM");
        $skills = json_decode($p['skillsJson'] ?? '[]', true);
        assertTest(in_array('SQL', $skills, true) && in_array('PowerBI', $skills, true), "BI post contains SQL and PowerBI skills");
    }
    if (str_contains($p['title'], 'Digital Marketing')) {
        $hasMktPost = true;
        assertTest(str_contains($p['location'], 'TP.HCM') && str_contains($p['location'], 'Cần Thơ'), "Marketing post location has TP.HCM / Cần Thơ");
        $skills = json_decode($p['skillsJson'] ?? '[]', true);
        assertTest(in_array('Digital Marketing', $skills, true) && in_array('SEO', $skills, true), "Marketing post contains Digital Marketing and SEO skills");
    }
}

assertTest($hasBiPost, "Post 1: Business Intelligence Intern exists and active");
assertTest($hasMktPost, "Post 2: Digital Marketing Intern exists and active");

// --------------------------------------------------------------------------
// 3. Test Talent Discovery for MB Bank (Hoàng Thị Mai Linh & Phạm Quốc Bảo)
// --------------------------------------------------------------------------
echo "\n3. Testing Talent Discovery for Economic & Marketing Students...\n";
$talentsResult = $talentRepo->listTalents((string)$mbEnterprise['id'], ['limit' => 100]);
$talentItems = $talentsResult['items'] ?? [];

echo "     Total discovered candidates for MB Bank: " . count($talentItems) . "\n";
assertTest(count($talentItems) >= 2, "Discovered candidates count >= 2");

$linhFound = null;
$baoFound = null;
foreach ($talentItems as $t) {
    if (str_contains($t['displayName'] ?? '', 'Mai Linh')) {
        $linhFound = $t;
    }
    if (str_contains($t['displayName'] ?? '', 'Phạm Quốc Bảo')) {
        $baoFound = $t;
    }
}

assertTest($linhFound !== null, "Candidate found: Hoàng Thị Mai Linh");
if ($linhFound) {
    assertTest(str_contains($linhFound['schoolName'] ?? '', 'Cần Thơ'), "Mai Linh is from Đại học Cần Thơ: {$linhFound['schoolName']}");
    assertTest((int)$linhFound['talentScore'] === 90, "Mai Linh talent score is 90 (got {$linhFound['talentScore']})");
    $skills = $linhFound['verifiedSkills'] ?? [];
    assertTest(in_array('PowerBI', $skills, true) || in_array('Phân tích dữ liệu', $skills, true), "Mai Linh has verified PowerBI / Data Analytics skills");
}

assertTest($baoFound !== null, "Candidate found: Phạm Quốc Bảo");
if ($baoFound) {
    assertTest(str_contains($baoFound['schoolName'] ?? '', 'FPT'), "Quốc Bảo is from Đại học FPT: {$baoFound['schoolName']}");
    assertTest((int)$baoFound['talentScore'] === 86, "Quốc Bảo talent score is 86 (got {$baoFound['talentScore']})");
    $skills = $baoFound['verifiedSkills'] ?? [];
    assertTest(in_array('SEO', $skills, true) || in_array('Content Creator', $skills, true) || in_array('Digital Marketing', $skills, true), "Quốc Bảo has verified SEO / Marketing skills");
}

// --------------------------------------------------------------------------
// 4. Test Enterprise Web Pages with MB Bank Session
// --------------------------------------------------------------------------
echo "\n4. Testing Web Page Rendering with MB Bank Session...\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/enterprise/index.php';
$_SERVER['SCRIPT_NAME'] = '/app/enterprise/index.php';

$mbUser1['role'] = RoleCodes::ENTERPRISE;
$sessionManager->login($mbUser1);
$_SESSION['user'] = $mbUser1;
$_SESSION['role'] = RoleCodes::ENTERPRISE;

// Render Dashboard
ob_start();
include dirname(__DIR__) . '/app/enterprise/index.php';
$dashHtml = ob_get_clean();

assertTest(!empty($dashHtml), "MB Bank Dashboard page renders output");
assertTest(str_contains($dashHtml, 'Ngân hàng TMCP Quân đội (MB Bank)'), "Dashboard displays 'Ngân hàng TMCP Quân đội (MB Bank)'");
assertTest(str_contains($dashHtml, 'MB'), "Header avatar initials display 'MB'");

// Render Internships
$_SERVER['REQUEST_URI'] = '/app/enterprise/internships/index.php';
$_SERVER['SCRIPT_NAME'] = '/app/enterprise/internships/index.php';
ob_start();
include dirname(__DIR__) . '/app/enterprise/internships/index.php';
$internHtml = ob_get_clean();

assertTest(!empty($internHtml), "MB Bank Internships page renders output");
assertTest(str_contains($internHtml, 'Business Intelligence Intern'), "Internships page contains 'Business Intelligence Intern'");
assertTest(str_contains($internHtml, 'Digital Marketing &amp; Truyền thông Thương hiệu') || str_contains($internHtml, 'Digital Marketing & Truyền thông Thương hiệu'), "Internships page contains 'Digital Marketing & Truyền thông Thương hiệu'");

// Render Talents
$_SERVER['REQUEST_URI'] = '/app/enterprise/talents.php';
$_SERVER['SCRIPT_NAME'] = '/app/enterprise/talents.php';
ob_start();
include dirname(__DIR__) . '/app/enterprise/talents.php';
$talentsHtml = ob_get_clean();

assertTest(!empty($talentsHtml), "MB Bank Talents page renders output");
assertTest(str_contains($talentsHtml, 'Hoàng Thị Mai Linh'), "Talents page displays Hoàng Thị Mai Linh");
assertTest(str_contains($talentsHtml, 'Phạm Quốc Bảo'), "Talents page displays Phạm Quốc Bảo");

echo "\n========================================================\n";
echo "   RESULTS: {$testsPassed}/{$totalTests} TESTS PASSED\n";
echo "========================================================\n";

if ($testsPassed === $totalTests) {
    echo "\n>>> ALL MB BANK ENTERPRISE REQUIREMENTS VERIFIED! <<<\n";
    exit(0);
} else {
    echo "\n>>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
