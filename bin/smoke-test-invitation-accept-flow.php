<?php
declare(strict_types=1);

define('TEST_MODE', true);

require __DIR__ . '/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Support\Id\RequestId;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " INTEGRATION TEST: LUỒNG TIẾP NHẬN LỜI MỜI THỰC TẬP VÀ ĐỒNG BỘ 4 BÊN\n";
echo "======================================================================\n\n";

$authRepo = new AuthRepository($pdo);
$authService = new AuthService($authRepo);

// Step 1: Find student Vũ Đức Anh & active internship post of FPT Software
echo "[Step 1] Setting up initial invitation state...\n";
$stUser = $pdo->query("SELECT id, fullName, email FROM users WHERE email = 'vuducanh@student.btec.edu.vn' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$stUser) {
    echo " -> FAILED: Student Vũ Đức Anh not found in users!\n";
    exit(1);
}
$stProfile = $pdo->query("SELECT id, classId FROM student_profiles WHERE userId = '{$stUser['id']}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$stId = $stProfile['id'];

// Get FPT enterprise and active post
$fptEntId = '31000000-0000-4000-8000-000000000015';
$fptEnt = $pdo->query("SELECT id, name FROM enterprises WHERE id = '{$fptEntId}'")->fetch(PDO::FETCH_ASSOC);
$post = $pdo->query("SELECT id, title FROM internship_posts WHERE id = '11000000-0000-4000-8000-000000000001'")->fetch(PDO::FETCH_ASSOC);

// Clean old application for Vũ Đức Anh to test fresh invitation flow
$pdo->prepare("DELETE FROM application_status_history WHERE applicationId IN (SELECT id FROM internship_applications WHERE studentId = ?)")->execute([$stId]);
$pdo->prepare("DELETE FROM internship_applications WHERE studentId = ?")->execute([$stId]);
$pdo->prepare("DELETE FROM notifications WHERE userId = ?")->execute([$stUser['id']]);

// Enterprise sends invitation -> Create application with status = 'invited'
$appId = Uuid::v4();
$pdo->prepare("
    INSERT INTO internship_applications (id, postId, studentId, status, message, reviewerNote, appliedAt, createdAt, updatedAt)
    VALUES (?, ?, ?, 'invited', 'FPT Software trân trọng gửi lời mời thực tập.', 'Hồ sơ năng lực AI xuất sắc.', NOW(6), NOW(6), NOW(6))
")->execute([$appId, $post['id'], $stId]);

// Create invitation notification for Vũ Đức Anh
$notifId = Uuid::v4();
$pdo->prepare("
    INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, readAt, createdAt)
    VALUES (?, ?, 'internship_invitation', 'internship', 'Lời mời thực tập từ FPT Software', 'FPT Software đã gửi lời mời thực tập vị trí Thực tập sinh AI.', ?, NULL, NOW(6))
")->execute([$notifId, $stUser['id'], "/app/enterprise/internships/{$post['id']}"]);

echo " -> Created invitation for {$stUser['fullName']} at {$fptEnt['name']} (Post: {$post['title']})\n";

// Step 2: Check Enterprise Applicants View (State: 'Đã mời')
echo "\n[Step 2] Checking Enterprise Applicants View (Status: 'invited')...\n";
$entUser = $authService->login(['email' => 'fpt@talenthub.local', 'password' => '123456'], RequestId::make(null));
$entSession = new SessionManager(array_merge(require dirname(__DIR__) . '/config/session.php', ['name' => SessionManager::sessionNameForRole('enterprise')]));
$entSession->start();
$entSession->login($entUser);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = "/app/enterprise/internships/applicants.php?postId={$post['id']}";
$_GET['postId'] = $post['id'];

ob_start();
include dirname(__DIR__) . '/app/enterprise/internships/applicants.php';
$entHtmlBefore = ob_get_clean();

echo " -> Pipeline count 'accepted' before accept: {$pipelineCounts['accepted']}\n";
echo " -> Candidate status for Vũ Đức Anh: {$applicants[0]['status']} ({$applicants[0]['status_label']})\n";
if ($applicants[0]['status'] !== 'invited') {
    echo " -> FAILED: Expected initial status 'invited', got {$applicants[0]['status']}!\n";
    exit(1);
}
echo " -> Verified: Candidate is initially in 'invited' / 'Đã mời' state.\n";

// Step 3: Student Vũ Đức Anh Accepts Invitation via POST to app/learner/notifications.php
echo "\n[Step 3] Student Vũ Đức Anh Accepts Invitation (POST accept_invitation)...\n";
$studentAuth = $authService->login(['email' => 'vuducanh@student.btec.edu.vn', 'password' => '123456'], RequestId::make(null));
$stSession = new SessionManager(array_merge(require dirname(__DIR__) . '/config/session.php', ['name' => SessionManager::sessionNameForRole('student')]));
$stSession->start();
$stSession->login($studentAuth);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/app/learner/notifications.php';
$_POST = [
    'action' => 'accept_invitation',
    'notificationId' => $notifId,
];

ob_start();
include dirname(__DIR__) . '/app/learner/notifications.php';
$jsonOutput = ob_get_clean();

echo " -> Response JSON: {$jsonOutput}\n";
$resData = json_decode($jsonOutput, true);
if (!$resData || empty($resData['success']) || $resData['status'] !== 'accepted') {
    echo " -> FAILED: Accept invitation API returned failure!\n";
    exit(1);
}
echo " -> SUCCESS: API returned success: true, status: 'accepted'!\n";

// Step 4: Verify Database State
echo "\n[Step 4] Verifying Database Records...\n";
$dbStatus = $pdo->query("SELECT status FROM internship_applications WHERE id = '{$appId}'")->fetchColumn();
echo " -> DB internship_applications.status: {$dbStatus}\n";
if ($dbStatus !== 'accepted') {
    echo " -> FAILED: DB status is {$dbStatus}, expected 'accepted'!\n";
    exit(1);
}

$readAt = $pdo->query("SELECT readAt FROM notifications WHERE id = '{$notifId}'")->fetchColumn();
echo " -> DB notifications.readAt: {$readAt}\n";
if (!$readAt) {
    echo " -> FAILED: Notification was not marked as read!\n";
    exit(1);
}

// Step 5: Enterprise View After Acceptance
echo "\n[Step 5] Checking Enterprise Applicants View After Acceptance...\n";
$entSession->start();
$entSession->login($entUser);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = "/app/enterprise/internships/applicants.php?postId={$post['id']}";
$_GET['postId'] = $post['id'];

ob_start();
include dirname(__DIR__) . '/app/enterprise/internships/applicants.php';
$entHtmlAfter = ob_get_clean();

echo " -> Pipeline count 'accepted' after accept: {$pipelineCounts['accepted']}\n";
echo " -> Candidate status for Vũ Đức Anh: {$applicants[0]['status']} ({$applicants[0]['status_label']})\n";

if ($pipelineCounts['accepted'] !== 1) {
    echo " -> FAILED: Expected pipelineCounts['accepted'] = 1, got {$pipelineCounts['accepted']}!\n";
    exit(1);
}

if ($applicants[0]['status'] !== 'accepted' || $applicants[0]['status_label'] !== 'Đã nhận') {
    echo " -> FAILED: Expected candidate status 'accepted' / 'Đã nhận'!\n";
    exit(1);
}
echo " -> SUCCESS: Enterprise applicant row displays '● Đã nhận' and Tab 'Đã nhận' = 1!\n";

// Step 6: School Dashboard View After Acceptance
echo "\n[Step 6] Checking School Dashboard KPI 'Thực tập đã tiếp nhận'...\n";
$schoolUser = $authService->login(['email' => 'school@talenthub.local', 'password' => '123456'], RequestId::make(null));
$schoolSession = new SessionManager(array_merge(require dirname(__DIR__) . '/config/session.php', ['name' => SessionManager::sessionNameForRole('school')]));
$schoolSession->start();
$schoolSession->login($schoolUser);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/school/index.php';

ob_start();
include dirname(__DIR__) . '/app/school/index.php';
$schoolHtmlAfter = ob_get_clean();

$acceptedKpi = $dashboard['metrics']['acceptedInternshipApplications'] ?? 0;
echo " -> School Dashboard KPI 'acceptedInternshipApplications': {$acceptedKpi}\n";
if ($acceptedKpi < 1) {
    echo " -> FAILED: Expected acceptedInternshipApplications >= 1, got {$acceptedKpi}!\n";
    exit(1);
}
echo " -> SUCCESS: School Dashboard automatically updated 'Thực tập đã tiếp nhận' = {$acceptedKpi}!\n\n";

echo "======================================================================\n";
echo " ALL INVITATION ACCEPTANCE & 4-WAY SYNC TESTS PASSED 100%!\n";
echo "======================================================================\n";
