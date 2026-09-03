<?php
require_once __DIR__ . '/icons.php';
// Ensure app_href() is available regardless of whether the caller loaded bootstrap.
if (!function_exists('app_href') && is_file(__DIR__ . '/../../../bin/bootstrap.php')) {
    require_once __DIR__ . '/../../../bin/bootstrap.php';
}
$activeRoute = $currentRoute ?? '/app/learner/index.php';
?>
<a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
<div class="learner-sidebar-backdrop" id="learner-sidebar-backdrop" aria-hidden="true"></div>

<aside class="learner-sidebar" id="learner-sidebar" aria-label="Điều hướng Học sinh/Sinh viên">
    <button class="learner-icon-button learner-sidebar__close" id="learner-sidebar-close" type="button" aria-label="Đóng danh mục điều hướng">
        <?= learner_icon('x', 22); ?>
    </button>

    <div class="learner-sidebar__brand">
        <a class="learner-brand" href="../../index.php" aria-label="Về trang chủ FTalentHub">
            <span class="learner-brand__mark" aria-hidden="true"><?= learner_icon('star', 20); ?></span>
            <div class="learner-brand__text">
                <span class="learner-brand__name">FTalent<span>Hub</span></span>
                <span class="learner-brand__subtitle">Khu vực sinh viên</span>
            </div>
        </a>
    </div>

    <nav class="learner-sidebar__nav" aria-label="Danh mục Học sinh/Sinh viên">
        <ul>
            <?php foreach ($learnerNav as $item): ?>
                <?php $isActive = $activeRoute === $item['route']; ?>
                <li>
                    <a
                        class="learner-sidebar__link<?= $isActive ? ' is-active' : ''; ?>"
                        href="<?= learner_escape(app_href($item['route'])); ?>"
                        <?= $isActive ? 'aria-current="page"' : ''; ?>
                        <?= !$item['implemented'] ? 'data-pending-route="true"' : ''; ?>
                    >
                        <span class="learner-sidebar__icon"><?= learner_icon($item['icon'], 20); ?></span>
                        <span><?= learner_escape($item['label']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <?php if (($isDatabaseMode ?? false) && isset($level['progressPercent'])): ?>
    <div class="learner-level-card" aria-label="Cấp độ hiện tại">
        <span class="learner-level-card__eyebrow">Cấp độ hiện tại</span>
        <div class="learner-level-card__title">
            <span class="learner-level-card__medal"><?= learner_escape($level['number']); ?></span>
            <strong><?= learner_escape($level['name']); ?></strong>
            <span class="learner-level-card__verified" aria-label="Dữ liệu đã xác nhận"><?= learner_icon('check', 14); ?></span>
        </div>
        <div class="learner-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= learner_escape($level['progressPercent']); ?>">
            <span style="--learner-progress: <?= learner_escape($level['progressPercent']); ?>%;"></span>
        </div>
        <?php if (($level['nextLevel'] ?? null) !== null): ?>
            <p>Còn <?= learner_escape($level['remainingHours']); ?> giờ đến <?= learner_escape($level['nextLevel']); ?></p>
        <?php else: ?>
            <p>Đã đạt cấp độ cao nhất</p>
        <?php endif; ?>
    </div>
    <?php elseif ($isDatabaseMode ?? false): ?>
    <div class="learner-level-card" aria-label="Trạng thái cấp độ">
        <span class="learner-level-card__eyebrow">Cấp độ</span>
        <strong>Chưa có dữ liệu cấp độ</strong>
        <p>Cấp độ sẽ hiển thị khi hệ thống quy tắc huy hiệu được kích hoạt.</p>
    </div>
    <?php else: ?>
    <div class="learner-level-card" aria-label="Cấp độ hiện tại">
        <span class="learner-level-card__eyebrow">Cấp độ hiện tại</span>
        <div class="learner-level-card__title">
            <span class="learner-level-card__medal"><?= learner_escape($level['number']); ?></span>
            <strong><?= learner_escape($level['name']); ?></strong>
            <span class="learner-level-card__verified" aria-label="Đã xác minh"><?= learner_icon('check', 14); ?></span>
        </div>
        <div class="learner-progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?= learner_escape($level['target']); ?>" aria-valuenow="<?= learner_escape($level['progress']); ?>">
            <span style="--learner-progress: <?= learner_escape($level['progress']); ?>%;"></span>
        </div>
        <p><?= learner_escape($level['progress']); ?>/<?= learner_escape($level['target']); ?> giờ đến <?= learner_escape($level['next_level']); ?></p>
    </div>
    <?php endif; ?>

    <!-- Bottom Action: Logout -->
    <div class="learner-sidebar__footer">
        <a href="<?= function_exists('app_href') ? app_href('/logout.php') : '/logout.php'; ?>" 
           class="learner-sidebar__link learner-sidebar__link--logout"
           aria-label="Đăng xuất khỏi hệ thống">
            <span class="learner-sidebar__icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </span>
            <span>Đăng xuất</span>
        </a>
    </div>
</aside>
