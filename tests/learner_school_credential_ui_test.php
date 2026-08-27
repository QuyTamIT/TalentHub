<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$partial = file_get_contents($root . '/app/learner/includes/school-credential-grid.php');
$css = file_get_contents($root . '/assets/css/learner.css');
$dashboard = file_get_contents($root . '/app/learner/index.php');
$badges = file_get_contents($root . '/app/learner/badges.php');
$profile = file_get_contents($root . '/app/learner/profile.php');
$roadmap = file_get_contents($root . '/app/learner/ai-recommendations.php');
$endpoint = file_get_contents($root . '/app/learner/api/v1/school-credentials.php');
$studentData = file_get_contents($root . '/app/learner/includes/student-data.php');

$assert(is_string($partial) && str_contains($partial, 'learner-school-credential-grid'), 'shared credential card partial exists');
$assert(is_string($partial) && str_contains($partial, "learner_escape"), 'credential text is escaped');
$assert(is_string($css) && str_contains($css, 'minmax(min(100%, 245px), 1fr)'), 'credential grid prevents narrow viewport overflow');
$assert(is_string($css) && str_contains($css, '@media (max-width: 390px)'), 'small mobile card header has a responsive rule');
$assert(is_string($dashboard) && str_contains($dashboard, 'Huy hiệu &amp; chứng chỉ dành cho bạn'), 'dashboard shows featured credentials');
$assert(is_string($dashboard) && str_contains($dashboard, '$dashboardAssessmentUnavailable'), 'dashboard distinguishes assessment service errors from real progress');
$assert(is_string($dashboard) && str_contains($dashboard, 'Dữ liệu đánh giá tạm thời chưa tải được'), 'dashboard exposes a truthful assessment unavailable state');
$assert(is_string($dashboard) && str_contains($dashboard, 'Trạng thái thành tích tạm thời chưa tải được'), 'credential heading exposes a truthful service unavailable state');
$assert(is_string($dashboard) && str_contains($dashboard, 'Tạo lộ trình AI'), 'dashboard keeps the ready-but-not-generated AI CTA');
$assert(is_string($dashboard) && str_contains($dashboard, 'data-dashboard-journey'), 'dashboard exposes the journey semantic hook');
$assert(is_string($dashboard) && str_contains($dashboard, 'learner-welcome__status'), 'dashboard hero exposes assessment progress');
$assert(is_string($dashboard) && str_contains($dashboard, 'learner-kpi-card__verified'), 'database KPIs expose verification state');
$assert(is_string($dashboard) && str_contains($dashboard, 'learner-skill-row__meta'), 'skill rows expose level metadata');
$assert(is_string($dashboard) && str_contains($dashboard, 'learner-journey-hero-v3.png'), 'dashboard uses the checkerboard-free hero illustration asset');
$assert(is_string($dashboard) && str_contains($dashboard, 'learner-welcome__image'), 'dashboard renders the refined hero image element');
$assert(is_string($css) && str_contains($css, 'Learner journey dashboard refresh'), 'dashboard refresh styles are present');
$assert(is_string($css) && str_contains($css, 'Hero visual refinement v2'), 'dashboard hero visual refinement styles are present');
$assert(is_string($css) && str_contains($css, 'linear-gradient(135deg, var(--surface)'), 'dashboard hero uses a light background that blends with the illustration');
$assert(is_string($css) && str_contains($css, '.learner-page-overview .learner-kpi-card__verified'), 'verified KPI styles are dashboard scoped');
$dashboardCss = substr($css, strpos($css, 'Learner journey dashboard refresh'));
$assert(!str_contains($dashboardCss, 'var(--success)'), 'dashboard refresh avoids the undefined success token');
$assert(str_contains($dashboardCss, 'var(--accent)'), 'dashboard refresh uses the approved accent green token');
$assert(is_string($studentData) && preg_match('/\}\s*else\s*\{\s*\$dashboardKpis\s*=\s*\[(.*?)\];\s*\$profileKpis/s', $studentData, $mockDashboardMatch) === 1, 'mock dashboard KPI block is discoverable');
$mockDashboardKpis = $mockDashboardMatch[1] ?? '';
foreach (['Cấp độ hiện tại', 'Huy hiệu đạt được', 'Giờ trải nghiệm', 'Hoạt động đã tham gia'] as $label) {
    $assert(str_contains($mockDashboardKpis, $label), "mock dashboard contains supported KPI {$label}");
}
$assert(!str_contains($mockDashboardKpis, 'Điểm năng lực'), 'mock dashboard excludes unsupported competency score');
$assert(!str_contains($mockDashboardKpis, 'Xếp hạng lớp'), 'mock dashboard excludes unsupported class rank');
$assert(is_string($badges) && str_contains($badges, 'Huy hiệu chính thức của trường'), 'badges page separates school catalog');
$assert(is_string($profile) && str_contains($profile, 'Chứng chỉ do trường cấp'), 'profile shows school certificates');
$assert(is_string($profile) && str_contains($profile, 'Chứng chỉ bên ngoài'), 'external certificates remain available separately');
$assert(is_string($roadmap) && str_contains($roadmap, 'AI đối chiếu bộ thành tích của trường'), 'AI roadmap shows matched credentials');
$assert(is_string($endpoint) && str_contains($endpoint, "studentId('badge.read_own')"), 'credential API is authenticated and permission scoped');

echo "learner_school_credential_ui_test: OK\n";
