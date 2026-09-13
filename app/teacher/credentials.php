<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/dashboard-data.php';

use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolCredentialManagementRepository;
use TalentHub\Modules\School\Service\SchoolCredentialManagementService;
use TalentHub\Rbac\Service\PermissionService;
use TalentHub\Support\Id\RequestId;

$backend = teacherDashboardBackendContext();
$pdo = $backend['pdo'] ?? null;
$session = $backend['session'] ?? null;
$user = is_array($backend['user'] ?? null) ? $backend['user'] : [];
$userId = (string) ($user['id'] ?? '');
$dashboardData = teacherDashboardReadData();
$teacherInfo = $dashboardData['teacherInfo'];
$pageTitle = 'Huy hiệu & Chứng chỉ';
$currentRoute = 'credentials.php';
$flash = null;
$error = null;
$data = [];

$sidebarNav = [
    ['title' => 'Tổng quan', 'route' => 'index.php', 'href' => '/app/teacher/index.php', 'icon' => 'grid', 'active' => false],
    ['title' => 'Hoạt động', 'route' => 'activities/', 'href' => '/app/teacher/activities/index.php', 'icon' => 'trophy', 'active' => false],
    ['title' => 'Chấm điểm', 'route' => 'assessments', 'href' => '/app/teacher/grading.php', 'icon' => 'clipboard-check', 'active' => false],
    ['title' => 'Học viên', 'route' => 'students', 'href' => '/app/teacher/students/index.php', 'icon' => 'users', 'active' => false],
    ['title' => 'Huy hiệu & Chứng chỉ', 'route' => 'credentials.php', 'href' => '/app/teacher/credentials.php', 'icon' => 'award', 'active' => true],
];

try {
    if (!$pdo instanceof PDO || !$session instanceof \TalentHub\Auth\Session\SessionManager || $userId === '') {
        throw new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ dữ liệu chưa sẵn sàng.');
    }
    $permissions = new PermissionService($pdo);
    $permissions->require($userId, 'school_credential.manage_own');
    $service = new SchoolCredentialManagementService(new SchoolCredentialManagementRepository($pdo));

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $requestId = RequestId::make(null);
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create_badge') {
            $service->createBadge($userId, $_POST, $requestId);
            $flash = 'Đã tạo mẫu huy hiệu.';
        } elseif ($action === 'create_certificate') {
            $service->createCertificateCatalog($userId, $_POST + [
                'criteria' => [],
                'recommendationProfile' => [],
                'recommendationEnabled' => false,
                'status' => 'active',
            ], $requestId);
            $flash = 'Đã tạo mẫu chứng chỉ.';
        } elseif ($action === 'award_credential') {
            $kind = (string) ($_POST['credentialKind'] ?? 'badge');
            $result = $kind === 'certificate'
                ? $service->issueCertificateToTarget($userId, $_POST, $requestId)
                : $service->awardBadgeToTarget($userId, $_POST, $requestId);
            $flash = sprintf('Đã ghi nhận cho %d sinh viên (%d mới, %d cập nhật).', $result['total'], $result['created'], $result['updated']);
        } elseif ($action === 'revoke_certificate') {
            $service->revokeCertificate($userId, (string) ($_POST['awardId'] ?? ''), (string) ($_POST['reason'] ?? ''), $requestId);
            $flash = 'Đã thu hồi chứng chỉ.';
        } else {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Thao tác thành tích không hợp lệ.');
        }
    }
    $data = $service->dashboard($userId);
} catch (ApiException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('[teacher-credentials] ' . $exception->getMessage());
    $error = 'Không thể tải dữ liệu thành tích lúc này.';
}

$credentialData = $data;
$credentialCsrfToken = $session instanceof \TalentHub\Auth\Session\SessionManager ? $session->csrfToken() : '';
$credentialIssuerName = (string) ($teacherInfo['school_name'] ?? 'Nhà trường');
$credentialPortal = 'teacher';
$credentialFlash = $flash;
$credentialError = $error;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | TalentHub Teacher</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/teacher.css">
    <link rel="stylesheet" href="../../assets/css/credential-management.css">
</head>
<body class="teacher-dashboard">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="teacher-layout">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <div class="teacher-main-wrapper">
            <?php require __DIR__ . '/includes/header.php'; ?>
            <main class="teacher-body" id="main-content">
                <div class="teacher-container">
                    <?php require dirname(__DIR__) . '/shared/credential-management-view.php'; ?>
                </div>
            </main>
        </div>
    </div>
    <script src="../../assets/js/teacher.js"></script>
    <script src="../../assets/js/credential-management.js"></script>
</body>
</html>
