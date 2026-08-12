<?php require_once __DIR__ . '/icons.php'; ?>
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
        <a class="learner-role-switch" href="../../role-selection.php">
            <?= learner_icon('arrow-left', 18); ?>
            <span>Đổi vai trò</span>
        </a>
    </div>

    <div class="learner-header__right">
        <form class="learner-search" id="learner-search-form" role="search">
            <label class="learner-visually-hidden" for="learner-search-input">Tìm hoạt động hoặc kỹ năng</label>
            <?= learner_icon('search', 20); ?>
            <input id="learner-search-input" name="q" type="search" placeholder="Tìm hoạt động, kỹ năng..." autocomplete="off">
        </form>

        <button class="learner-icon-button" id="learner-notification-button" type="button" aria-label="Xem thông báo">
            <?= learner_icon('bell', 21); ?>
            <span class="learner-notification-dot" aria-hidden="true"></span>
        </button>

        <button class="learner-avatar" type="button" aria-label="Mở tài khoản Nguyễn Văn A">
            <?= learner_escape($student['initials']); ?>
        </button>
    </div>
</header>

<div class="learner-toast" id="learner-toast" role="status" aria-live="polite" aria-atomic="true">
    <span class="learner-toast__icon"><?= learner_icon('check', 18); ?></span>
    <span class="learner-toast__message">Đã cập nhật.</span>
</div>
