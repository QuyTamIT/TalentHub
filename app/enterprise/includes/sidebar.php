<?php
/**
 * Enterprise Dashboard - Sidebar Component
 * 
 * Note for Junior Developers:
 * - Sidebar contains navigation for Enterprise functions.
 * - Notifications are located in the top header, NOT in the sidebar per specification.
 */
?>
<!-- Sidebar Overlay Backdrop for Mobile & Tablet -->
<div class="ent-sidebar-backdrop" id="ent-sidebar-backdrop" aria-hidden="true"></div>

<aside class="ent-sidebar" id="ent-sidebar">
    <!-- Brand Logo -->
    <div class="ent-sidebar__brand">
        <a href="../../index.php" class="site-header__brand" aria-label="Về trang chủ TalentHub">
            <div class="site-header__brand-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
            </div>
            <div class="site-header__brand-text">Talent<span>Hub</span></div>
        </a>
        <div class="ent-sidebar__subtitle">Khu vực Doanh nghiệp</div>
    </div>

    <!-- Company Badge Box -->
    <div class="ent-sidebar__company">
        <div class="ent-sidebar__company-avatar">
            <?= htmlspecialchars($enterpriseInfo['logo_initials']); ?>
        </div>
        <div class="ent-sidebar__company-info">
            <h4><?= htmlspecialchars($enterpriseInfo['company_name']); ?></h4>
            <span class="ent-sidebar__badge"><?= htmlspecialchars($enterpriseInfo['account_type']); ?></span>
        </div>
    </div>

    <!-- Navigation List -->
    <nav class="ent-sidebar__nav" aria-label="Điều hướng Doanh nghiệp">
        <div class="ent-sidebar__nav-title">QUẢN LÝ DOANH NGHIỆP</div>
        <ul>
            <?php foreach ($sidebarNav as $navItem): ?>
                <li>
                    <a href="<?= htmlspecialchars($navItem['route']); ?>" 
                       class="ent-sidebar__link <?= $navItem['active'] ? 'is-active' : ''; ?>"
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

    <!-- Bottom Action to Return to Role Selection -->
    <div class="ent-sidebar__footer">
        <a href="../../role-selection.php" class="ent-sidebar__link ent-sidebar__link--switch">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 17l5-5-5-5M19.8 12H9M13 22a10 10 0 1 1 0-20"></path>
            </svg>
            <span>Đổi vai trò</span>
        </a>
    </div>
</aside>
