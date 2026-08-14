<?php
/**
 * Teacher Dashboard - KPI Cards Component
 */
?>
<section class="teacher-kpis-grid" aria-label="Chỉ số tổng quan">
    <?php foreach ($kpis as $kpi): ?>
        <article class="teacher-kpi-card teacher-animate">
            <div class="teacher-kpi-card__header">
                <span class="teacher-kpi-card__label"><?= htmlspecialchars($kpi['label']); ?></span>
                <span class="teacher-kpi-card__icon">
                    <?php if ($kpi['icon'] === 'users'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    <?php elseif ($kpi['icon'] === 'trophy'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                            <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                            <path d="M4 22h16"></path>
                            <path d="M18 2H6v7a6 6 0 0 0 12 0V2z"></path>
                        </svg>
                    <?php elseif ($kpi['icon'] === 'clipboard-check'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"></path>
                            <rect x="9" y="3" width="6" height="4" rx="2"></rect>
                            <polyline points="9 14 11 16 15 12"></polyline>
                        </svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    <?php endif; ?>
                </span>
            </div>
            <div class="teacher-kpi-card__value"><?= htmlspecialchars($kpi['value']); ?></div>
            <div class="teacher-kpi-card__footer">
                <span class="teacher-kpi-card__change teacher-kpi-card__change--<?= htmlspecialchars($kpi['change_type']); ?>">
                    <?= htmlspecialchars($kpi['change']); ?>
                </span>
                <span class="teacher-status-pill teacher-status-pill--<?= htmlspecialchars($kpi['change_type']); ?>">
                    <?= htmlspecialchars($kpi['status']); ?>
                </span>
            </div>
        </article>
    <?php endforeach; ?>
</section>
