<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Migration\Migration;

$root = dirname(__DIR__);
$applied = $root . '/Database/migrations/20260821000500_create_internships_and_application_lifecycle.php';
$repair = $root . '/Database/migrations/20260821000510_reconcile_phase7_exact_metadata.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

$assert(hash_file('sha256', $applied) === '28ed78fc9e46068518bf2a87377a337761cc45337b6de6918778a20781353fc7', 'applied Phase 7 migration stays byte-identical');
$assert(is_file($repair), 'forward repair migration exists');
$source = file_get_contents($repair);
$assert(is_string($source), 'forward repair migration is readable');
$migration = require $repair;
$assert($migration instanceof Migration, 'forward repair implements Migration');
$assert(!$migration->isReversible(), 'forward repair is non-reversible');
$assert(str_contains($source, "workType SET DEFAULT 'full_time'"), 'workType default is reconciled');
$assert(str_contains($source, 'educationLevel VARCHAR(100) NOT NULL'), 'educationLevel metadata is reconciled');
$assert(str_contains($source, 'DROP COLUMN cvUrl'), 'obsolete cvUrl is removed');
$assert(str_contains($source, "schemaVersion SET DEFAULT '1.0.0'"), 'snapshot schema version default is reconciled');
$assert(str_contains($source, "@@session.time_zone"), 'UTC preflight is enforced');
$assert(str_contains($source, 'CHAR_LENGTH(educationLevel) > 100'), 'unsafe narrowing is rejected');
$assert(str_contains($source, 'cvUrl IS NOT NULL'), 'non-null obsolete data is rejected before drop');

echo "phase7_forward_repair_migration_test: OK\n";
