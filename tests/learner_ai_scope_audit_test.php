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
$php = escapeshellarg(PHP_BINARY);
$audit = escapeshellarg($repositoryRoot . '/app/learner/tools/ai-scope-audit.php');
$virtualArgument = ' --audit-path=' . escapeshellarg($probePath);

exec("{$php} {$audit} --format=json{$virtualArgument} 2>&1", $unapprovedOutput, $unapprovedExitCode);
$unapproved = json_decode(implode("\n", $unapprovedOutput), true, 512, JSON_THROW_ON_ERROR);
audit_assert($unapprovedExitCode === 2, 'default CLI rejects the unapproved virtual sibling migration');
audit_assert(in_array($probePath, $unapproved['approval_required_paths'], true), 'default CLI reports the virtual sibling migration as approval-required');

$approvedArgument = ' --approved-database-path=' . escapeshellarg($migrationPath);
exec("{$php} {$audit} --format=json{$virtualArgument}{$approvedArgument} 2>&1", $canonicalOutput, $canonicalExitCode);
$canonical = json_decode(implode("\n", $canonicalOutput), true, 512, JSON_THROW_ON_ERROR);
audit_assert($canonicalExitCode === 2, 'canonical 002 approval does not approve its virtual sibling');
audit_assert(in_array($probePath, $canonical['approval_required_paths'], true), 'virtual sibling remains approval-required after canonical approval');

$probeArgument = ' --approved-database-path=' . escapeshellarg($probePath);
exec("{$php} {$audit} --format=json{$virtualArgument}{$approvedArgument}{$probeArgument} 2>&1", $exactOutput, $exactExitCode);
$exact = json_decode(implode("\n", $exactOutput), true, 512, JSON_THROW_ON_ERROR);
audit_assert(!in_array($probePath, $exact['approval_required_paths'], true), 'explicit exact sibling approval removes the virtual sibling approval requirement');
audit_assert(!in_array($probePath, $exact['forbidden_paths'], true), 'explicit exact sibling approval does not classify the virtual sibling as forbidden');

$prefixArgument = ' --approved-database-path=' . escapeshellarg('Database/migrations/learner/998_scope_audit');
exec("{$php} {$audit} --format=json{$virtualArgument}{$approvedArgument}{$prefixArgument} 2>&1", $prefixOutput, $prefixExitCode);
$prefix = json_decode(implode("\n", $prefixOutput), true, 512, JSON_THROW_ON_ERROR);
audit_assert($prefixExitCode === 2, 'prefix approval does not permit the virtual sibling');
audit_assert(in_array($probePath, $prefix['approval_required_paths'], true), 'prefix approval leaves virtual sibling approval-required');

$lookalikeArgument = ' --approved-database-path=' . escapeshellarg('Database/migrations/learner/998_scope_audit_probe.php.bak');
exec("{$php} {$audit} --format=json{$virtualArgument}{$approvedArgument}{$lookalikeArgument} 2>&1", $lookalikeOutput, $lookalikeExitCode);
$lookalike = json_decode(implode("\n", $lookalikeOutput), true, 512, JSON_THROW_ON_ERROR);
audit_assert($lookalikeExitCode === 2, 'lookalike approval does not permit the virtual sibling');
audit_assert(in_array($probePath, $lookalike['approval_required_paths'], true), 'lookalike approval leaves virtual sibling approval-required');

$leadingTraversalPath = '../Database/migrations/learner/998_scope_audit_probe.php';
$leadingTraversalArgument = ' --audit-path=' . escapeshellarg($leadingTraversalPath);
$leadingTraversalApprovalArgument = ' --approved-database-path=' . escapeshellarg($leadingTraversalPath);
exec("{$php} {$audit} --format=json{$leadingTraversalArgument}{$leadingTraversalApprovalArgument} 2>&1", $leadingTraversalOutput, $leadingTraversalExitCode);
$leadingTraversal = json_decode(implode("\n", $leadingTraversalOutput), true, 512, JSON_THROW_ON_ERROR);
audit_assert($leadingTraversalExitCode === 2, 'leading traversal remains rejected when identically approved');
audit_assert($leadingTraversal['allowed'] === false, 'leading traversal approval cannot make the audit allowed');
audit_assert(in_array('../Database/migrations/learner/998_scope_audit_probe.php', $leadingTraversal['forbidden_paths'], true), 'leading traversal is reported as forbidden');

$absoluteWindowsPath = 'C:\\outside\\Database\\migrations\\learner\\998_scope_audit_probe.php';
$absoluteWindowsArgument = ' --audit-path=' . escapeshellarg($absoluteWindowsPath);
$absoluteWindowsApprovalArgument = ' --approved-database-path=' . escapeshellarg($absoluteWindowsPath);
exec("{$php} {$audit} --format=json{$absoluteWindowsArgument}{$absoluteWindowsApprovalArgument} 2>&1", $absoluteWindowsOutput, $absoluteWindowsExitCode);
$absoluteWindows = json_decode(implode("\n", $absoluteWindowsOutput), true, 512, JSON_THROW_ON_ERROR);
audit_assert($absoluteWindowsExitCode === 2, 'absolute Windows path remains rejected when identically approved');
audit_assert($absoluteWindows['allowed'] === false, 'absolute Windows path approval cannot make the audit allowed');
audit_assert(in_array('/C:/outside/Database/migrations/learner/998_scope_audit_probe.php', $absoluteWindows['forbidden_paths'], true), 'absolute Windows path is reported as forbidden');

echo "learner_ai_scope_audit_test: OK\n";
