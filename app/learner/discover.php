<?php
/** TalentHub Learner - Aptitude discovery */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/assessment-data.php';

$pageTitle = 'Khám phá năng khiếu';
$currentRoute = '/app/learner/discover.php';

$assessmentCodes = ['holland', 'mbti', 'disc', 'multiple_intelligence'];

$radarCenterX = 260;
$radarCenterY = 180;
$radarRadius = 120;
$radarAngles = [-90, -30, 30, 90, 150, 210];
$radarGrids = [];

foreach ([0.25, 0.5, 0.75, 1] as $scale) {
    $gridPoints = [];
    foreach ($radarAngles as $angle) {
        $radians = deg2rad($angle);
        $gridPoints[] = number_format($radarCenterX + cos($radians) * $radarRadius * $scale, 1, '.', '') . ','
            . number_format($radarCenterY + sin($radians) * $radarRadius * $scale, 1, '.', '');
    }
    $radarGrids[] = implode(' ', $gridPoints);
}

$radarPoints = [];
foreach ($radarScores as $index => $score) {
    $radians = deg2rad($radarAngles[$index]);
    $scaledRadius = $radarRadius * ((float) $score['score'] / 100);
    $radarPoints[] = [
        'x' => number_format($radarCenterX + cos($radians) * $scaledRadius, 1, '.', ''),
        'y' => number_format($radarCenterY + sin($radians) * $scaledRadius, 1, '.', ''),
    ];
}

$radarPolygon = implode(' ', array_map(
    static fn(array $point): string => $point['x'] . ',' . $point['y'],
    $radarPoints
));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Khám phá năng khiếu và định hướng phát triển của bạn trên TalentHub.">
    <title>Khám phá năng khiếu | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-discover">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-discover-page-title',
                    'eyebrow' => 'Hiểu bản thân hơn',
                    'title' => 'Khám phá năng khiếu',
                    'description' => 'Bộ 4 bài đánh giá khoa học giúp bạn hiểu rõ điểm mạnh và định hướng phát triển toàn diện.',
                    'icon' => 'compass',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>

                <section class="learner-assessment-grid" aria-label="Các bài đánh giá năng khiếu" data-assessment-catalog data-catalog-endpoint="/app/learner/api/v1/assessments.php">
                    <div class="learner-card learner-assessment-state" data-catalog-loading>
                        <span class="learner-assessment-spinner" aria-hidden="true"></span>
                        <p>Đang tải danh mục bài đánh giá...</p>
                    </div>
                    <div class="learner-card learner-empty-catalog" data-empty-catalog hidden>
                        <p>Chưa có phiên bản được duyệt. Vui lòng quay lại sau.</p>
                    </div>
                    <div data-catalog-cards></div>
                    <div data-assessment-card-templates hidden aria-hidden="true">
                        <?php foreach ($assessmentCodes as $assessmentCode): ?>
                            <template data-assessment-card-template="<?= learner_escape($assessmentCode); ?>"></template>
                        <?php endforeach; ?>
                    </div>
                </section>

                <div class="learner-data-note learner-discover-data-note"><?= learner_icon('info', 17); ?><p>Kết quả từ 4 bài đánh giá được sử dụng để cá nhân hóa gợi ý năng lực và trải nghiệm học tập.</p></div>

                <div class="learner-discovery-grid">
                    <section class="learner-card learner-radar-card" aria-labelledby="radar-title">
                        <div class="learner-section-heading learner-section-heading--stacked">
                            <h2 id="radar-title">Bản đồ năng khiếu</h2>
                            <p>Đa trí thông minh – Multiple Intelligence</p>
                        </div>

                        <div class="learner-radar-wrap">
                            <svg class="learner-radar" viewBox="0 0 520 360" role="img" aria-labelledby="radar-svg-title radar-svg-desc">
                                <title id="radar-svg-title">Biểu đồ radar năng khiếu tổng hợp</title>
                                <desc id="radar-svg-desc">Sáu trục gồm Logic 72, Sáng tạo 66, Vận động 58, Giao tiếp 78, Âm nhạc 62 và Tự nhiên 70 điểm.</desc>

                                <?php foreach ($radarGrids as $grid): ?>
                                    <polygon class="learner-radar__grid" points="<?= learner_escape($grid); ?>"></polygon>
                                <?php endforeach; ?>

                                <?php foreach ($radarAngles as $angle): ?>
                                    <?php
                                    $radians = deg2rad($angle);
                                    $axisX = number_format($radarCenterX + cos($radians) * $radarRadius, 1, '.', '');
                                    $axisY = number_format($radarCenterY + sin($radians) * $radarRadius, 1, '.', '');
                                    ?>
                                    <line class="learner-radar__axis" x1="<?= $radarCenterX; ?>" y1="<?= $radarCenterY; ?>" x2="<?= learner_escape($axisX); ?>" y2="<?= learner_escape($axisY); ?>"></line>
                                <?php endforeach; ?>

                                <polygon class="learner-radar-data" points="<?= learner_escape($radarPolygon); ?>"></polygon>

                                <?php foreach ($radarPoints as $point): ?>
                                    <circle class="learner-radar__point" cx="<?= learner_escape($point['x']); ?>" cy="<?= learner_escape($point['y']); ?>" r="4"></circle>
                                <?php endforeach; ?>

                                <g class="learner-radar__labels">
                                    <text x="260" y="28" text-anchor="middle">Logic</text>
                                    <text x="400" y="112" text-anchor="start">Sáng tạo</text>
                                    <text x="405" y="258" text-anchor="start">Vận động</text>
                                    <text x="260" y="346" text-anchor="middle">Giao tiếp</text>
                                    <text x="115" y="258" text-anchor="end">Âm nhạc</text>
                                    <text x="110" y="112" text-anchor="end">Tự nhiên</text>
                                </g>
                            </svg>
                        </div>

                        <div class="learner-radar-legend" aria-label="Điểm chi tiết">
                            <?php foreach ($radarScores as $score): ?>
                                <span><?= learner_icon($score['icon'], 16); ?> <?= learner_escape($score['label']); ?> <strong><?= learner_escape($score['score']); ?></strong></span>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="learner-card learner-directions" aria-labelledby="directions-title">
                        <div class="learner-section-heading learner-section-heading--stacked">
                            <p>Kết quả tổng hợp</p>
                            <h2 id="directions-title">Định hướng của bạn</h2>
                        </div>
                        <div class="learner-direction-list">
                            <?php foreach ($careerDirections as $direction): ?>
                                <article class="learner-direction-row">
                                    <span class="learner-direction-row__icon learner-icon-tile learner-icon-tile--<?= learner_escape($direction['tone']); ?>"><?= learner_icon($direction['icon'], 22); ?></span>
                                    <div class="learner-direction-row__content">
                                        <div>
                                            <span><?= learner_escape($direction['label']); ?></span>
                                            <strong><?= learner_escape($direction['score']); ?>%</strong>
                                        </div>
                                        <div class="learner-progress" role="progressbar" aria-label="<?= learner_escape($direction['label']); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= learner_escape($direction['score']); ?>">
                                            <span class="learner-progress--<?= learner_escape($direction['tone']); ?>" style="--learner-progress: <?= learner_escape($direction['score']); ?>%;"></span>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script id="learner-session-boot" type="application/json"><?= json_encode(['csrfToken' => $GLOBALS['learner_page_context']['csrfToken'] ?? ''], JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/learner-assessment.js"></script>
</body>
</html>
