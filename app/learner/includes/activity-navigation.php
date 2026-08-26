<?php

declare(strict_types=1);

require_once __DIR__ . '/icons.php';

$activityNavigationActive = isset($activityNavigationActive) && is_string($activityNavigationActive)
    ? $activityNavigationActive
    : 'discover';
$learnerActivityNavigation = [
    ['key' => 'discover', 'label' => 'Khám phá', 'href' => 'activities.php', 'icon' => 'compass'],
    ['key' => 'registered', 'label' => 'Đã đăng ký', 'href' => 'my-activities.php', 'icon' => 'clipboard'],
    ['key' => 'history', 'label' => 'Lịch sử', 'href' => 'activity-history.php', 'icon' => 'clock'],
];
?>
<nav class="learner-activity-navigation" aria-label="Điều hướng Hoạt động">
    <?php foreach ($learnerActivityNavigation as $activityNavigationItem): ?>
        <?php $isActivityNavigationActive = $activityNavigationItem['key'] === $activityNavigationActive; ?>
        <a
            class="learner-activity-navigation__link<?= $isActivityNavigationActive ? ' is-active' : ''; ?>"
            href="<?= learner_escape($activityNavigationItem['href']); ?>"
            <?= $isActivityNavigationActive ? 'aria-current="page"' : ''; ?>
        >
            <?= learner_icon($activityNavigationItem['icon'], 19); ?>
            <span><?= learner_escape($activityNavigationItem['label']); ?></span>
        </a>
    <?php endforeach; ?>
</nav>
