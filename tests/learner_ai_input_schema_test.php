<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner;
use TalentHub\Learner\Data\Readiness\AiScopePolicy;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function ai_schema_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function ai_schema_expect_exception(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        ai_schema_assert($exception->getMessage() === $message, "exception is {$message}");
        return;
    }

    ai_schema_assert(false, "expected RuntimeException: {$message}");
}

/** @return array<string,array{columns:list<string>,indexes:list<string>,foreignKeys:array<string,array{table:string,from:string,to:string}>}> */
function ai_schema_contract(): array
{
    return [
        'skills' => ['columns' => ['id', 'code', 'name', 'category', 'status', 'createdAt', 'updatedAt'], 'indexes' => ['uq_skills_code', 'idx_skills_status_category'], 'foreignKeys' => []],
        'student_skills' => ['columns' => ['id', 'studentId', 'skillId', 'levelScore', 'sourceType', 'verificationStatus', 'verifiedAt', 'createdAt', 'updatedAt'], 'indexes' => ['uq_student_skills_student_skill_source', 'idx_student_skills_skill', 'idx_student_skills_student_verification'], 'foreignKeys' => ['fk_student_skills_student' => ['table' => 'student_profiles', 'from' => 'studentId', 'to' => 'id'], 'fk_student_skills_skill' => ['table' => 'skills', 'from' => 'skillId', 'to' => 'id']]],
        'talent_tests' => ['columns' => ['id', 'code', 'name', 'type', 'status', 'createdAt', 'updatedAt'], 'indexes' => ['uq_talent_tests_code', 'idx_talent_tests_status_type'], 'foreignKeys' => []],
        'test_questions' => ['columns' => ['id', 'testId', 'code', 'content', 'optionsJson', 'status', 'createdAt', 'updatedAt'], 'indexes' => ['uq_test_questions_test_code', 'idx_test_questions_test_status'], 'foreignKeys' => ['fk_test_questions_test' => ['table' => 'talent_tests', 'from' => 'testId', 'to' => 'id']]],
        'test_attempts' => ['columns' => ['id', 'testId', 'studentId', 'status', 'startedAt', 'submittedAt', 'createdAt', 'updatedAt'], 'indexes' => ['idx_test_attempts_test', 'idx_test_attempts_student_status'], 'foreignKeys' => ['fk_test_attempts_test' => ['table' => 'talent_tests', 'from' => 'testId', 'to' => 'id'], 'fk_test_attempts_student' => ['table' => 'student_profiles', 'from' => 'studentId', 'to' => 'id']]],
        'test_results' => ['columns' => ['id', 'attemptId', 'resultCode', 'summary', 'dimensionScoresJson', 'scoringVersion', 'createdAt'], 'indexes' => ['uq_test_results_attempt'], 'foreignKeys' => ['fk_test_results_attempt' => ['table' => 'test_attempts', 'from' => 'attemptId', 'to' => 'id']]],
        'privacy_consents' => ['columns' => ['id', 'studentId', 'scope', 'isGranted', 'policyVersion', 'grantedAt', 'revokedAt', 'createdAt'], 'indexes' => ['uq_privacy_consents_student_scope_policy_created', 'idx_privacy_consents_student_scope_granted'], 'foreignKeys' => ['fk_privacy_consents_student' => ['table' => 'student_profiles', 'from' => 'studentId', 'to' => 'id']]],
        'activity_qr_tokens' => ['columns' => ['id', 'activityId', 'tokenHash', 'validFrom', 'validUntil', 'status', 'createdAt'], 'indexes' => ['uq_activity_qr_tokens_token_hash', 'idx_activity_qr_tokens_activity_status'], 'foreignKeys' => ['fk_activity_qr_tokens_activity' => ['table' => 'activities', 'from' => 'activityId', 'to' => 'id']]],
        'checkins' => ['columns' => ['id', 'registrationId', 'qrTokenId', 'status', 'checkedInAt', 'confirmedAt', 'createdAt'], 'indexes' => ['uq_checkins_registration', 'idx_checkins_qr_token'], 'foreignKeys' => ['fk_checkins_registration' => ['table' => 'activity_registrations', 'from' => 'registrationId', 'to' => 'id'], 'fk_checkins_qr_token' => ['table' => 'activity_qr_tokens', 'from' => 'qrTokenId', 'to' => 'id']]],
        'experience_logs' => ['columns' => ['id', 'studentId', 'activityId', 'checkinId', 'hours', 'status', 'auditReason', 'confirmedAt', 'createdAt'], 'indexes' => ['uq_experience_logs_checkin', 'idx_experience_logs_student_status', 'idx_experience_logs_activity'], 'foreignKeys' => ['fk_experience_logs_student' => ['table' => 'student_profiles', 'from' => 'studentId', 'to' => 'id'], 'fk_experience_logs_activity' => ['table' => 'activities', 'from' => 'activityId', 'to' => 'id'], 'fk_experience_logs_checkin' => ['table' => 'checkins', 'from' => 'checkinId', 'to' => 'id']]],
    ];
}

function ai_schema_fixture(bool $conflictingSkills = false): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE student_profiles (id CHAR(36) NOT NULL PRIMARY KEY)');
    $pdo->exec('CREATE TABLE activities (id CHAR(36) NOT NULL PRIMARY KEY)');
    $pdo->exec('CREATE TABLE activity_registrations (id CHAR(36) NOT NULL PRIMARY KEY)');
    if ($conflictingSkills) {
        $pdo->exec('CREATE TABLE skills (id CHAR(36) NOT NULL PRIMARY KEY)');
    }
    return $pdo;
}

function ai_schema_fixture_with_parent_id(string $parent, string $idDefinition): PDO
{
    $pdo = ai_schema_fixture();
    $pdo->exec('DROP TABLE ' . $parent);
    $pdo->exec('CREATE TABLE ' . $parent . ' (id ' . $idDefinition . ')');
    return $pdo;
}

$repositoryRoot = dirname(__DIR__);
$migrationPath = $repositoryRoot . '/Database/migrations/learner/002_create_ai_input_foundation.php';
$dcrPath = $repositoryRoot . '/docs/superpowers/database-change-requests/2026-08-16-ai-input-foundation.md';
$dcr = (string) file_get_contents($dcrPath);
ai_schema_assert(str_contains($dcr, '**Status:** exact-DDL approval granted; disposable proof and the separately authorized shared-database execution completed'), 'DCR records the approved and completed execution state');
ai_schema_assert(str_contains($dcr, '## Execution record (completed)'), 'DCR contains completed execution evidence');
ai_schema_assert(str_contains($dcr, "The first shared invocation returned `['002_create_ai_input_foundation']`; the immediate second invocation returned `[]`"), 'DCR records shared migration idempotency');
ai_schema_assert(str_contains($dcr, 'migration source was created from SHA-256 `af48c71c5d4dd825da3dfd8a2325662b9ae0dd1cd09123fa709a8296d5c0838a`'), 'DCR records the approved migration source fingerprint');
ai_schema_assert(preg_match('/```sql\n(.*?)\n```/s', $dcr, $match) === 1, 'DCR contains exact SQL code fence');
$dcrSql = $match[1];
ai_schema_assert(hash('sha256', $dcrSql) === 'af48c71c5d4dd825da3dfd8a2325662b9ae0dd1cd09123fa709a8296d5c0838a', 'DCR SQL fingerprint is approved');

$definition = require $migrationPath;
$migration = $definition->migration;
ai_schema_assert($definition->version === '002_create_ai_input_foundation', 'migration definition has approved version');
ai_schema_assert($migration->version() === '002_create_ai_input_foundation', 'migration implementation has approved version');
ai_schema_assert(implode("\n\n", $migration->statements('mysql')) === $dcrSql, 'MySQL statements exactly reproduce approved DCR fence');
ai_schema_assert(hash('sha256', implode("\n\n", $migration->statements('mysql'))) === 'af48c71c5d4dd825da3dfd8a2325662b9ae0dd1cd09123fa709a8296d5c0838a', 'MySQL statement fingerprint is approved');
ai_schema_assert((new AiScopePolicy())->inspectMigrationText((string) file_get_contents($migrationPath)) === [], 'migration source has no destructive SQL token');

$pdo = ai_schema_fixture();
$inspector = new SchemaInspector($pdo, 'main');
$runner = new LearnerForwardMigrationRunner($pdo, dirname($migrationPath), $inspector);
ai_schema_assert($runner->migrateApproved(['002_create_ai_input_foundation']) === ['002_create_ai_input_foundation'], 'first approved run applies exactly migration 002');
foreach (ai_schema_contract() as $table => $contract) {
    ai_schema_assert($inspector->hasTable($table), "canonical table exists: {$table}");
    foreach ($contract['columns'] as $column) {
        ai_schema_assert($inspector->hasColumn($table, $column), "required column exists: {$table}.{$column}");
    }
    foreach ($contract['indexes'] as $index) {
        ai_schema_assert($inspector->hasIndex($table, $index), "named unique or FK-facing index exists: {$table}.{$index}");
    }
    foreach ($contract['foreignKeys'] as $name => $foreignKey) {
        ai_schema_assert($inspector->hasForeignKey($table, $foreignKey['table'], $foreignKey['from'], $foreignKey['to']), "SQLite foreign key exists: {$name}");
    }
}
ai_schema_assert($runner->migrateApproved(['002_create_ai_input_foundation']) === [], 'second approved run is a no-op');
ai_schema_assert((int) $pdo->query('SELECT COUNT(*) FROM learner_forward_migrations')->fetchColumn() === 1, 'registry has one record');

$conflictingPdo = ai_schema_fixture(true);
$conflictingInspector = new SchemaInspector($conflictingPdo, 'main');
$conflictingRunner = new LearnerForwardMigrationRunner($conflictingPdo, dirname($migrationPath), $conflictingInspector);
ai_schema_expect_exception(
    static fn (): array => $conflictingRunner->migrateApproved(['002_create_ai_input_foundation']),
    'Learner migration preflight rejected existing canonical target: skills'
);
ai_schema_assert(!$conflictingInspector->hasTable('learner_forward_migrations'), 'conflicting target creates no registry record');

$missingParentPdo = new PDO('sqlite::memory:');
$missingParentPdo->exec('CREATE TABLE student_profiles (id CHAR(36) NOT NULL PRIMARY KEY)');
$missingParentPdo->exec('CREATE TABLE activities (id CHAR(36) NOT NULL PRIMARY KEY)');
$missingParentInspector = new SchemaInspector($missingParentPdo, 'main');
$missingParentRunner = new LearnerForwardMigrationRunner($missingParentPdo, dirname($migrationPath), $missingParentInspector);
ai_schema_expect_exception(
    static fn (): array => $missingParentRunner->migrateApproved(['002_create_ai_input_foundation']),
    'Learner migration preflight missing required parent table: activity_registrations'
);
ai_schema_assert(!$missingParentInspector->hasTable('learner_forward_migrations'), 'missing parent creates no registry record');

$wrongTypePdo = ai_schema_fixture_with_parent_id('student_profiles', 'VARCHAR(36) NOT NULL PRIMARY KEY');
$wrongTypeInspector = new SchemaInspector($wrongTypePdo, 'main');
$wrongTypeRunner = new LearnerForwardMigrationRunner($wrongTypePdo, dirname($migrationPath), $wrongTypeInspector);
ai_schema_expect_exception(
    static fn (): array => $wrongTypeRunner->migrateApproved(['002_create_ai_input_foundation']),
    'Learner migration preflight requires CHAR(36) parent id: student_profiles.id'
);
ai_schema_assert(!$wrongTypeInspector->hasTable('learner_forward_migrations'), 'wrong parent type creates no registry record');

$nonPrimaryPdo = ai_schema_fixture_with_parent_id('activities', 'CHAR(36) NOT NULL');
$nonPrimaryInspector = new SchemaInspector($nonPrimaryPdo, 'main');
$nonPrimaryRunner = new LearnerForwardMigrationRunner($nonPrimaryPdo, dirname($migrationPath), $nonPrimaryInspector);
ai_schema_expect_exception(
    static fn (): array => $nonPrimaryRunner->migrateApproved(['002_create_ai_input_foundation']),
    'Learner migration preflight requires primary-key parent id: activities.id'
);
ai_schema_assert(!$nonPrimaryInspector->hasTable('learner_forward_migrations'), 'non-primary parent id creates no registry record');

$compositePrimaryPdo = ai_schema_fixture();
$compositePrimaryPdo->exec('DROP TABLE activities');
$compositePrimaryPdo->exec('CREATE TABLE activities (id CHAR(36) NOT NULL, secondaryId CHAR(36) NOT NULL, PRIMARY KEY (id, secondaryId))');
$compositePrimaryInspector = new SchemaInspector($compositePrimaryPdo, 'main');
$compositePrimaryRunner = new LearnerForwardMigrationRunner($compositePrimaryPdo, dirname($migrationPath), $compositePrimaryInspector);
ai_schema_expect_exception(
    static fn (): array => $compositePrimaryRunner->migrateApproved(['002_create_ai_input_foundation']),
    'Learner migration preflight requires primary-key parent id: activities.id'
);
ai_schema_assert(!$compositePrimaryInspector->hasTable('learner_forward_migrations'), 'composite parent id creates no registry record');

echo "learner_ai_input_schema_test: OK\n";
