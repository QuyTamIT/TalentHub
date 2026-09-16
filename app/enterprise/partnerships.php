<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/dashboard-data.php';

$context['permissions']->require((string) $user['id'], 'partnership.read_own_business');
$partnerSchools = [];
$loadError = null;
try {
    $partnerSchools = $partnershipService->listApprovedSchoolsForEnterprise((string) $user['id'])['items'];
} catch (\TalentHub\Http\ApiException $exception) {
    http_response_code($exception->status);
    $loadError = $exception->getMessage();
} catch (\Throwable $exception) {
    error_log('Enterprise partnerships fetch failed: ' . $exception->getMessage());
    http_response_code(500);
    $loadError = 'Không thể tải danh sách đối tác. Vui lòng tải lại trang.';
}

$pageTitle = 'Đối tác trường học';
$currentRoute = '/app/enterprise/partnerships.php';
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
    <meta name="color-scheme" content="light">
    <title>Đối tác trường học | FTalentHub</title>
    <?php foreach (['home', 'global', 'brand-component', 'polish', 'enterprise'] as $stylesheet): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(app_href('/assets/css/' . $stylesheet . '.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
</head>
<body class="enterprise-dashboard">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="ent-layout">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>
        <div class="ent-main-wrapper">
            <?php require __DIR__ . '/includes/header.php'; ?>
            <main class="ent-body" id="main-content">
                <div class="container-fluid">
                    <h1 class="ent-page-title">Đối tác trường học</h1>
                    <p>Các trường đang hợp tác với doanh nghiệp của bạn.</p>
                    <?php if ($loadError !== null): ?>
                        <p role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php elseif ($partnerSchools === []): ?>
                        <div class="ent-empty-state">
                            <h2 class="ent-empty-state__title">Chưa có trường đối tác</h2>
                            <p class="ent-empty-state__desc">Trường sẽ xuất hiện tại đây khi nhà trường thêm doanh nghiệp làm đối tác.</p>
                        </div>
                    <?php else: ?>
                        <table class="ent-table">
                            <caption><?= count($partnerSchools); ?> trường đang hợp tác</caption>
                            <thead><tr><th scope="col">Trường học</th><th scope="col">Cấp đào tạo</th><th scope="col">Trạng thái</th></tr></thead>
                            <tbody>
                                <?php foreach ($partnerSchools as $school): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) $school['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars((string) ($school['level'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>Đang hợp tác</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <script src="<?= htmlspecialchars(app_href('/assets/js/enterprise.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
