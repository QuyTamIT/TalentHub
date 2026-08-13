<?php
/**
 * Enterprise Dashboard - Pending Action Items Component
 * Clean, compact action list for enterprise tasks scanning.
 */
?>
<section class="ent-section-box">
    <div class="ent-section-box__header">
        <div>
            <h3 class="ent-section-box__title">Việc cần xử lý</h3>
            <p class="ent-section-box__subtitle">Danh sách công việc cần doanh nghiệp phản hồi và xử lý</p>
        </div>
        <span class="ent-section-box__count">4 việc cần làm</span>
    </div>

    <div class="ent-actions-list">
        <?php foreach ($pendingActions as $item): ?>
            <div class="ent-action-row ent-action-row--<?= htmlspecialchars($item['type']); ?>">
                <div class="ent-action-row__icon">
                    <?php if ($item['type'] === 'urgent'): ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    <?php elseif ($item['type'] === 'warning'): ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                    <?php else: ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    <?php endif; ?>
                </div>

                <div class="ent-action-row__body">
                    <div class="ent-action-row__title"><?= htmlspecialchars($item['title']); ?></div>
                    <div class="ent-action-row__subtitle"><?= htmlspecialchars($item['subtitle']); ?></div>
                </div>

                <a href="<?= htmlspecialchars($item['route']); ?>" class="btn btn-secondary btn-sm ent-action-row__btn" data-route="<?= htmlspecialchars($item['route']); ?>">
                    <?= htmlspecialchars($item['action_label']); ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
