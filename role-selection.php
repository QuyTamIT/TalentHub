<?php
require_once __DIR__ . '/app/shared/BrandHeader.php';
/**
 * TalentHub - Role Selection Page
 * Allows visitors to choose the role they want to register.
 *
 * Note for Junior Developers:
 * - This page is accessed after clicking "Vào app" or "Trải nghiệm ngay" from Home.
 * - Shared CSS design tokens are loaded from assets/css/home.css
 * - Shared CSS design tokens are loaded from assets/css/global.css
 * - Scoped role selection styles are defined in assets/css/role-selection.css
 * - Interaction and fallback handlers are in assets/js/role-selection.js
 */
require __DIR__ . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;

$session = new SessionManager(require __DIR__ . '/config/session.php');
$session->start();

$roleSelectionError = isset($_GET['error']) ? (string) $_GET['error'] : null;
$roleSelectionHint = isset($_GET['hint']) ? (string) $_GET['hint'] : null;
$roleSelectionErrorMessages = [
    'student_profile_missing' => [
        'title' => 'Tài khoản chưa có hồ sơ học viên',
        'description' => 'Tài khoản của bạn chưa được liên kết với hồ sơ học viên. Vui lòng liên hệ quản trị viên để được hỗ trợ.',
    ],
    'school_missing' => [
        'title' => 'Tài khoản chưa liên kết nhà trường',
        'description' => 'Tài khoản của bạn chưa được liên kết với nhà trường. Vui lòng liên hệ quản trị viên để được hỗ trợ.',
    ],
    'enterprise_missing' => [
        'title' => 'Tài khoản chưa liên kết doanh nghiệp',
        'description' => 'Tài khoản của bạn chưa được liên kết với doanh nghiệp. Vui lòng liên hệ quản trị viên để được hỗ trợ.',
    ],
];
$roleSelectionErrorMessage = $roleSelectionError !== null
    ? ($roleSelectionErrorMessages[$roleSelectionError] ?? [
        'title' => 'Không thể truy cập khu vực này',
        'description' => 'Đã xảy ra lỗi khi vào khu vực bạn yêu cầu.',
    ])
    : null;

$roles = [
    [
        'id' => 'learner',
        'title' => 'Học sinh / Sinh viên',
        'description' => 'Khám phá năng khiếu, xây dựng hồ sơ năng lực và tham gia hoạt động.',
        'cta' => 'Đăng ký học viên',
        'route' => 'register.php',
        'is_popular' => true,
        'badge' => 'Phổ biến nhất',
        'icon_type' => 'student',
    ],
    [
        'id' => 'teacher',
        'title' => 'Giáo viên / Cố vấn',
        'description' => 'Quản lý hoạt động, theo dõi và đánh giá năng lực người học.',
        'cta' => 'Đăng ký giáo viên',
        'route' => 'register-teacher.php',
        'is_popular' => false,
        'icon_type' => 'teacher',
    ],
    [
        'id' => 'school',
        'title' => 'Nhà trường',
        'description' => 'Theo dõi năng lực, lớp học, phân tích và báo cáo toàn trường.',
        'cta' => 'Đăng ký nhà trường',
        'route' => 'register-school.php',
        'is_popular' => false,
        'icon_type' => 'school',
    ],
    [
        'id' => 'enterprise',
        'title' => 'Doanh nghiệp',
        'description' => 'Tìm kiếm nhân tài, tuyển thực tập và tài trợ dự án.',
        'cta' => 'Đăng ký doanh nghiệp',
        'route' => 'register-enterprise.php',
        'is_popular' => false,
        'icon_type' => 'enterprise',
    ],
];

foreach ($roles as &$role) {
    $role['registration_href'] = app_href('/' . $role['route']);
}
unset($role);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="./assets/images/logo.svg" type="image/svg+xml">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub - Chọn vai trò để bắt đầu đăng ký tài khoản phù hợp.">
    <title>Chọn vai trò đăng ký | TalentHub</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/brand-component.css">
    <link rel="stylesheet" href="assets/css/role-selection.css">
    <link rel="stylesheet" href="assets/css/polish.css">
</head>
<body class="role-selection-page">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>

    <!-- Header / Top Bar -->
    <header class="role-selection-header">
        <div class="container role-selection-header__container">
            <!-- Brand Logo -->
            <?php renderBrandHeader('index.php', 'Lựa chọn khu vực', 'Về trang chủ FTalentHub'); ?>

            <!-- Back to Home Button -->
            <a href="index.php" class="btn btn-secondary role-selection-header__back-btn" aria-label="Quay lại trang chủ">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                <span>Quay lại trang chủ</span>
            </a>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="role-selection-main" id="main-content">
        <div class="container">
            <!-- Section Header -->
            <div class="role-selection-intro">
                <span class="section-tag">Bắt đầu cùng TalentHub</span>
                <h1 class="role-selection-title">Bạn muốn đăng ký với vai trò nào?</h1>
                <p class="role-selection-description">
                    Chọn vai trò phù hợp để tiếp tục đến biểu mẫu đăng ký dành riêng cho bạn.
                </p>
            </div>

            <?php if ($roleSelectionErrorMessage !== null): ?>
                <div class="role-selection-alert role-selection-alert--warning" role="alert">
                    <strong><?= htmlspecialchars($roleSelectionErrorMessage['title']); ?></strong>
                    <span><?= htmlspecialchars($roleSelectionErrorMessage['description']); ?></span>
                    <?php if ($roleSelectionHint !== null): ?>
                        <code class="role-selection-alert__hint"><?= htmlspecialchars($roleSelectionHint); ?></code>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Role Cards Grid -->
            <div class="role-cards-grid">
                <?php foreach ($roles as $role): ?>
                    <a href="<?= htmlspecialchars($role['registration_href']); ?>"
                         class="role-card <?= $role['is_popular'] ? 'role-card--popular' : ''; ?>"
                         data-route="<?= htmlspecialchars($role['registration_href']); ?>"
                         data-role-name="<?= htmlspecialchars($role['title']); ?>"
                         aria-label="Chọn vai trò <?= htmlspecialchars($role['title']); ?>">

                        <?php if ($role['is_popular']): ?>
                            <span class="role-card__badge"><?= htmlspecialchars($role['badge']); ?></span>
                        <?php endif; ?>

                        <div class="role-card__icon role-card__icon--<?= htmlspecialchars($role['icon_type']); ?>">
                            <?php if ($role['icon_type'] === 'student'): ?>
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                                </svg>
                            <?php elseif ($role['icon_type'] === 'teacher'): ?>
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            <?php elseif ($role['icon_type'] === 'school'): ?>
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 21h18"></path>
                                    <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path>
                                    <path d="M9 10h2v2H9zM13 10h2v2h-2zM9 14h2v2H9zM13 14h2v2h-2z"></path>
                                </svg>
                            <?php else: ?>
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                </svg>
                            <?php endif; ?>
                        </div>

                        <h2 class="role-card__title"><?= htmlspecialchars($role['title']); ?></h2>
                        <p class="role-card__description"><?= htmlspecialchars($role['description']); ?></p>

                        <span class="btn <?= $role['is_popular'] ? 'btn-primary' : 'btn-secondary'; ?> role-card__cta" aria-hidden="true">
                            <?= htmlspecialchars($role['cta']); ?>
                            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Footer Note -->
            <div class="role-selection-note">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <span>Sau khi đăng ký thành công, bạn sẽ được chuyển đến trang đăng nhập. Hồ sơ tổ chức và giáo viên có thể cần được xác minh trước khi kích hoạt.</span>
            </div>
        </div>
    </main>

    <!-- JavaScript Assets -->
    <script src="assets/js/role-selection.js"></script>
</body>
</html>
