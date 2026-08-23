<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Migration\Migration;

$root = dirname(__DIR__);
$migrationFile = $root . '/Database/migrations/20260821000500_create_internships_and_application_lifecycle.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

// 1. Applied Phase 3-5 migrations are protected byte-for-byte.
$protectedMigrationHashes = [
    '20260821000100_create_student_passport_sharing.php' => '32f32abf8705b231ee5e8859b6dd6ae7042992c5eea2eaa75c30b8c9c7296960',
    '20260821000200_create_student_certificates_and_projects.php' => 'f6899580317336e88aaba83b6abffbdacb5827e256489a75b59cb11c769deb45',
    '20260821000204_validate_phase_3_canonical_contracts.php' => '1e33e4cba89fa1f7f595c644642be2f4407bdeb2cf4495c5f3a737220a53e387',
    '20260821000205_preflight_phase_3_reconciliation.php' => 'b99cb7d0c05eb8c2e741c849a38dd54c381c14f2d7cf2463969152ad0c3f5ca1',
    '20260821000206_validate_phase_3_exact_metadata.php' => '0a76232f91da8a26e61ef4a7131f26f0d295ffb751a651052e28fe479254eb9c',
    '20260821000210_reconcile_phase_3_contracts.php' => '68dc925723bca8625d128085e70776f193383190744a2d8ac1071b4edb1b6492',
    '20260821000300_extend_activity_registration_lifecycle.php' => '58a0f99923e6a33fb6d88302ebb1c67318de6bab5c5eafb1de18f5132e8dee04',
    '20260821000400_create_activity_experience_policies.php' => '475ffb17c426c92e96fcb66b9c5b04a0bd98f665bd697b3d0ea75942c966df80',
];
foreach ($protectedMigrationHashes as $pastFile => $expectedHash) {
    $pastPath = $root . '/Database/migrations/' . $pastFile;
    $assert(is_file($pastPath), "Applied migration {$pastFile} must remain present.");
    $assert(
        hash_file('sha256', $pastPath) === $expectedHash,
        "Applied migration {$pastFile} must remain byte-identical."
    );
}

// 2. Phase 7 migration file existence and basic interface
$assert(is_file($migrationFile), 'Phase 7 migration file 20260821000500_create_internships_and_application_lifecycle.php must exist.');
$migrationSource = file_get_contents($migrationFile);
$assert(is_string($migrationSource), 'Phase 7 migration source must be readable.');

$migration = require $migrationFile;
$assert($migration instanceof Migration, 'Phase 7 migration must implement Migration interface.');
$assert(!$migration->isReversible(), 'Phase 7 migration is forward-only and non-reversible.');

// 3. internship_posts contract
$assert(
    str_contains($migrationSource, 'CREATE TABLE IF NOT EXISTS internship_posts') || str_contains($migrationSource, 'CREATE TABLE internship_posts'),
    'Creates internship_posts table.'
);
$assert(str_contains($migrationSource, 'enterpriseId CHAR(36)'), 'internship_posts has enterpriseId column.');
$assert(str_contains($migrationSource, 'title VARCHAR(255)'), 'internship_posts has title column.');
$assert(str_contains($migrationSource, 'field VARCHAR(150)'), 'internship_posts has field column.');
$assert(str_contains($migrationSource, 'status VARCHAR(50)'), 'internship_posts has status column.');
$assert(
    str_contains($migrationSource, "'draft'")
        && str_contains($migrationSource, "'active'")
        && str_contains($migrationSource, "'closed'")
        && str_contains($migrationSource, "'cancelled'"),
    'internship_posts locks the canonical Phase 7 status vocabulary.'
);
$assert(str_contains($migrationSource, 'location VARCHAR(255)'), 'internship_posts has location column.');
$assert(str_contains($migrationSource, 'slots INT UNSIGNED') || str_contains($migrationSource, 'slots SMALLINT UNSIGNED') || str_contains($migrationSource, 'slots INT'), 'internship_posts has slots column.');
$assert(str_contains($migrationSource, 'deadline DATETIME(6)'), 'internship_posts has deadline DATETIME(6) column.');
$assert(str_contains($migrationSource, 'skillsJson JSON'), 'internship_posts has skillsJson JSON column.');
$assert(str_contains($migrationSource, 'requirementsJson JSON'), 'internship_posts has optional requirementsJson column.');
$assert(str_contains($migrationSource, 'fk_internship_posts_enterprise') || str_contains($migrationSource, 'REFERENCES enterprises(id)'), 'internship_posts references enterprises.');

// 4. internship_applications contract
$assert(
    str_contains($migrationSource, 'CREATE TABLE IF NOT EXISTS internship_applications') || str_contains($migrationSource, 'CREATE TABLE internship_applications'),
    'Creates internship_applications table.'
);
$assert(str_contains($migrationSource, 'postId CHAR(36)'), 'internship_applications has postId column.');
$assert(str_contains($migrationSource, 'studentId CHAR(36)'), 'internship_applications has studentId column.');
$assert(str_contains($migrationSource, 'status VARCHAR(50)'), 'internship_applications has status column.');
$assert(
    str_contains($migrationSource, "'submitted'")
        && str_contains($migrationSource, "'reviewing'")
        && str_contains($migrationSource, "'interview'")
        && str_contains($migrationSource, "'accepted'")
        && str_contains($migrationSource, "'declined'")
        && str_contains($migrationSource, "'withdrawn'"),
    'internship_applications locks the canonical Phase 7 status vocabulary.'
);
$assert(str_contains($migrationSource, 'appliedAt DATETIME(6)'), 'internship_applications has appliedAt column.');
$assert(str_contains($migrationSource, 'message VARCHAR(500)'), 'internship_applications stores the candidate message separately.');
$assert(str_contains($migrationSource, 'reviewerNote TEXT'), 'internship_applications stores internal reviewer notes separately.');
$assert(str_contains($migrationSource, 'reviewedAt DATETIME(6)'), 'internship_applications records the review timestamp.');
$assert(str_contains($migrationSource, 'reviewedBy CHAR(36)'), 'internship_applications records the reviewing user.');
$assert(!str_contains($migrationSource, 'coverMessage'), 'Non-canonical coverMessage column is forbidden.');
$assert(!str_contains($migrationSource, 'reviewerUserId'), 'Non-canonical reviewerUserId column is forbidden.');
$assert(str_contains($migrationSource, 'uq_internship_applications_post_student') || str_contains($migrationSource, 'UNIQUE KEY uq_internship_applications_post_student (postId, studentId)') || str_contains($migrationSource, 'UNIQUE (postId, studentId)'), 'internship_applications has unique (postId, studentId) constraint.');
$assert(str_contains($migrationSource, 'fk_internship_applications_post') || str_contains($migrationSource, 'REFERENCES internship_posts(id)'), 'internship_applications references internship_posts.');
$assert(str_contains($migrationSource, 'fk_internship_applications_student') || str_contains($migrationSource, 'REFERENCES student_profiles(id)'), 'internship_applications references student_profiles.');
$assert(str_contains($migrationSource, 'fk_internship_applications_reviewer'), 'internship_applications references the reviewing user.');

// 5. application_status_history contract
$assert(
    str_contains($migrationSource, 'CREATE TABLE IF NOT EXISTS application_status_history') || str_contains($migrationSource, 'CREATE TABLE application_status_history'),
    'Creates application_status_history table.'
);
$assert(str_contains($migrationSource, 'applicationId CHAR(36)'), 'application_status_history has applicationId column.');
$assert(str_contains($migrationSource, 'toStatus VARCHAR(50)'), 'application_status_history has toStatus column.');
$assert(str_contains($migrationSource, 'changedByUserId CHAR(36)'), 'application_status_history has changedByUserId column.');
$assert(str_contains($migrationSource, 'changedByRole VARCHAR(50)'), 'application_status_history has changedByRole column.');
$assert(str_contains($migrationSource, 'note TEXT'), 'application_status_history stores the transition note.');
$assert(!str_contains($migrationSource, 'changeReason'), 'Non-canonical changeReason history column is forbidden.');
$assert(str_contains($migrationSource, 'fk_application_status_history_application') || str_contains($migrationSource, 'REFERENCES internship_applications(id)'), 'application_status_history references internship_applications.');
$assert(
    preg_match('/FOREIGN KEY\s*\(applicationId\)\s*REFERENCES\s+internship_applications\s*\(id\)\s*ON DELETE RESTRICT/i', $migrationSource) === 1,
    'application history prevents hard deletion of its application.'
);

// 6. application_profile_snapshots contract - unique one-to-one, canonical consent reference, JSON validation
$assert(
    str_contains($migrationSource, 'CREATE TABLE IF NOT EXISTS application_profile_snapshots') || str_contains($migrationSource, 'CREATE TABLE application_profile_snapshots'),
    'Creates application_profile_snapshots table.'
);
$assert(str_contains($migrationSource, 'applicationId CHAR(36)'), 'application_profile_snapshots has applicationId column.');
$assert(str_contains($migrationSource, 'consentId CHAR(36)'), 'application_profile_snapshots has consentId column.');
$assert(str_contains($migrationSource, 'schemaVersion VARCHAR(50)'), 'application_profile_snapshots has schemaVersion column.');
$assert(str_contains($migrationSource, 'snapshotPayload JSON'), 'application_profile_snapshots has snapshotPayload JSON column.');
$assert(str_contains($migrationSource, 'uq_application_profile_snapshots_application') || str_contains($migrationSource, 'UNIQUE (applicationId)') || str_contains($migrationSource, 'UNIQUE KEY uq_application_profile_snapshots_application (applicationId)'), 'application_profile_snapshots has unique one-to-one applicationId constraint.');
$assert(str_contains($migrationSource, 'fk_application_profile_snapshots_application') || str_contains($migrationSource, 'REFERENCES internship_applications(id)'), 'application_profile_snapshots references internship_applications.');
$assert(
    preg_match('/FOREIGN KEY\s*\(applicationId\)\s*REFERENCES\s+internship_applications\s*\(id\)\s*ON DELETE RESTRICT/i', $migrationSource) === 1,
    'application snapshot prevents hard deletion of its application.'
);
$assert(str_contains($migrationSource, 'fk_application_profile_snapshots_consent') || str_contains($migrationSource, 'REFERENCES privacy_consents(id)'), 'application_profile_snapshots references privacy_consents.');
$assert(str_contains($migrationSource, 'JSON_VALID(snapshotPayload)') || str_contains($migrationSource, 'chk_application_profile_snapshots_payload'), 'application_profile_snapshots enforces valid JSON.');

// 7. Safety invariants
$assert(!str_contains(strtoupper($migrationSource), 'DELETE FROM'), 'Migration must not delete existing application data.');
$assert(!str_contains(strtoupper($migrationSource), 'DROP TABLE USERS'), 'Migration must not drop users table.');
$assert(!str_contains(strtoupper($migrationSource), 'DROP TABLE STUDENT_PROFILES'), 'Migration must not drop student_profiles table.');
$assert(!str_contains(strtoupper($migrationSource), 'DROP TABLE ENTERPRISES'), 'Migration must not drop enterprises table.');

echo "application_profile_snapshot_migration_test: OK\n";
