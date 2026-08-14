<?php
/**
 * School Dashboard - Top Talents Component
 */

$topTalents = [
    [
        'name' => 'Nguyễn Văn Minh',
        'class' => '12A',
        'talent' => 'Toán học',
        'score' => '98/100'
    ],
    [
        'name' => 'Trần Thu Hà',
        'class' => '11B',
        'talent' => 'Âm nhạc',
        'score' => '95/100'
    ],
    [
        'name' => 'Lê Hoàng Nam',
        'class' => '10C',
        'talent' => 'Lập trình',
        'score' => '92/100'
    ],
    [
        'name' => 'Phạm Thị Lan',
        'class' => '12D',
        'talent' => 'Ngữ Văn',
        'score' => '90/100'
    ]
];

function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= mb_substr($word, 0, 1);
    }
    return $initials;
}
?>
<section class="school-section-box">
    <div class="school-section-box__header">
        <div>
            <h3 class="school-section-box__title">Top tài năng nổi bật</h3>
            <p class="school-section-box__subtitle">Học sinh có thành tích xuất sắc</p>
        </div>
        <a href="/app/school/analytics.php" class="school-section-box__link">Xem tất cả</a>
    </div>
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
</section>
