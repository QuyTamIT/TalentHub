<?php
/**
 * Teacher Dashboard - Header Component
 */
?>
<header class="teacher-header">
    <div class="teacher-header__left">
        <button class="teacher-header__toggle" id="teacher-sidebar-toggle" aria-label="Mở danh mục điều hướng">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <h1 class="teacher-header__title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Tổng quan Giáo viên'; ?></h1>
    </div>

    <div class="teacher-header__right">
        <div class="teacher-header__notif" id="teacher-notif-trigger" title="Thông báo">
            <button class="teacher-header__icon-btn" type="button" aria-label="Thông báo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <?php if (!empty($teacherInfo['notification_count'])): ?>
                    <span class="teacher-header__badge"><?= htmlspecialchars((string) $teacherInfo['notification_count']); ?></span>
                <?php endif; ?>
            </button>
        </div>

        <div class="teacher-header__account">
            <div class="teacher-header__avatar"><?= htmlspecialchars($teacherInfo['avatar_initials']); ?></div>
            <div class="teacher-header__user-info">
                <span class="teacher-header__name"><?= htmlspecialchars($teacherInfo['full_name']); ?></span>
                <span class="teacher-header__role"><?= htmlspecialchars($teacherInfo['role_label']); ?></span>
            </div>
        </div>
    </div>
</header>
