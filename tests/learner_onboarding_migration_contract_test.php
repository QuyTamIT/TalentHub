<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$migrationPath = $root . '/Database/migrations/20260824000100_create_learner_onboarding_states.php';
$dcrPath = $root . '/docs/superpowers/database-change-requests/2026-08-24-learner-onboarding-state.md';

$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};

$assert(is_file($migrationPath), 'Onboarding migration exists.');
$assert(is_file($dcrPath), 'Database change request exists.');

$migration = (string) file_get_contents($migrationPath);
$dcr = (string) file_get_contents($dcrPath);

$assert(str_contains($migration, 'CREATE TABLE learner_onboarding_states'), 'Creates onboarding table.');
$assert(str_contains($migration, 'PRIMARY KEY (studentId)'), 'One row per student.');
$assert(str_contains($migration, "status IN ('pending', 'accepted', 'completed')"), 'Constrains status.');
$assert(str_contains($migration, 'fk_learner_onboarding_states_student'), 'Owns student FK.');
$assert(!preg_match('/INSERT\s+INTO\s+learner_onboarding_states/i', $migration), 'Migration must not backfill existing students.');
$assert(str_contains($dcr, 'APPROVAL REQUIRED'), 'DCR retains explicit approval gate.');
$assert(str_contains($dcr, 'No existing student rows are inserted'), 'DCR documents compatibility.');

echo "learner_onboarding_migration_contract_test: OK\n";
