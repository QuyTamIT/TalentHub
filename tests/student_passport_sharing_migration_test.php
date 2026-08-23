<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Migration\Migration;

$root = dirname(__DIR__);
$migrationFile = $root . '/Database/migrations/20260821000100_create_student_passport_sharing.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$assert(is_file($migrationFile), 'Passport sharing migration file must exist.');
$migrationSource = file_get_contents($migrationFile);
$assert(is_string($migrationSource), 'Passport sharing migration source must be readable.');

$migration = require $migrationFile;
$assert($migration instanceof Migration, 'Passport sharing migration must implement Migration.');
$assert(!$migration->isReversible(), 'Passport sharing migration is forward-only and non-reversible.');

// Schema contracts
$assert(str_contains($migrationSource, 'CREATE TABLE IF NOT EXISTS student_profile_details') || str_contains($migrationSource, 'CREATE TABLE student_profile_details'), 'Creates student_profile_details table.');
$assert(str_contains($migrationSource, 'studentId CHAR(36)'), 'student_profile_details has studentId column.');
$assert(str_contains($migrationSource, 'location VARCHAR(255)'), 'student_profile_details has location column.');
$assert(str_contains($migrationSource, 'bio TEXT'), 'student_profile_details has bio column.');
$assert(str_contains($migrationSource, 'avatarUrl VARCHAR(500)'), 'student_profile_details has avatarUrl column.');
$assert(str_contains($migrationSource, 'headline VARCHAR(255)'), 'student_profile_details has headline column.');
$assert(str_contains($migrationSource, 'fk_student_profile_details_student') || str_contains($migrationSource, 'REFERENCES student_profiles(id)'), 'student_profile_details references student_profiles.');

$assert(str_contains($migrationSource, 'CREATE TABLE IF NOT EXISTS student_profile_shares') || str_contains($migrationSource, 'CREATE TABLE student_profile_shares'), 'Creates student_profile_shares table.');
$assert(str_contains($migrationSource, 'tokenHash CHAR(64)'), 'student_profile_shares has tokenHash column.');
$assert(str_contains($migrationSource, 'sharedFieldsJson JSON'), 'student_profile_shares has sharedFieldsJson column.');
$assert(str_contains($migrationSource, 'expiresAt DATETIME(6)'), 'student_profile_shares has expiresAt column.');
$assert(str_contains($migrationSource, 'revokedAt DATETIME(6)'), 'student_profile_shares has revokedAt column.');
$assert(str_contains($migrationSource, 'uq_student_profile_shares_token_hash'), 'student_profile_shares has unique token hash index.');
$assert(str_contains($migrationSource, 'idx_student_profile_shares_student_active'), 'student_profile_shares has student active lookup index.');
$assert(str_contains($migrationSource, 'JSON_VALID(sharedFieldsJson)'), 'student_profile_shares validates JSON.');
$assert(str_contains($migrationSource, 'expiresAt > createdAt') || str_contains($migrationSource, 'chk_student_profile_shares_expiry'), 'student_profile_shares checks expiry > createdAt.');

// Privacy consent scope expansion
$assert(str_contains($migrationSource, 'profile_share'), 'Expands privacy consent scope with profile_share.');
$assert(str_contains($migrationSource, 'application_profile_share'), 'Expands privacy consent scope with application_profile_share.');
$assert(str_contains($migrationSource, 'assessment') && str_contains($migrationSource, 'skills') && str_contains($migrationSource, 'activity') && str_contains($migrationSource, 'evaluation'), 'Preserves existing privacy consent scopes.');

// Safety invariants
$assert(!str_contains(strtoupper($migrationSource), 'DELETE FROM'), 'Passport sharing migration must not delete application data.');
$assert(!str_contains(strtoupper($migrationSource), 'DROP TABLE users'), 'Migration must not drop users table.');
$assert(!str_contains(strtoupper($migrationSource), 'DROP TABLE student_profiles'), 'Migration must not drop student_profiles table.');

echo "student_passport_sharing_migration_test: OK\n";
