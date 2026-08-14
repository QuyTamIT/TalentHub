<?php
/**
 * School Dashboard - Header Component
 *
 * Note for Developers:
 * - Header displays current page title, mobile toggle, mock notifications bell, and school avatar.
 * - Notification bell is UI mock only (no API/database).
 */
?>
<header class="sch-header">
    <div class="sch-header__left">
        <!-- Mobile Sidebar Toggle -->
        <button class="sch-header__toggle" id="sch-sidebar-toggle" aria-label="Mở danh mục điều hướng">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <div>
            <h1 class="sch-header__title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Tổng quan Nhà trường'; ?></h1>
            <?php if (isset($pageSubtitle)): ?>
                <div class="sch-header__breadcrumb"><?= htmlspecialchars($pageSubtitle); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="sch-header__right">
        <!-- Notification Bell (UI Mock Only) -->
        <div class="sch-header__notif" id="sch-notif-trigger" title="Thông báo mới (Mock UI)">
            <button class="sch-header__icon-btn" aria-label="Thông báo mới">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span class="sch-header__badge">5</span>
            </button>
        </div>

        <!-- School Account Area -->
        <div class="sch-header__account">
            <div class="sch-header__avatar">
                <?= htmlspecialchars($schoolInfo['logo_initials']); ?>
            </div>
            <div class="sch-header__user-info">
                <span class="sch-header__school-name"><?= htmlspecialchars($schoolInfo['name']); ?></span>
                <span class="sch-header__package-name"><?= htmlspecialchars($schoolInfo['account_type']); ?> • <?= htmlspecialchars($schoolInfo['academic_year']); ?></span>
            </div>
        </div>
    </div>
</header>