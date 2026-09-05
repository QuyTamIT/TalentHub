<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Migration\Migration;

$failures = 0;
$assert = static function (string $name, bool $condition) use (&$failures): void {
    if ($condition) {
        echo "[PASS] {$name}\n";
        return;
    }

    ++$failures;
    fwrite(STDERR, "[FAIL] {$name}\n");
};

$root = dirname(__DIR__);
$aiPath = $root . '/Database/migrations/20260901000100_create_ai_suggestions.php';
$aiSource = file_get_contents($aiPath);
$aiMigration = require $aiPath;
$assert('AI migration implements Migration', $aiMigration instanceof Migration);
$assert('AI migration remains reversible', $aiMigration instanceof Migration && $aiMigration->isReversible());
$assert('AI migration keeps the ai_suggestions table', is_string($aiSource) && str_contains($aiSource, 'CREATE TABLE IF NOT EXISTS `ai_suggestions`'));
$assert('AI migration keeps its user index', is_string($aiSource) && str_contains($aiSource, 'KEY `idx_ai_suggestions_user_id` (`user_id`)'));

$permissionPath = $root . '/Database/migrations/20260906000100_reconcile_activity_review_permission_scope.php';
$permissionMigration = require $permissionPath;
$assert('Activity review reconciliation implements Migration', $permissionMigration instanceof Migration);
$assert('Activity review reconciliation is forward-only', $permissionMigration instanceof Migration && !$permissionMigration->isReversible());

echo "Summary: {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
