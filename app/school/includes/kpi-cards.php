<?php
/**
 * School Dashboard - KPI Cards Component
 */
?>
<section class="sch-kpis-grid">
    <?php foreach ($schoolKpis as $kpi): ?>
        <article class="sch-kpi-card">
            <div class="sch-kpi-card__header">
                <span class="sch-kpi-card__label"><?= htmlspecialchars($kpi['label']); ?></span>
                <span class="sch-kpi-card__icon">
                    <?php if ($kpi['icon'] === 'users'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    <?php elseif ($kpi['icon'] === 'calendar'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    <?php elseif ($kpi['icon'] === 'trending-up'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                            <polyline points="17 6 23 6 23 12"></polyline>
                        </svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    <?php endif; ?>
                </span>
            </div>

            <div class="sch-kpi-card__value"><?= htmlspecialchars($kpi['value']); ?></div>

            <div class="sch-kpi-card__footer">
                <span class="sch-kpi-card__change sch-kpi-card__change--<?= htmlspecialchars($kpi['change_type']); ?>">
                    <?= htmlspecialchars($kpi['change']); ?>
                </span>
            </div>
        </article>
    <?php endforeach; ?>
</section>