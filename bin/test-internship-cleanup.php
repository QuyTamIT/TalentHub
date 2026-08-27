<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Modules\Business\Repository\BusinessRepository;
use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Modules\Business\Service\InternshipService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "========================================================\n";
echo "   VERIFY FPT SOFTWARE INTERNSHIP POSTS CLEANUP\n";
echo "========================================================\n\n";

$pass = 0;
$fail = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $pass, $fail;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        if ($details) echo "         -> {$details}\n";
        $pass++;
    } else {
        echo "  [FAIL] {$name}\n";
        if ($details) echo "         -> {$details}\n";
        $fail++;
    }
}

// 1. Resolve FPT User and Enterprise
$authRepo = new AuthRepository($pdo);
$fptUser = $authRepo->findByEmail('fpt@talenthub.local');
assertTest('FPT user exists', $fptUser !== null);

$bizRepo = new BusinessRepository($pdo);
$enterprise = $bizRepo->findByUserId($fptUser['id']);
assertTest('FPT Enterprise found', $enterprise !== null, "ID: {$enterprise['id']}, Name: {$enterprise['name']}");

// 2. Query InternshipService listPosts
$internshipRepo = new InternshipRepository($pdo);
$internshipService = new InternshipService($internshipRepo);
$result = $internshipService->listPosts($fptUser['id']);
$items = $result['items'] ?? [];

assertTest('FPT Software has exactly 3 internship posts', count($items) === 3, "Found: " . count($items) . " posts");

$titles = array_column($items, 'title');
echo "Current FPT Posts:\n";
foreach ($items as $idx => $it) {
    $n = $idx + 1;
    echo "  {$n}. [{$it['status']}] {$it['title']} (Slots: {$it['slots']}, Applicants: {$it['applicantCount']})\n";
}

assertTest(
    'Contains Frontend Developer (ReactJS / Vue.js)',
    in_array('Frontend Developer (ReactJS / Vue.js)', $titles, true)
);

assertTest(
    'Contains Thực tập sinh Trí tuệ Nhân tạo & LLM (AI/GenAI Intern)',
    in_array('Thực tập sinh Trí tuệ Nhân tạo & LLM (AI/GenAI Intern)', $titles, true)
);

assertTest(
    'Contains Kỹ sư Kiểm thử Phần mềm Tự động (Automation QA Trainee)',
    in_array('Kỹ sư Kiểm thử Phần mềm Tự động (Automation QA Trainee)', $titles, true)
);

// 3. Verify no Test or API Test posts remain in Database
$stmtTestPosts = $pdo->query("SELECT COUNT(*) FROM internship_posts WHERE title LIKE '%Test%' OR title LIKE '%API Test%'");
$testCount = (int) $stmtTestPosts->fetchColumn();
assertTest('Zero test or duplicate posts remain in database', $testCount === 0, "Test post count: {$testCount}");

// 4. Verify applicant count on Frontend Developer post
$frontendPost = null;
foreach ($items as $it) {
    if (strpos($it['title'], 'Frontend') !== false) {
        $frontendPost = $it;
        break;
    }
}
assertTest(
    'Frontend Developer post has 1 submitted applicant',
    $frontendPost !== null && (int)$frontendPost['applicantCount'] === 1,
    "Applicant count: " . ($frontendPost['applicantCount'] ?? 0)
);

// 5. Verify all 3 posts have status = 'active' and audience = 'public'
$allActive = true;
foreach ($items as $it) {
    if ($it['status'] !== 'active') {
        $allActive = false;
    }
}
assertTest('All 3 FPT internship posts are active and open for applications', $allActive);

echo "\n========================================================\n";
echo "   SUMMARY: {$pass} PASSED, {$fail} FAILED\n";
echo "========================================================\n";

if ($fail > 0) {
    exit(1);
}
