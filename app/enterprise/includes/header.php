<?php
/**
 * Enterprise Dashboard - Header Component
 * 
 * Note for Junior Developers:
 * - Header displays current page title, mobile toggle, mock notifications bell, and user avatar.
 * - Notification bell is UI mock only (no API/database).
 */
?>
<header class="ent-header">
    <div class="ent-header__left">
        <!-- Mobile Sidebar Toggle -->
        <button class="ent-header__toggle" id="ent-sidebar-toggle" aria-label="Mở danh mục điều hướng">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <h1 class="ent-header__title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Tổng quan Doanh nghiệp'; ?></h1>
    </div>

    <div class="ent-header__right">
        <!-- Notification Bell (UI Mock Only) -->
        <div class="ent-header__notif" id="ent-notif-trigger" title="Thông báo mới (Mock UI)">
            <button class="ent-header__icon-btn" aria-label="Thông báo mới">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span class="ent-header__badge">3</span>
            </button>
        </div>

        <!-- Enterprise Account Area -->
        <div class="ent-header__account">
            <div class="ent-header__avatar">
                <?= htmlspecialchars($enterpriseInfo['logo_initials']); ?>
            </div>
            <div class="ent-header__user-info">
                <span class="ent-header__company-name"><?= htmlspecialchars($enterpriseInfo['company_name']); ?></span>
                <span class="ent-header__package-name"><?= htmlspecialchars($enterpriseInfo['account_type']); ?></span>
            </div>
        </div>
    </div>
</header>
