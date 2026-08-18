<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/Database/seeds/System/RolePermissionSeeder.php';

use TalentHub\Database\Migration\Migration;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;

$root = dirname(__DIR__);
$migrationFile = $root . '/Database/migrations/20260818000100_create_activity_qr_sessions.php';
$migrationSource = file_get_contents($migrationFile);
$seederSource = file_get_contents($root . '/Database/seeds/System/RolePermissionSeeder.php');
$migration = require $migrationFile;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert($migration instanceof Migration, 'QR migration must implement the migration contract.');
$assert(!$migration->isReversible(), 'Data-preserving QR conversion must reject unsafe automatic rollback.');
$assert(is_string($migrationSource), 'QR migration source must be readable.');
$assert(str_contains($migrationSource, 'CREATE TABLE activity_qr_sessions'), 'Migration creates the canonical QR session table.');
$assert(str_contains($migrationSource, 'INSERT INTO activity_qr_sessions'), 'Migration backfills legacy QR tokens without deleting them.');
$assert(str_contains($migrationSource, 'ADD COLUMN qrSessionId'), 'Migration adds the canonical check-in session reference.');
$assert(str_contains($migrationSource, 'DROP COLUMN qrTokenId'), 'Migration removes the obsolete check-in token reference after backfill.');
$assert(!str_contains(strtoupper($migrationSource), 'DELETE FROM'), 'Migration must not delete application data.');

$assert(is_string($seederSource), 'Role permission seeder source must be readable.');
foreach ([
    'activity.create_managed',
    'activity.update_managed',
    'qr_session.create_managed',
    'qr_session.read_managed',
    'qr_session.revoke_managed',
] as $permission) {
    $assert(str_contains($seederSource, "'{$permission}'"), "Teacher permission is missing: {$permission}");
}

$counts = (new RolePermissionSeeder())->expectedCounts();
$assert($counts === ['roles' => 4, 'permissions' => 99, 'mappings' => 117], 'Canonical RBAC counts changed unexpectedly: ' . json_encode($counts));

echo "qr_session_migration_contract_test: OK\n";
