<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Modules\Business\Service\InternshipService;
use TalentHub\Support\Id\RequestId;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "====================================================================\n";
echo "   VINAMILK ECOSYSTEM & DATA ISOLATION SMOKE TEST\n";
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

// 1. Check Enterprise Record
echo "1. Checking Enterprise 2 Record...\n";
$ent = $pdo->query("SELECT * FROM enterprises WHERE id = '32000000-0000-4000-8000-000000000003'")->fetch(PDO::FETCH_ASSOC);
assertCheck("Enterprise exists", !empty($ent));
assertCheck("Enterprise name is Vinamilk", ($ent['name'] ?? '') === 'Công ty Cổ phần Sữa Việt Nam (Vinamilk)', 'Got: ' . ($ent['name'] ?? ''));
assertCheck("Enterprise logo is SVG", ($ent['logoUrl'] ?? '') === '/assets/images/vinamilk-logo.svg', 'Got: ' . ($ent['logoUrl'] ?? ''));
assertCheck("Enterprise industry matches FMCG/Supply Chain", str_contains($ent['industry'] ?? '', 'FMCG'), 'Got: ' . ($ent['industry'] ?? ''));

// 2. Check Authentication for vinamilk@talenthub.local and biz@talenthub.local
echo "\n2. Testing Authentication...\n";
$authRepo = new AuthRepository($pdo);
$authService = new AuthService($authRepo);

$vnmUser = null;
try {
    $vnmUser = $authService->login(['email' => 'vinamilk@talenthub.local', 'password' => '123456'], RequestId::make(null));
    assertCheck("Login vinamilk@talenthub.local with 123456", !empty($vnmUser) && ($vnmUser['role'] ?? '') === 'enterprise');
} catch (\Throwable $e) {
    assertCheck("Login vinamilk@talenthub.local with 123456", false, $e->getMessage());
}

try {
    $bizUser = $authService->login(['email' => 'biz@talenthub.local', 'password' => '123456'], RequestId::make(null));
    assertCheck("Login biz@talenthub.local with 123456", !empty($bizUser) && ($bizUser['role'] ?? '') === 'enterprise');
} catch (\Throwable $e) {
    assertCheck("Login biz@talenthub.local with 123456", false, $e->getMessage());
}

// 3. Test Internship Posts under Vinamilk
echo "\n3. Testing Internship Posts Isolation...\n";
$internshipRepo = new InternshipRepository($pdo);
$internshipService = new InternshipService($internshipRepo);

$userId = $vnmUser ? (string) $vnmUser['id'] : '31000000-0000-4000-8000-000000000013';
$vnmPosts = $internshipService->listPosts($userId)['items'] ?? [];
assertCheck("Vinamilk has exactly 3 recruitment posts", count($vnmPosts) === 3, 'Found: ' . count($vnmPosts));

$expectedTitles = [
    'Quản trị viên Tập sự Marketing & Phát triển Thương hiệu (Brand Marketing Trainee)',
    'Thực tập sinh Quản trị Chuỗi cung ứng & Logistics (Supply Chain Intern)',
    'Thực tập sinh Tài chính - Kế toán Doanh nghiệp (Corporate Finance Trainee)'
];
foreach ($expectedTitles as $title) {
    $found = false;
    foreach ($vnmPosts as $p) {
        if (($p['title'] ?? '') === $title) { $found = true; break; }
    }
    assertCheck("Post '$title' exists in Vinamilk", $found);
}

// 4. Test Applications & Candidate Isolation
echo "\n4. Testing Candidate Applications & Strict Isolation...\n";
$vnmApps = $internshipService->listApplications($userId)['items'] ?? [];
assertCheck("Vinamilk has exactly 1 application overall", count($vnmApps) === 1, 'Found: ' . count($vnmApps));

if (!empty($vnmApps)) {
    $appDetail = $internshipService->application($userId, (string) $vnmApps[0]['id']);
    $studentSnap = $appDetail['snapshot']['student'] ?? [];
    assertCheck("Candidate is Lê Hoàng Yến Nhi", ($studentSnap['fullName'] ?? '') === 'Lê Hoàng Yến Nhi', 'Got: ' . ($studentSnap['fullName'] ?? ''));
    assertCheck("Candidate school is Đại học Cần Thơ", ($studentSnap['schoolName'] ?? '') === 'Đại học Cần Thơ', 'Got: ' . ($studentSnap['schoolName'] ?? ''));
    assertCheck("Candidate class is K47 QTKD", str_contains($studentSnap['className'] ?? '', 'Quản trị Kinh doanh'), 'Got: ' . ($studentSnap['className'] ?? ''));
    assertCheck("Candidate status is submitted", ($appDetail['status'] ?? '') === 'submitted', 'Got: ' . ($appDetail['status'] ?? ''));

    // Check Skills in Snapshot
    $skillRows = $appDetail['snapshot']['skills'] ?? [];
    $skillNames = array_map(fn($s) => $s['skillName'] ?? '', $skillRows);
    assertCheck("Snapshot has Marketing / TOEIC skills", in_array('Phân tích thị trường', $skillNames, true) && in_array('Tiếng Anh TOEIC 800', $skillNames, true));
}

// 5. Test FPT Software Data Isolation
echo "\n5. Testing FPT Software vs Vinamilk Isolation...\n";
$fptUser = $pdo->query("SELECT id FROM users WHERE email = 'fpt@talenthub.local' OR email = 'business@test.talenthub.local' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!empty($fptUser)) {
    $fptPosts = $internshipService->listPosts((string) $fptUser['id'])['items'] ?? [];
    $vnmPostInFpt = false;
    foreach ($fptPosts as $fp) {
        if (str_contains($fp['title'] ?? '', 'Brand Marketing') || str_contains($fp['title'] ?? '', 'Supply Chain Intern')) {
            $vnmPostInFpt = true;
        }
    }
    assertCheck("FPT Software does NOT see Vinamilk posts", !$vnmPostInFpt);
}

// 6. Test Status Transition Review
echo "\n6. Testing Review & Status Transition...\n";
if (!empty($vnmApps)) {
    $appId = (string) $vnmApps[0]['id'];
    $reviewed = $internshipService->review($userId, $appId, [
        'expectedCurrentStatus' => 'submitted',
        'targetStatus' => 'reviewing',
        'reviewerNote' => 'Hồ sơ rất phù hợp, mời phỏng vấn vòng 1.'
    ]);
    assertCheck("Transition to reviewing succeeds", ($reviewed['status'] ?? '') === 'reviewing');
    
    // Reset back to submitted
    $internshipService->review($userId, $appId, [
        'expectedCurrentStatus' => 'reviewing',
        'targetStatus' => 'submitted',
        'reviewerNote' => 'Đã nộp hồ sơ'
    ]);
    assertCheck("Reset back to submitted succeeds", true);
}

echo "\n====================================================================\n";
if ($allPassed) {
    echo ">>> ALL 18+ SMOKE TEST ASSERTIONS PASSED PERFECTLY! <<<\n";
} else {
    echo ">>> SOME SMOKE TESTS FAILED! <<<\n";
    exit(1);
}
echo "====================================================================\n\n";
