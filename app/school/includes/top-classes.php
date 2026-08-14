<?php
/**
 * School Dashboard - Top Classes Component
 * Lists top 5 classes ranked by average talent score.
 */
?>
<section class="sch-section-box">
    <div class="sch-section-box__header">
        <div>
            <h3 class="sch-section-box__title">Top 5 lớp xuất sắc</h3>
            <p class="sch-section-box__subtitle">Lớp có điểm năng lực trung bình cao nhất trường</p>
        </div>
        <a href="classes.php" class="sch-section-box__link" data-route="classes.php">
            Xem tất cả &rarr;
        </a>
    </div>

    <div class="sch-classes-list">
        <?php foreach ($topClasses as $cls): ?>
            <div class="sch-class-row">
                <div class="sch-class-row__rank <?= $cls['rank'] <= 3 ? 'sch-class-row__rank--' . $cls['rank'] : ''; ?>">
                    <?= $cls['rank']; ?>
                </div>
                <div class="sch-class-row__info">
                    <div class="sch-class-row__name">Lớp <?= htmlspecialchars($cls['class']); ?></div>
                    <div class="sch-class-row__major"><?= htmlspecialchars($cls['major']); ?> &bull; <?= $cls['students']; ?> học sinh</div>
                </div>
                <div class="sch-class-row__score"><?= $cls['score']; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>