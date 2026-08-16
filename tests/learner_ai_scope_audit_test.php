<?php

declare(strict_types=1);

function audit_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$repositoryRoot = dirname(__DIR__);
$migrationPath = 'Database/migrations/learner/002_create_ai_input_foundation.php';
$php = escapeshellarg(PHP_BINARY);
$audit = escapeshellarg($repositoryRoot . '/app/learner/tools/ai-scope-audit.php');
exec("{$php} {$audit} --format=json 2>&1", $unapprovedOutput, $unapprovedExitCode);
$unapproved = json_decode(implode("\n", $unapprovedOutput), true, 512, JSON_THROW_ON_ERROR);
if (in_array($migrationPath, $unapproved['approval_required_paths'], true)) {
    audit_assert($unapprovedExitCode === 2, 'unapproved migration path is rejected');
}
$approvedArgument = ' --approved-database-path=' . escapeshellarg($migrationPath);
exec("{$php} {$audit} --format=json{$approvedArgument} 2>&1", $approvedOutput, $approvedExitCode);
$approved = json_decode(implode("\n", $approvedOutput), true, 512, JSON_THROW_ON_ERROR);
audit_assert($approvedExitCode === 0, 'explicitly approved migration path is allowed');
audit_assert($approved['allowed'] === true, 'audit accepts the explicit approved path');
audit_assert(!in_array($migrationPath, $approved['approval_required_paths'], true), 'approved migration is not approval-required');
$secondApprovedArgument = ' --approved-database-path=' . escapeshellarg('Database/migrations/learner/999_unrelated.php');
exec("{$php} {$audit} --format=json{$approvedArgument}{$secondApprovedArgument} 2>&1", $repeatOutput, $repeatExitCode);
$repeat = json_decode(implode("\n", $repeatOutput), true, 512, JSON_THROW_ON_ERROR);
audit_assert($repeatExitCode === 0 && $repeat['allowed'] === true, 'repeatable approval paths retain the explicit approved migration');

echo "learner_ai_scope_audit_test: OK\n";
