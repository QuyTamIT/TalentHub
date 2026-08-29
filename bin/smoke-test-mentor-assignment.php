<?php
declare(strict_types=1);

define('TEST_MODE', true);

require __DIR__ . '/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Support\Id\RequestId;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " SMOKE TEST: KIỂM THỬ PHÂN CÔNG MENTOR GIÁM SÁT THỰC TẬP (SCHOOL PORTAL)\n";
echo "======================================================================\n\n";

$authRepo = new AuthRepository($pdo);
$authService = new AuthService($authRepo);

// Step 1: Login as School Admin
echo "[Step 1] Logging in as School Admin (school@talenthub.local)...\n";
$schoolUser = $authService->login(['email' => 'school@talenthub.local', 'password' => '123456'], RequestId::make(null));
$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = SessionManager::sessionNameForRole('school');
$schoolSession = new SessionManager($sessionConfig);
$schoolSession->start();
$schoolSession->login($schoolUser);
echo " -> Login OK: {$schoolUser['fullName']}\n";

$schoolRepo = new TalentHub\Modules\School\Repository\SchoolRepository($pdo);
$schoolService = new TalentHub\Modules\School\Service\SchoolDashboardService($schoolRepo, $pdo);

// Find accepted application for Vũ Đức Anh
$appRow = $pdo->query("
    SELECT ia.id, ia.postId, ia.studentId, u.fullName as studentName
    FROM internship_applications ia
    JOIN student_profiles sp ON sp.id = ia.studentId
    JOIN users u ON u.id = sp.userId
    WHERE ia.status = 'accepted'
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$appRow) {
    echo " -> FAILED: No accepted application found for test!\n";
    exit(1);
}
$appId = $appRow['id'];
echo " -> Target Application: {$appId} (Student: {$appRow['studentName']})\n";

// Find Teacher ThS. Nguyễn Văn Hùng
$teacher = $pdo->query("
    SELECT tp.id, u.fullName, u.email
    FROM teacher_profiles tp
    JOIN users u ON u.id = tp.userId
    WHERE u.email = 'teacher@talenthub.local' OR u.fullName LIKE '%Nguyễn Văn Hùng%'
    ORDER BY (u.email = 'teacher@talenthub.local') DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    echo " -> FAILED: Teacher ThS. Nguyễn Văn Hùng not found!\n";
    exit(1);
}
$teacherProfileId = $teacher['id'];
echo " -> Target Mentor: {$teacherProfileId} ({$teacher['fullName']})\n";

// Step 2: Test assigning Mentor ThS. Nguyễn Văn Hùng via Service
echo "\n[Step 2] Assigning Mentor to Application via Service...\n";
$assignRes = $schoolService->assignInternshipMentor($schoolUser['id'], $appId, $teacherProfileId);
echo " -> Service response: mentorName = '{$assignRes['mentorName']}', mentorTeacherId = '{$assignRes['mentorTeacherId']}'\n";

$dbCheck = $pdo->prepare("SELECT * FROM internship_mentor_assignments WHERE applicationId = ?");
$dbCheck->execute([$appId]);
$assignmentRecord = $dbCheck->fetch(PDO::FETCH_ASSOC);

if (!$assignmentRecord || $assignmentRecord['mentorTeacherId'] !== $teacherProfileId) {
    echo " -> FAILED: Database assignment record does not match teacher ID!\n";
    exit(1);
}
echo " -> SUCCESS: Database updated with mentorTeacherId = '{$teacherProfileId}'!\n";

// Step 3: Test unassigning Mentor (passing empty string / null)
echo "\n[Step 3] Testing unassigning Mentor (passing empty string)...\n";
$unassignRes = $schoolService->assignInternshipMentor($schoolUser['id'], $appId, '');
$dbCheck->execute([$appId]);
$assignmentAfterUnassign = $dbCheck->fetch(PDO::FETCH_ASSOC);

if ($assignmentAfterUnassign) {
    echo " -> FAILED: Database assignment record was not deleted after unassigning!\n";
    exit(1);
}
echo " -> SUCCESS: Unassigned mentor safely without throwing RuntimeException!\n";

// Step 4: Test AJAX POST handler in app/school/internships.php
echo "\n[Step 4] Testing AJAX POST to app/school/internships.php...\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_POST['csrfToken'] = $schoolSession->csrfToken();
$_POST['applicationId'] = $appId;
$_POST['mentorTeacherId'] = $teacherProfileId;
$_POST['ajax'] = '1';

ob_start();
include dirname(__DIR__) . '/app/school/internships.php';
$ajaxOutput = ob_get_clean();

echo " -> AJAX Response: {$ajaxOutput}\n";
$json = json_decode($ajaxOutput, true);

if (!isset($json['success']) || $json['success'] !== true) {
    echo " -> FAILED: AJAX response is not success: true!\n";
    exit(1);
}
echo " -> SUCCESS: AJAX response confirmed success with message: '{$json['message']}'!\n";

// Step 5: Test rendering GET app/school/internships.php
echo "\n[Step 5] Testing GET app/school/internships.php...\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
unset($_SERVER['HTTP_X_REQUESTED_WITH']);
unset($_POST['ajax']);
unset($_POST['applicationId']);
unset($_POST['mentorTeacherId']);

ob_start();
include dirname(__DIR__) . '/app/school/internships.php';
$pageHtml = ob_get_clean();

if (strpos($pageHtml, 'ThS. Nguyễn Văn Hùng') === false) {
    echo " -> FAILED: Teacher ThS. Nguyễn Văn Hùng not rendered in HTML select!\n";
    exit(1);
}
if (strpos($pageHtml, 'selected') === false) {
    echo " -> FAILED: Teacher option was not marked selected!\n";
    exit(1);
}
echo " -> SUCCESS: Page rendered with selected mentor 'ThS. Nguyễn Văn Hùng'!\n\n";

echo "======================================================================\n";
echo " ALL MENTOR ASSIGNMENT TESTS PASSED 100%!\n";
echo "======================================================================\n";
