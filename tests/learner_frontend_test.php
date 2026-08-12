<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function check(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, "FAIL: {$message}\n");
        return;
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

function render_page(string $path): string
{
    ob_start();
    require $path;
    return (string) ob_get_clean();
}

$dataSource = $root . '/app/learner/includes/student-data.php';
$sidebarSource = $root . '/app/learner/includes/sidebar.php';
$headerSource = $root . '/app/learner/includes/header.php';

check(file_exists($dataSource), 'Learner data provider exists');
check(file_exists($sidebarSource), 'Shared Learner sidebar exists');
check(file_exists($headerSource), 'Shared Learner header exists');

if (file_exists($dataSource)) {
    require $dataSource;

    check(learner_escape('<script>') === '&lt;script&gt;', 'Dynamic learner data is HTML escaped');
    check(count($learnerNav) === 9, 'Sidebar data contains nine navigation items');
    check(($student['name'] ?? '') === 'Nguyễn Văn A', 'Student mock data exposes the approved learner');
    check(count($activityCatalog ?? []) === 6, 'Activity catalog contains six records');
    check(count($checkinHistory ?? []) === 4, 'Check-in history contains four records');
    check(isset($evaluationTerms['2025-2026-2']), 'Default evaluation term exists');
    check(
        isset($evaluationTerms['2024-2025-2']) && $evaluationTerms['2024-2025-2']['evaluation'] === null,
        'Evaluation data exposes an empty term'
    );
    check(count(array_unique(array_column($learnerNav, 'route'))) === 9, 'Sidebar routes are unique');
    check(!in_array(false, array_column($learnerNav, 'implemented'), true), 'All learner routes are implemented');
    check(count($learnerBadges ?? []) === 6, 'Badge collection contains six records');
    check(count($learnerLevels ?? []) === 4, 'Level path contains four levels');
    check(
        isset($defaultStatisticsPeriod, $learnerStatisticsPeriods[$defaultStatisticsPeriod]),
        'Default statistics period exists'
    );
    check(isset($aiRecommendation['sufficient']), 'AI recommendation exposes data sufficiency');

    $navByLabel = array_column($learnerNav, null, 'label');
    check(
        ($navByLabel['Hoạt động']['route'] ?? '') === '/app/learner/activities.php'
            && ($navByLabel['Hoạt động']['implemented'] ?? false),
        'Activities route is implemented'
    );
    check(
        ($navByLabel['Check-in QR']['route'] ?? '') === '/app/learner/checkin.php'
            && ($navByLabel['Check-in QR']['implemented'] ?? false),
        'Check-in route is implemented'
    );
    check(
        ($navByLabel['Đánh giá']['route'] ?? '') === '/app/learner/evaluation.php'
            && ($navByLabel['Đánh giá']['implemented'] ?? false),
        'Evaluation route is implemented'
    );
    check(
        ($navByLabel['AI gợi ý']['route'] ?? '') === '/app/learner/ai-recommendations.php'
            && ($navByLabel['AI gợi ý']['implemented'] ?? false),
        'AI recommendations route is implemented'
    );
    check(
        ($navByLabel['Huy hiệu']['route'] ?? '') === '/app/learner/badges.php'
            && ($navByLabel['Huy hiệu']['implemented'] ?? false),
        'Badges route is implemented'
    );
    check(
        ($navByLabel['Thống kê']['route'] ?? '') === '/app/learner/statistics.php'
            && ($navByLabel['Thống kê']['implemented'] ?? false),
        'Statistics route is implemented'
    );
}

$overviewPath = $root . '/app/learner/index.php';
check(file_exists($overviewPath), 'Learner overview page exists');

if (file_exists($overviewPath)) {
    $overview = render_page($overviewPath);

    check(str_contains($overview, 'Chào mừng trở lại, Nguyễn Văn A'), 'Overview renders welcome copy');
    check(substr_count($overview, 'learner-kpi-card') >= 4, 'Overview renders four KPI cards');
    check(str_contains($overview, 'Hồ sơ kỹ năng'), 'Overview renders the skills summary');
    check(str_contains($overview, 'Thuyết trình'), 'Overview renders the approved presentation skill');
    check(str_contains($overview, 'AI gợi ý cho bạn'), 'Overview renders the AI recommendation');
    check(str_contains($overview, 'Hoạt động sắp diễn ra'), 'Overview renders upcoming activities');
    check(str_contains($overview, 'href="discover.php"'), 'Overview aptitude CTA targets discover page');
}

$profilePath = $root . '/app/learner/profile.php';
check(file_exists($profilePath), 'Learner profile page exists');

if (file_exists($profilePath)) {
    $profile = render_page($profilePath);

    check(str_contains($profile, 'Đã xác minh'), 'Profile renders verified status');
    check(str_contains($profile, 'Chia sẻ hồ sơ'), 'Profile renders share action');
    check(str_contains($profile, 'Chỉnh sửa'), 'Profile renders edit action');
    check(str_contains($profile, 'learner-edit-modal'), 'Profile provides edit modal');
    check(str_contains($profile, 'learner-share-modal'), 'Profile provides share modal');
    check(str_contains($profile, 'aria-modal="true"'), 'Profile modals expose dialog semantics');
    check(substr_count($profile, 'aria-describedby="learner-error-') >= 5, 'Profile fields reference their validation messages');
    check(substr_count($profile, 'role="alert"') >= 5, 'Profile validation messages are announced');
    check(str_contains($profile, 'Dự án đã tham gia'), 'Profile renders projects');
}

$discoverPath = $root . '/app/learner/discover.php';
check(file_exists($discoverPath), 'Learner discovery page exists');

if (file_exists($discoverPath)) {
    $discover = render_page($discoverPath);

    foreach (['Holland', 'MBTI', 'DISC', 'Đa trí thông minh'] as $assessmentName) {
        check(str_contains($discover, $assessmentName), "Discovery renders {$assessmentName}");
    }

    check(str_contains($discover, 'role="img"'), 'Radar chart exposes image semantics');
    check(str_contains($discover, 'learner-radar-data'), 'Radar chart renders a data polygon');
    check(str_contains($discover, 'Kỹ thuật'), 'Discovery renders career directions');
    check(str_contains($discover, 'learner-assessment-modal'), 'Discovery provides assessment feedback modal');
}

$activitiesPath = $root . '/app/learner/activities.php';
check(file_exists($activitiesPath), 'Learner activities page exists');

if (file_exists($activitiesPath)) {
    $activitiesPage = render_page($activitiesPath);

    check(str_contains($activitiesPage, 'Khám phá hoạt động'), 'Activities renders heading');
    check(substr_count($activitiesPage, 'data-activity-card') === 6, 'Activities renders six cards');
    check(substr_count($activitiesPage, 'data-activity-filter=') === 5, 'Activities renders five filters');
    check(str_contains($activitiesPage, 'id="learner-registration-modal"'), 'Activities provides registration modal');
    check(str_contains($activitiesPage, 'data-activity-empty'), 'Activities provides empty state');
    check(str_contains($activitiesPage, 'role="progressbar"'), 'Activities exposes capacity progress semantics');
    check(!str_contains($activitiesPage, 'onclick='), 'Activities uses unobtrusive JavaScript');
}

$checkinPath = $root . '/app/learner/checkin.php';
check(file_exists($checkinPath), 'Learner check-in page exists');

if (file_exists($checkinPath)) {
    $checkin = render_page($checkinPath);

    check(str_contains($checkin, 'Check-in trải nghiệm'), 'Check-in renders heading');
    check(str_contains($checkin, 'aria-label="Mã QR mẫu của Nguyễn Văn A"'), 'Check-in QR has an accessible label');
    check(substr_count($checkin, 'data-checkin-record') === 4, 'Check-in renders four history records');
    check(str_contains($checkin, 'Đây là giao diện demo'), 'Scanner identifies demo behavior');
    check(str_contains($checkin, 'id="learner-scanner-modal"'), 'Check-in provides scanner modal');
    check(!str_contains($checkin, 'getUserMedia'), 'Check-in never requests camera permission');
}

$evaluationPath = $root . '/app/learner/evaluation.php';
check(file_exists($evaluationPath), 'Learner evaluation page exists');

if (file_exists($evaluationPath)) {
    $evaluation = render_page($evaluationPath);
    $evaluationSource = (string) file_get_contents($evaluationPath);

    check(str_contains($evaluation, 'Đánh giá năng lực'), 'Evaluation renders heading');
    check(str_contains($evaluation, 'id="learner-evaluation-term"'), 'Evaluation renders semester selector');
    check(substr_count($evaluation, 'data-evaluation-criterion=""') === 4, 'Default evaluation renders four criteria');
    check(str_contains($evaluation, 'data-evaluation-total>90<'), 'Evaluation renders total 90');
    check(str_contains($evaluation, 'data-evaluation-classification>Xuất sắc<'), 'Evaluation renders excellent classification');
    check(str_contains($evaluation, 'data-evaluation-empty'), 'Evaluation provides empty state');
    check(
        str_contains($evaluation, 'data-evaluation-status data-state="published" role="status" aria-live="polite" aria-atomic="true"'),
        'Evaluation publication status is announced'
    );
    check(
        str_contains($evaluationSource, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT'),
        'Evaluation JSON is serialized safely'
    );
}

$aiRecommendationsPath = $root . '/app/learner/ai-recommendations.php';
check(file_exists($aiRecommendationsPath), 'Learner AI recommendations page exists');

if (file_exists($aiRecommendationsPath)) {
    $aiRecommendations = render_page($aiRecommendationsPath);
    $aiRecommendationsSource = (string) file_get_contents($aiRecommendationsPath);

    check(str_contains($aiRecommendations, 'AI phân tích năng lực'), 'AI recommendations renders heading');
    check(substr_count($aiRecommendations, 'data-ai-analysis-card') === 3, 'AI recommendations renders three analysis cards');
    check(substr_count($aiRecommendations, 'data-ai-roadmap-step') === 3, 'AI recommendations renders three roadmap steps');
    check(str_contains($aiRecommendations, 'data-ai-loading'), 'AI recommendations provides loading state');
    check(str_contains($aiRecommendations, 'data-ai-insufficient'), 'AI recommendations provides insufficient-data state');
    check(str_contains($aiRecommendations, 'href="activities.php"'), 'AI recommendations CTA targets activities');
    check(
        str_contains($aiRecommendationsSource, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT'),
        'AI recommendation JSON is serialized safely'
    );
}

$badgesPath = $root . '/app/learner/badges.php';
check(file_exists($badgesPath), 'Learner badges page exists');

if (file_exists($badgesPath)) {
    $badgesPage = render_page($badgesPath);

    check(str_contains($badgesPage, 'Huy hiệu và cấp độ'), 'Badges renders heading');
    check(substr_count($badgesPage, 'data-level-item') === 4, 'Badges renders four levels');
    check(substr_count($badgesPage, 'data-badge-card') === 6, 'Badges renders six badge cards');
    check(substr_count($badgesPage, 'data-badge-filter=') === 4, 'Badges renders four filters');
    check(str_contains($badgesPage, 'data-badge-empty'), 'Badges provides empty state');
    check(str_contains($badgesPage, 'role="progressbar"'), 'Badges exposes progress semantics');
}

$statisticsPath = $root . '/app/learner/statistics.php';
check(file_exists($statisticsPath), 'Learner personal statistics page exists');

if (file_exists($statisticsPath)) {
    $statisticsPage = render_page($statisticsPath);
    $statisticsSource = (string) file_get_contents($statisticsPath);

    check(str_contains($statisticsPage, 'Thống kê cá nhân'), 'Statistics renders personal heading');
    check(str_contains($statisticsPage, 'id="learner-statistics-period"'), 'Statistics renders period selector');
    check(substr_count($statisticsPage, 'data-statistics-kpi') === 4, 'Statistics renders four personal KPIs');
    check(str_contains($statisticsPage, 'data-experience-chart'), 'Statistics renders experience SVG chart');
    check(str_contains($statisticsPage, 'data-field-chart'), 'Statistics renders field allocation SVG chart');
    check(str_contains($statisticsPage, 'role="img"'), 'Statistics charts expose image semantics');
    check(substr_count($statisticsPage, 'data-statistics-skill') === 4, 'Statistics renders four skill progress records');
    check(substr_count($statisticsPage, 'data-activity-summary') === 4, 'Statistics renders four activity totals');
    check(str_contains($statisticsPage, 'data-statistics-empty'), 'Statistics provides empty state');
    check(
        str_contains($statisticsSource, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT'),
        'Statistics JSON is serialized safely'
    );
    check(!str_contains($statisticsPage, 'toàn trường'), 'Statistics contains no school-wide data');
}

$roleSelectionSource = (string) file_get_contents($root . '/role-selection.php');
$roleSelectionScript = (string) file_get_contents($root . '/assets/js/role-selection.js');
check(str_contains($roleSelectionSource, "'route' => 'app/learner/index.php'"), 'Role selection targets the implemented Learner entry page');
check(str_contains($roleSelectionScript, "route.includes('learner')"), 'Role selection script recognizes Learner as implemented');

$cssPath = $root . '/assets/css/learner.css';
check(file_exists($cssPath), 'Learner stylesheet exists');

if (file_exists($cssPath)) {
    $css = (string) file_get_contents($cssPath);

    check(str_contains($css, '.learner-layout'), 'Learner stylesheet scopes the app layout');
    check(str_contains($css, '@media (max-width: 1100px)'), 'Learner stylesheet defines tablet behavior');
    check(str_contains($css, '@media (max-width: 720px)'), 'Learner stylesheet defines mobile behavior');
    check(str_contains($css, 'prefers-reduced-motion'), 'Learner stylesheet respects reduced motion');
    check(str_contains($css, '.learner-activity-catalog'), 'Learner stylesheet styles activity catalog');
    check(str_contains($css, '.learner-checkin-grid'), 'Learner stylesheet styles check-in layout');
    check(str_contains($css, '.learner-evaluation-grid'), 'Learner stylesheet styles evaluation layout');
    check(str_contains($css, '.learner-scanner-frame'), 'Learner stylesheet styles demo scanner');
    check(!str_contains($css, '.ent-'), 'Learner stylesheet does not target Enterprise selectors');
    check(!preg_match('/(?:linear|radial)-gradient\s*\(/i', $css), 'Learner stylesheet contains no gradient');
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("\n%d learner frontend assertion(s) failed.\n", count($failures)));
    exit(1);
}

fwrite(STDOUT, "\nAll learner frontend assertions passed.\n");
