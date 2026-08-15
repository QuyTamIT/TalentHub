<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=test');
putenv('TALENTHUB_LEARNER_SOURCE=mock');
$_ENV['APP_ENV'] = 'test';
$_ENV['TALENTHUB_LEARNER_SOURCE'] = 'mock';
$_SERVER['APP_ENV'] = 'test';
$_SERVER['TALENTHUB_LEARNER_SOURCE'] = 'mock';

function render_learner_page(string $path, array $query): string
{
    $_GET = $query;
    ob_start();
    include $path;
    return (string) ob_get_clean();
}

function render_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$hub = render_learner_page($root . '/app/learner/ecosystem.php', ['tab' => 'enterprises']);
$enterprise = render_learner_page($root . '/app/learner/partner.php', ['type' => 'enterprise', 'id' => 'fpt-software']);
$school = render_learner_page($root . '/app/learner/partner.php', ['type' => 'school', 'id' => 'dai-hoc-bach-khoa-ha-noi']);
$opportunity = render_learner_page($root . '/app/learner/opportunity.php', ['type' => 'internship', 'id' => '1']);
$missing = render_learner_page($root . '/app/learner/opportunity.php', ['type' => 'internship', 'id' => '9999']);

foreach ([$hub, $enterprise, $school, $opportunity, $missing] as $html) {
    render_assert(!preg_match('/Warning|Fatal error|Parse error/i', $html), 'page renders without PHP diagnostics');
    render_assert(str_contains($html, 'lang="vi"'), 'page declares Vietnamese language');
}

render_assert(str_contains($hub, 'id="panel-enterprises"'), 'screen 1 enterprise hub is rendered');
render_assert(str_contains($hub, 'id="panel-schools"'), 'screen 6 school hub is rendered');
render_assert(str_contains($hub, 'id="learner-application-drawer"'), 'screen 5 application tracker is rendered');
render_assert(str_contains($hub, 'Thực tập sinh Frontend Developer'), 'enterprise mock opportunity reaches learner hub');
render_assert(!str_contains($hub, 'Thiết kế UI/UX &amp; Product Design'), 'enterprise draft is hidden from learner hub');

render_assert(str_contains($enterprise, 'Cơ hội được đọc trực tiếp từ mock data doanh nghiệp'), 'screen 2 explains enterprise source');
render_assert(str_contains($school, 'Ngành đào tạo nổi bật'), 'screen 7 school programs are rendered');
render_assert(str_contains($school, 'Dữ liệu demo'), 'school data is clearly labelled as demo');

render_assert(str_contains($opportunity, 'Mô tả cơ hội'), 'screen 3 opportunity detail is rendered');
render_assert(str_contains($opportunity, 'id="learner-application-modal"'), 'screen 4 application modal is rendered');
render_assert(str_contains($opportunity, 'data-application-form'), 'application form is interactive');
render_assert(str_contains($missing, 'Không tìm thấy cơ hội'), 'unknown opportunity has an explicit empty state');

echo "learner_ecosystem_render_test: OK\n";
