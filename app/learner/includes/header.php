<?php
require_once __DIR__ . '/icons.php';
// Ensure app_href() is available regardless of whether the caller loaded bootstrap.
if (!function_exists('app_href') && is_file(__DIR__ . '/../../../bin/bootstrap.php')) {
    require_once __DIR__ . '/../../../bin/bootstrap.php';
}

$basePrefix = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/TalentHub') !== false) ? '/TalentHub' : '';
$profileRoute = '/app/learner/profile.php';
$profileUrl = function_exists('app_href') ? app_href($profileRoute) : ($basePrefix . $profileRoute);
$logoutUrl = function_exists('app_href') ? app_href('/logout.php') : ($basePrefix . '/logout.php');

$headerSearchLabel = $headerSearchLabel ?? 'Tìm hoạt động hoặc kỹ năng';
$headerSearchPlaceholder = $headerSearchPlaceholder ?? 'Tìm hoạt động, kỹ năng...';
$learnerOnboarding = $GLOBALS['learner_page_context']['onboarding'] ?? [];
$learnerOnboardingRestricted = ($learnerOnboarding['required'] ?? false) === true
    && ($learnerOnboarding['status'] ?? null) !== 'completed';

$studentName = !empty($student['name']) ? $student['name'] : 'Nguyễn Văn An';
$studentInitials = !empty($student['initials']) ? $student['initials'] : (mb_strtoupper(mb_substr($studentName, 0, 1)));
$accountType = 'Tài khoản Sinh viên';
?>
<header class="learner-header">
    <div class="learner-header__left">
        <button
            class="learner-icon-button learner-header__menu"
            id="learner-sidebar-toggle"
            type="button"
            aria-label="Mở danh mục điều hướng"
            aria-controls="learner-sidebar"
            aria-expanded="false"
        >
            <?= learner_icon('menu', 24); ?>
        </button>
    </div>

    <div class="learner-header__right">
        <form class="learner-search" id="learner-search-form" role="search">
            <label class="learner-visually-hidden" for="learner-search-input"><?= learner_escape($headerSearchLabel); ?></label>
            <?= learner_icon('search', 20); ?>
            <input id="learner-search-input" name="q" type="search" placeholder="<?= learner_escape($headerSearchPlaceholder); ?>" autocomplete="off">
        </form>

        <?php if (!$learnerOnboardingRestricted): ?>
        <a class="learner-icon-button" id="learner-notification-button" href="notifications.php" aria-label="Xem thông báo">
            <?= learner_icon('bell', 21); ?>
            <span class="learner-notification-dot" id="learner-unread-badge" aria-hidden="true" style="display: none;"></span>
        </a>
        <?php endif; ?>

        <!-- Learner Account Area with Dropdown -->
        <div class="learner-header__account-wrapper" id="learner-account-wrapper">
            <button 
                type="button" 
                class="learner-header__account" 
                id="learner-account-trigger" 
                aria-haspopup="menu" 
                aria-expanded="false" 
                aria-controls="learner-account-menu"
                aria-label="Tài khoản sinh viên: <?= learner_escape($studentName); ?>"
            >
                <div class="learner-header__avatar" aria-hidden="true">
                    <span><?= learner_escape($studentInitials); ?></span>
                </div>
                <div class="learner-header__user-info">
                    <span class="learner-header__user-name"><?= learner_escape($studentName); ?></span>
                    <span class="learner-header__user-role"><?= learner_escape($accountType); ?></span>
                </div>
                <span class="learner-header__chevron" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </button>

            <!-- Account Dropdown Menu -->
            <div 
                class="learner-account-menu" 
                id="learner-account-menu" 
                role="menu" 
                aria-labelledby="learner-account-trigger"
                hidden
            >
                <!-- User Identity Header -->
                <div class="learner-account-menu__identity" role="none">
                    <div class="learner-account-menu__avatar" aria-hidden="true">
                        <span><?= learner_escape($studentInitials); ?></span>
                    </div>
                    <div class="learner-account-menu__details">
                        <span class="learner-account-menu__name"><?= learner_escape($studentName); ?></span>
                        <span class="learner-account-menu__badge"><?= learner_escape($accountType); ?></span>
                    </div>
                </div>

                <div class="learner-account-menu__divider" role="separator"></div>

                <!-- Navigation List -->
                <ul class="learner-account-menu__list" role="none">
                    <li role="none">
                        <a 
                            href="<?= learner_escape($profileUrl); ?>" 
                            class="learner-account-menu__item" 
                            role="menuitem"
                            data-route="<?= learner_escape($profileRoute); ?>"
                            tabindex="-1"
                        >
                            <svg class="learner-account-menu__item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span>Hồ sơ cá nhân</span>
                        </a>
                    </li>
                    <li role="none">
                        <a 
                            href="<?= learner_escape($profileUrl); ?>" 
                            class="learner-account-menu__item" 
                            role="menuitem"
                            tabindex="-1"
                        >
                            <svg class="learner-account-menu__item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            <span>Cài đặt tài khoản</span>
                        </a>
                    </li>
                </ul>

                <div class="learner-account-menu__divider" role="separator"></div>

                <!-- Logout Link -->
                <ul class="learner-account-menu__list" role="none">
                    <li role="none">
                        <a 
                            href="<?= learner_escape($logoutUrl); ?>" 
                            class="learner-account-menu__item learner-account-menu__item--logout" 
                            role="menuitem"
                            tabindex="-1"
                        >
                            <svg class="learner-account-menu__item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
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

<div class="learner-toast" id="learner-toast" role="status" aria-live="polite" aria-atomic="true">
    <span class="learner-toast__icon"><?= learner_icon('check', 18); ?></span>
    <span class="learner-toast__message">Đã cập nhật.</span>
</div>

<?php if (isset($GLOBALS['learner_page_context'])): ?>
<script id="learner-session-boot" type="application/json"><?= json_encode([
    'csrfToken' => $GLOBALS['learner_page_context']['csrfToken'],
    'apiBase' => app_href('/app/learner/api/v1'),
    'onboardingRestricted' => $learnerOnboardingRestricted,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
<?php endif; ?>
<script src="../../assets/js/learner-notifications.js" defer></script>
