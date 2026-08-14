<?php
/** TalentHub Learner - Aptitude discovery */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/assessment-data.php';

$pageTitle = 'Khám phá năng khiếu';
$currentRoute = '/app/learner/discover.php';
$assessmentLabels = [
    'result' => 'Xem kết quả',
    'continue' => 'Tiếp tục',
    'start' => 'Bắt đầu bài test',
];
$hollandDefinition = learner_assessment_definition('holland');

$radarCenterX = 260;
$radarCenterY = 180;
$radarRadius = 120;
$radarAngles = [-90, -30, 30, 90, 150, 210];
$radarPoints = [];
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
                <div class="learner-page-heading">
                    <h1>Khám phá năng khiếu</h1>
                    <p>Bộ bài đánh giá giúp bạn hiểu chính mình hơn.</p>
                </div>

                <section class="learner-assessment-grid" aria-label="Các bài đánh giá năng khiếu">
                    <?php foreach ($assessments as $assessment): ?>
                        <article class="learner-card learner-assessment-card" data-assessment-card="<?= learner_escape($assessment['id']); ?>" data-state="<?= learner_escape($assessment['state']); ?>">
                            <span class="learner-assessment-card__status <?= $assessment['id'] === 'holland' ? 'is-experimental' : 'is-coming-soon'; ?>">
                                <?= $assessment['id'] === 'holland' ? 'Bản thử nghiệm' : 'Sắp triển khai'; ?>
                            </span>
                            <span class="learner-assessment-card__icon learner-icon-tile learner-icon-tile--<?= learner_escape($assessment['tone']); ?>"><?= learner_icon($assessment['icon'], 28); ?></span>
                            <h2><?= learner_escape($assessment['name']); ?></h2>
                            <p><?= learner_escape($assessment['description']); ?></p>
                            <?php if ($assessment['state'] === 'continue'): ?>
                                <div class="learner-assessment-progress" aria-label="Tiến độ <?= learner_escape($assessment['name']); ?>">
                                    <span><?= learner_escape($assessment['progress']); ?>% hoàn thành</span>
                                    <div class="learner-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= learner_escape($assessment['progress']); ?>">
                                        <span class="learner-progress--warning" style="--learner-progress: <?= learner_escape($assessment['progress']); ?>%;"></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($assessment['id'] === 'holland'): ?>
                                <a class="learner-btn learner-btn--primary learner-btn--block" href="assessment.php?id=holland">Mở bài test Holland</a>
                            <?php else: ?>
                                <button
                                    class="learner-btn <?= $assessment['state'] === 'start' ? 'learner-btn--primary' : 'learner-btn--secondary'; ?> learner-btn--block"
                                    type="button"
                                    data-assessment-action="<?= learner_escape($assessment['state']); ?>"
                                    data-assessment-id="<?= learner_escape($assessment['id']); ?>"
                                    data-assessment-name="<?= learner_escape($assessment['name']); ?>"
                                    data-assessment-result="<?= learner_escape($assessmentResults[$assessment['id']]); ?>"
                                >
                                    Xem dữ liệu demo
                                </button>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </section>

                <section class="learner-card learner-holland-latest" data-holland-latest hidden>
                    <div><span class="learner-eyebrow">Kết quả Holland trên trình duyệt này</span><h2>Mã Holland gần nhất: <strong data-holland-latest-code></strong></h2><p data-holland-latest-date></p></div>
                    <a class="learner-btn learner-btn--outline" data-holland-latest-link href="assessment-result.php?id=holland">Xem kết quả chi tiết</a>
                </section>

                <div class="learner-data-note learner-discover-data-note"><?= learner_icon('info', 17); ?><p>Biểu đồ “Bản đồ năng khiếu” bên dưới là dữ liệu demo của bài Đa trí thông minh, không tự thay đổi theo kết quả Holland.</p></div>

                <div class="learner-discovery-grid">
                    <section class="learner-card learner-radar-card" aria-labelledby="radar-title">
                        <div class="learner-section-heading learner-section-heading--stacked">
                            <h2 id="radar-title">Bản đồ năng khiếu</h2>
                            <p>Đa trí thông minh – Multiple Intelligence</p>
                        </div>

                        <div class="learner-radar-wrap">
                            <svg class="learner-radar" viewBox="0 0 520 360" role="img" aria-labelledby="radar-svg-title radar-svg-desc">
                                <title id="radar-svg-title">Biểu đồ radar năng khiếu của Nguyễn Văn A</title>
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

    <div class="learner-modal" id="learner-assessment-modal" role="dialog" aria-modal="true" aria-labelledby="learner-assessment-modal-title" hidden>
        <div class="learner-modal__backdrop" data-close-modal></div>
        <div class="learner-modal__dialog learner-modal__dialog--compact" tabindex="-1">
            <div class="learner-modal__header">
                <div>
                    <span class="learner-modal__eyebrow">Khám phá năng khiếu</span>
                    <h2 id="learner-assessment-modal-title" data-assessment-modal-title>Bài đánh giá</h2>
                </div>
                <button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng thông tin bài đánh giá"><?= learner_icon('x', 22); ?></button>
            </div>
            <p class="learner-modal__copy" data-assessment-modal-copy></p>
            <div class="learner-modal__actions">
                <button class="learner-btn learner-btn--secondary" type="button" data-close-modal>Đóng</button>
                <button class="learner-btn learner-btn--primary" type="button" data-confirm-assessment>Tiếp tục</button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/learner-assessment.js"></script>
</body>
</html>
