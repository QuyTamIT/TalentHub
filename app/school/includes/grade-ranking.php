<?php
/**
 * School Dashboard - Grade Ranking Component
 * Renders bar chart comparing 3 grades (10, 11, 12).
 */
?>
<section class="sch-section-box">
    <div class="sch-section-box__header">
        <div>
            <h3 class="sch-section-box__title">Bảng xếp hạng khối</h3>
            <p class="sch-section-box__subtitle">Tổng giờ hoạt động và điểm trung bình năng lực theo khối</p>
        </div>
    </div>

    <div class="sch-ranking-list">
        <?php
        $maxHours = max(array_column($gradeRanking, 'hours'));
        $medals = ['gold', 'silver', 'bronze'];
        $medalSymbols = ['🥇', '🥈', '🥉'];
        ?>
        <?php foreach ($gradeRanking as $i => $row): ?>
            <div class="sch-ranking-row">
                <div class="sch-ranking-row__medal sch-ranking-row__medal--<?= $medals[$i] ?? 'bronze'; ?>">
                    <?= $medalSymbols[$i] ?? ($row['rank']); ?>
                </div>
                <div class="sch-ranking-row__body">
                    <div class="sch-ranking-row__top">
                        <span class="sch-ranking-row__grade">
                            <?= htmlspecialchars($row['grade']); ?>
                            &bull;
                            <span style="font-weight: 500; color: var(--text-secondary);">
                                <?= number_format($row['students']); ?> học sinh
                            </span>
                        </span>
                        <span class="sch-ranking-row__hours"><?= number_format($row['hours']); ?>h</span>
                    </div>
                    <div class="sch-ranking-row__bar-track">
                        <div class="sch-ranking-row__bar-fill"
                             style="width: <?= ($row['hours'] / $maxHours) * 100; ?>%;"></div>
                    </div>
                </div>
                <div class="sch-ranking-row__score"><?= $row['avg_score']; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>