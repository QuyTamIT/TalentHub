<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$migrationPath = $root . '/Database/migrations/20260824000100_create_learner_onboarding_states.php';
$requestIdMigrationPath = $root . '/Database/migrations/20260824000200_widen_audit_request_id.php';
$dcrPath = $root . '/docs/superpowers/database-change-requests/2026-08-24-learner-onboarding-state.md';
$requestIdDcrPath = $root . '/docs/superpowers/database-change-requests/2026-08-24-audit-request-id-width.md';

$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};

$assert(is_file($migrationPath), 'Onboarding migration exists.');
$assert(is_file($requestIdMigrationPath), 'Audit request ID compatibility migration exists.');
$assert(is_file($dcrPath), 'Database change request exists.');
$assert(is_file($requestIdDcrPath), 'Audit request ID database change request exists.');

$migration = (string) file_get_contents($migrationPath);
$requestIdMigration = (string) file_get_contents($requestIdMigrationPath);
$dcr = (string) file_get_contents($dcrPath);
$requestIdDcr = (string) file_get_contents($requestIdDcrPath);

$assert(str_contains($migration, 'CREATE TABLE learner_onboarding_states'), 'Creates onboarding table.');
$assert(str_contains($migration, 'PRIMARY KEY (studentId)'), 'One row per student.');
$assert(str_contains($migration, "status IN ('pending', 'accepted', 'completed')"), 'Constrains status.');
$assert(str_contains($migration, 'fk_learner_onboarding_states_student'), 'Owns student FK.');
$assert(!preg_match('/INSERT\s+INTO\s+learner_onboarding_states/i', $migration), 'Migration must not backfill existing students.');
$assert(str_contains($requestIdMigration, 'MODIFY COLUMN requestId VARCHAR(64) NULL'), 'Audit request ID accepts the application contract of up to 64 characters.');
$assert(str_contains($requestIdDcr, 'TABLE_ROWS'), 'Audit request ID DCR requires a production-size preflight.');
$assert(str_contains($requestIdDcr, 'lock strategy'), 'Audit request ID DCR requires an explicit lock strategy.');
$assert(str_contains($requestIdDcr, 'idx_audit_logs_request'), 'Audit request ID DCR verifies index preservation.');
$assert(str_contains($requestIdDcr, 'APPROVAL REQUIRED'), 'Audit request ID DCR retains an explicit production approval gate.');
$assert(str_contains($dcr, 'APPROVAL REQUIRED'), 'DCR retains explicit approval gate.');
$assert(str_contains($dcr, 'No existing student rows are inserted'), 'DCR documents compatibility.');

echo "learner_onboarding_migration_contract_test: OK\n";
