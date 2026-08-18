<?php
/**
 * TalentHub - Role Selection Page
 * Allows visitors to choose their area (Learner, Teacher, School, Enterprise).
 * 
 * Note for Junior Developers:
 * - This page is accessed after clicking "Vào app" or "Trải nghiệm ngay" from Home.
 * - Shared CSS design tokens are loaded from assets/css/home.css
 * - Scoped role selection styles are defined in assets/css/role-selection.css
 * - Interaction and fallback handlers are in assets/js/role-selection.js
 */

$roles = [
    [
        'id' => 'learner',
        'title' => 'Học sinh / Sinh viên',
        'description' => 'Khám phá năng khiếu, xây dựng hồ sơ năng lực và tham gia hoạt động.',
        'cta' => 'Vào khu vực này',
        'route' => 'app/learner/index.php',
        'is_popular' => true,
        'badge' => 'Phổ biến nhất',
        'icon_type' => 'student'
    ],
    [
        'id' => 'teacher',
        'title' => 'Giáo viên / HLV',
        'description' => 'Quản lý hoạt động, theo dõi và đánh giá năng lực người học.',
        'cta' => 'Vào khu vực này',
        'route' => 'app/teacher/index.php',
        'is_popular' => false,
        'icon_type' => 'teacher'
    ],
    [
        'id' => 'school',
        'title' => 'Nhà trường',
        'description' => 'Theo dõi năng lực, lớp học, phân tích và báo cáo toàn trường.',
        'cta' => 'Vào khu vực này',
        'route' => 'app/school/index.php',
        'is_popular' => false,
        'icon_type' => 'school'
    ],
    [
        'id' => 'enterprise',
        'title' => 'Doanh nghiệp',
        'description' => 'Tìm kiếm nhân tài, tuyển thực tập và tài trợ dự án.',
        'cta' => 'Vào khu vực này',
        'route' => 'app/enterprise/index.php',
        'is_popular' => false,
        'icon_type' => 'enterprise'
    ]
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="./assets/images/logo.svg" type="image/svg+xml">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub - Chọn vai trò sử dụng để trải nghiệm các tính năng phù hợp.">
    <title>Chọn Vai Trò Trải Nghiệm - TalentHub</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/role-selection.css">
</head>
<body class="role-selection-page">

    <!-- Header / Top Bar -->
    <header class="role-selection-header">
        <div class="container role-selection-header__container">
            <!-- Brand Logo -->
            <a href="index.php" class="site-header__brand" aria-label="Về trang chủ TalentHub">
                <div class="site-header__brand-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                </div>
                <div class="site-header__brand-text">Talent<span>Hub</span></div>
            </a>

            <!-- Back to Home Button -->
            <a href="index.php" class="btn btn-secondary role-selection-header__back-btn" aria-label="Quay lại trang chủ">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Quay lại trang chủ
            </a>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="role-selection-main">
        <div class="container">
            <!-- Section Header -->
            <div class="role-selection-intro">
                <span class="section-tag">✨ Chọn vai trò trải nghiệm</span>
                <h1 class="role-selection-title">Bạn đang đăng nhập với vai trò nào?</h1>
                <p class="role-selection-description">
                    Vui lòng chọn vai trò phù hợp để truy cập giao diện và các tính năng được tối ưu hóa cho bạn.
                </p>
            </div>

            <!-- Role Cards Grid -->
            <div class="role-cards-grid">
                <?php foreach ($roles as $role): ?>
                    <div class="role-card <?= $role['is_popular'] ? 'role-card--popular' : ''; ?>" 
                         data-route="<?= htmlspecialchars($role['route']); ?>" 
                         data-role-name="<?= htmlspecialchars($role['title']); ?>"
                         tabindex="0"
                         role="button"
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

                        <!-- TODO: Future route navigation target: <?= htmlspecialchars($role['route']); ?> -->
                        <a href="<?= htmlspecialchars($role['route']); ?>" class="btn <?= $role['is_popular'] ? 'btn-primary' : 'btn-secondary'; ?> role-card__cta">
                            <?= htmlspecialchars($role['cta']); ?>
                            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer Note -->
            <div class="role-selection-note">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <span>Lưu ý: Bạn có thể truy cập đúng khu vực tính năng của TalentHub dựa trên vai trò của mình.</span>
            </div>
        </div>
    </main>

    <!-- Notification Toast Element (Fallback feedback for non-existent routes) -->
    <div class="role-toast" id="role-toast" aria-live="polite" aria-atomic="true">
        <div class="role-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 8v4l3 3"></path>
            </svg>
            <span class="role-toast__message">Khu vực đang được phát triển!</span>
        </div>
    </div>

    <!-- JavaScript Assets -->
    <script src="assets/js/role-selection.js"></script>
</body>
</html>
