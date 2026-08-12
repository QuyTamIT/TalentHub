<?php
require_once __DIR__ . '/icons.php';
$activeRoute = $currentRoute ?? '/app/learner/index.php';
?>
<div class="learner-sidebar-backdrop" id="learner-sidebar-backdrop" aria-hidden="true"></div>

<aside class="learner-sidebar" id="learner-sidebar" aria-label="Điều hướng Học sinh/Sinh viên">
    <div class="learner-sidebar__brand">
        <a class="learner-brand" href="../../index.php" aria-label="Về trang chủ TalentHub">
            <span class="learner-brand__mark" aria-hidden="true"><?= learner_icon('sparkles', 24); ?></span>
            <span class="learner-brand__name">Talent<span>Hub</span></span>
        </a>
        <p>Khu vực Học sinh</p>
    </div>

    <nav class="learner-sidebar__nav" aria-label="Danh mục Học sinh/Sinh viên">
        <ul>
            <?php foreach ($learnerNav as $item): ?>
                <?php $isActive = $activeRoute === $item['route']; ?>
                <li>
                    <a
                        class="learner-sidebar__link<?= $isActive ? ' is-active' : ''; ?>"
                        href="<?= learner_escape($item['route']); ?>"
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
</aside>
