<?php
/**
 * Teacher Dashboard - Recent Activity Feed Component
 */
?>
<section class="teacher-section-box">
    <div class="teacher-section-box__header">
        <h3 class="teacher-section-box__title">Hoạt động gần đây</h3>
    </div>

    <?php if (empty($recentActivities)): ?>
        <div class="teacher-empty-state">
            <div class="teacher-empty-state__icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <h4 class="teacher-empty-state__title">Chưa có hoạt động</h4>
            <p class="teacher-empty-state__desc">Dữ liệu sẽ xuất hiện khi học viên check-in hoặc khi giáo viên có hoạt động sắp diễn ra.</p>
        </div>
    <?php else: ?>
        <div class="teacher-activity-timeline">
            <?php foreach ($recentActivities as $activity): ?>
                <div class="teacher-activity-item teacher-activity-item--<?= htmlspecialchars($activity['type']); ?>">
                    <div class="teacher-activity-item__indicator">
                        <?php if ($activity['icon'] === 'qr'): ?>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                        <?php else: ?>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                                <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                                <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                                <path d="M18 2H6v7a6 6 0 0 0 12 0V2z"></path>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="teacher-activity-item__content">
                        <p class="teacher-activity-item__text"><?= htmlspecialchars($activity['title']); ?></p>
                        <span class="teacher-activity-item__meta"><?= htmlspecialchars($activity['meta']); ?></span>
                        <span class="teacher-activity-item__time"><?= htmlspecialchars($activity['time']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
