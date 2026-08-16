<?php
/**
 * Teacher Dashboard - Sidebar Component
 */
$teacherSidebarHomeHref = '/index.php';
$teacherSidebarRoleHref = '/role-selection.php';
$teacherRouteHrefs = [
    'grid' => '/app/teacher/index.php',
    'trophy' => '/app/teacher/activities/index.php',
    'clipboard-check' => '/app/teacher/assessments/index.php',
    'users' => '/app/teacher/students/index.php',
];
?>
<div class="teacher-sidebar-backdrop" id="teacher-sidebar-backdrop" aria-hidden="true"></div>

<aside class="teacher-sidebar" id="teacher-sidebar">
    <div class="teacher-sidebar__brand">
        <a href="<?= htmlspecialchars($teacherSidebarHomeHref); ?>" class="site-header__brand" aria-label="Về trang chủ TalentHub">
            <div class="site-header__brand-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
            </div>
            <div class="site-header__brand-text">Talent<span>Hub</span></div>
        </a>
        <div class="teacher-sidebar__subtitle">Khu vực Giáo viên</div>
    </div>

    <nav class="teacher-sidebar__nav" aria-label="Điều hướng Giáo viên">
        <div class="teacher-sidebar__nav-title">QUẢN LÝ GIÁO VIÊN</div>
        <ul>
            <?php foreach ($sidebarNav as $navItem):
                $isActive = (isset($currentRoute) && $navItem['route'] === $currentRoute) || (!isset($currentRoute) && $navItem['active']);
                $navHref = $teacherRouteHrefs[$navItem['icon']] ?? '#';
            ?>
                <li>
                    <a href="<?= htmlspecialchars($navHref); ?>"
                       class="teacher-sidebar__link <?= $isActive ? 'is-active' : ''; ?>"
                       data-route="<?= htmlspecialchars($navItem['route']); ?>">
                        <span class="teacher-sidebar__icon">
                            <?php if ($navItem['icon'] === 'grid'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'trophy'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                                    <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                                    <path d="M4 22h16"></path>
                                    <path d="M18 2H6v7a6 6 0 0 0 12 0V2z"></path>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'clipboard-check'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"></path>
                                    <rect x="9" y="3" width="6" height="4" rx="2"></rect>
                                    <polyline points="9 14 11 16 15 12"></polyline>
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
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                            <?php endif; ?>
                        </span>
                        <span><?= htmlspecialchars($navItem['title']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="teacher-sidebar__footer">
        <a href="<?= htmlspecialchars($teacherSidebarRoleHref); ?>" class="teacher-sidebar__link teacher-sidebar__link--switch">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 17l5-5-5-5M19.8 12H9M13 22a10 10 0 1 1 0-20"></path>
            </svg>
            <span>Đổi vai trò</span>
        </a>
    </div>
</aside>
