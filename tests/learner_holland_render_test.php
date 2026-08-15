<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=test');
putenv('TALENTHUB_LEARNER_SOURCE=mock');
$_ENV['APP_ENV'] = 'test';
$_ENV['TALENTHUB_LEARNER_SOURCE'] = 'mock';
$_SERVER['APP_ENV'] = 'test';
$_SERVER['TALENTHUB_LEARNER_SOURCE'] = 'mock';

function holland_render(string $path, array $query = []): string
{
    $_GET = $query;
    ob_start();
    include $path;
    return (string) ob_get_clean();
}

function holland_render_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$assessmentPath = $root . '/app/learner/assessment.php';
$resultPath = $root . '/app/learner/assessment-result.php';
holland_render_assert(is_file($assessmentPath), 'assessment route exists');
holland_render_assert(is_file($resultPath), 'result route exists');

$assessment = holland_render($assessmentPath, ['id' => 'holland']);
$result = holland_render($resultPath, ['id' => 'holland']);
$discover = holland_render($root . '/app/learner/discover.php');

foreach (['assessment' => $assessment, 'result' => $result, 'discover' => $discover] as $page => $html) {
    if (preg_match('/(?:Warning|Fatal error|Parse error)[^<]*/i', strip_tags($html), $diagnostic)) {
        fwrite(STDERR, "{$page}: {$diagnostic[0]}\n");
        exit(1);
    }
}

holland_render_assert(str_contains($assessment, 'data-assessment-runner'), 'assessment runner marker exists');
holland_render_assert(str_contains($assessment, 'id="learner-assessment-boot"'), 'runner receives boot data');
holland_render_assert(str_contains($assessment, 'data-assessment-loading'), 'loading state is rendered');
holland_render_assert(str_contains($assessment, 'data-assessment-error'), 'error state is rendered');
holland_render_assert(str_contains($assessment, 'data-assessment-expired'), 'expired state is rendered');
holland_render_assert(str_contains($assessment, 'data-assessment-submit-modal'), 'submit confirmation is rendered');

holland_render_assert(str_contains($result, 'data-assessment-result-page'), 'result page marker exists');
holland_render_assert(str_contains($result, 'data-assessment-history'), 'history section is rendered');
holland_render_assert(str_contains($result, 'IRA'), 'mock cross-device history is visible');
holland_render_assert(str_contains($discover, 'assessment.php?id=holland'), 'Holland card links to the real flow');
holland_render_assert(str_contains($discover, 'Bản thử nghiệm'), 'Holland is clearly labelled experimental');
holland_render_assert(substr_count($discover, 'Sắp triển khai') === 3, 'three unavailable assessments are clearly labelled');
holland_render_assert(str_contains($discover, 'data-holland-latest'), 'latest Holland result can return to discovery');
holland_render_assert(str_contains($discover, 'không tự thay đổi theo kết quả Holland'), 'multiple intelligence mock is distinguished from Holland');

$javascript = file_get_contents($root . '/assets/js/learner.js');
holland_render_assert(str_contains($javascript, "'/app/learner/assessment.php'"), 'assessment route is whitelisted');
holland_render_assert(str_contains($javascript, "'/app/learner/assessment-result.php'"), 'result route is whitelisted');

echo "learner_holland_render_test: OK\n";
