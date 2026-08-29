<?php
/**
 * School Dashboard - KPI Cards Component
 */

$kpis = [
    [
        'label' => 'Sinh viên đang hoạt động',
        'value' => '1,247',
        'change' => '+12 sinh viên tuần này',
        'change_type' => 'positive',
        'icon' => 'users'
    ],
    [
        'label' => 'Hoạt động tháng này',
        'value' => '18',
        'change' => '8 sắp diễn ra',
        'change_type' => 'neutral',
        'icon' => 'calendar'
    ],
    [
        'label' => 'Chứng chỉ đã cấp',
        'value' => '456',
        'change' => '+23 tuần này',
        'change_type' => 'positive',
        'icon' => 'award'
    ],
    [
        'label' => 'Tỷ lệ hoàn thiện hồ sơ',
        'value' => '78%',
        'change' => 'Mục tiêu: 85%',
        'change_type' => 'neutral',
        'icon' => 'check-circle'
    ]
];
?>
<section class="school-kpis-grid">
    <?php foreach ($kpis as $kpi): ?>
        <article class="school-kpi-card">
            <div class="school-kpi-card__header">
                <span class="school-kpi-card__label"><?= htmlspecialchars($kpi['label']); ?></span>
                <span class="school-kpi-card__icon">
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
                    <?php elseif ($kpi['icon'] === 'award'): ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="8" r="7"></circle>
                            <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                        </svg>
                    <?php else: ?>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    <?php endif; ?>
                </span>
            </div>

            <div class="school-kpi-card__value"><?= htmlspecialchars($kpi['value']); ?></div>
            
            <div class="school-kpi-card__footer">
                <span class="school-kpi-card__change school-kpi-card__change--<?= htmlspecialchars($kpi['change_type']); ?>">
                    <?= htmlspecialchars($kpi['change']); ?>
                </span>
            </div>
        </article>
    <?php endforeach; ?>
</section>
