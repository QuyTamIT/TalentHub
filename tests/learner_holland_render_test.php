<?php

declare(strict_types=1);

namespace TalentHub\Bootstrap {
    final class PortalGuard
    {
        /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
        public static function requireRole(string $role, string $fallbackPath): array
        {
            if ($role !== 'student' || $fallbackPath !== '/app/learner/index.php') {
                throw new \RuntimeException('Unexpected learner render authorization contract.');
            }

            return [
                'id' => 'user-demo-nguyen-van-a',
                'email' => 'a.nguyen@school.edu.vn',
                'fullName' => 'Nguyễn Văn A',
                'role' => 'student',
                'status' => 'active',
            ];
        }
    }
}

namespace {
$root = dirname(__DIR__);
putenv('APP_ENV=test');
putenv('TALENTHUB_LEARNER_SOURCE=mock');
$_ENV['APP_ENV'] = 'test';
$_ENV['TALENTHUB_LEARNER_SOURCE'] = 'mock';
$_SERVER['APP_ENV'] = 'test';
$_SERVER['TALENTHUB_LEARNER_SOURCE'] = 'mock';

require_once $root . '/bin/bootstrap.php';

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
$discoverPath = $root . '/app/learner/discover.php';

holland_render_assert(is_file($assessmentPath), 'assessment route exists');
holland_render_assert(is_file($resultPath), 'result route exists');
holland_render_assert(is_file($discoverPath), 'discover route exists');

$discover = holland_render($discoverPath);
$assessment = holland_render($assessmentPath, ['code' => 'holland']);
$result = holland_render($resultPath, ['code' => 'holland']);

foreach (['assessment' => $assessment, 'result' => $result, 'discover' => $discover] as $page => $html) {
    if (preg_match('/(?:Warning|Fatal error|Parse error)[^<]*/i', strip_tags($html), $diagnostic)) {
        fwrite(STDERR, "{$page}: {$diagnostic[0]}\n");
        exit(1);
    }
}

// 1. Discover page: all 4 assessment cards rendered, no "Sắp triển khai" cards
holland_render_assert(str_contains($discover, 'data-assessment-catalog'), 'database-driven catalog root exists');
holland_render_assert(str_contains($discover, 'data-catalog-endpoint="/app/learner/api/v1/assessments.php"'), 'catalog API endpoint is exposed');
holland_render_assert(str_contains($discover, 'data-assessment-card-template="holland"'), 'Holland card template exists');
holland_render_assert(str_contains($discover, 'data-assessment-card-template="mbti"'), 'MBTI card template exists');
holland_render_assert(str_contains($discover, 'data-assessment-card-template="disc"'), 'DISC card template exists');
holland_render_assert(str_contains($discover, 'data-assessment-card-template="multiple_intelligence"'), 'Multiple Intelligence card template exists');
holland_render_assert(!str_contains($discover, 'Sắp triển khai'), 'No "Sắp triển khai" cards exist');

// 2. Discover page boot data contains session boot and no client-supplied student_id
holland_render_assert(str_contains($discover, 'id="learner-session-boot"'), 'session boot exists');
holland_render_assert(!str_contains($discover, '"student_id"'), 'student_id is not in client boot');

// 3. Runner page: generic data-assessment-code, loading, save-error, expired, validation-error, band confirmation
holland_render_assert(str_contains($assessment, 'data-assessment-runner'), 'assessment runner marker exists');
holland_render_assert(str_contains($assessment, 'data-assessment-code="holland"') || str_contains($assessment, 'data-assessment-code'), 'runner has data-assessment-code');
holland_render_assert(str_contains($assessment, 'data-assessment-loading'), 'loading state is rendered');
holland_render_assert(str_contains($assessment, 'data-assessment-error'), 'error state is rendered');
holland_render_assert(str_contains($assessment, 'data-assessment-save-error'), 'save-error state is rendered');
holland_render_assert(str_contains($assessment, 'data-assessment-expired'), 'expired state is rendered');
holland_render_assert(str_contains($assessment, 'data-assessment-validation-error'), 'validation-error state is rendered');
holland_render_assert(str_contains($assessment, 'data-assessment-band-confirmation'), 'band confirmation modal is rendered');
holland_render_assert(str_contains($assessment, 'value="middle"'), 'band option middle exists');
holland_render_assert(str_contains($assessment, 'value="high"'), 'band option high exists');
holland_render_assert(str_contains($assessment, 'value="college"'), 'band option college exists');

// 4. Result page: generic result dimension list, advisory disclaimer
holland_render_assert(str_contains($result, 'data-assessment-result-page'), 'result page marker exists');
holland_render_assert(str_contains($result, 'data-result-dimension-list'), 'generic result dimension list exists');
holland_render_assert(str_contains($result, 'data-advisory-disclaimer'), 'advisory disclaimer exists');
holland_render_assert(str_contains($result, 'data-assessment-result-loading'), 'result loading state exists');
holland_render_assert(str_contains($result, 'data-assessment-result-error'), 'result error state exists');

$javascript = file_get_contents($root . '/assets/js/learner.js');
holland_render_assert(str_contains($javascript, "'/app/learner/assessment.php'"), 'assessment route is whitelisted');
holland_render_assert(str_contains($javascript, "'/app/learner/assessment-result.php'"), 'result route is whitelisted');

echo "learner_holland_render_test: OK\n";
}
