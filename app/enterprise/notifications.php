<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/dashboard-data.php';
require_once dirname(__DIR__) . '/shared/PortalNotificationPage.php';

$pageTitle = 'Thông báo';
$currentRoute = '/app/enterprise/notifications.php';
$sidebarNav = [
    ['title' => 'Tổng quan', 'route' => '/app/enterprise/index.php', 'icon' => 'grid', 'active' => false],
    ['title' => 'Tìm nhân tài', 'route' => '/app/enterprise/talents.php', 'icon' => 'search-users', 'active' => false],
    ['title' => 'Tuyển thực tập', 'route' => '/app/enterprise/internships/', 'icon' => 'briefcase', 'active' => false],
    ['title' => 'Tài trợ dự án', 'route' => '/app/enterprise/sponsorships/', 'icon' => 'award', 'active' => false],
    ['title' => 'Hồ sơ doanh nghiệp', 'route' => '/app/enterprise/profile.php', 'icon' => 'building', 'active' => false],
];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Trung tâm thông báo dành cho Doanh nghiệp TalentHub.">
    <title>Thông báo Doanh nghiệp | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/enterprise.css">
    <link rel="stylesheet" href="../../assets/css/portal-notifications.css">
</head>
<body class="enterprise-dashboard">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="ent-layout">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <div class="ent-main-wrapper">
            <?php require __DIR__ . '/includes/header.php'; ?>
            <main class="ent-body" id="main-content">
                <div class="container-fluid">
                    <?php renderPortalNotificationCenter('Theo dõi hồ sơ ứng tuyển, kết nối nhân tài và các cập nhật tuyển dụng mới nhất.'); ?>
                </div>
            </main>
        </div>
    </div>
    <script src="../../assets/js/enterprise.js"></script>
</body>
</html>