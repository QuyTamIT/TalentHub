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
    check(!str_contains($css, '.ent-'), 'Learner stylesheet does not target Enterprise selectors');
    check(!preg_match('/linear-gradient\s*\(/i', $css), 'Learner stylesheet contains no gradient');
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("\n%d learner frontend assertion(s) failed.\n", count($failures)));
    exit(1);
}

fwrite(STDOUT, "\nAll learner frontend assertions passed.\n");
