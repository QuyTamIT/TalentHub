<?php
/**
 * School Dashboard - Sidebar Component
 */

$sidebarNav = [
    [
        'title' => 'Tổng quan',
        'route' => '/app/school/',
        'icon' => 'grid',
        'active' => true
    ],
    [
        'title' => 'Phân tích',
        'route' => '/app/school/analytics.php',
        'icon' => 'trending-up',
        'active' => false
    ],
    [
        'title' => 'Báo cáo',
        'route' => '/app/school/reports.php',
        'icon' => 'file-text',
        'badge' => 2
    ],
    [
        'title' => 'Lớp & Khối',
        'route' => '/app/school/classes.php',
        'icon' => 'users',
        'active' => false
    ]
];

$schoolInfo = [
    'name' => 'THPT Nguyễn Trãi',
    'logo_initials' => 'NT',
    'level' => 'Trung học Phổ thông',
    'district' => 'Quận 1, TP.HCM',
    'academic_year' => '2025 - 2026'
];
?>
<!-- Sidebar Overlay Backdrop for Mobile -->
<div class="school-sidebar-backdrop" id="school-sidebar-backdrop" aria-hidden="true"></div>

<aside class="school-sidebar" id="school-sidebar">
    <!-- Brand Logo -->
    <div class="school-sidebar__brand">
        <a href="../../index.php" class="site-header__brand" aria-label="Về trang chủ TalentHub">
            <div class="site-header__brand-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
            </div>
            <div class="site-header__brand-text">Talent<span>Hub</span></div>
        </a>
        <div class="school-sidebar__subtitle">Khu vực Nhà trường</div>
    </div>

    <!-- School Card -->
    <div class="school-sidebar__school-card">
        <div class="school-sidebar__school-avatar">
            <?= htmlspecialchars($schoolInfo['logo_initials']); ?>
        </div>
        <div class="school-sidebar__school-info">
            <span class="school-sidebar__school-name"><?= htmlspecialchars($schoolInfo['name']); ?></span>
            <div class="school-sidebar__school-meta">
                <span class="school-sidebar__badge"><?= htmlspecialchars($schoolInfo['level']); ?></span>
            </div>
        </div>
    </div>

    <!-- Navigation List -->
    <nav class="school-sidebar__nav" aria-label="Điều hướng Nhà trường">
        <div class="school-sidebar__nav-title">QUẢN LÝ TRƯỜNG HỌC</div>
        <ul>
            <?php foreach ($sidebarNav as $navItem): 
                $isActive = isset($currentRoute) && $navItem['route'] === $currentRoute;
            ?>
                <li>
                    <a href="<?= htmlspecialchars($navItem['route']); ?>" 
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

    <!-- Bottom Action -->
    <div class="school-sidebar__footer">
        <a href="/role-selection.php" class="school-sidebar__link school-sidebar__link--switch">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 17l5-5-5-5M19.8 12H9M13 22a10 10 0 1 1 0-20"></path>
            </svg>
            <span>Đổi vai trò</span>
        </a>
    </div>
</aside>
