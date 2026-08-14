<?php
/**
 * School Dashboard - Talent Distribution Component
 * Renders SVG donut chart + legend with progress bars.
 */
?>
<section class="sch-section-box">
    <div class="sch-section-box__header">
        <div>
            <h3 class="sch-section-box__title">Phân bổ năng khiếu</h3>
            <p class="sch-section-box__subtitle">Tỷ lệ học sinh theo 5 lĩnh vực năng khiếu</p>
        </div>
        <a href="analytics.php" class="sch-section-box__link" data-route="analytics.php">
            Phân tích chi tiết &rarr;
        </a>
    </div>

    <div class="sch-distribution">
        <?php
        // Compute donut arc segments (SVG path)
        $radius = 70;
        $circumference = 2 * M_PI * $radius;
        $offset = 0;
        $colors = [
            'orange' => '#F97316',
            'blue'   => '#2563EB',
            'purple' => '#9333EA',
            'green'  => '#16A34A',
            'amber'  => '#F59E0B'
        ];
        ?>
        <div class="sch-distribution__chart">
            <svg width="180" height="180" viewBox="0 0 180 180" role="img" aria-labelledby="donutTitle donutDesc">
                <title id="donutTitle">Biểu đồ phân bổ năng khiếu toàn trường</title>
                <desc id="donutDesc">Biểu đồ tròn thể hiện tỷ lệ phần trăm học sinh theo 5 lĩnh vực: Kỹ thuật, Học thuật, Nghệ thuật, Kinh doanh, Thể thao.</desc>
                <circle cx="90" cy="90" r="<?= $radius; ?>" fill="none" stroke="#F1F5F9" stroke-width="22"/>
                <?php foreach ($talentDistribution as $i => $item):
                    $segLen = ($item['percent'] / 100) * $circumference;
                    $color = $colors[$item['color']] ?? '#64748B';
                ?>
                    <circle cx="90" cy="90" r="<?= $radius; ?>"
                            fill="none"
                            stroke="<?= $color; ?>"
                            stroke-width="22"
                            stroke-dasharray="<?= $segLen; ?> <?= $circumference - $segLen; ?>"
                            stroke-dashoffset="<?= -$offset; ?>"
                            transform="rotate(-90 90 90)"
                            style="transition: stroke-dasharray 0.4s ease;"/>
                    <?php $offset += $segLen; endforeach; ?>
                <text x="90" y="86" text-anchor="middle" font-size="13" fill="#64748B" font-weight="500">Tổng cộng</text>
                <text x="90" y="108" text-anchor="middle" font-size="22" fill="#0F172A" font-weight="700">1,247</text>
                <text x="90" y="124" text-anchor="middle" font-size="11" fill="#94A3B8" font-weight="500">học sinh</text>
            </svg>
        </div>

        <div class="sch-distribution__legend">
            <?php foreach ($talentDistribution as $item): ?>
                <div class="sch-distribution__row">
                    <div class="sch-distribution__row-left">
                        <span class="sch-distribution__dot sch-distribution__dot--<?= htmlspecialchars($item['color']); ?>"></span>
                        <span><?= htmlspecialchars($item['field']); ?></span>
                    </div>
                    <div class="sch-distribution__row-right">
                        <div class="sch-distribution__bar-track">
                            <div class="sch-distribution__bar-fill"
                                 style="width: <?= $item['percent']; ?>%; background-color: <?= $colors[$item['color']] ?? '#64748B'; ?>;"></div>
                        </div>
                        <span class="sch-distribution__percent"><?= $item['percent']; ?>%</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>