<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Migration\Migration;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$path = dirname(__DIR__) . '/Database/migrations/20260821000300_extend_activity_registration_lifecycle.php';
$assert(is_file($path), 'Phase 4 lifecycle migration exists.');
$source = file_get_contents($path);
$assert(is_string($source), 'Phase 4 lifecycle migration is readable.');
$migration = require $path;
$assert($migration instanceof Migration, 'Phase 4 lifecycle migration implements Migration.');
$assert(!$migration->isReversible(), 'Phase 4 lifecycle migration is forward-only.');

foreach (['activity_registrations', 'activities', 'permissions', 'roles', 'role_permissions'] as $table) {
    $assert(str_contains($source, "'{$table}'"), "Migration names required table {$table}.");
}
$assert(str_contains($source, 'assertTableExists($context, $table)'), 'Migration preflights every required table through its shared guard.');

foreach (['cancelledAt', 'cancellationReason', 'waitlisted', 'activity_registration_policies'] as $capability) {
    $assert(str_contains($source, $capability), "Migration contains {$capability} capability.");
}

$assert(str_contains($source, 'chk_activity_registrations_status'), 'Migration replaces the named registration status CHECK.');
$assert(str_contains($source, 'chk_activity_registrations_cancellation'), 'Migration adds cancellation consistency CHECK.');
$assert(str_contains($source, "status IN('pending','approved','rejected','cancelled','attended','waitlisted')"), 'Migration preserves five statuses and adds only waitlisted.');
$assert(str_contains($source, "cancellationReason = 'legacy_migration'"), 'Migration backfills a deterministic legacy cancellation reason.');
$assert(str_contains($source, 'uq_activity_registrations_activity_student'), 'Migration verifies canonical unique registration identity.');
$assert(str_contains($source, 'fk_activity_registrations_activity'), 'Migration verifies activity foreign key.');
$assert(str_contains($source, 'fk_activity_registrations_student'), 'Migration verifies Student foreign key.');
$assert(str_contains($source, "activity_registration.update_managed"), 'Migration creates the managed transition permission.');
$assert(str_contains($source, "role.code = 'teacher'"), 'Migration maps managed transition permission only to Teacher.');
$assert(!str_contains(strtoupper($source), 'DROP TABLE'), 'Migration does not drop application tables.');
$assert(!str_contains(strtoupper($source), 'TRUNCATE TABLE'), 'Migration does not truncate application tables.');
$assert(!str_contains(strtoupper($source), 'DELETE FROM ACTIVITY_REGISTRATIONS'), 'Migration does not delete registrations.');

echo "activity_registration_lifecycle_migration_test: OK\n";
