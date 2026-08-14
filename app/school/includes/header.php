<?php
/**
 * School Dashboard - Header Component
 */

$schoolRole = 'Ban Giám hiệu';
?>
<header class="school-header">
    <div class="school-header__left">
        <!-- Mobile Sidebar Toggle -->
        <button class="school-header__toggle" id="school-sidebar-toggle" aria-label="Mở danh mục điều hướng">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <h1 class="school-header__title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Tổng quan Nhà trường'; ?></h1>
    </div>

    <div class="school-header__right">
        <!-- Notification Bell (UI Mock Only) -->
        <div class="school-header__notif" id="school-notif-trigger" title="Thông báo mới (Mock UI)">
            <button class="school-header__icon-btn" aria-label="Thông báo mới">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span class="school-header__badge">3</span>
            </button>
        </div>

        <!-- School Account Area -->
        <div class="school-header__account">
            <div class="school-header__avatar">
                <?= htmlspecialchars($schoolInfo['logo_initials']); ?>
            </div>
            <div class="school-header__user-info">
                <span class="school-header__user-name"><?= htmlspecialchars($schoolInfo['name']); ?></span>
                <span class="school-header__user-role"><?= htmlspecialchars($schoolRole); ?></span>
            </div>
        </div>
    </div>
</header>
