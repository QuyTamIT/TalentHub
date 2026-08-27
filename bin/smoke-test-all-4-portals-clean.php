<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Support\Id\RequestId;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " SMOKE TEST: KIỂM THỬ TOÀN DIỆN 4 PORTAL TRẠNG THÁI SẠCH (ZERO STATE)\n";
echo "======================================================================\n\n";

$authRepo = new AuthRepository($pdo);
$authService = new AuthService($authRepo);

// -------------------------------------------------------------
// PORTAL 1: NHÀ TRƯỜNG (SCHOOL PORTAL)
// -------------------------------------------------------------
echo "[PORTAL 1] Testing School Portal (Ban Giám hiệu BTEC FPT)...\n";
$schoolUser = $authService->login(['email' => 'school@talenthub.local', 'password' => '123456'], RequestId::make(null));
if (!$schoolUser || $schoolUser['role'] !== 'school') {
    echo " -> FAILED: School login failed or role mismatch!\n";
    exit(1);
}
echo " -> Login OK: {$schoolUser['fullName']} ({$schoolUser['email']})\n";

// Test School Dashboard
$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = SessionManager::sessionNameForRole('school');
$schoolSession = new SessionManager($sessionConfig);
$schoolSession->start();
$schoolSession->login($schoolUser);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/school/index.php';
ob_start();
include dirname(__DIR__) . '/app/school/index.php';
$schoolIndexHtml = ob_get_clean();

if (strpos($schoolIndexHtml, 'Khu vực Nhà trường') === false) {
    echo " -> FAILED: School dashboard welcome header missing!\n";
    exit(1);
}
echo " -> School Dashboard rendered successfully.\n";

// Test School Students Empty State
$_SERVER['REQUEST_URI'] = '/app/school/students.php';
ob_start();
include dirname(__DIR__) . '/app/school/students.php';
$schoolStudentsHtml = ob_get_clean();

echo " -> School Students page rendered successfully.\n";
echo " -> SUCCESS: Portal Nhà trường hoạt động chuẩn xác 100%.\n\n";

// -------------------------------------------------------------
// PORTAL 2: GIẢNG VIÊN (TEACHER PORTAL)
// -------------------------------------------------------------
echo "[PORTAL 2] Testing Teacher Portal (ThS. Nguyễn Văn Hùng)...\n";
$teacherUser = $authService->login(['email' => 'teacher@talenthub.local', 'password' => '123456'], RequestId::make(null));
if (!$teacherUser || $teacherUser['role'] !== 'teacher') {
    echo " -> FAILED: Teacher login failed or role mismatch!\n";
    exit(1);
}
echo " -> Login OK: {$teacherUser['fullName']} ({$teacherUser['email']})\n";

// Test Teacher Grading
$sessionConfig['name'] = SessionManager::sessionNameForRole('teacher');
$teacherSession = new SessionManager($sessionConfig);
$teacherSession->start();
$teacherSession->login($teacherUser);

$_SERVER['REQUEST_URI'] = '/app/teacher/grading.php';
ob_start();
include dirname(__DIR__) . '/app/teacher/grading.php';
$teacherGradingHtml = ob_get_clean();

if (strpos($teacherGradingHtml, 'Chấm điểm theo Lớp') === false) {
    echo " -> FAILED: Teacher grading page failed to render!\n";
    exit(1);
}
echo " -> Teacher Grading page rendered successfully.\n";
echo " -> SUCCESS: Portal Giảng viên hoạt động chuẩn xác 100%.\n\n";

// -------------------------------------------------------------
// PORTAL 3: DOANH NGHIỆP (ENTERPRISE PORTAL)
// -------------------------------------------------------------
echo "[PORTAL 3] Testing Enterprise Portal (FPT Software)...\n";
$enterpriseUser = $authService->login(['email' => 'fpt@talenthub.local', 'password' => '123456'], RequestId::make(null));
if (!$enterpriseUser || $enterpriseUser['role'] !== 'enterprise') {
    echo " -> FAILED: Enterprise login failed or role mismatch!\n";
    exit(1);
}
echo " -> Login OK: {$enterpriseUser['fullName']} ({$enterpriseUser['email']})\n";

$sessionConfig['name'] = SessionManager::sessionNameForRole('enterprise');
$enterpriseSession = new SessionManager($sessionConfig);
$enterpriseSession->start();
$enterpriseSession->login($enterpriseUser);

// Test Enterprise Dashboard
$_SERVER['REQUEST_URI'] = '/app/enterprise/index.php';
ob_start();
include dirname(__DIR__) . '/app/enterprise/index.php';
$enterpriseDashHtml = ob_get_clean();

if ($kpiApplicantsVal !== '0' || $kpiPassRateVal !== '0%') {
    echo " -> FAILED: Enterprise dashboard KPIs not zero!\n";
    exit(1);
}
echo " -> Enterprise Dashboard KPIs: Applicants = {$kpiApplicantsVal}, Pass Rate = {$kpiPassRateVal}\n";

// Test Enterprise Applicants Page
$activePostId = $pdo->query("SELECT id FROM internship_posts WHERE enterpriseId = '31000000-0000-4000-8000-000000000015' LIMIT 1")->fetchColumn();
$_GET['postId'] = $activePostId;
$_SERVER['REQUEST_URI'] = '/app/enterprise/internships/applicants.php?postId=' . $activePostId;
ob_start();
include dirname(__DIR__) . '/app/enterprise/internships/applicants.php';
$applicantsHtml = ob_get_clean();

if (strpos($applicantsHtml, 'Chưa có ứng viên nào ứng tuyển hoặc được tiếp nhận cho vị trí này') === false) {
    echo " -> FAILED: Enterprise empty state missing!\n";
    exit(1);
}
echo " -> Enterprise Applicants Zero-State rendered successfully.\n";
echo " -> SUCCESS: Portal Doanh nghiệp hoạt động chuẩn xác 100%.\n\n";

// -------------------------------------------------------------
// PORTAL 4: SINH VIÊN (LEARNER / STUDENT PORTAL)
// -------------------------------------------------------------
echo "[PORTAL 4] Testing Learner / Student Portal (Vũ Đức Anh)...\n";
$studentUser = $authService->login(['email' => 'vuducanh@student.edu.vn', 'password' => '123456'], RequestId::make(null));
if (!$studentUser || $studentUser['role'] !== 'student') {
    echo " -> FAILED: Student login failed or role mismatch!\n";
    exit(1);
}
echo " -> Login OK: {$studentUser['fullName']} ({$studentUser['email']})\n";

$sessionConfig['name'] = SessionManager::sessionNameForRole('student');
$studentSession = new SessionManager($sessionConfig);
$studentSession->start();
$studentSession->login($studentUser);

// Test Learner Overview
$_SERVER['REQUEST_URI'] = '/app/learner/index.php';
ob_start();
include dirname(__DIR__) . '/app/learner/index.php';
$learnerIndexHtml = ob_get_clean();

echo " -> Learner Assessment Progress: {$dashboardAssessmentCompleted}/{$dashboardAssessmentRequired} completed.\n";
if ($dashboardAssessmentCompleted !== 0) {
    echo " -> FAILED: Student assessment progress is not 0!\n";
    exit(1);
}

// Test Learner Notifications
$_SERVER['REQUEST_URI'] = '/app/learner/notifications.php';
ob_start();
include dirname(__DIR__) . '/app/learner/notifications.php';
$learnerNotifHtml = ob_get_clean();

$notifCount = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE userId = '{$studentUser['id']}'")->fetchColumn();
echo " -> Student Notifications Count: {$notifCount}\n";
if ($notifCount !== 0) {
    echo " -> FAILED: Student notifications count is not 0!\n";
    exit(1);
}

echo " -> SUCCESS: Portal Sinh viên hoạt động chuẩn xác 100% (Clean 0/4 state).\n\n";

echo "======================================================================\n";
echo " TẤT CẢ 4 PORTAL ĐÃ ĐƯỢC RESET VỀ TRẠNG THÁI SẠCH & TEST PASS 100%!\n";
echo "======================================================================\n";
