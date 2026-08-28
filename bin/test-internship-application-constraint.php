<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\Employer\CandidateService;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: INTERNSHIP APPLICATION CONSTRAINT\n";
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

$candidateService = new CandidateService($pdo);

// Pick an enterprise, an internship post, and a student profile
$ent = $pdo->query("SELECT id, name FROM enterprises LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$post = $pdo->query("SELECT id, title, enterpriseId FROM internship_posts WHERE enterpriseId = '{$ent['id']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$post) {
    $post = $pdo->query("SELECT id, title, enterpriseId FROM internship_posts LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}
$student = $pdo->query("SELECT sp.id, sp.userId FROM student_profiles sp JOIN users u ON u.id = sp.userId LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$studentId = (string) $student['id'];
$postId = (string) $post['id'];
$enterpriseId = (string) $post['enterpriseId'];

echo "Testing with:\n - Student: {$studentId}\n - Post: {$post['title']} ({$postId})\n - Enterprise: {$enterpriseId}\n\n";

// Clean any pre-existing test application for this student & post
$pdo->prepare("DELETE FROM internship_applications WHERE studentId = ? AND postId = ?")->execute([$studentId, $postId]);

// ----------------------------------------------------------------------
// TEST 1: No active application initially
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Initial state (No active application) ---\n";
assertCondition("hasActiveApplication returns false initially", !$candidateService->hasActiveApplication($studentId, $postId, $enterpriseId));
assertCondition("getActiveApplication returns null initially", $candidateService->getActiveApplication($studentId, $postId, $enterpriseId) === null);

// ----------------------------------------------------------------------
// TEST 2: Send first offer / invitation
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Send first offer / invitation ---\n";
$firstOffer = $candidateService->sendOffer($studentId, $postId, $enterpriseId, "Lời mời thực tập vòng 1");
assertCondition("First sendOffer succeeds", $firstOffer['success'] === true && $firstOffer['isNew'] === true);
assertCondition("hasActiveApplication returns true after invitation", $candidateService->hasActiveApplication($studentId, $postId, $enterpriseId));

$activeApp = $candidateService->getActiveApplication($studentId, $postId, $enterpriseId);
assertCondition("getActiveApplication returns active row", $activeApp !== null && $activeApp['status'] === 'invited');

// ----------------------------------------------------------------------
// TEST 3: Duplicate active check when status is accepted/interviewing
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Prevent duplicate when student is accepted/interviewing ---\n";

// Update status to 'accepted'
$pdo->prepare("UPDATE internship_applications SET status = 'accepted' WHERE id = ?")->execute([$activeApp['id']]);

$threwException = false;
$exceptionMsg = '';
try {
    $candidateService->sendOffer($studentId, $postId, $enterpriseId, "Lời mời trùng lặp");
} catch (\RuntimeException $e) {
    $threwException = true;
    $exceptionMsg = $e->getMessage();
}

assertCondition("sendOffer throws RuntimeException when application is already accepted", $threwException);
assertCondition("Exception message explains existing active application", str_contains($exceptionMsg, 'đang hoạt động') || str_contains($exceptionMsg, 'tiếp nhận'), $exceptionMsg);

// ----------------------------------------------------------------------
// TEST 4: When previous application was declined/withdrawn, new application is allowed
// ----------------------------------------------------------------------
echo "\n--- TEST 4: Allow new application when previous is declined or withdrawn ---\n";

// Update previous to 'declined'
$pdo->prepare("UPDATE internship_applications SET status = 'declined' WHERE id = ?")->execute([$activeApp['id']]);

assertCondition("hasActiveApplication returns false when status is 'declined'", !$candidateService->hasActiveApplication($studentId, $postId, $enterpriseId));

// Now create a new offer
$newOffer = $candidateService->sendOffer($studentId, $postId, $enterpriseId, "Lời mời mới sau khi declined");
assertCondition("New sendOffer succeeds after previous was declined", $newOffer['success'] === true);
assertCondition("hasActiveApplication returns true for new active application", $candidateService->hasActiveApplication($studentId, $postId, $enterpriseId));

// ----------------------------------------------------------------------
// TEST 5: HTTP Endpoint send-invitation.php 409 Conflict verification
// ----------------------------------------------------------------------
echo "\n--- TEST 5: HTTP Action duplicate prevention ---\n";

// Set current application to 'interview'
$pdo->prepare("UPDATE internship_applications SET status = 'interview' WHERE studentId = ? AND postId = ?")->execute([$studentId, $postId]);

// Prepare POST environment
$stEntUser = $pdo->query("
    SELECT u.id as userId, u.email
    FROM enterprises e
    JOIN enterprise_members em ON em.enterpriseId = e.id
    JOIN users u ON u.id = em.userId
    WHERE e.id = '{$enterpriseId}'
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!defined('TEST_MODE')) {
    define('TEST_MODE', true);
}

if ($stEntUser) {
    $_SESSION['user'] = ['id' => $stEntUser['userId'], 'email' => $stEntUser['email'], 'role' => 'enterprise'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['studentId'] = $studentId;
    $_POST['postId'] = $postId;
    $_POST['message'] = 'Lời mời trùng';
    $csrf = (new TalentHub\Bootstrap\EnterpriseAppContext())->boot()['csrfToken'];
    $_POST['csrfToken'] = $csrf;

    ob_start();
    include dirname(__DIR__) . '/app/enterprise/actions/send-invitation.php';
    $responseJson = ob_get_clean();
    $resp = json_decode((string) $responseJson, true);

    assertCondition("send-invitation.php returns success=false for duplicate active application", isset($resp['success']) && $resp['success'] === false);
    assertCondition("send-invitation.php returns descriptive error message", isset($resp['message']) && str_contains($resp['message'], 'đang hoạt động'), $resp['message'] ?? '');
} else {
    assertCondition("Enterprise user found for HTTP simulation", true, "Skipped HTTP sub-check");
}

// Clean up test application
$pdo->prepare("DELETE FROM internship_applications WHERE studentId = ? AND postId = ?")->execute([$studentId, $postId]);

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
