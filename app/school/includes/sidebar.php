<?php
require_once dirname(__DIR__, 2) . '/shared/BrandHeader.php';
/**
 * School Dashboard - Sidebar Component
 *
 * Expects the following variables from the parent scope:
 *   - $schoolInfo  (array) resolved by the SchoolAppContext
 *   - $currentRoute (string) route path used to highlight the active item
 */

if (!isset($schoolInfo)) {
    $schoolInfo = [
        'name'          => 'Trường học',
        'logo_initials' => 'TH',
        'level'         => '',
        'district'      => '',
        'academic_year' => '',
    ];
}
$sidebarNav = [
    [
        'title'  => 'Tổng quan',
        'route'  => '/app/school/',
        'icon'   => 'grid',
    ],
    [
        'title' => 'Học sinh / Sinh viên',
        'route' => '/app/school/students.php',
        'icon'  => 'user',
    ],
    [
        'title' => 'Giảng viên',
        'route' => '/app/school/teachers.php',
        'icon'  => 'users',
    ],
    [
        'title' => 'Lớp & Chuyên ngành',
        'route' => '/app/school/classes.php',
        'icon'  => 'book',
    ],
    [
        'title' => 'Đối tác',
        'route' => '/app/school/partnerships.php',
        'icon' => 'users',
    ],
    [
        'title' => 'Dự án',
        'route' => '/app/school/projects.php',
        'icon' => 'book',
    ],
    [
        'title' => 'Thực tập',
        'route' => '/app/school/internships.php',
        'icon' => 'file-text',
    ],
    [
        'title' => 'Phân tích',
        'route' => '/app/school/analytics.php',
        'icon'  => 'trending-up',
    ],
    [
        'title' => 'Báo cáo',
        'route' => '/app/school/reports.php',
        'icon'  => 'file-text',
    ],
    [
        'title' => 'Hồ sơ & Tài khoản',
        'route' => '/app/school/account.php',
        'icon'  => 'user',
    ],
];
?>
<!-- Sidebar Overlay Backdrop for Mobile -->
<div class="school-sidebar-backdrop" id="school-sidebar-backdrop" aria-hidden="true"></div>

<aside class="school-sidebar" id="school-sidebar">
    <!-- Brand Logo -->
    <div class="school-sidebar__brand">
        <?php renderBrandHeader('../../index.php', 'Khu vực Nhà trường', 'Về trang chủ FTalentHub'); if (false): ?>
            <span class="learner-brand__mark" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>
                </svg>
            </span>
            <div class="learner-brand__text">
                <span class="learner-brand__name">FTalent<span>Hub</span></span>
                <span class="learner-brand__subtitle">Khu vực Nhà trường</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Navigation List -->
    <nav class="school-sidebar__nav" aria-label="Điều hướng Nhà trường">
        <div class="school-sidebar__nav-title">QUẢN LÝ TRƯỜNG HỌC</div>
        <ul>
            <?php
            $currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            foreach ($sidebarNav as $navItem):
                $routePath = parse_url($navItem['route'], PHP_URL_PATH) ?: $navItem['route'];
                $routeBase = basename($routePath);
                $isActive  = ($routeBase !== '' && $routeBase === $currentPage)
                    || (isset($currentRoute) && $navItem['route'] === $currentRoute);
            ?>
                <li>
                    <a href="<?= htmlspecialchars(app_href($navItem['route'])); ?>"
                       class="school-sidebar__link <?= $isActive ? 'is-active' : ''; ?>">
                        <span class="school-sidebar__icon">
                            <?php if ($navItem['icon'] === 'grid'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'trending-up'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                    <polyline points="17 6 23 6 23 12"></polyline>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'file-text'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'users'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'user'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'book'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'cog'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'lock'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            <?php else: ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                            <?php endif; ?>
                        </span>
                        <span><?= htmlspecialchars($navItem['title']); ?></span>
                        <?php if (isset($navItem['badge'])): ?>
                            <span class="school-sidebar__badge-count"><?= $navItem['badge']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Bottom Action: Logout -->
    <div class="school-sidebar__footer">
        <a href="<?= function_exists('app_href') ? app_href('/app/auth/logout.php?role=school') : '/app/auth/logout.php?role=school'; ?>" 
           class="school-sidebar__link school-sidebar__link--logout"
           aria-label="Đăng xuất khỏi hệ thống">
            <span class="school-sidebar__icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </span>
            <span>Đăng xuất</span>
        </a>
    </div>
</aside>
