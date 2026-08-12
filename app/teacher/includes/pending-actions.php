<?php
/**
 * Teacher Dashboard - Pending Action Items Component
 */
?>
<section class="teacher-section-box">
    <div class="teacher-section-box__header">
        <div>
            <h3 class="teacher-section-box__title">Việc cần xử lý</h3>
            <p class="teacher-section-box__subtitle">Các tác vụ liên quan đến sân chơi, chấm điểm và điểm danh.</p>
        </div>
        <span class="teacher-section-box__count"><?= htmlspecialchars((string) count($pendingActions)); ?> mục</span>
    </div>

    <div class="teacher-actions-list">
        <?php foreach ($pendingActions as $item): ?>
            <div class="teacher-action-row teacher-action-row--<?= htmlspecialchars($item['type']); ?>">
                <div class="teacher-action-row__icon">
                    <?php if ($item['icon'] === 'clipboard-check'): ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"></path>
                            <rect x="9" y="3" width="6" height="4" rx="2"></rect>
                            <polyline points="9 14 11 16 15 12"></polyline>
                        </svg>
                    <?php elseif ($item['icon'] === 'users'): ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        </svg>
                    <?php elseif ($item['icon'] === 'qr'): ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                    <?php else: ?>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    <?php endif; ?>
                </div>
                <div class="teacher-action-row__body">
                    <div class="teacher-action-row__title">
                        <?= htmlspecialchars($item['title']); ?>
                        <span class="teacher-action-row__count"><?= htmlspecialchars((string) $item['count']); ?></span>
                    </div>
                    <div class="teacher-action-row__subtitle"><?= htmlspecialchars($item['subtitle']); ?></div>
                </div>
                <span class="teacher-status-pill teacher-status-pill--<?= htmlspecialchars($item['type']); ?>">
                    <?= htmlspecialchars($item['status']); ?>
                </span>
                <?php if (!empty($item['disabled'])): ?>
                    <button class="btn btn-secondary btn-sm teacher-action-row__btn" type="button" disabled>
                        <?= htmlspecialchars($item['action_label']); ?>
                    </button>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($item['route']); ?>" class="btn btn-secondary btn-sm teacher-action-row__btn" data-route="<?= htmlspecialchars($item['route']); ?>">
                        <?= htmlspecialchars($item['action_label']); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
