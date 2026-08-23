<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Migration\Migration;

$root = dirname(__DIR__);
$strictPreflightMigrationFile = $root . '/Database/migrations/20260821000204_validate_phase_3_canonical_contracts.php';
$preflightMigrationFile = $root . '/Database/migrations/20260821000205_preflight_phase_3_reconciliation.php';
$exactMetadataMigrationFile = $root . '/Database/migrations/20260821000206_validate_phase_3_exact_metadata.php';
$migrationFile = $root . '/Database/migrations/20260821000210_reconcile_phase_3_contracts.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$assert(is_file($strictPreflightMigrationFile), 'Strict Phase 3 canonical precursor must exist and sort before all repair migrations.');
$strictPreflightSource = file_get_contents($strictPreflightMigrationFile);
$assert(is_string($strictPreflightSource), 'Strict Phase 3 canonical precursor must be readable.');
$strictPreflightMigration = require $strictPreflightMigrationFile;
$assert($strictPreflightMigration instanceof Migration, 'Strict Phase 3 precursor implements Migration.');
$assert(!$strictPreflightMigration->isReversible(), 'Strict Phase 3 precursor is forward-only validation.');
foreach (['column_default', 'datetime_precision', 'extra', 'column_type'] as $metadata) {
    $assert(str_contains($strictPreflightSource, $metadata), "Strict precursor checks full column metadata {$metadata}.");
}
$assert(str_contains($strictPreflightSource, 'normalizedCheckClause'), 'Strict precursor compares normalized CHECK expressions exactly.');
$assert(str_contains($strictPreflightSource, "c.studentId <> s.studentId"), 'Strict precursor rejects consent linked to another Student.');
$assert(str_contains($strictPreflightSource, "c.scope <> 'profile_share'"), 'Strict precursor rejects consent with the wrong scope.');
$assert(!str_contains(strtoupper($strictPreflightSource), 'ALTER TABLE'), 'Strict precursor performs no DDL.');

$assert(is_file($preflightMigrationFile), 'Phase 3 reconciliation precursor must exist and sort before the applied repair.');
$preflightSource = file_get_contents($preflightMigrationFile);
$assert(is_string($preflightSource), 'Phase 3 reconciliation precursor must be readable.');
$preflightMigration = require $preflightMigrationFile;
$assert($preflightMigration instanceof Migration, 'Phase 3 precursor implements Migration.');
$assert(!$preflightMigration->isReversible(), 'Phase 3 precursor is forward-only validation.');
foreach (['certificates', 'projects', 'project_members', 'student_profile_details', 'student_profile_shares', 'privacy_consents'] as $table) {
    $assert(str_contains($preflightSource, "assertTableExists('{$table}')"), "Precursor requires {$table}.");
}
foreach (['character_maximum_length', 'is_nullable', 'column_type'] as $metadata) {
    $assert(str_contains($preflightSource, $metadata), "Precursor checks column metadata {$metadata}.");
}
$assert(str_contains($preflightSource, 'GROUP_CONCAT(column_name ORDER BY seq_in_index'), 'Precursor verifies exact ordered index columns.');
$assert(str_contains($preflightSource, 'non_unique'), 'Precursor verifies index uniqueness.');
$assert(str_contains($preflightSource, 'delete_rule'), 'Precursor verifies foreign-key delete actions.');
$assert(str_contains($preflightSource, 'update_rule'), 'Precursor verifies foreign-key update actions.');
$assert(str_contains($preflightSource, 'check_clause'), 'Precursor verifies required CHECK definitions.');
$assert(str_contains($preflightSource, 'MAX(CHAR_LENGTH(category))'), 'Precursor rejects populated category values that would be narrowed.');
$assert(!str_contains(strtoupper($preflightSource), 'ALTER TABLE'), 'Precursor performs no DDL before reconciliation.');

$assert(is_file($exactMetadataMigrationFile), 'Exact Phase 3 metadata validator must exist and sort before reconciliation.');
$exactMetadataSource = file_get_contents($exactMetadataMigrationFile);
$assert(is_string($exactMetadataSource), 'Exact Phase 3 metadata validator must be readable.');
$exactMetadataMigration = require $exactMetadataMigrationFile;
$assert($exactMetadataMigration instanceof Migration, 'Exact metadata validator implements Migration.');
$assert(!$exactMetadataMigration->isReversible(), 'Exact metadata validator is forward-only validation.');
$assert(str_contains($exactMetadataSource, 'normalizeExactCheck'), 'Exact validator preserves CHECK grouping during comparison.');
$assert(!str_contains($exactMetadataSource, "'(', ')'"), 'Exact validator does not erase CHECK grouping parentheses.');
$assert(str_contains($exactMetadataSource, 'default_generated on update current_timestamp(6)'), 'Exact validator pins canonical ON UPDATE behavior.');
$assert(str_contains($exactMetadataSource, "default => ''"), 'Exact validator rejects unexpected EXTRA metadata on ordinary columns.');
$assert(!str_contains(strtoupper($exactMetadataSource), 'ALTER TABLE'), 'Exact metadata validator performs no DDL.');

$assert(is_file($migrationFile), 'Phase 3 reconciliation migration must exist.');
$source = file_get_contents($migrationFile);
$assert(is_string($source), 'Phase 3 reconciliation migration must be readable.');
$migration = require $migrationFile;
$assert($migration instanceof Migration, 'Phase 3 reconciliation migration implements Migration.');
$assert(!$migration->isReversible(), 'Phase 3 reconciliation migration is forward-only.');

foreach (['projects', 'project_members', 'student_profile_shares', 'privacy_consents'] as $table) {
    $assert(str_contains($source, "assertTableExists('{$table}')"), "Preflight requires {$table}.");
}

$assert(str_contains($source, 'category'), 'Migration reconciles projects.category.');
$assert(str_contains($source, 'contribution'), 'Migration reconciles project_members.contribution.');
$assert(str_contains($source, 'consentId'), 'Migration links profile shares to consent.');
$assert(str_contains($source, 'fk_student_profile_shares_consent'), 'Migration creates consent foreign key.');
$assert(str_contains($source, 'idx_student_profile_shares_consent'), 'Migration creates consent index.');
$assert(str_contains($source, 'DATETIME(6)'), 'Migration widens project dates to DATETIME(6).');
$assert(str_contains($source, 'information_schema.columns'), 'Migration performs semantic column preflight.');
$assert(str_contains($source, 'information_schema.statistics'), 'Migration performs index preflight.');
$assert(str_contains($source, 'information_schema.referential_constraints'), 'Migration performs foreign-key preflight.');

$upper = strtoupper($source);
$assert(!str_contains($upper, 'DROP TABLE'), 'Migration must not drop tables.');
$assert(!str_contains($upper, 'TRUNCATE TABLE'), 'Migration must not truncate tables.');
$assert(!str_contains($upper, 'DELETE FROM'), 'Migration must not delete application rows.');

echo "phase_3_reconciliation_migration_test: OK\n";
