<?php
/**
 * Enterprise Dashboard - Sidebar Component
 *
 * Note for Junior Developers:
 * - Sidebar contains navigation for Enterprise functions.
 * - Notifications are located in the top header, NOT in the sidebar per specification.
 */

// Ensure app_href() is available regardless of whether the caller loaded bootstrap.
if (!function_exists('app_href') && is_file(__DIR__ . '/../../../bin/bootstrap.php')) {
    require_once __DIR__ . '/../../../bin/bootstrap.php';
}
require_once dirname(__DIR__, 2) . '/shared/BrandHeader.php';
?>
<!-- Sidebar Overlay Backdrop for Mobile & Tablet -->
<div class="ent-sidebar-backdrop" id="ent-sidebar-backdrop" aria-hidden="true"></div>

<aside class="ent-sidebar" id="ent-sidebar">
    <!-- Brand Logo -->
    <div class="ent-sidebar__brand">
        <?php renderBrandHeader(app_href('/app/enterprise/index.php'), 'Khu vực Doanh nghiệp', 'Về trang chủ FTalentHub'); if (false): ?>
            <span class="learner-brand__mark" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>
                </svg>
            </span>
            <div class="learner-brand__text">
                <span class="learner-brand__name">FTalent<span>Hub</span></span>
                <span class="learner-brand__subtitle">Khu vực Doanh nghiệp</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Navigation List -->
    <nav class="ent-sidebar__nav" aria-label="Điều hướng Doanh nghiệp">
        <div class="ent-sidebar__nav-title">QUẢN LÝ DOANH NGHIỆP</div>
        <ul>
            <?php foreach ($sidebarNav as $navItem): 
                $isActive = (isset($currentRoute) && ($navItem['route'] === $currentRoute || strpos($currentRoute, strtok($navItem['route'], '.')) === 0 && $navItem['route'] !== '/app/enterprise')) || (!isset($currentRoute) && $navItem['active']);
                
                $hrefRoute = app_href($navItem['route']);
            ?>
                <li>
                    <a href="<?= htmlspecialchars($hrefRoute); ?>" 
                       class="ent-sidebar__link <?= $isActive ? 'is-active' : ''; ?>"
                       data-route="<?= htmlspecialchars($navItem['route']); ?>">
                        
                        <span class="ent-sidebar__icon">
                            <?php if ($navItem['icon'] === 'grid'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'search-users'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'briefcase'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'award'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="8" r="7"></circle>
                                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                                </svg>
                            <?php elseif ($navItem['icon'] === 'building' || $navItem['icon'] === 'profile'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            <?php else: ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="20" x2="12" y2="10"></line>
                                    <line x1="18" y1="20" x2="18" y2="4"></line>
                                    <line x1="6" y1="20" x2="6" y2="16"></line>
                                </svg>
                            <?php endif; ?>
                        </span>
                        <span><?= htmlspecialchars($navItem['title']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Bottom Action: Logout -->
    <div class="ent-sidebar__footer">
        <a href="<?= function_exists('app_href') ? app_href('/logout.php') : '/logout.php'; ?>" 
           class="ent-sidebar__link ent-sidebar__link--logout"
           aria-label="Đăng xuất khỏi hệ thống">
            <span class="ent-sidebar__icon">
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
