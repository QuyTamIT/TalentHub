<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/dashboard-data.php';
require_once dirname(__DIR__) . '/shared/PortalNotificationPage.php';

$dashboardData = teacherDashboardReadData();
$teacherInfo = $dashboardData['teacherInfo'];
$backendContext = teacherDashboardBackendContext();
$session = $backendContext['session'] ?? null;
$pageTitle = 'Thông báo';
$currentRoute = 'notifications.php';
$sidebarNav = [
    ['title' => 'Tổng quan', 'route' => 'index.php', 'href' => '/app/teacher/index.php', 'icon' => 'grid', 'active' => false],
    ['title' => 'Hoạt động', 'route' => 'activities/', 'href' => '/app/teacher/activities/index.php', 'icon' => 'trophy', 'active' => false],
    ['title' => 'Chấm điểm', 'route' => 'assessments', 'href' => '/app/teacher/grading.php', 'icon' => 'clipboard-check', 'active' => false],
    ['title' => 'Học viên', 'route' => 'students', 'href' => '/app/teacher/students/index.php', 'icon' => 'users', 'active' => false],
];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Trung tâm thông báo dành cho Giáo viên TalentHub.">
    <title>Thông báo Giáo viên | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/teacher.css">
    <link rel="stylesheet" href="../../assets/css/portal-notifications.css">
</head>
<body class="teacher-dashboard">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="teacher-layout">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <div class="teacher-main-wrapper">
            <?php require __DIR__ . '/includes/header.php'; ?>
            <main class="teacher-body" id="main-content">
                <div class="teacher-container">
                    <?php renderPortalNotificationCenter('Theo dõi đăng ký hoạt động, bài đánh giá và các công việc mới cần xử lý.'); ?>
                </div>
            </main>
        </div>
    </div>
    <script src="../../assets/js/teacher.js"></script>
</body>
</html>