<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$script = $root . '/tests/learner_holland_render_test.php';
$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open([PHP_BINARY, $script], $descriptorSpec, $pipes, $root);
if (!is_resource($process)) {
    fwrite(STDERR, "Assertion failed: render test process must start.\n");
    exit(1);
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($exitCode !== 0) {
    fwrite(STDERR, "Assertion failed: render test must exit successfully.\n{$stderr}");
    exit(1);
}

if (!str_contains((string) $stdout, 'learner_holland_render_test: OK')) {
    fwrite(STDERR, "Assertion failed: render test must execute all assertions instead of exiting silently.\n{$stderr}");
    exit(1);
}

echo "learner_holland_render_process_test: OK\n";
