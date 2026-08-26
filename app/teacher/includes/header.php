<?php
/**
 * Teacher Dashboard - Header Component
 *
 * Synchronized 100% with Enterprise Portal:
 * - Mobile toggle & header title
 * - Notification bell
 * - Account trigger button with Avatar, Teacher Name, Role subtitle, rotating chevron
 * - Floating account dropdown with Identity header, Profile link ("Hồ sơ giáo viên"), Divider, and Logout action
 */

if (!function_exists('app_href') && is_file(dirname(__DIR__, 3) . '/bin/bootstrap.php')) {
    require_once dirname(__DIR__, 3) . '/bin/bootstrap.php';
}

$teacherFullName = $teacherInfo['full_name'] ?? 'Thầy Nguyễn Văn Bình';
$teacherAvatar = $teacherInfo['avatar_initials'] ?? 'TB';
$teacherRoleLabel = $teacherInfo['role_label'] ?? 'Giáo viên / Hướng dẫn viên';
$teacherSchoolName = $teacherInfo['school_name'] ?? 'THPT Nguyễn Trãi';

$profileRoute = '/app/teacher/profile.php';
$profileUrl = function_exists('app_href') ? app_href($profileRoute) : '/app/teacher/profile.php';
$logoutUrl = function_exists('app_href') ? app_href('/logout.php?role=teacher') : '/logout.php?role=teacher';
?>
<header class="teacher-header">
    <div class="teacher-header__left">
        <!-- Mobile Sidebar Toggle -->
        <button class="teacher-header__toggle" id="teacher-sidebar-toggle" aria-label="Mở danh mục điều hướng">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <h1 class="teacher-header__title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Tổng quan Giáo viên'; ?></h1>
    </div>

    <div class="teacher-header__right">
        <!-- Notification Bell -->
        <div class="teacher-header__notif" id="teacher-notif-trigger" title="Thông báo mới (Mock UI)">
            <button class="teacher-header__icon-btn" type="button" aria-label="Thông báo mới">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <?php if (!empty($teacherInfo['notification_count'])): ?>
                    <span class="teacher-header__badge"><?= htmlspecialchars((string) $teacherInfo['notification_count']); ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Teacher Account Area with Dropdown -->
        <div class="teacher-header__account-wrapper" id="teacher-account-wrapper">
            <button 
                type="button" 
                class="teacher-header__account" 
                id="teacher-account-trigger" 
                aria-haspopup="menu" 
                aria-expanded="false" 
                aria-controls="teacher-account-menu"
                aria-label="Tài khoản giáo viên: <?= htmlspecialchars($teacherFullName); ?>"
            >
                <div class="teacher-header__avatar" aria-hidden="true">
                    <?= htmlspecialchars($teacherAvatar); ?>
                </div>
                <div class="teacher-header__user-info">
                    <span class="teacher-header__company-name"><?= htmlspecialchars($teacherFullName); ?></span>
                    <span class="teacher-header__package-name"><?= htmlspecialchars($teacherRoleLabel); ?></span>
                </div>
                <span class="teacher-header__chevron" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </button>

            <!-- Account Dropdown Menu -->
            <div 
                class="teacher-account-menu" 
                id="teacher-account-menu" 
                role="menu" 
                aria-labelledby="teacher-account-trigger"
                hidden
            >
                <!-- Teacher Identity Header -->
                <div class="teacher-account-menu__identity" role="none">
                    <div class="teacher-account-menu__avatar" aria-hidden="true">
                        <?= htmlspecialchars($teacherAvatar); ?>
                    </div>
                    <div class="teacher-account-menu__details">
                        <span class="teacher-account-menu__company-name"><?= htmlspecialchars($teacherFullName); ?></span>
                        <span class="teacher-account-menu__badge">Tài khoản Giáo viên</span>
                    </div>
                </div>

                <div class="teacher-account-menu__divider" role="separator"></div>

                <!-- Navigation List -->
                <ul class="teacher-account-menu__list" role="none">
                    <li role="none">
                        <a 
                            href="<?= htmlspecialchars($profileUrl); ?>" 
                            class="teacher-account-menu__item" 
                            role="menuitem"
                            data-route="<?= htmlspecialchars($profileRoute); ?>"
                            tabindex="-1"
                        >
                            <svg class="teacher-account-menu__item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span>Hồ sơ giáo viên</span>
                        </a>
                    </li>
                </ul>

                <div class="teacher-account-menu__divider" role="separator"></div>

                <!-- Logout Link -->
                <ul class="teacher-account-menu__list" role="none">
                    <li role="none">
                        <a 
                            href="<?= htmlspecialchars($logoutUrl); ?>" 
                            class="teacher-account-menu__item teacher-account-menu__item--logout" 
                            role="menuitem"
                            tabindex="-1"
                        >
                            <svg class="teacher-account-menu__item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span>Đăng xuất</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
