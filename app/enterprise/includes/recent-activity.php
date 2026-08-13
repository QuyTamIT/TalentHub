<?php
/**
 * Enterprise Dashboard - Recent Activity Feed Component
 * Information-first timeline list for recent updates.
 */
?>
<section class="ent-section-box">
    <div class="ent-section-box__header">
        <h3 class="ent-section-box__title">Hoạt động gần đây</h3>
    </div>

    <div class="ent-activity-timeline">
        <?php foreach ($recentActivities as $act): ?>
            <div class="ent-activity-item">
                <div class="ent-activity-item__indicator"></div>
                <div class="ent-activity-item__content">
                    <p class="ent-activity-item__text"><?= htmlspecialchars($act['title']); ?></p>
                    <span class="ent-activity-item__time"><?= htmlspecialchars($act['time']); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
