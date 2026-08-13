<?php
/**
 * Enterprise Dashboard - KPI Cards Component
 * Uniform, clean metric cards for enterprise metrics scanning.
 */
?>
<section class="ent-kpis-grid">
    <?php foreach ($kpis as $kpi): ?>
        <article class="ent-kpi-card">
            <div class="ent-kpi-card__header">
                <span class="ent-kpi-card__label"><?= htmlspecialchars($kpi['label']); ?></span>
                <span class="ent-kpi-card__icon">
                    <?php if ($kpi['icon'] === 'user-check'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <polyline points="17 11 19 13 23 9"></polyline>
                        </svg>
                    <?php elseif ($kpi['icon'] === 'file-text'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                    <?php elseif ($kpi['icon'] === 'gift'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 12 20 22 4 22 4 12"></polyline>
                            <rect x="2" y="7" width="20" height="5"></rect>
                            <line x1="12" y1="22" x2="12" y2="7"></line>
                        </svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                            <polyline points="17 6 23 6 23 12"></polyline>
                        </svg>
                    <?php endif; ?>
                </span>
            </div>

            <div class="ent-kpi-card__value"><?= htmlspecialchars($kpi['value']); ?></div>
            
            <div class="ent-kpi-card__footer">
                <span class="ent-kpi-card__change ent-kpi-card__change--<?= htmlspecialchars($kpi['change_type']); ?>">
                    <?= htmlspecialchars($kpi['change']); ?>
                </span>
            </div>
        </article>
    <?php endforeach; ?>
</section>
