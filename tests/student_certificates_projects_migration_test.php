<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Migration\Migration;

$root = dirname(__DIR__);
$migrationFile = $root . '/Database/migrations/20260821000200_create_student_certificates_and_projects.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$assert(is_file($migrationFile), 'Certificates and projects migration file must exist.');
$migrationSource = file_get_contents($migrationFile);
$assert(is_string($migrationSource), 'Certificates and projects migration source must be readable.');

$migration = require $migrationFile;
$assert($migration instanceof Migration, 'Migration must implement Migration interface.');
$assert(!$migration->isReversible(), 'Certificates and projects migration is forward-only and non-reversible.');

// Certificates schema
$assert(str_contains($migrationSource, 'CREATE TABLE IF NOT EXISTS certificates') || str_contains($migrationSource, 'CREATE TABLE certificates'), 'Creates certificates table.');
$assert(str_contains($migrationSource, 'studentId CHAR(36)'), 'certificates has studentId column.');
$assert(str_contains($migrationSource, 'title VARCHAR(255)'), 'certificates has title column.');
$assert(str_contains($migrationSource, 'issuingOrganization VARCHAR(255)'), 'certificates has issuingOrganization column.');
$assert(str_contains($migrationSource, 'issueDate DATE'), 'certificates has issueDate column.');
$assert(str_contains($migrationSource, 'expiryDate DATE'), 'certificates has expiryDate column.');
$assert(str_contains($migrationSource, 'credentialId VARCHAR(255)'), 'certificates has credentialId column.');
$assert(str_contains($migrationSource, 'credentialUrl VARCHAR(500)'), 'certificates has credentialUrl column.');
$assert(str_contains($migrationSource, 'verificationStatus VARCHAR(32)'), 'certificates has verificationStatus column.');
$assert(str_contains($migrationSource, 'verifiedBy CHAR(36)'), 'certificates has verifiedBy column.');
$assert(str_contains($migrationSource, 'verifiedAt DATETIME(6)'), 'certificates has verifiedAt column.');
$assert(str_contains($migrationSource, 'idx_certificates_student_status'), 'certificates has student status index.');
$assert(str_contains($migrationSource, "'unverified'") && str_contains($migrationSource, "'verified'") && str_contains($migrationSource, "'rejected'"), 'certificates checks verificationStatus.');
$assert(str_contains($migrationSource, 'expiryDate >= issueDate') || str_contains($migrationSource, 'chk_certificates_expiry'), 'certificates checks expiry >= issueDate.');

// Projects schema
$assert(str_contains($migrationSource, 'CREATE TABLE IF NOT EXISTS projects') || str_contains($migrationSource, 'CREATE TABLE projects'), 'Creates projects table.');
$assert(str_contains($migrationSource, 'schoolId CHAR(36)'), 'projects has schoolId column.');
$assert(str_contains($migrationSource, 'mentorTeacherId CHAR(36)'), 'projects has mentorTeacherId column.');
$assert(str_contains($migrationSource, 'title VARCHAR(255)'), 'projects has title column.');
$assert(str_contains($migrationSource, 'description TEXT'), 'projects has description column.');
$assert(str_contains($migrationSource, 'projectUrl VARCHAR(500)'), 'projects has projectUrl column.');
$assert(str_contains($migrationSource, 'startAt DATE') || str_contains($migrationSource, 'startAt DATETIME'), 'projects has startAt column.');
$assert(str_contains($migrationSource, 'endAt DATE') || str_contains($migrationSource, 'endAt DATETIME'), 'projects has endAt column.');
$assert(str_contains($migrationSource, 'status VARCHAR(32)'), 'projects has status column.');
$assert(str_contains($migrationSource, "'draft'") && str_contains($migrationSource, "'in_progress'") && str_contains($migrationSource, "'completed'") && str_contains($migrationSource, "'archived'"), 'projects checks status values.');

// Project members schema
$assert(str_contains($migrationSource, 'CREATE TABLE IF NOT EXISTS project_members') || str_contains($migrationSource, 'CREATE TABLE project_members'), 'Creates project_members table.');
$assert(str_contains($migrationSource, 'projectId CHAR(36)'), 'project_members has projectId column.');
$assert(str_contains($migrationSource, 'role VARCHAR(100)'), 'project_members has role column.');
$assert(str_contains($migrationSource, 'uq_project_members_student'), 'project_members has unique project student index.');
$assert(str_contains($migrationSource, "'active'") && str_contains($migrationSource, "'left'") && str_contains($migrationSource, "'removed'"), 'project_members checks status values.');

// Safety invariants
$assert(!str_contains(strtoupper($migrationSource), 'DELETE FROM'), 'Migration must not delete application data.');
$assert(!str_contains(strtoupper($migrationSource), 'DROP TABLE users'), 'Migration must not drop users table.');
$assert(!str_contains(strtoupper($migrationSource), 'DROP TABLE student_profiles'), 'Migration must not drop student_profiles table.');

echo "student_certificates_projects_migration_test: OK\n";
