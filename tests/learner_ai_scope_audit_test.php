<?php

declare(strict_types=1);

function audit_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

$repositoryRoot = dirname(__DIR__);
$migrationPath = 'Database/migrations/learner/002_create_ai_input_foundation.php';
$probePath = 'Database/migrations/learner/998_scope_audit_probe.php';
$probeAbsolutePath = $repositoryRoot . '/' . $probePath;
$probeSource = "<?php\n\n// Test-only unapproved learner migration probe.\n";
$php = escapeshellarg(PHP_BINARY);
$audit = escapeshellarg($repositoryRoot . '/app/learner/tools/ai-scope-audit.php');

if (file_exists($probeAbsolutePath)) {
    throw new RuntimeException('Scope-audit test probe already exists');
}

$probeCreated = false;
try {
    $probeWrite = file_put_contents($probeAbsolutePath, $probeSource);
    $probeCreated = is_file($probeAbsolutePath);
    audit_assert($probeWrite !== false, 'test creates its unapproved sibling probe');

    exec("{$php} {$audit} --format=json 2>&1", $unapprovedOutput, $unapprovedExitCode);
    $unapproved = json_decode(implode("\n", $unapprovedOutput), true, 512, JSON_THROW_ON_ERROR);
    audit_assert($unapprovedExitCode === 2, 'default CLI rejects the unapproved sibling migration');
    audit_assert(in_array($probePath, $unapproved['approval_required_paths'], true), 'default CLI reports the sibling migration as approval-required');

    $approvedArgument = ' --approved-database-path=' . escapeshellarg($migrationPath);
    exec("{$php} {$audit} --format=json{$approvedArgument} 2>&1", $canonicalOutput, $canonicalExitCode);
    $canonical = json_decode(implode("\n", $canonicalOutput), true, 512, JSON_THROW_ON_ERROR);
    audit_assert($canonicalExitCode === 2, 'canonical 002 approval does not approve its sibling');
    audit_assert(in_array($probePath, $canonical['approval_required_paths'], true), 'sibling remains approval-required after canonical approval');

    $probeArgument = ' --approved-database-path=' . escapeshellarg($probePath);
    exec("{$php} {$audit} --format=json{$approvedArgument}{$probeArgument} 2>&1", $exactOutput, $exactExitCode);
    $exact = json_decode(implode("\n", $exactOutput), true, 512, JSON_THROW_ON_ERROR);
    audit_assert($exactExitCode === 0 && $exact['allowed'] === true, 'explicit exact sibling approval permits the sibling alongside canonical 002');

    $prefixArgument = ' --approved-database-path=' . escapeshellarg('Database/migrations/learner/998_scope_audit');
    exec("{$php} {$audit} --format=json{$approvedArgument}{$prefixArgument} 2>&1", $prefixOutput, $prefixExitCode);
    $prefix = json_decode(implode("\n", $prefixOutput), true, 512, JSON_THROW_ON_ERROR);
    audit_assert($prefixExitCode === 2, 'prefix approval does not permit the sibling');
    audit_assert(in_array($probePath, $prefix['approval_required_paths'], true), 'prefix approval leaves sibling approval-required');

    $lookalikeArgument = ' --approved-database-path=' . escapeshellarg('Database/migrations/learner/998_scope_audit_probe.php.bak');
    exec("{$php} {$audit} --format=json{$approvedArgument}{$lookalikeArgument} 2>&1", $lookalikeOutput, $lookalikeExitCode);
    $lookalike = json_decode(implode("\n", $lookalikeOutput), true, 512, JSON_THROW_ON_ERROR);
    audit_assert($lookalikeExitCode === 2, 'lookalike approval does not permit the sibling');
    audit_assert(in_array($probePath, $lookalike['approval_required_paths'], true), 'lookalike approval leaves sibling approval-required');
    audit_assert(file_get_contents($probeAbsolutePath) === $probeSource, 'source audit is read-only for the test probe');
} finally {
    if ($probeCreated && is_file($probeAbsolutePath)) {
        unlink($probeAbsolutePath);
    }
}

echo "learner_ai_scope_audit_test: OK\n";
