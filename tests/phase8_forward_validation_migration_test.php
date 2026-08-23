<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Migration\Migration;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

$path = dirname(__DIR__) . '/Database/migrations/20260821000610_validate_phase8_notification_contracts.php';
$assert(is_file($path), 'Phase 8 forward validation migration exists');

$migration = require $path;
$assert($migration instanceof Migration, 'Phase 8 forward validation migration implements Migration');
$assert($migration->isReversible() === false, 'Phase 8 forward validation is irreversible');

$source = file_get_contents($path) ?: '';
foreach ([
    'information_schema.columns',
    'information_schema.statistics',
    'information_schema.referential_constraints',
    'information_schema.key_column_usage',
    'notification.manage_preferences_own',
    "['enterprise', 'school', 'student', 'teacher']",
    'utf8mb4_unicode_ci',
] as $contractEvidence) {
    $assert(str_contains($source, $contractEvidence), "forward validation covers {$contractEvidence}");
}

$assert(!str_contains($source, 'DROP TABLE'), 'forward validation never drops notification tables');
$assert(!str_contains($source, 'DELETE FROM'), 'forward validation never deletes runtime data');

echo "phase8_forward_validation_migration_test: OK ({$assertions} assertions)\n";
