<?php
/** TalentHub Learner - Personal statistics */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Thống kê cá nhân';
$currentRoute = '/app/learner/statistics.php';
$currentStatistics = $learnerStatisticsPeriods[$defaultStatisticsPeriod];
$experience = $currentStatistics['experience'];
$chartMaximum = max(20, ...$experience['hours'], ...$experience['comparison']);
$chartLeft = 46;
$chartTop = 24;
$chartWidth = 550;
$chartHeight = 170;
$chartStep = $chartWidth / max(1, count($experience['hours']));
$barWidth = min(36, $chartStep * 0.42);
$linePoints = [];
$experienceSeriesDescription = 'Giờ trải nghiệm của bạn: ' . implode(', ', array_map(
    static fn ($label, $hours) => "{$label}: {$hours} giờ",
    $experience['labels'],
    $experience['hours']
));
$comparisonSeriesDescription = 'Xu hướng tham chiếu: ' . implode(', ', array_map(
    static fn ($label, $hours) => "{$label}: {$hours} giờ",
    $experience['labels'],
    $experience['comparison']
));

foreach ($experience['comparison'] as $index => $value) {
    $x = $chartLeft + ($index + 0.5) * $chartStep;
    $y = $chartTop + $chartHeight - ($value / $chartMaximum * $chartHeight);
    $linePoints[] = round($x, 2) . ',' . round($y, 2);
}

$fieldTotal = array_sum(array_column($currentStatistics['fields'], 'hours'));
$donutRadius = 70;
$donutCircumference = 2 * M_PI * $donutRadius;
$donutOffset = 0.0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Theo dõi thống kê học tập và trải nghiệm cá nhân của học sinh, sinh viên trên TalentHub.">
    <title>Thống kê cá nhân | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-statistics">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <div class="learner-statistics-heading">
                    <div class="learner-page-heading">
                        <h1>Thống kê cá nhân</h1>
                        <p>Theo dõi tiến bộ học tập và trải nghiệm của bạn theo thời gian.</p>
                    </div>

                    <label class="learner-statistics-period" for="learner-statistics-period">
                        <?= learner_icon('calendar', 19); ?>
                        <span class="learner-visually-hidden">Chọn khoảng thời gian thống kê</span>
                        <select id="learner-statistics-period" name="period">
                            <?php foreach ($learnerStatisticsPeriods as $periodId => $period): ?>
                                <option value="<?= learner_escape($periodId); ?>" <?= $periodId === $defaultStatisticsPeriod ? 'selected' : ''; ?>>
                                    <?= learner_escape($period['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <p class="learner-visually-hidden" data-statistics-status role="status" aria-live="polite" aria-atomic="true">
                    Đang hiển thị thống kê <?= learner_escape($currentStatistics['label']); ?>.
                </p>

                <div data-statistics-content>
                    <section class="learner-statistics-kpis" aria-label="Chỉ số cá nhân">
                        <?php foreach ($currentStatistics['kpis'] as $kpi): ?>
                            <article class="learner-card learner-statistics-kpi learner-statistics-kpi--<?= learner_escape($kpi['tone']); ?>" data-statistics-kpi data-kpi-id="<?= learner_escape($kpi['id']); ?>">
                                <span class="learner-statistics-kpi__icon" aria-hidden="true"><?= learner_icon($kpi['icon'], 27); ?></span>
                                <div>
                                    <strong data-kpi-value><?= learner_escape($kpi['value']); ?></strong>
                                    <span data-kpi-suffix><?= learner_escape($kpi['suffix']); ?></span>
                                </div>
                                <p data-kpi-change><?= learner_escape($kpi['change']); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </section>

                    <div class="learner-statistics-grid">
                        <section class="learner-card learner-statistics-panel learner-statistics-experience" aria-labelledby="learner-experience-title">
                            <div class="learner-statistics-panel__heading">
                                <h2 id="learner-experience-title">Giờ trải nghiệm theo tháng</h2>
                                <div class="learner-chart-legend" aria-hidden="true">
                                    <span><i class="learner-chart-legend__bar"></i> Thời gian của bạn</span>
                                    <span><i class="learner-chart-legend__line"></i> Xu hướng tham chiếu</span>
                                </div>
                            </div>
                            <svg class="learner-experience-chart" data-experience-chart viewBox="0 0 640 250" role="img" aria-labelledby="learner-experience-chart-title learner-experience-chart-description">
                                <title id="learner-experience-chart-title">Biểu đồ giờ trải nghiệm cá nhân theo tháng</title>
                                <desc id="learner-experience-chart-description" data-experience-description><?= learner_escape($experienceSeriesDescription . '. ' . $comparisonSeriesDescription . '.'); ?></desc>
                                <g class="learner-experience-chart__grid" aria-hidden="true">
                                    <line x1="46" y1="24" x2="46" y2="194"></line>
                                    <line x1="46" y1="194" x2="596" y2="194"></line>
                                    <line x1="46" y1="109" x2="596" y2="109"></line>
                                    <line x1="46" y1="24" x2="596" y2="24"></line>
                                </g>
                                <g data-experience-bars aria-hidden="true">
                                    <?php foreach ($experience['hours'] as $index => $hours): ?>
                                        <?php
                                        $height = $hours / $chartMaximum * $chartHeight;
                                        $x = $chartLeft + ($index + 0.5) * $chartStep - $barWidth / 2;
                                        $y = $chartTop + $chartHeight - $height;
                                        ?>
                                        <rect x="<?= learner_escape(round($x, 2)); ?>" y="<?= learner_escape(round($y, 2)); ?>" width="<?= learner_escape(round($barWidth, 2)); ?>" height="<?= learner_escape(round($height, 2)); ?>" rx="5"></rect>
                                    <?php endforeach; ?>
                                </g>
                                <g data-experience-line aria-hidden="true">
                                    <polyline points="<?= learner_escape(implode(' ', $linePoints)); ?>"></polyline>
                                    <?php foreach ($experience['comparison'] as $index => $value): ?>
                                        <?php
                                        $x = $chartLeft + ($index + 0.5) * $chartStep;
                                        $y = $chartTop + $chartHeight - ($value / $chartMaximum * $chartHeight);
                                        ?>
                                        <circle cx="<?= learner_escape(round($x, 2)); ?>" cy="<?= learner_escape(round($y, 2)); ?>" r="4"></circle>
                                    <?php endforeach; ?>
                                </g>
                                <g class="learner-experience-chart__labels" data-experience-labels aria-hidden="true">
                                    <?php foreach ($experience['labels'] as $index => $label): ?>
                                        <text x="<?= learner_escape(round($chartLeft + ($index + 0.5) * $chartStep, 2)); ?>" y="224" text-anchor="middle"><?= learner_escape($label); ?></text>
                                    <?php endforeach; ?>
                                </g>
                            </svg>
                        </section>

                        <section class="learner-card learner-statistics-panel learner-field-panel" aria-labelledby="learner-field-title">
                            <h2 id="learner-field-title">Phân bổ lĩnh vực</h2>
                            <div class="learner-field-chart-wrap">
                                <svg class="learner-field-chart" data-field-chart viewBox="0 0 200 200" role="img" aria-labelledby="learner-field-chart-title learner-field-chart-description">
                                    <title id="learner-field-chart-title">Phân bổ giờ trải nghiệm cá nhân theo lĩnh vực</title>
                                    <desc id="learner-field-chart-description" data-field-description><?= learner_escape(implode(', ', array_map(static fn ($field) => $field['label'] . ': ' . $field['hours'] . ' giờ', $currentStatistics['fields']))); ?></desc>
                                    <circle class="learner-statistics-donut__track" cx="100" cy="100" r="<?= learner_escape($donutRadius); ?>"></circle>
                                    <g data-field-segments>
                                        <?php foreach ($currentStatistics['fields'] as $field): ?>
                                            <?php
                                            $segmentLength = $donutCircumference * $field['percentage'] / 100;
                                            ?>
                                            <circle
                                                class="learner-statistics-donut__segment learner-statistics-donut__segment--<?= learner_escape($field['tone']); ?>"
                                                cx="100"
                                                cy="100"
                                                r="<?= learner_escape($donutRadius); ?>"
                                                stroke-dasharray="<?= learner_escape(round($segmentLength, 2)); ?> <?= learner_escape(round($donutCircumference - $segmentLength, 2)); ?>"
                                                stroke-dashoffset="<?= learner_escape(round(-$donutOffset, 2)); ?>"
                                            ></circle>
                                            <?php $donutOffset += $segmentLength; ?>
                                        <?php endforeach; ?>
                                    </g>
                                    <text class="learner-field-chart__total" x="100" y="96" text-anchor="middle" data-field-total><?= learner_escape($fieldTotal); ?></text>
                                    <text class="learner-field-chart__unit" x="100" y="119" text-anchor="middle">giờ</text>
                                </svg>
                                <div class="learner-field-legend" data-field-legend>
                                    <?php foreach ($currentStatistics['fields'] as $field): ?>
                                        <div class="learner-field-legend__item">
                                            <span class="learner-field-legend__dot learner-field-legend__dot--<?= learner_escape($field['tone']); ?>" aria-hidden="true"></span>
                                            <span><strong><?= learner_escape($field['label']); ?></strong><small><?= learner_escape($field['hours']); ?> giờ (<?= learner_escape($field['percentage']); ?>%)</small></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </section>

                        <section class="learner-card learner-statistics-panel learner-statistics-skills" aria-labelledby="learner-progress-title">
                            <h2 id="learner-progress-title">Kỹ năng tiến bộ</h2>
                            <div class="learner-statistics-skills__list">
                                <?php foreach ($currentStatistics['skills'] as $skill): ?>
                                    <article class="learner-statistics-skill" data-statistics-skill>
                                        <span class="learner-statistics-skill__icon learner-statistics-skill__icon--<?= learner_escape($skill['tone']); ?>" aria-hidden="true"><?= learner_icon($skill['icon'], 21); ?></span>
                                        <div>
                                            <div class="learner-statistics-skill__heading">
                                                <strong data-skill-name><?= learner_escape($skill['name']); ?></strong>
                                                <b data-skill-score><?= learner_escape($skill['score']); ?>%</b>
                                            </div>
                                            <div class="learner-progress" role="progressbar" aria-label="<?= learner_escape($skill['name']); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= learner_escape($skill['score']); ?>">
                                                <span class="learner-progress--<?= learner_escape($skill['tone']); ?>" style="--learner-progress: <?= learner_escape($skill['score']); ?>%;"></span>
                                            </div>
                                            <small data-skill-level><?= learner_escape($skill['level']); ?></small>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>

                    <section class="learner-card learner-activity-summary-section" aria-labelledby="learner-activity-summary-title">
                        <h2 id="learner-activity-summary-title">Hoạt động và hoàn thành</h2>
                        <div class="learner-activity-summary-grid">
                            <?php foreach ($currentStatistics['activities'] as $activity): ?>
                                <article class="learner-activity-summary learner-activity-summary--<?= learner_escape($activity['tone']); ?>" data-activity-summary data-activity-id="<?= learner_escape($activity['id']); ?>">
                                    <span class="learner-activity-summary__icon" aria-hidden="true"><?= learner_icon($activity['icon'], 26); ?></span>
                                    <div>
                                        <span data-activity-label><?= learner_escape($activity['label']); ?></span>
                                        <strong data-activity-value><?= learner_escape($activity['value']); ?></strong>
                                        <small data-activity-change><?= learner_escape($activity['change']); ?></small>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

                <section class="learner-card learner-empty-state learner-statistics-empty" data-statistics-empty role="status" aria-live="polite" hidden>
                    <span class="learner-empty-state__icon"><?= learner_icon('chart', 30); ?></span>
                    <h2>Chưa có dữ liệu trong khoảng thời gian này</h2>
                    <p>Hãy chọn một khoảng thời gian khác để xem tiến trình cá nhân.</p>
                </section>
            </main>
        </div>
    </div>

    <script type="application/json" id="learner-statistics-data"><?=
        json_encode(
            $learnerStatisticsPeriods,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
