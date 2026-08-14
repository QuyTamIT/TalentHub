<?php
/**
 * School Dashboard - Sidebar Component
 *
 * Note for Developers:
 * - Sidebar contains navigation for School functions.
 * - Active route is detected by $currentRoute and matched against each nav item.
 * - Uses Blue (#2563EB) accent to differentiate from Enterprise.
 */
?>
<!-- Sidebar Overlay Backdrop for Mobile & Tablet -->
<div class="sch-sidebar-backdrop" id="sch-sidebar-backdrop" aria-hidden="true"></div>

<aside class="sch-sidebar" id="sch-sidebar">
    <!-- Brand Logo -->
    <div class="sch-sidebar__brand">
        <a href="../../index.php" class="site-header__brand" aria-label="Về trang chủ TalentHub">
            <div class="site-header__brand-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
            </div>
            <div class="site-header__brand-text">Talent<span>Hub</span></div>
        </a>
        <div class="sch-sidebar__subtitle">Khu vực Nhà trường</div>
    </div>

    <!-- School Badge Box -->
    <div class="sch-sidebar__school">
        <div class="sch-sidebar__school-avatar">
            <?= htmlspecialchars($schoolInfo['logo_initials']); ?>
        </div>
        <div class="sch-sidebar__school-info">
            <h4><?= htmlspecialchars($schoolInfo['name']); ?></h4>
            <span class="sch-sidebar__badge"><?= htmlspecialchars($schoolInfo['account_type']); ?></span>
        </div>
    </div>

    <!-- Navigation List -->
    <nav class="sch-sidebar__nav" aria-label="Điều hướng Nhà trường">
        <div class="sch-sidebar__nav-title">QUẢN LÝ NHÀ TRƯỜNG</div>
        <ul>
            <?php foreach ($sidebarNav as $navItem):
                $routeClean = strtok($navItem['route'], '?');
                $currentClean = isset($currentRoute) ? strtok($currentRoute, '?') : '';
                $isActive = (isset($currentRoute) && (
                    $currentClean === $routeClean ||
                    ($routeClean === '/app/school' && $currentClean === '/app/school/index.php')
                )) || (!isset($currentRoute) && !empty($navItem['active']));
            ?>
                <li>
                    <a href="<?= htmlspecialchars($navItem['route']); ?>"
                       class="sch-sidebar__link <?= $isActive ? 'is-active' : ''; ?>"
                       data-route="<?= htmlspecialchars($navItem['route']); ?>"
                       <?= $isActive ? 'aria-current="page"' : ''; ?>>

                        <span class="sch-sidebar__icon">
                            <?php if ($navItem['icon'] === 'grid'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'chart'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="20" x2="12" y2="10"></line>
                                    <line x1="18" y1="20" x2="18" y2="4"></line>
                                    <line x1="6" y1="20" x2="6" y2="16"></line>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'file-text'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                </svg>
                            <?php else: ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            <?php endif; ?>
                        </span>
                        <span><?= htmlspecialchars($navItem['title']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Bottom Action to Return to Role Selection -->
    <div class="sch-sidebar__footer">
        <a href="../../role-selection.php" class="sch-sidebar__link sch-sidebar__link--switch">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 17l5-5-5-5M19.8 12H9M13 22a10 10 0 1 1 0-20"></path>
            </svg>
            <span>Đổi vai trò</span>
        </a>
    </div>
</aside>