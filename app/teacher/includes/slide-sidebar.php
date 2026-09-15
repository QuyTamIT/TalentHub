<?php
/**
 * Teacher Dashboard - Slide Sidebar Component
 *
 * Implements the left column of the 2-column slide layout.
 */

if (!function_exists('app_href') && is_file(dirname(__DIR__, 3) . '/bin/bootstrap.php')) {
    require_once dirname(__DIR__, 3) . '/bin/bootstrap.php';
}
require_once dirname(__DIR__, 2) . '/shared/BrandHeader.php';

$teacherSidebarHomeHref = function_exists('app_href') ? app_href('/app/teacher/index.php') : '../../index.php';
$logoutUrl = function_exists('app_href') ? app_href('/logout.php?role=teacher') : '/logout.php?role=teacher';

// Determine the current page based on $currentRoute
$pageTitleText = 'Tổng quan';
$introText = 'Chào mừng đến với hệ thống quản lý TalentHub dành cho Giáo viên. Theo dõi tiến độ học tập và quản lý các hoạt động ngoại khóa.';
$features = [
    ['icon' => 'grid', 'text' => 'Thống kê tổng quan'],
    ['icon' => 'trophy', 'text' => 'Quản lý hoạt động đang diễn ra'],
    ['icon' => 'users', 'text' => 'Theo dõi tình hình lớp học']
];
$pillClass = 'slide-pill--overview';

if (isset($currentRoute)) {
    if (str_starts_with($currentRoute, 'activities')) {
        $pageTitleText = 'Sân chơi của tôi';
        $introText = 'Quản lý các hoạt động, sự kiện và sân chơi dành cho học viên. Thiết lập thời gian, sức chứa và duyệt đăng ký.';
        $features = [
            ['icon' => 'calendar', 'text' => 'Lịch trình hoạt động'],
            ['icon' => 'check-circle', 'text' => 'Duyệt học viên tham gia'],
            ['icon' => 'file-text', 'text' => 'Báo cáo hoạt động']
        ];
        $pillClass = 'slide-pill--playgrounds';
    } elseif ($currentRoute === 'assessments') {
        $pageTitleText = 'Chấm điểm';
        $introText = 'Đánh giá năng lực, chấm điểm trải nghiệm thực tế và cung cấp nhận xét chi tiết cho từng học viên.';
        $features = [
            ['icon' => 'star', 'text' => 'Đánh giá theo tiêu chí'],
            ['icon' => 'edit-3', 'text' => 'Ghi nhận xét trực tiếp'],
            ['icon' => 'award', 'text' => 'Tự động tính điểm tài năng']
        ];
        $pillClass = 'slide-pill--grading';
    } elseif (str_starts_with($currentRoute, 'students')) {
        $pageTitleText = 'Học viên của tôi';
        $introText = 'Xem hồ sơ năng lực học viên, chi tiết điểm số, số giờ trải nghiệm và các kỹ năng, huy hiệu đạt được.';
        $features = [
            ['icon' => 'user-check', 'text' => 'Hồ sơ tài năng cá nhân'],
            ['icon' => 'clock', 'text' => 'Theo dõi giờ trải nghiệm'],
            ['icon' => 'shield', 'text' => 'Danh sách huy hiệu']
        ];
        $pillClass = 'slide-pill--students';
    }
}
?>

<aside class="slide-sidebar">
    <!-- Brand Logo -->
    <div class="slide-sidebar__brand">
        <a href="<?= htmlspecialchars($teacherSidebarHomeHref); ?>"
           class="slide-sidebar__brand-link"
           aria-label="Về trang chủ FTalentHub Giáo viên">
            <img src="<?= htmlspecialchars(function_exists('app_href') ? app_href('/assets/images/talenthub-logo.png') : '/assets/images/talenthub-logo.png'); ?>"
                 alt="FTalentHub Logo"
                 class="slide-sidebar__logo-img" />
        </a>
    </div>

    <!-- Title -->
    <div class="slide-sidebar__title-area">
        <h2 class="slide-sidebar__main-title">KHU VỰC GIÁO VIÊN</h2>
        <div class="slide-pill <?= $pillClass ?>">
            <?= htmlspecialchars(mb_strtoupper($pageTitleText, 'UTF-8')) ?>
        </div>
    </div>

    <!-- Intro and Features -->
    <div class="slide-sidebar__content">
        <p class="slide-sidebar__intro">
            <?= htmlspecialchars($introText) ?>
        </p>

        <ul class="slide-sidebar__features">
            <?php foreach ($features as $feature): ?>
                <li>
                    <span class="slide-sidebar__feature-icon">
                        <?php if ($feature['icon'] === 'grid'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                        <?php elseif ($feature['icon'] === 'trophy'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"></path></svg>
                        <?php elseif ($feature['icon'] === 'users'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        <?php elseif ($feature['icon'] === 'calendar'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <?php elseif ($feature['icon'] === 'check-circle'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <?php elseif ($feature['icon'] === 'file-text'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        <?php elseif ($feature['icon'] === 'star'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                        <?php elseif ($feature['icon'] === 'edit-3'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                        <?php elseif ($feature['icon'] === 'award'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                        <?php elseif ($feature['icon'] === 'user-check'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
                        <?php elseif ($feature['icon'] === 'clock'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <?php elseif ($feature['icon'] === 'shield'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <?php endif; ?>
                    </span>
                    <span><?= htmlspecialchars($feature['text']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Navigation links back to other pages -->
    <nav class="slide-sidebar__nav">
        <ul>
            <?php foreach ($sidebarNav as $navItem): ?>
                <?php
                $isActive = (isset($currentRoute) && ($navItem['route'] === $currentRoute || strpos($currentRoute, strtok($navItem['route'], '.')) === 0)) || (!isset($currentRoute) && !empty($navItem['active']));
                $navHref = $navItem['href'] ?? '#';
                ?>
                <li>
                    <a href="<?= htmlspecialchars($navHref) ?>" class="slide-sidebar__nav-link <?= $isActive ? 'is-active' : '' ?>">
                        <?= htmlspecialchars($navItem['title']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Bottom Action: Logout -->
    <div class="slide-sidebar__footer">
        <a href="<?= htmlspecialchars($logoutUrl); ?>"
           class="slide-sidebar__logout"
           aria-label="Đăng xuất khỏi hệ thống">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Đăng xuất</span>
        </a>
    </div>
</aside>
