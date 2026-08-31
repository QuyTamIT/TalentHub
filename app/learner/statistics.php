<?php
/** TalentHub Learner - Personal statistics (Comprehensive Learning Analytics Dashboard) */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Thống kê cá nhân';
$currentRoute = '/app/learner/statistics.php';

$selectedPeriod = $_GET['period'] ?? 'semester';
$selectedPeriod = in_array(strtolower(trim((string) $selectedPeriod)), \TalentHub\Learner\Data\Service\StatisticsService::ALLOWED_PERIODS, true)
    ? strtolower(trim((string) $selectedPeriod))
    : 'semester';

$periodOptionLabels = [
    'semester' => 'Học kỳ hiện tại',
    'month' => 'Tháng này',
    'week' => 'Tuần này',
    'year' => 'Năm học này',
    'all' => 'Toàn bộ quá trình',
];

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
    $skillCards = $statsData['skills'];
    $evaluation = $statsData['evaluations'];
    $projects = $statsData['projects'];
    $aiInsights = $statsData['ai_insights'];
} else {
    // Fallback: giá trị mặc định trung thực khi chưa kết nối DB
    $periodLabel = $periodOptionLabels[$selectedPeriod] ?? 'Học kỳ này';
    $kpis = [
        ['id' => 'competency', 'label' => 'Điểm năng lực', 'value' => '0/100', 'suffix' => 'điểm', 'tone' => 'primary', 'icon' => 'star'],
        ['id' => 'hours', 'label' => 'Giờ trải nghiệm', 'value' => 0, 'suffix' => 'giờ', 'tone' => 'teal', 'icon' => 'clock'],
        ['id' => 'streak', 'label' => 'Chuỗi rèn luyện', 'value' => 0, 'suffix' => 'ngày', 'tone' => 'orange', 'icon' => 'flame'],
        ['id' => 'activities', 'label' => 'Hoạt động hoàn thành', 'value' => 0, 'suffix' => 'hoạt động', 'tone' => 'success', 'icon' => 'activity'],
        ['id' => 'projects', 'label' => 'Dự án tham gia', 'value' => 0, 'suffix' => 'dự án', 'tone' => 'purple', 'icon' => 'folder'],
        ['id' => 'badges', 'label' => 'Huy hiệu đạt được', 'value' => 0, 'suffix' => 'huy hiệu', 'tone' => 'blue', 'icon' => 'award'],
    ];
    $experience = ['hours' => [0, 0, 0, 0, 0, 0, 0], 'labels' => ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'], 'dates' => []];
    $fields = [];
    $facts = ['confirmed_experience_hours' => 0, 'attended_activity_count' => 0, 'submitted_assessment_type_count' => 0, 'published_teacher_evaluation_count' => 0];
    $level = \TalentHub\Learner\Data\Domain\LevelProgression::fromHours(0);
    $skillCards = [];
    $evaluation = [
        'term' => 'Học kỳ hiện tại',
        'total_score' => null,
        'ranking' => '',
        'classification' => 'Chưa có đánh giá',
        'criteria' => [],
        'teacher_comment' => '',
    ];
    $projects = [
        'total' => 0,
        'completed' => 0,
        'in_progress' => 0,
        'leader_roles' => 0,
        'featured' => [],
    ];
    $aiInsights = [
        'executive_summary' => 'Chưa có đủ dữ liệu hoạt động trong kỳ này. Hãy tham gia hoạt động và check-in để AI bắt đầu phân tích năng lực của bạn.',
        'strengths' => [],
        'recommendations' => [
            'Tham gia các hoạt động hoặc dự án để bắt đầu tích lũy giờ trải nghiệm thực tế.',
            'Duy trì check-in để xây dựng chuỗi ngày rèn luyện liên tục.',
        ],
    ];
}

$skillCategoryLabels = [
    'technical' => 'Kỹ thuật',
    'soft' => 'Kỹ năng mềm',
    'creative' => 'Sáng tạo',
    'academic' => 'Học thuật',
    'business' => 'Kinh doanh',
    'sports' => 'Thể thao',
];

$isEmptyProfile = (int) ($facts['confirmed_experience_hours'] ?? 0) === 0
    && (int) ($facts['submitted_assessment_type_count'] ?? 0) === 0
    && (int) ($facts['published_teacher_evaluation_count'] ?? 0) === 0
    && $skillCards === [];

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
    'sports' => 'teal',
    'arts' => 'purple',
    'general' => 'neutral',
];
$fieldLabelMap = [
    'technology' => 'Công nghệ & Kỹ thuật',
    'career' => 'Hướng nghiệp',
    'personal' => 'Kỹ năng mềm',
    'academic' => 'Học thuật',
    'sports' => 'Thể thao',
    'arts' => 'Nghệ thuật',
    'general' => 'Khác',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Bảng điều khiển phân tích học tập và năng lực cá nhân: giờ trải nghiệm, kỹ năng, trắc nghiệm định hướng, đánh giá giảng viên, dự án và nhận định AI.">
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
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-statistics-page-title',
                    'eyebrow' => 'Nhìn lại hành trình',
                    'title' => 'Bảng điều khiển phân tích năng lực & học tập cá nhân',
                    'description' => 'Tổng hợp đa chiều giờ trải nghiệm, kỹ năng, trắc nghiệm định hướng, đánh giá giảng viên, dự án và nhận định AI của riêng bạn.',
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

                <section class="learner-card learner-statistics-empty" role="status" data-statistics-empty <?= $isEmptyProfile ? '' : 'hidden'; ?>>
                    <span class="learner-statistics-empty__icon" aria-hidden="true"><?= learner_icon('compass', 34); ?></span>
                    <h2>Bạn đang ở bước khởi đầu hành trình!</h2>
                    <p>Hãy hoàn thành các bước dưới đây để kích hoạt toàn bộ chỉ số thống kê.</p>
                    <div class="learner-statistics-empty__actions">
                        <a class="learner-btn learner-btn--primary" href="discover.php"><?= learner_icon('compass', 18); ?> Khám phá năng khiếu</a>
                        <a class="learner-btn learner-btn--outline" href="activities.php"><?= learner_icon('calendar', 18); ?> Đăng ký hoạt động mới</a>
                        <a class="learner-btn learner-btn--outline" href="checkin.php"><?= learner_icon('qr', 18); ?> Check-in tham gia</a>
                    </div>
                </section>

                <div class="learner-statistics-heading learner-statistics-heading--actions">
                    <div class="learner-owner-badge">
                        <?= learner_icon('user', 16); ?>
                        <span>Dữ liệu tổng hợp từ hồ sơ cá nhân của bạn</span>
                    </div>

                    <label class="learner-statistics-period" for="learner-statistics-period">
                        <?= learner_icon('calendar', 19); ?>
                        <span class="learner-visually-hidden">Chọn khoảng thời gian thống kê</span>
                        <select id="learner-statistics-period" name="period">
                            <option value="semester" <?= $selectedPeriod === 'semester' ? 'selected' : ''; ?>>Học kỳ hiện tại</option>
                            <option value="month" <?= $selectedPeriod === 'month' ? 'selected' : ''; ?>>Tháng này</option>
                            <option value="week" <?= $selectedPeriod === 'week' ? 'selected' : ''; ?>>Tuần này</option>
                            <option value="year" <?= $selectedPeriod === 'year' ? 'selected' : ''; ?>>Năm học này</option>
                            <option value="all" <?= $selectedPeriod === 'all' ? 'selected' : ''; ?>>Toàn bộ quá trình</option>
                        </select>
                    </label>
                </div>

                <p class="learner-visually-hidden" data-statistics-status role="status" aria-live="polite" aria-atomic="true">
                    Đang hiển thị thống kê <?= learner_escape($periodLabel); ?>.
                </p>

                <div data-statistics-content>
                    <section class="learner-statistics-period-summary" aria-labelledby="learner-statistics-period-title">
                        <h2 id="learner-statistics-period-title" data-period-kpi-title>Chỉ số trong <?= learner_escape($periodLabel); ?></h2>
                        <div class="learner-statistics-kpis-grid">
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

                    <div class="learner-statistics-grid learner-statistics-grid--charts">
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

                    <div class="learner-statistics-grid learner-statistics-grid--competency">
                        <section class="learner-card learner-statistics-panel learner-skill-panel" aria-labelledby="learner-skills-title">
                            <div class="learner-statistics-panel__heading">
                                <h2 id="learner-skills-title">Năng lực kỹ năng cốt lõi</h2>
                                <span class="learner-skill-panel__hint">Điểm /100 · cập nhật từ hoạt động đã xác nhận</span>
                            </div>
                            <ol class="learner-skill-bars-list" data-skills-list <?= $skillCards === [] ? 'hidden' : ''; ?>>
                                <?php foreach ($skillCards as $skill): ?>
                                    <li class="learner-skill-bar" data-skill-item>
                                        <div class="learner-skill-bar__heading">
                                            <span class="learner-skill-bar__name" data-skill-name><?= learner_escape($skill['name']); ?></span>
                                            <span class="learner-skill-bar__score"><b data-skill-score><?= learner_escape($skill['score']); ?></b><small>/100</small></span>
                                        </div>
                                        <div class="learner-skill-bar__track" role="progressbar" aria-label="<?= learner_escape($skill['name']); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= learner_escape($skill['score']); ?>">
                                            <span class="learner-skill-bar__fill learner-skill-bar__fill--<?= learner_escape($skill['tone']); ?>" data-skill-fill style="--learner-progress: <?= learner_escape($skill['score']); ?>%;"></span>
                                        </div>
                                        <div class="learner-skill-bar__meta">
                                            <span class="learner-skill-bar__level" data-skill-level><?= learner_escape($skill['level']); ?></span>
                                            <span class="learner-skill-bar__category" data-skill-category><?= learner_escape($skillCategoryLabels[$skill['category']] ?? ucfirst($skill['category'])); ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <div class="learner-statistics-field-empty" data-skills-empty <?= $skillCards !== [] ? 'hidden' : ''; ?>>
                                <p>Chưa có kỹ năng nào được ghi nhận. Hãy tham gia hoạt động để hệ thống đánh giá năng lực của bạn.</p>
                            </div>
                        </section>

                        <section class="learner-card learner-evaluation-card" aria-labelledby="learner-evaluation-title" data-evaluation-card>
                            <header class="learner-evaluation-card__header">
                                <div>
                                    <h2 id="learner-evaluation-title">Điểm rèn luyện từ Giảng viên</h2>
                                    <p class="learner-evaluation-card__term" data-evaluation-term><?= learner_escape($evaluation['term'] ?? ''); ?></p>
                                </div>
                                <?php $evaluationTotal = $evaluation['total_score'] ?? null; ?>
                                <span class="learner-evaluation-card__classification learner-evaluation-card__classification--<?= $evaluationTotal !== null ? 'active' : 'empty'; ?>" data-evaluation-classification><?= learner_escape($evaluation['classification'] ?? 'Chưa có đánh giá'); ?></span>
                            </header>
                            <div class="learner-evaluation-card__score-row" data-evaluation-score-row <?= $evaluationTotal === null ? 'hidden' : ''; ?>>
                                <p class="learner-evaluation-card__total">
                                    <strong data-evaluation-total><?= learner_escape($evaluationTotal ?? 0); ?></strong><span>/100</span>
                                </p>
                                <p class="learner-evaluation-card__ranking" data-evaluation-ranking><?= learner_escape($evaluation['ranking'] ?? ''); ?></p>
                            </div>
                            <div class="learner-evaluation-card__criteria" data-evaluation-criteria>
                                <?php foreach (($evaluation['criteria'] ?? []) as $criterion): ?>
                                    <div class="learner-evaluation-criterion" data-criterion-item>
                                        <div class="learner-evaluation-criterion__heading">
                                            <span data-criterion-name><?= learner_escape($criterion['name']); ?></span>
                                            <span class="learner-evaluation-criterion__points"><b data-criterion-score><?= learner_escape($criterion['score']); ?></b>/<span data-criterion-max><?= learner_escape($criterion['max']); ?></span></span>
                                        </div>
                                        <div class="learner-progress" role="progressbar" aria-label="<?= learner_escape($criterion['name']); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= learner_escape($criterion['percentage']); ?>">
                                            <span style="--learner-progress: <?= learner_escape($criterion['percentage']); ?>%;"></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="learner-statistics-field-empty" data-evaluation-empty <?= $evaluationTotal !== null ? 'hidden' : ''; ?>>Chưa có đánh giá rèn luyện nào được công bố trong học kỳ này.</p>
                            <blockquote class="learner-evaluation-card__comment" data-evaluation-comment <?= trim((string) ($evaluation['teacher_comment'] ?? '')) === '' ? 'hidden' : ''; ?>>
                                <?= learner_icon('message-circle', 20); ?>
                                <p><?= learner_escape($evaluation['teacher_comment'] ?? ''); ?></p>
                            </blockquote>
                        </section>
                    </div>

                    <div class="learner-statistics-grid learner-statistics-grid--growth">
                        <section class="learner-card learner-projects-card" aria-labelledby="learner-projects-title" data-projects-card>
                            <header class="learner-projects-card__header">
                                <h2 id="learner-projects-title">Dự án & Nghiên cứu</h2>
                                <span class="learner-projects-card__hint">Vai trò thực tế của bạn trong các dự án</span>
                            </header>
                            <div class="learner-projects-card__stats">
                                <div class="learner-projects-card__stat learner-projects-card__stat--purple"><strong data-projects-total><?= learner_escape($projects['total'] ?? 0); ?></strong><span>Dự án tham gia</span></div>
                                <div class="learner-projects-card__stat learner-projects-card__stat--success"><strong data-projects-completed><?= learner_escape($projects['completed'] ?? 0); ?></strong><span>Hoàn thành</span></div>
                                <div class="learner-projects-card__stat learner-projects-card__stat--warning"><strong data-projects-in-progress><?= learner_escape($projects['in_progress'] ?? 0); ?></strong><span>Đang triển khai</span></div>
                                <div class="learner-projects-card__stat learner-projects-card__stat--primary"><strong data-projects-leader><?= learner_escape($projects['leader_roles'] ?? 0); ?></strong><span>Vai trò trưởng nhóm</span></div>
                            </div>
                            <ul class="learner-projects-card__list" data-projects-list <?= empty($projects['featured']) ? 'hidden' : ''; ?>>
                                <?php foreach (($projects['featured'] ?? []) as $project): ?>
                                    <li class="learner-projects-card__item" data-project-item>
                                        <span class="learner-projects-card__icon" aria-hidden="true"><?= learner_icon('folder', 20); ?></span>
                                        <span class="learner-projects-card__name" data-project-name><?= learner_escape($project['name']); ?></span>
                                        <span class="learner-badge learner-badge--muted" data-project-role><?= learner_escape($project['role']); ?></span>
                                        <span class="learner-badge learner-badge--<?= learner_escape($project['tone']); ?>" data-project-status><?= learner_escape($project['status']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="learner-statistics-field-empty" data-projects-empty <?= empty($projects['featured']) ? '' : 'hidden'; ?>>Chưa tham gia dự án nào. Hãy đăng ký dự án hoặc nghiên cứu để tích lũy sản phẩm thực tế.</p>
                        </section>

                        <section class="learner-card learner-level-summary-card learner-level-summary-card--panel" aria-labelledby="learner-level-title">
                            <div class="learner-level-summary-card__identity">
                                <span class="learner-level-emblem learner-level-summary-card__emblem" aria-hidden="true"><?= learner_icon('award', 40); ?></span>
                                <div>
                                    <h3 id="learner-level-title">Cấp độ: <?= learner_escape($level['name']); ?> (Cấp <?= learner_escape($level['number']); ?>)</h3>
                                    <p data-level-remaining><?= ($level['nextLevel'] ?? null) !== null
                                        ? 'Còn ' . learner_escape($level['remainingHours'] ?? 0) . ' giờ trải nghiệm để lên cấp ' . learner_escape($level['nextLevel']) . '.'
                                        : 'Bạn đã đạt cấp độ cao nhất của hành trình rèn luyện.'; ?></p>
                                </div>
                            </div>
                            <div class="learner-level-summary-card__progress">
                                <div class="learner-level-summary-card__progress-heading">
                                    <span>Tiến độ cấp độ</span>
                                    <span data-level-percent><?= learner_escape($level['progressPercent'] ?? 0); ?>%</span>
                                </div>
                                <div class="learner-progress" role="progressbar" aria-label="Tiến độ cấp độ" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= learner_escape($level['progressPercent'] ?? 0); ?>">
                                    <span style="--learner-progress: <?= learner_escape($level['progressPercent'] ?? 0); ?>%;"></span>
                                </div>
                                <p class="learner-level-summary-card__hours"><strong data-level-current><?= learner_escape($level['currentHours'] ?? 0); ?></strong> / <span data-level-target><?= learner_escape($level['targetHours'] ?? 0); ?></span> giờ đã xác nhận</p>
                            </div>
                        </section>
                    </div>

                    <section class="learner-card learner-ai-insights-card" aria-labelledby="learner-ai-title" data-ai-insights-card>
                        <header class="learner-ai-insights-card__header">
                            <span class="learner-ai-insights-card__icon" aria-hidden="true"><?= learner_icon('sparkles', 26); ?></span>
                            <div>
                                <span class="learner-ai-insights-card__eyebrow">Phân tích thông minh từ AI</span>
                                <h2 id="learner-ai-title">Nhận định & bước phát triển tiếp theo</h2>
                            </div>
                        </header>
                        <p class="learner-ai-insights-card__summary" data-ai-summary><?= learner_escape($aiInsights['executive_summary'] ?? ''); ?></p>
                        <div class="learner-ai-insights-card__grid">
                            <div class="learner-ai-insights-card__column">
                                <h3><?= learner_icon('star', 18); ?> Điểm mạnh nổi bật</h3>
                                <ul data-ai-strengths>
                                    <?php foreach (($aiInsights['strengths'] ?? []) as $strength): ?>
                                        <li><?= learner_escape($strength); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="learner-ai-insights-card__column">
                                <h3><?= learner_icon('arrow-right', 18); ?> Khuyến nghị phát triển</h3>
                                <ul data-ai-recommendations>
                                    <?php foreach (($aiInsights['recommendations'] ?? []) as $recommendation): ?>
                                        <li><?= learner_escape($recommendation); ?></li>
                                    <?php endforeach; ?>
                                </ul>
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
