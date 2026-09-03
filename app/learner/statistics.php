<?php
/** TalentHub Learner - Personal statistics */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Thống kê cá nhân';
$currentRoute = '/app/learner/statistics.php';

$selectedPeriod = $_GET['period'] ?? 'month';
$selectedPeriod = in_array(strtolower(trim((string) $selectedPeriod)), ['week', 'month'], true)
    ? strtolower(trim((string) $selectedPeriod))
    : 'month';

$statsData = null;
$statisticsLoadError = false;
if ($isDatabaseMode ?? false) {
    try {
        $studentId = (string) ($student['id'] ?? learner_current_student_id());
        $statsData = learner_repository_factory()->statisticsService()->forStudentPeriod($studentId, $selectedPeriod);
    } catch (Throwable $e) {
        $statsData = null;
        $statisticsLoadError = true;
    }
}

if ($statsData !== null) {
    $kpis = $statsData['kpis'];
    $experience = $statsData['experience'];
    $fields = $statsData['fields'];
    $facts = $statsData['facts'];
    $level = $statsData['level'];
    $periodLabel = $statsData['period']['label'];
} else {
    // Fallback
    $kpis = [
        ['id' => 'hours', 'label' => 'Giờ trải nghiệm', 'value' => 0, 'suffix' => 'giờ', 'tone' => 'teal', 'icon' => 'clock'],
        ['id' => 'activities', 'label' => 'Hoạt động tham gia', 'value' => 0, 'suffix' => 'hoạt động', 'tone' => 'orange', 'icon' => 'activity'],
        ['id' => 'assessments', 'label' => 'Đánh giá hoàn thành', 'value' => 0, 'suffix' => 'bài', 'tone' => 'purple', 'icon' => 'award'],
        ['id' => 'badges', 'label' => 'Huy hiệu đạt được', 'value' => 0, 'suffix' => 'huy hiệu', 'tone' => 'blue', 'icon' => 'star'],
    ];
    $experience = ['hours' => [0, 0, 0, 0, 0, 0, 0], 'labels' => ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']];
    $fields = [];
    $facts = ['confirmed_experience_hours' => 0, 'attended_activity_count' => 0, 'submitted_assessment_type_count' => 0, 'published_teacher_evaluation_count' => 0];
    $level = ['name' => 'Explorer', 'number' => 1, 'currentHours' => 0, 'targetHours' => 10, 'progressPercent' => 0];
    $periodLabel = $selectedPeriod === 'week' ? 'Tuần này' : 'Tháng này';
}

// Chart calculations
$hoursList = $experience['hours'] ?? [];
$labelsList = $experience['labels'] ?? [];
$datesList = $experience['dates'] ?? [];
$chartMaximum = max(10, ...($hoursList !== [] ? $hoursList : [10]));
$chartLeft = 46;
$chartTop = 24;
$chartWidth = 550;
$chartHeight = 170;
$pointCount = max(1, count($hoursList));
$chartStep = $chartWidth / $pointCount;
$barWidth = min(36, $chartStep * 0.55);
$axisLabelIndexes = [];
$axisLabelCount = min(7, count($hoursList));
if ($axisLabelCount === 1) {
    $axisLabelIndexes[0] = true;
} elseif ($axisLabelCount > 1) {
    for ($slot = 0; $slot < $axisLabelCount; $slot++) {
        $axisLabelIndexes[(int) round($slot * (count($hoursList) - 1) / ($axisLabelCount - 1))] = true;
    }
}

// Field donut calculations
$fieldTotal = array_sum(array_column($fields, 'hours'));
$donutRadius = 70;
$donutCircumference = 2 * M_PI * $donutRadius;
$donutOffset = 0.0;

$fieldColorMap = [
    'technology' => 'primary',
    'career' => 'secondary',
    'personal' => 'warning',
    'academic' => 'accent',
    'general' => 'neutral',
];
$fieldLabelMap = [
    'technology' => 'Công nghệ',
    'career' => 'Hướng nghiệp',
    'personal' => 'Phát triển cá nhân',
    'academic' => 'Học thuật',
    'general' => 'Khác',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Theo dõi thống kê học tập và trải nghiệm cá nhân của học sinh, sinh viên trên TalentHub.">
    <title>Thống kê cá nhân | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
    <link rel="stylesheet" href="../../assets/css/typeui-selects.css">
</head>
<body class="learner-app learner-page-statistics">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-statistics-page-title',
                    'eyebrow' => 'Nhìn lại hành trình',
                    'title' => 'Thống kê cá nhân',
                    'description' => 'Theo dõi tiến bộ học tập và giờ trải nghiệm thực tế của riêng bạn.',
                    'icon' => 'chart',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>

                <?php if ($statisticsLoadError): ?>
                    <section class="learner-card learner-empty-state" role="alert" data-statistics-load-error>
                        <span class="learner-empty-state__icon"><?= learner_icon('chart', 30); ?></span>
                        <h2>Chưa có dữ liệu thống kê</h2>
                        <p>Không thể tải dữ liệu đã xác nhận ở thời điểm này.</p>
                        <a class="learner-btn learner-btn--outline" href="statistics.php?period=<?= learner_escape($selectedPeriod); ?>">Thử tải lại</a>
                    </section>
                <?php endif; ?>

                <div class="learner-statistics-heading learner-statistics-heading--actions">
                    <div class="learner-owner-badge">
                        <?= learner_icon('user', 16); ?>
                        <span>Dữ liệu tổng hợp từ hồ sơ cá nhân của bạn</span>
                    </div>

                    <label class="learner-statistics-period typeui-select-shell" for="learner-statistics-period">
                        <?= learner_icon('calendar', 19); ?>
                        <span class="learner-visually-hidden">Chọn khoảng thời gian thống kê</span>
                        <select id="learner-statistics-period" name="period" class="typeui-select typeui-select--bare">
                            <option value="month" <?= $selectedPeriod === 'month' ? 'selected' : ''; ?>>Tháng này</option>
                            <option value="week" <?= $selectedPeriod === 'week' ? 'selected' : ''; ?>>Tuần này</option>
                        </select>
                    </label>
                </div>

                <p class="learner-visually-hidden" data-statistics-status role="status" aria-live="polite" aria-atomic="true">
                    Đang hiển thị thống kê <?= learner_escape($periodLabel); ?>.
                </p>

                <div data-statistics-content>
                    <section class="learner-card learner-statistics-lifetime" aria-labelledby="learner-statistics-lifetime-title">
                        <div class="learner-statistics-section-heading">
                            <div>
                                <span class="learner-statistics-section-heading__eyebrow">Toàn bộ hành trình</span>
                                <h2 id="learner-statistics-lifetime-title">Tổng tích lũy</h2>
                            </div>
                            <p>Các số liệu đã xác nhận từ hồ sơ của bạn, không bị giới hạn bởi kỳ đang chọn.</p>
                        </div>
                        <div class="learner-statistics-lifetime__grid">
                            <article class="learner-statistics-lifetime__item learner-statistics-lifetime__item--teal">
                                <span class="learner-statistics-lifetime__icon" aria-hidden="true"><?= learner_icon('clock', 22); ?></span>
                                <div><strong data-lifetime-hours><?= learner_escape($facts['confirmed_experience_hours'] ?? 0); ?></strong><span>Giờ trải nghiệm</span></div>
                            </article>
                            <article class="learner-statistics-lifetime__item learner-statistics-lifetime__item--orange">
                                <span class="learner-statistics-lifetime__icon" aria-hidden="true"><?= learner_icon('activity', 22); ?></span>
                                <div><strong data-lifetime-activities><?= learner_escape($facts['attended_activity_count'] ?? 0); ?></strong><span>Hoạt động đã tham dự</span></div>
                            </article>
                            <article class="learner-statistics-lifetime__item learner-statistics-lifetime__item--purple">
                                <span class="learner-statistics-lifetime__icon" aria-hidden="true"><?= learner_icon('award', 22); ?></span>
                                <div><strong data-lifetime-assessments><?= learner_escape($facts['submitted_assessment_type_count'] ?? 0); ?></strong><span>Loại bài đánh giá</span></div>
                            </article>
                            <article class="learner-statistics-lifetime__item learner-statistics-lifetime__item--blue">
                                <span class="learner-statistics-lifetime__icon" aria-hidden="true"><?= learner_icon('star', 22); ?></span>
                                <div><strong data-lifetime-evaluations><?= learner_escape($facts['published_teacher_evaluation_count'] ?? 0); ?></strong><span>Đánh giá đã công bố</span></div>
                            </article>
                        </div>
                    </section>

                    <section class="learner-statistics-period-summary" aria-labelledby="learner-statistics-period-title">
                        <h2 id="learner-statistics-period-title" data-period-kpi-title>Chỉ số trong <?= learner_escape($periodLabel); ?></h2>
                        <div class="learner-statistics-kpis">
                            <?php foreach ($kpis as $kpi): ?>
                                <article class="learner-card learner-statistics-kpi learner-statistics-kpi--<?= learner_escape($kpi['tone'] ?? 'teal'); ?>" data-statistics-kpi data-kpi-id="<?= learner_escape($kpi['id']); ?>">
                                    <span class="learner-statistics-kpi__icon" aria-hidden="true"><?= learner_icon($kpi['icon'] ?? 'clock', 27); ?></span>
                                    <div>
                                        <strong data-kpi-value><?= learner_escape($kpi['value']); ?></strong>
                                        <span data-kpi-suffix><?= learner_escape($kpi['suffix']); ?></span>
                                    </div>
                                    <p><?= learner_escape($kpi['label']); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <div class="learner-statistics-grid">
                        <section class="learner-card learner-statistics-panel learner-statistics-experience" aria-labelledby="learner-experience-title">
                            <div class="learner-statistics-panel__heading">
                                <h2 id="learner-experience-title" data-experience-period-title>Giờ trải nghiệm (<?= learner_escape($periodLabel); ?>)</h2>
                                <div class="learner-chart-legend" aria-hidden="true">
                                    <span><i class="learner-chart-legend__bar"></i> Giờ trong kỳ đã chọn</span>
                                </div>
                            </div>
                            <svg class="learner-experience-chart" data-experience-chart viewBox="0 0 640 250" role="img" aria-labelledby="learner-experience-chart-title">
                                <title id="learner-experience-chart-title" data-experience-chart-title>Biểu đồ giờ trải nghiệm cá nhân <?= learner_escape($periodLabel); ?></title>
                                <g class="learner-experience-chart__grid" aria-hidden="true">
                                    <line x1="46" y1="24" x2="46" y2="194"></line>
                                    <line x1="46" y1="194" x2="596" y2="194"></line>
                                    <line x1="46" y1="109" x2="596" y2="109"></line>
                                    <line x1="46" y1="24" x2="596" y2="24"></line>
                                </g>
                                <g data-experience-bars aria-hidden="true">
                                    <?php foreach ($hoursList as $index => $hours): ?>
                                        <?php
                                        $height = $chartMaximum > 0 ? ($hours / $chartMaximum * $chartHeight) : 0;
                                        $x = $chartLeft + ($index + 0.5) * $chartStep - $barWidth / 2;
                                        $y = $chartTop + $chartHeight - $height;
                                        $barDate = (string) ($datesList[$index] ?? $labelsList[$index] ?? ('Mốc ' . ($index + 1)));
                                        $barTitle = 'Ngày ' . $barDate . ': ' . $hours . ' giờ';
                                        ?>
                                        <rect x="<?= learner_escape(round($x, 2)); ?>" y="<?= learner_escape(round($y, 2)); ?>" width="<?= learner_escape(round($barWidth, 2)); ?>" height="<?= learner_escape(round($height, 2)); ?>" rx="4">
                                            <title><?= learner_escape($barTitle); ?></title>
                                        </rect>
                                    <?php endforeach; ?>
                                </g>
                                <g class="learner-experience-chart__labels" data-experience-labels aria-hidden="true">
                                    <?php foreach ($labelsList as $index => $label): ?>
                                        <?php if (isset($axisLabelIndexes[$index])): ?>
                                            <text x="<?= learner_escape(round($chartLeft + ($index + 0.5) * $chartStep, 2)); ?>" y="220" text-anchor="middle"><?= learner_escape($label); ?></text>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </g>
                            </svg>
                            <ol class="learner-visually-hidden" data-experience-accessible-list aria-label="Dữ liệu giờ trải nghiệm theo ngày">
                                <?php foreach ($hoursList as $index => $hours): ?>
                                    <?php
                                    $accessibleDate = (string) ($datesList[$index] ?? $labelsList[$index] ?? ('Mốc ' . ($index + 1)));
                                    $accessibleTitle = 'Ngày ' . $accessibleDate . ': ' . $hours . ' giờ';
                                    ?>
                                    <li><?= learner_escape($accessibleTitle); ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </section>

                        <section class="learner-card learner-statistics-panel learner-field-panel" aria-labelledby="learner-field-title">
                            <h2 id="learner-field-title">Phân bổ lĩnh vực trải nghiệm</h2>
                            <div class="learner-field-chart-wrap" data-field-content <?= $fields === [] ? 'hidden' : ''; ?>>
                                <svg class="learner-field-chart" data-field-chart viewBox="0 0 200 200" role="img" aria-labelledby="learner-field-chart-title">
                                    <title id="learner-field-chart-title">Phân bổ giờ trải nghiệm cá nhân theo lĩnh vực</title>
                                    <circle class="learner-statistics-donut__track" cx="100" cy="100" r="<?= learner_escape($donutRadius); ?>"></circle>
                                    <g data-field-segments>
                                        <?php foreach ($fields as $field): ?>
                                            <?php
                                            $category = (string) ($field['category'] ?? 'general');
                                            $catTone = $fieldColorMap[$category] ?? 'neutral';
                                            $segmentLength = $donutCircumference * ($field['percentage'] ?? 0) / 100;
                                            ?>
                                            <circle
                                                class="learner-statistics-donut__segment learner-statistics-donut__segment--<?= learner_escape($catTone); ?>"
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
                                    <?php foreach ($fields as $field): ?>
                                        <?php
                                        $category = (string) ($field['category'] ?? 'general');
                                        $catTone = $fieldColorMap[$category] ?? 'neutral';
                                        $catLabel = $fieldLabelMap[$category] ?? ucfirst($category);
                                        ?>
                                        <div class="learner-field-legend__item">
                                            <span class="learner-field-legend__dot learner-field-legend__dot--<?= learner_escape($catTone); ?>" aria-hidden="true"></span>
                                            <span><strong><?= learner_escape($catLabel); ?></strong><small><?= learner_escape($field['hours']); ?> giờ (<?= learner_escape($field['percentage']); ?>%)</small></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="learner-statistics-field-empty" data-field-empty <?= $fields !== [] ? 'hidden' : ''; ?>>
                                <p>Chưa có dữ liệu phân bổ lĩnh vực trong khoảng thời gian này.</p>
                            </div>
                        </section>
                    </div>

                    <section class="learner-card learner-level-summary-card">
                        <div class="learner-level-summary-card__identity">
                            <span class="learner-level-emblem learner-level-summary-card__emblem" aria-hidden="true"><?= learner_icon('award', 40); ?></span>
                            <div>
                                <h3>Cấp độ: <?= learner_escape($level['name']); ?> (Cấp <?= learner_escape($level['number']); ?>)</h3>
                                <p>Tiến độ cấp độ được tính từ dữ liệu trải nghiệm đã xác nhận.</p>
                            </div>
                        </div>
                        <div class="learner-level-summary-card__progress">
                            <div class="learner-level-summary-card__progress-heading">
                                <span>Tiến độ cấp độ</span>
                                <span><?= learner_escape($level['progressPercent'] ?? 0); ?>%</span>
                            </div>
                            <div class="learner-progress" role="progressbar" aria-label="Tiến độ cấp độ" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= learner_escape($level['progressPercent'] ?? 0); ?>">
                                <span style="--learner-progress: <?= learner_escape($level['progressPercent'] ?? 0); ?>%;"></span>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner-statistics.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
