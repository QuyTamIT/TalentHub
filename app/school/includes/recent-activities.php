<?php
/**
 * School Dashboard - Recent Activity Component
 */
?>
<section class="sch-section-box">
    <div class="sch-section-box__header">
        <h3 class="sch-section-box__title">Hoạt động gần đây</h3>
    </div>

    <div class="sch-activity-timeline">
        <?php foreach ($recentActivities as $act): ?>
            <div class="sch-activity-item">
                <div class="sch-activity-item__indicator"></div>
                <div class="sch-activity-item__content">
                    <p class="sch-activity-item__text"><?= htmlspecialchars($act['title']); ?></p>
                    <span class="sch-activity-item__time"><?= htmlspecialchars($act['time']); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>