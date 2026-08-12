<?php
/**
 * Teacher Dashboard - Activity Overview Component
 */
?>
<section class="teacher-section-box">
    <div class="teacher-section-box__header">
        <div>
            <h3 class="teacher-section-box__title">Tổng quan hoạt động / học viên</h3>
            <p class="teacher-section-box__subtitle">Tổng hợp từ hoạt động, đăng ký, điểm danh và giờ trải nghiệm.</p>
        </div>
    </div>

    <div class="teacher-overview-grid">
        <?php foreach ($activityOverview as $item): ?>
            <article class="teacher-overview-card">
                <span class="teacher-overview-card__label"><?= htmlspecialchars($item['label']); ?></span>
                <strong class="teacher-overview-card__value"><?= htmlspecialchars($item['value']); ?></strong>
                <span class="teacher-overview-card__meta"><?= htmlspecialchars($item['meta']); ?></span>
                <div class="teacher-progress">
                    <div class="teacher-progress__top">
                        <span><?= htmlspecialchars($item['bar_label']); ?></span>
                        <strong><?= htmlspecialchars((string) $item['bar_value']); ?>%</strong>
                    </div>
                    <div class="teacher-progress__track" aria-hidden="true">
                        <span style="width: <?= htmlspecialchars((string) $item['bar_value']); ?>%;"></span>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
