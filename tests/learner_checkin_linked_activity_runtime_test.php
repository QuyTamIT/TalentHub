<?php

declare(strict_types=1);

namespace TalentHub\Bootstrap {
    final class PortalGuard
    {
        public static function requireRole(string $role, string $fallbackPath): array
        {
            return [
                'id' => 'phase9-render-user',
                'email' => 'phase9-render@example.invalid',
                'fullName' => 'Phase 9 Render',
                'role' => 'student',
                'status' => 'active',
            ];
        }
    }
}

namespace {

$root = dirname(__DIR__);
$worker = ($argv[1] ?? '') === '--worker';

if ($worker) {
    $scenario = (string) ($argv[2] ?? 'invalid');
    $GLOBALS['phase9_checkin_scenario'] = $scenario;

    function learner_activity_find(string $activityId): ?array
    {
        $scenario = (string) ($GLOBALS['phase9_checkin_scenario'] ?? 'invalid');
        if (in_array($scenario, ['cross_school', 'invalid'], true)) {
            return null;
        }
        return [
            'id' => $activityId,
            'title' => 'Phase 9 scoped linked activity',
            'start_at' => '2026-08-25T09:00:00+00:00',
            'location' => 'Phòng kiểm thử',
        ];
    }

    function learner_activity_registration_history(string $studentId): array
    {
        $scenario = (string) ($GLOBALS['phase9_checkin_scenario'] ?? 'invalid');
        if (in_array($scenario, ['unregistered', 'cross_school', 'invalid'], true)) {
            return [];
        }
        return [[
            'id' => '33333333-3333-4333-8333-333333333333',
            'activity_id' => '22222222-2222-4222-8222-222222222222',
            'student_id' => $studentId,
            'status' => $scenario,
        ]];
    }

    $_GET['activity'] = $scenario === 'invalid'
        ? 'not-a-valid-uuid'
        : '22222222-2222-4222-8222-222222222222';
    require $root . '/app/learner/checkin.php';
    exit(0);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$render = static function (string $scenario) use ($root): string {
    $command = [PHP_BINARY, __FILE__, '--worker', $scenario];
    $environment = array_merge($_ENV, getenv(), [
        'APP_ENV' => 'test',
        'TALENTHUB_LEARNER_SOURCE' => 'mock',
    ]);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start linked-activity render worker.');
    }
    $html = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) {
        throw new RuntimeException('Linked-activity render failed without exposing credentials: ' . trim($error));
    }
    return (string) $html;
};

$approved = $render('approved');
$assert(str_contains($approved, 'Phase 9 scoped linked activity'), 'approved own registration renders the linked activity.');
$assert(str_contains($approved, 'Đã được duyệt'), 'approved linked activity uses an eligible approval label.');

$pending = $render('pending');
$assert(str_contains($pending, 'Phase 9 scoped linked activity'), 'pending own registration may render the linked activity.');
$assert(str_contains($pending, 'Chờ giáo viên duyệt'), 'pending linked activity is not presented as approved.');
$assert(!str_contains($pending, 'Đã được duyệt'), 'pending linked activity never implies check-in eligibility.');

$waitlisted = $render('waitlisted');
$assert(str_contains($waitlisted, 'Danh sách chờ'), 'waitlisted linked activity keeps its distinct status.');
$assert(!str_contains($waitlisted, 'Đã được duyệt'), 'waitlisted linked activity never implies check-in eligibility.');

$attended = $render('attended');
$assert(str_contains($attended, 'Đã tham gia'), 'attended linked activity does not invite a second check-in.');
$assert(!str_contains($attended, 'Đã được duyệt'), 'attended linked activity is not presented as check-in eligible.');

foreach (['cancelled', 'rejected', 'no_show', 'unregistered', 'cross_school', 'invalid'] as $scenario) {
    $html = $render($scenario);
    $assert(!str_contains($html, 'Phase 9 scoped linked activity'), "{$scenario} does not render a misleading linked activity card.");
}

echo "learner_checkin_linked_activity_runtime_test: OK\n";

}
