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

$assert(is_string($partial) && str_contains($partial, 'learner-school-credential-grid'), 'shared credential card partial exists');
$assert(is_string($partial) && str_contains($partial, "learner_escape"), 'credential text is escaped');
$assert(is_string($css) && str_contains($css, 'minmax(min(100%, 245px), 1fr)'), 'credential grid prevents narrow viewport overflow');
$assert(is_string($css) && str_contains($css, '@media (max-width: 390px)'), 'small mobile card header has a responsive rule');
$assert(is_string($dashboard) && str_contains($dashboard, 'Huy hiệu &amp; chứng chỉ dành cho bạn'), 'dashboard shows featured credentials');
$assert(is_string($badges) && str_contains($badges, 'Huy hiệu chính thức của trường'), 'badges page separates school catalog');
$assert(is_string($profile) && str_contains($profile, 'Chứng chỉ do trường cấp'), 'profile shows school certificates');
$assert(is_string($profile) && str_contains($profile, 'Chứng chỉ bên ngoài'), 'external certificates remain available separately');
$assert(is_string($roadmap) && str_contains($roadmap, 'AI đối chiếu bộ thành tích của trường'), 'AI roadmap shows matched credentials');
$assert(is_string($endpoint) && str_contains($endpoint, "studentId('badge.read_own')"), 'credential API is authenticated and permission scoped');

echo "learner_school_credential_ui_test: OK\n";
