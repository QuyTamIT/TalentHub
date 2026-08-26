<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$handoffPath = $root . '/Database/seeds/learner/Activity/SchoolActivityQrHandoff.php';
$rendererPath = $root . '/Database/seeds/learner/Activity/render-activity-qr.py';
$runnerPath = $root . '/Database/seeds/learner/Activity/run-school-activity-catalog.php';
$gitignorePath = $root . '/.gitignore';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(is_file($handoffPath), 'One-time QR handoff implementation exists.');
$assert(is_file($rendererPath), 'Bundled-runtime QR renderer exists.');
$gitignore = (string) file_get_contents($gitignorePath);
$assert(substr_count($gitignore, '/.codex_tmp/activity-qr-fixtures/') === 1, 'QR fixture root is ignored by exactly one explicit rule.');
$runner = (string) file_get_contents($runnerPath);
$assert(str_contains($runner, '--qr-output-dir='), 'Runner accepts an explicit QR output directory.');
$assert(str_contains($runner, 'Primary apply requires --qr-output-dir'), 'Primary apply refuses a missing QR output directory.');

if (is_file($handoffPath)) {
    require_once $handoffPath;
    $source = (string) file_get_contents($handoffPath);
    foreach ([
        '.pending', 'manifest.json', 'schoolId', 'schoolName', 'activityId', 'activityTitle',
        'sessionId', 'expiresAt', 'file', 'prepare', 'finalize', 'rollback', 'is_link',
    ] as $fragment) {
        $assert(str_contains($source, $fragment), "QR handoff contains required contract fragment: {$fragment}.");
    }
    $resolve = TalentHub\Learner\Seeds\Activity\SchoolActivityQrHandoff::resolveOutputDirectory(...);
    foreach ([
        '../outside',
        '.codex_tmp/activity-qr-fixtures/../outside',
        '.codex_tmp/activity-qr-fixtures/nested/child',
        $root . '/.codex_tmp/activity-qr-fixtures/absolute',
        '.tmp/activity-qr-fixtures/wrong-root',
    ] as $unsafe) {
        try {
            $resolve($root, $unsafe);
            $assert(false, "Path security rejects unsafe output: {$unsafe}.");
        } catch (Throwable) {
            $assert(true, "Path security rejects unsafe output: {$unsafe}.");
        }
    }
    $safe = $resolve($root, '.codex_tmp/activity-qr-fixtures/path-contract-' . bin2hex(random_bytes(4)));
    $assert(str_contains(str_replace('\\', '/', $safe), '/.codex_tmp/activity-qr-fixtures/path-contract-'), 'Path security accepts one new child under the exact ignored root.');
}

if ($failures !== []) {
    fwrite(STDERR, "learner_school_activity_qr_handoff_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_school_activity_qr_handoff_test: OK\n";
