<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/Database/seeds/System/RolePermissionSeeder.php';

use TalentHub\Database\Migration\Migration;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;

$root = dirname(__DIR__);
$migrationFile = $root . '/Database/migrations/20260818000200_create_activity_qr_sessions.php';
$reconciliationFile = $root . '/Database/migrations/20260820000100_reconcile_checkins_schema.php';
$migrationSource = file_get_contents($migrationFile);
$reconciliationSource = file_get_contents($reconciliationFile);
$seederSource = file_get_contents($root . '/Database/seeds/System/RolePermissionSeeder.php');
$migration = require $migrationFile;
$reconciliation = require $reconciliationFile;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert($migration instanceof Migration, 'QR migration must implement the migration contract.');
$assert(!$migration->isReversible(), 'Data-preserving QR conversion must reject unsafe automatic rollback.');
$assert($reconciliation instanceof Migration, 'Check-in reconciliation must implement the migration contract.');
$assert(!$reconciliation->isReversible(), 'Check-in reconciliation must preserve upgraded data.');
$assert(is_string($reconciliationSource), 'Check-in reconciliation source must be readable.');
$assert(is_string($migrationSource), 'QR migration source must be readable.');
$assert(str_contains($migrationSource, 'CREATE TABLE activity_qr_sessions'), 'Migration creates the canonical QR session table.');
$assert(str_contains($migrationSource, 'INSERT INTO activity_qr_sessions'), 'Migration backfills legacy QR tokens without deleting them.');
$assert(str_contains($migrationSource, 'ADD COLUMN qrSessionId'), 'Migration adds the canonical check-in session reference.');
$assert(str_contains($migrationSource, 'DROP COLUMN qrTokenId'), 'Migration removes the obsolete check-in token reference after backfill.');
$assert(str_contains($migrationSource, 'ADD COLUMN status'), 'Existing check-ins gain the canonical status column.');
$assert(str_contains($migrationSource, 'ADD COLUMN confirmedAt'), 'Existing check-ins gain the canonical confirmation timestamp.');
$assert(str_contains($migrationSource, 'ADD COLUMN createdAt'), 'Existing check-ins gain the canonical creation timestamp.');
$assert(str_contains($migrationSource, 'ADD UNIQUE KEY uq_checkins_registration'), 'Existing check-ins gain registration uniqueness.');
foreach (['chk_checkins_status', 'chk_checkins_checked_in_at', 'chk_checkins_confirmed_at'] as $constraint) {
    $assert(str_contains($migrationSource, "ADD CONSTRAINT {$constraint}"), "Existing check-ins are missing constraint: {$constraint}");
}
$assert(!str_contains(strtoupper($migrationSource), 'DELETE FROM'), 'Migration must not delete application data.');

$assert(is_string($seederSource), 'Role permission seeder source must be readable.');
foreach ([
    'activity.create_managed',
    'activity.update_managed',
    'qr_session.create_managed',
    'qr_session.read_managed',
    'qr_session.revoke_managed',
    'checkin.read_managed',
    'notification.manage_preferences_own',
    'certificate.manage_own',
    'activity_registration.update_managed',
] as $permission) {
    $assert(str_contains($seederSource, "'{$permission}'"), "Permission is missing: {$permission}");
}

// Canonical RBAC counts increased intentionally from 4/100/118 to 4/103/124 due to three reviewed permissions:
// 1. notification.manage_preferences_own is common to 4 roles (+1 unique permission, +4 mappings)
// 2. certificate.manage_own belongs to Student (+1 permission, +1 mapping)
// 3. activity_registration.update_managed belongs to Teacher (+1 permission, +1 mapping)
$counts = (new RolePermissionSeeder())->expectedCounts();
$assert($counts === ['roles' => 4, 'permissions' => 103, 'mappings' => 124], 'Canonical RBAC counts changed unexpectedly: ' . json_encode($counts));

echo "qr_session_migration_contract_test: OK\n";
