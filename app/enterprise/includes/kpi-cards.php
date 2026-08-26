<?php
/**
 * Enterprise Dashboard - 4 Metric KPI Cards Component
 * 
 * Clean, modern 4-column metric bar with pastel icon badges,
 * large bold figures, clear labels, and subtle green trend badges.
 */
?>
<section class="ent-metrics-bar" aria-label="Các chỉ số hoạt động chính">
    <?php foreach ($kpis as $kpi): 
        $colorClass = $kpi['color'] ?? 'blue';
    ?>
        <article class="ent-metric-card ent-metric-card--<?= htmlspecialchars($colorClass); ?>">
            <!-- Top Row: Pastel Icon Badge -->
            <div class="ent-metric-card__header">
                <div class="ent-metric-card__icon-box ent-metric-card__icon-box--<?= htmlspecialchars($colorClass); ?>" aria-hidden="true">
                    <?php if ($kpi['icon'] === 'user-check'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <polyline points="17 11 19 13 23 9"></polyline>
                        </svg>
                    <?php elseif ($kpi['icon'] === 'file-text'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                    <?php elseif ($kpi['icon'] === 'gift'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 12 20 22 4 22 4 12"></polyline>
                            <rect x="2" y="7" width="20" height="5"></rect>
                            <line x1="12" y1="22" x2="12" y2="7"></line>
                            <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path>
                            <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>
                        </svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                            <polyline points="17 6 23 6 23 12"></polyline>
                        </svg>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Large Bold Metric Value -->
            <div class="ent-metric-card__value"><?= htmlspecialchars($kpi['value']); ?></div>

            <!-- Metric Name Label -->
            <div class="ent-metric-card__label"><?= htmlspecialchars($kpi['label']); ?></div>

            <!-- Bottom Green Trend Status -->
            <div class="ent-metric-card__footer">
                <span class="ent-metric-card__trend ent-metric-card__trend--positive">
                    <?php if (strpos($kpi['change'], '↑') !== false || strpos($kpi['change'], '+') !== false): ?>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="18 15 12 9 6 15"></polyline>
                        </svg>
                    <?php else: ?>
                        <span class="ent-metric-card__dot" aria-hidden="true"></span>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($kpi['change']); ?></span>
                </span>
            </div>
        </article>
    <?php endforeach; ?>
</section>
