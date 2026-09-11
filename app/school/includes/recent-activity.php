<?php
/**
 * School Dashboard - Recent Activity Component
 */

$recentActivities = $dashboard['recentActivity'] ?? $recentActivities ?? [];
if (!is_array($recentActivities)) {
    $recentActivities = [];
}
?>
<section class="school-section-box">
    <div class="school-section-box__header">
        <div>
            <h3 class="school-section-box__title">Hoạt động gần đây</h3>
            <p class="school-section-box__subtitle">Cập nhật mới nhất từ trường</p>
        </div>
    </div>
    <div class="school-activity-timeline">
        <?php if (!empty($recentActivities)): ?>
            <?php foreach ($recentActivities as $activity): ?>
                <div class="school-activity-item">
                    <span class="school-activity-item__indicator"></span>
                    <span class="school-activity-item__text"><?= htmlspecialchars(is_array($activity) ? ($activity['text'] ?? $activity['title'] ?? '') : (string)$activity); ?></span>
                    <span class="school-activity-item__time"><?= htmlspecialchars(is_array($activity) ? ($activity['time'] ?? $activity['created_at'] ?? '') : ''); ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="school-empty-state" style="padding: 24px; text-align: center; color: var(--school-text-secondary, #64748b);">
                <p>Chưa có hoạt động nào được ghi nhận gần đây.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
