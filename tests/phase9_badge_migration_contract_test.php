<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Migration\Migration;

$root = dirname(__DIR__);
$migrationFile = $root . '/Database/migrations/20260821000700_create_badges_and_award_rules.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

// 1. Migration file must exist
$assert(is_file($migrationFile), 'Phase 9 migration file 20260821000700_create_badges_and_award_rules.php must exist.');

$source = file_get_contents($migrationFile);
$assert(is_string($source), 'Phase 9 migration source must be readable.');

// 2. Migration instance & contract
$migration = require $migrationFile;
$assert($migration instanceof Migration, 'Phase 9 migration must implement Migration contract.');
$assert(!$migration->isReversible(), 'Phase 9 migration must be non-reversible (forward-only).');
$assert(str_contains($source, 'assertExactTargetTableSet'), 'Migration preflight rejects partial target-table state.');
$assert(str_contains($source, 'assertCatalogConflicts'), 'Migration preflight rejects conflicting catalog/rule rows.');
$assert(str_contains($source, 'assertForeignKey'), 'Migration verifies exact foreign-key actions.');
$assert(str_contains($source, 'assertCheckConstraint'), 'Migration verifies exact CHECK constraints.');

// 3. Schema contracts: Table creation
$assert(
    str_contains($source, 'CREATE TABLE IF NOT EXISTS badges') || str_contains($source, 'CREATE TABLE badges'),
    'Migration creates badges table.'
);
$assert(
    str_contains($source, 'CREATE TABLE IF NOT EXISTS badge_rule_definitions') || str_contains($source, 'CREATE TABLE badge_rule_definitions'),
    'Migration creates badge_rule_definitions table.'
);
$assert(
    str_contains($source, 'CREATE TABLE IF NOT EXISTS student_badges') || str_contains($source, 'CREATE TABLE student_badges'),
    'Migration creates student_badges table.'
);

// 4. Badges columns and indexes
$assert(str_contains($source, 'code VARCHAR(64)'), 'badges has code column.');
$assert(str_contains($source, 'category VARCHAR(64)'), 'badges has category column.');
$assert(str_contains($source, 'level INT'), 'badges has level column.');
$assert(str_contains($source, 'status VARCHAR(32)'), 'badges has status column.');
$assert(str_contains($source, 'uq_badges_code'), 'badges has uq_badges_code index.');
$assert(str_contains($source, 'chk_badges_status'), 'badges has chk_badges_status check.');
$assert(str_contains($source, 'chk_badges_level'), 'badges has chk_badges_level check.');

// 5. Badge rule definitions columns and indexes
$assert(str_contains($source, 'badgeId CHAR(36)'), 'badge_rule_definitions has badgeId column.');
$assert(str_contains($source, 'ruleType VARCHAR(64)'), 'badge_rule_definitions has ruleType column.');
$assert(str_contains($source, 'thresholdCriteria JSON'), 'badge_rule_definitions has thresholdCriteria JSON column.');
$assert(str_contains($source, 'version INT'), 'badge_rule_definitions has version column.');
$assert(str_contains($source, 'isActive TINYINT(1)'), 'badge_rule_definitions has isActive column.');
$assert(str_contains($source, 'uq_badge_rules_badge_version'), 'badge_rule_definitions has uq_badge_rules_badge_version unique index.');
$assert(str_contains($source, 'idx_badge_rules_active'), 'badge_rule_definitions has idx_badge_rules_active index.');
$assert(str_contains($source, 'fk_badge_rule_definitions_badge'), 'badge_rule_definitions has FK to badges.');

// 6. Student badges columns and indexes
$assert(str_contains($source, 'studentId CHAR(36)'), 'student_badges has studentId column.');
$assert(str_contains($source, 'ruleDefinitionId CHAR(36)'), 'student_badges has ruleDefinitionId column.');
$assert(str_contains($source, 'awardedAt DATETIME(6)'), 'student_badges has awardedAt column.');
$assert(str_contains($source, 'awardedBy VARCHAR(64)'), 'student_badges has awardedBy column.');
$assert(str_contains($source, 'awardContext JSON'), 'student_badges has awardContext JSON column.');
$assert(str_contains($source, 'uq_student_badges_award'), 'student_badges has uq_student_badges_award unique index.');
$assert(str_contains($source, 'idx_student_badges_badge'), 'student_badges has idx_student_badges_badge index.');
$assert(str_contains($source, 'idx_student_badges_rule'), 'student_badges has idx_student_badges_rule index.');
$assert(str_contains($source, 'idx_student_badges_student_awarded'), 'student_badges has idx_student_badges_student_awarded index.');
$assert(str_contains($source, 'fk_student_badges_student'), 'student_badges has FK to student_profiles.');
$assert(str_contains($source, 'fk_student_badges_badge'), 'student_badges has FK to badges.');
$assert(str_contains($source, 'fk_student_badges_rule'), 'student_badges has FK to badge_rule_definitions.');
$assert(str_contains($source, 'chk_student_badges_awarded_by'), 'student_badges has chk_student_badges_awarded_by check.');

// 7. System catalog and rules seeded
$catalogBadges = [
    'first_experience',
    'experience_10h',
    'active_participant',
    'assessment_explorer',
    'teacher_recognition',
];
foreach ($catalogBadges as $badgeCode) {
    $assert(str_contains($source, "'{$badgeCode}'"), "Migration seeds catalog badge {$badgeCode}.");
}

$facts = [
    'confirmed_experience_hours',
    'attended_activity_count',
    'submitted_assessment_type_count',
    'published_teacher_evaluation_count',
];
foreach ($facts as $fact) {
    $assert(str_contains($source, "'{$fact}'"), "Migration configures rule fact {$fact}.");
}

// 8. Safety invariants
$assert(!str_contains(strtoupper($source), 'DROP TABLE'), 'Migration must not DROP tables.');
$assert(!str_contains(strtoupper($source), 'TRUNCATE'), 'Migration must not TRUNCATE tables.');
$assert(!str_contains(strtoupper($source), 'ON DUPLICATE KEY UPDATE'), 'Migration must fail on conflicting catalog rows instead of repairing them implicitly.');

echo "phase9_badge_migration_contract_test: OK\n";
