<?php
/**
 * School Dashboard - Top Talents Component
 */

if (!isset($topTalents) || !is_array($topTalents)) {
    $topTalents = [];
}

function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= mb_substr($word, 0, 1);
    }
    return $initials ?: 'SV';
}
?>
<section class="school-section-box">
    <div class="school-section-box__header">
        <div>
            <h3 class="school-section-box__title">Top tài năng nổi bật</h3>
            <p class="school-section-box__subtitle">Học sinh / Sinh viên có thành tích xuất sắc</p>
        </div>
        <a href="./analytics.php" class="school-section-box__link">Xem tất cả</a>
    </div>
    <?php if (empty($topTalents)): ?>
        <div style="text-align: center; color: var(--text-muted); padding: 2rem 1rem;">
            <p style="font-size: 0.925rem; color: var(--text-secondary); margin: 0;">Chưa có tài năng nổi bật được ghi nhận.</p>
        </div>
    <?php else: ?>
    <div class="school-talents-list">
        <?php foreach ($topTalents as $talent): ?>
            <div class="school-talent-card">
                <div class="school-talent-card__left">
                    <div class="school-talent-card__avatar">
                        <?= htmlspecialchars(getInitials($talent['name'])); ?>
                    </div>
                    <div class="school-talent-card__details">
                        <div class="school-talent-card__name-row">
                            <span class="school-talent-card__name"><?= htmlspecialchars($talent['name']); ?></span>
                            <span class="school-talent-card__score-badge"><?= htmlspecialchars($talent['score']); ?></span>
                        </div>
                        <div class="school-talent-card__meta-line">
                            <span>Lớp <?= htmlspecialchars($talent['class']); ?></span>
                            <span class="school-talent-card__meta-divider">•</span>
                            <span class="school-talent-card__exp"><?= htmlspecialchars($talent['talent']); ?></span>
                        </div>
                    </div>
                </div>
                <div class="school-talent-card__actions">
                    <button class="btn btn-sm btn-outline">Xem hồ sơ</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
