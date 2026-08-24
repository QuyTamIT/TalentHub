<?php
require_once __DIR__ . '/icons.php';
$headerSearchLabel = $headerSearchLabel ?? 'Tìm hoạt động hoặc kỹ năng';
$headerSearchPlaceholder = $headerSearchPlaceholder ?? 'Tìm hoạt động, kỹ năng...';
$learnerOnboarding = $GLOBALS['learner_page_context']['onboarding'] ?? [];
$learnerOnboardingRestricted = ($learnerOnboarding['required'] ?? false) === true
    && ($learnerOnboarding['status'] ?? null) !== 'completed';
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

        <button class="learner-avatar" type="button" aria-label="Mở tài khoản <?= learner_escape($student['name']); ?>" data-learner-account>
            <?= learner_escape($student['initials']); ?>
        </button>
    </div>
</header>

<div class="learner-toast" id="learner-toast" role="status" aria-live="polite" aria-atomic="true">
    <span class="learner-toast__icon"><?= learner_icon('check', 18); ?></span>
    <span class="learner-toast__message">Đã cập nhật.</span>
</div>

<?php if (isset($GLOBALS['learner_page_context'])): ?>
<script id="learner-session-boot" type="application/json"><?= json_encode([
    'csrfToken' => $GLOBALS['learner_page_context']['csrfToken'],
    'apiBase' => '/api/v1',
    'onboardingRestricted' => $learnerOnboardingRestricted,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
<?php endif; ?>
<script src="../../assets/js/learner-notifications.js" defer></script>
