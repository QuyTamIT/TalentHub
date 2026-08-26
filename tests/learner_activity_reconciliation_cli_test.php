<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$job = $root . '/app/learner/jobs/reconcile-activity-attendance.php';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$assert(is_file($job), 'Phase 9 reconciliation CLI exists.');
$source = (string) file_get_contents($job);
$guard = "if (PHP_SAPI !== 'cli')";
$assert(str_contains($source, $guard), 'HTTP invocation is refused before reconciliation work.');
$assert(strpos($source, $guard) < strpos($source, "require"), 'SAPI guard precedes application bootstrap.');
foreach (['--schema=', '--grace-hours=', '--limit=', '--dry-run', '--allow-primary', 'SELECT DATABASE()'] as $contract) {
    $assert(str_contains($source, $contract), "CLI contains {$contract} safety contract.");
}

$run = static function (array $arguments) use ($job, $root): array {
    $process = proc_open([PHP_BINARY, $job, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start reconciliation CLI.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['exit' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
};

foreach ([
    ['--schema=invalid'],
    ['--schema=talenthub', '--grace-hours=0', '--dry-run'],
    ['--schema=talenthub', '--limit=0', '--dry-run'],
    ['--schema=talenthub', '--limit=1001', '--dry-run'],
    ['--schema=talenthub_local'],
    ['--schema=talenthub'],
] as $arguments) {
    $result = $run($arguments);
    $assert($result['exit'] !== 0, 'unsafe/invalid CLI invocation is refused.');
    $combined = $result['stdout'] . $result['stderr'];
    $assert(!preg_match('/password|token|cookie|session/i', $combined), 'refusal output does not disclose sensitive configuration.');
}

echo "learner_activity_reconciliation_cli_test: OK\n";
