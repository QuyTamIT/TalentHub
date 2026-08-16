<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner;
use TalentHub\Learner\Data\Readiness\AiScopePolicy;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

const AI_INPUT_EXTENSIONS_DDL_SHA256 = 'b051de910491339de78eebb95e01da683dd3230601b7357e12013099e54d9ed4';

function ai_extensions_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function ai_extensions_expect_constraint(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (\PDOException) {
        return;
    }

    ai_extensions_assert(false, $message);
}

function ai_extensions_expect_exception(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        ai_extensions_assert($exception->getMessage() === $message, "exception is {$message}");
        return;
    }

    ai_extensions_assert(false, "expected RuntimeException: {$message}");
}

/** @return array<string,array{columns:list<string>,indexes:list<string>,foreignKeys:array<string,array{table:string,from:string,to:string}>}> */
function ai_extensions_contract(): array
{
    return [
        'learner_assessment_versions' => [
            'columns' => ['id', 'testId', 'version', 'scoringVersion', 'schemaHash', 'status', 'publishedAt', 'createdAt'],
            'indexes' => ['uq_learner_assessment_versions_test_version', 'idx_learner_assessment_versions_test_status'],
            'foreignKeys' => ['fk_learner_assessment_versions_test' => ['table' => 'talent_tests', 'from' => 'testId', 'to' => 'id']],
        ],
        'learner_assessment_question_versions' => [
            'columns' => ['id', 'versionId', 'questionId', 'position', 'dimensionCode', 'required', 'createdAt'],
            'indexes' => ['uq_learner_assessment_question_versions_version_question', 'uq_learner_assessment_question_versions_version_position', 'idx_learner_assessment_question_versions_question'],
            'foreignKeys' => [
                'fk_learner_assessment_question_versions_version' => ['table' => 'learner_assessment_versions', 'from' => 'versionId', 'to' => 'id'],
                'fk_learner_assessment_question_versions_question' => ['table' => 'test_questions', 'from' => 'questionId', 'to' => 'id'],
            ],
        ],
        'learner_assessment_attempt_metadata' => [
            'columns' => ['id', 'attemptId', 'versionId', 'status', 'expiresAt', 'submittedAt', 'inputHash', 'createdAt', 'updatedAt'],
            'indexes' => ['uq_learner_assessment_attempt_metadata_attempt', 'idx_learner_assessment_attempt_metadata_version_status'],
            'foreignKeys' => [
                'fk_learner_assessment_attempt_metadata_attempt' => ['table' => 'test_attempts', 'from' => 'attemptId', 'to' => 'id'],
                'fk_learner_assessment_attempt_metadata_version' => ['table' => 'learner_assessment_versions', 'from' => 'versionId', 'to' => 'id'],
            ],
        ],
        'learner_assessment_answers' => [
            'columns' => ['id', 'attemptId', 'questionId', 'answerJson', 'answeredAt'],
            'indexes' => ['uq_learner_assessment_answers_attempt_question', 'idx_learner_assessment_answers_question'],
            'foreignKeys' => [
                'fk_learner_assessment_answers_attempt' => ['table' => 'learner_assessment_attempt_metadata', 'from' => 'attemptId', 'to' => 'attemptId'],
                'fk_learner_assessment_answers_question' => ['table' => 'test_questions', 'from' => 'questionId', 'to' => 'id'],
            ],
        ],
        'learner_skill_evidence' => [
            'columns' => ['id', 'studentSkillId', 'evidenceType', 'evidenceRef', 'verificationStatus', 'observedAt', 'createdAt'],
            'indexes' => ['idx_learner_skill_evidence_student_skill_observed', 'idx_learner_skill_evidence_evidence_ref'],
            'foreignKeys' => ['fk_learner_skill_evidence_student_skill' => ['table' => 'student_skills', 'from' => 'studentSkillId', 'to' => 'id']],
        ],
        'learner_ai_consent_events' => [
            'columns' => ['id', 'studentId', 'scope', 'action', 'policyVersion', 'occurredAt', 'requestId'],
            'indexes' => ['uq_learner_ai_consent_events_student_scope_occurred_request', 'idx_learner_ai_consent_events_student_scope_occurred'],
            'foreignKeys' => ['fk_learner_ai_consent_events_student' => ['table' => 'student_profiles', 'from' => 'studentId', 'to' => 'id']],
        ],
    ];
}

function ai_extensions_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE student_profiles (id CHAR(36) NOT NULL PRIMARY KEY)');
    $pdo->exec('CREATE TABLE activities (id CHAR(36) NOT NULL PRIMARY KEY)');
    $pdo->exec('CREATE TABLE activity_registrations (id CHAR(36) NOT NULL PRIMARY KEY)');
    return $pdo;
}

/** @param array{table:string,from:string,to:string} $foreignKey */
function ai_extensions_assert_restrict_cascade(PDO $pdo, string $table, array $foreignKey): void
{
    $statement = $pdo->query('PRAGMA foreign_key_list(' . $table . ')');
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (($row['table'] ?? null) === $foreignKey['table']
            && ($row['from'] ?? null) === $foreignKey['from']
            && ($row['to'] ?? null) === $foreignKey['to']) {
            ai_extensions_assert(($row['on_delete'] ?? null) === 'RESTRICT', "{$table}.{$foreignKey['from']} deletes are restricted");
            ai_extensions_assert(($row['on_update'] ?? null) === 'CASCADE', "{$table}.{$foreignKey['from']} updates cascade");
            return;
        }
    }

    ai_extensions_assert(false, "foreign key is available for action check: {$table}.{$foreignKey['from']}");
}

function ai_extensions_assert_trigger(PDO $pdo, string $name): void
{
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'trigger' AND name = :name");
    $statement->execute(['name' => $name]);
    ai_extensions_assert($statement->fetchColumn() !== false, "SQLite trigger exists: {$name}");
}

function ai_extensions_insert_fixture_rows(PDO $pdo): void
{
    $pdo->exec("INSERT INTO student_profiles (id) VALUES ('student-000000000000000000000000000001')");
    $pdo->exec("INSERT INTO skills (id, code, name, category, status) VALUES ('skill-00000000000000000000000000000001', 'iot', 'IoT', 'technology', 'active')");
    $pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus) VALUES ('student-skill-000000000000000000000000001', 'student-000000000000000000000000000001', 'skill-00000000000000000000000000000001', 80, 'self_declared', 'self_declared')");
    $pdo->exec("INSERT INTO talent_tests (id, code, name, type, status) VALUES ('test-000000000000000000000000000000001', 'holland', 'Holland', 'interest', 'published')");
    $pdo->exec("INSERT INTO test_questions (id, testId, code, content, optionsJson, status) VALUES ('question-00000000000000000000000000001', 'test-000000000000000000000000000000001', 'r1', 'Question', '[]', 'published')");
    $pdo->exec("INSERT INTO test_attempts (id, testId, studentId, status) VALUES ('attempt-0000000000000000000000000000001', 'test-000000000000000000000000000000001', 'student-000000000000000000000000000001', 'in_progress')");
    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt) VALUES ('version-0000000000000000000000000000001', 'test-000000000000000000000000000000001', '1.0.0', 'score-1.0.0', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'published', '2026-08-16 00:00:00')");
    $pdo->exec("INSERT INTO learner_assessment_question_versions (id, versionId, questionId, position, dimensionCode, required) VALUES ('question-version-000000000000000000000000001', 'version-0000000000000000000000000000001', 'question-00000000000000000000000000001', 1, 'R', 1)");
    $pdo->exec("INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status) VALUES ('attempt-metadata-00000000000000000000000001', 'attempt-0000000000000000000000000000001', 'version-0000000000000000000000000000001', 'in_progress')");
    $pdo->exec("INSERT INTO learner_assessment_answers (id, attemptId, questionId, answerJson) VALUES ('answer-00000000000000000000000000000001', 'attempt-0000000000000000000000000000001', 'question-00000000000000000000000000001', '{\"choice\":\"A\"}')");
    $pdo->exec("INSERT INTO learner_skill_evidence (id, studentSkillId, evidenceType, evidenceRef, verificationStatus, observedAt) VALUES ('evidence-000000000000000000000000000001', 'student-skill-000000000000000000000000001', 'assessment', 'attempt-0000000000000000000000000000001', 'verified', '2026-08-16 00:00:00')");
    $pdo->exec("INSERT INTO learner_ai_consent_events (id, studentId, scope, action, policyVersion, occurredAt, requestId) VALUES ('consent-000000000000000000000000000001', 'student-000000000000000000000000000001', 'assessment', 'granted', 'policy-1.0.0', '2026-08-16 00:00:00', 'request-000000000000000000000000000001')");
}

$repositoryRoot = dirname(__DIR__);
$migrationPath = $repositoryRoot . '/Database/migrations/learner/003_create_ai_input_extensions.php';
$dcrPath = $repositoryRoot . '/docs/superpowers/database-change-requests/2026-08-16-ai-input-extensions.md';
ai_extensions_assert(is_file($dcrPath), 'Task 4 DCR exists before extension migration source');
ai_extensions_assert(is_file($migrationPath), 'Task 4 migration source exists after the approved DCR');

$dcr = (string) file_get_contents($dcrPath);
ai_extensions_assert(str_contains($dcr, '**Status:** exact-DDL source/disposable approval granted; shared-database execution remains pending'), 'DCR keeps shared execution separately gated');
ai_extensions_assert(str_contains($dcr, 'APPROVAL REQUIRED: do not execute migration 003 against a shared database'), 'DCR requires a separate shared execution approval');
ai_extensions_assert(preg_match('/```sql\n(.*?)\n```/s', $dcr, $match) === 1, 'DCR contains exact SQL code fence');
$dcrSql = $match[1];
ai_extensions_assert(hash('sha256', $dcrSql) === AI_INPUT_EXTENSIONS_DDL_SHA256, 'DCR SQL fingerprint is approved');

$definition = require $migrationPath;
$migration = $definition->migration;
ai_extensions_assert($definition->version === '003_create_ai_input_extensions', 'migration definition has approved version');
ai_extensions_assert($migration->version() === '003_create_ai_input_extensions', 'migration implementation has approved version');
ai_extensions_assert(implode("\n\n", $migration->statements('mysql')) === $dcrSql, 'MySQL statements exactly reproduce approved DCR fence');
ai_extensions_assert(hash('sha256', implode("\n\n", $migration->statements('mysql'))) === AI_INPUT_EXTENSIONS_DDL_SHA256, 'MySQL statement fingerprint is approved');
ai_extensions_assert(!str_contains(implode("\n\n", $migration->statements('mysql')), 'CREATE TABLE IF NOT EXISTS'), 'extension migration refuses pre-existing targets instead of accepting an unchecked shape');
ai_extensions_assert((new AiScopePolicy())->inspectMigrationText((string) file_get_contents($migrationPath)) === [], 'migration source has no destructive SQL token');

$pdo = ai_extensions_fixture();
$inspector = new SchemaInspector($pdo, 'main');
$runner = new LearnerForwardMigrationRunner($pdo, dirname($migrationPath), $inspector);
ai_extensions_assert($runner->migrateApproved(['002_create_ai_input_foundation']) === ['002_create_ai_input_foundation'], 'Task 3 foundation applies before Task 4 extension');
ai_extensions_assert($runner->migrateApproved(['003_create_ai_input_extensions']) === ['003_create_ai_input_extensions'], 'first approved run applies exactly migration 003');

foreach (ai_extensions_contract() as $table => $contract) {
    ai_extensions_assert($inspector->hasTable($table), "canonical extension table exists: {$table}");
    foreach ($contract['columns'] as $column) {
        ai_extensions_assert($inspector->hasColumn($table, $column), "required extension column exists: {$table}.{$column}");
    }
    foreach ($contract['indexes'] as $index) {
        ai_extensions_assert($inspector->hasIndex($table, $index), "named extension index exists: {$table}.{$index}");
    }
    foreach ($contract['foreignKeys'] as $name => $foreignKey) {
        ai_extensions_assert($inspector->hasForeignKey($table, $foreignKey['table'], $foreignKey['from'], $foreignKey['to']), "SQLite foreign key exists: {$name}");
        ai_extensions_assert_restrict_cascade($pdo, $table, $foreignKey);
    }
}
foreach ([
    'trg_learner_assessment_attempt_metadata_test_match_insert',
    'trg_learner_assessment_attempt_metadata_test_match_update',
    'trg_learner_assessment_answers_version_match_insert',
    'trg_learner_assessment_answers_version_match_update',
    'trg_learner_ai_consent_events_append_only_update',
    'trg_learner_ai_consent_events_append_only_delete',
] as $trigger) {
    ai_extensions_assert_trigger($pdo, $trigger);
}

ai_extensions_insert_fixture_rows($pdo);
$pdo->exec("INSERT INTO talent_tests (id, code, name, type, status) VALUES ('test-000000000000000000000000000000002', 'aptitude', 'Aptitude', 'aptitude', 'published')");
$pdo->exec("INSERT INTO test_questions (id, testId, code, content, optionsJson, status) VALUES ('question-00000000000000000000000000002', 'test-000000000000000000000000000000002', 'a1', 'Other question', '[]', 'published')");
$pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt) VALUES ('version-0000000000000000000000000000002', 'test-000000000000000000000000000000002', '1.0.0', 'score-1.0.0', 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc', 'published', '2026-08-16 00:00:00')");
ai_extensions_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt) VALUES ('version-duplicate-0000000000000000000000001', 'test-000000000000000000000000000000001', '1.0.0', 'score-1.0.0', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'published', '2026-08-16 00:00:00')"),
    'assessment version rejects duplicate (testId, version)'
);
ai_extensions_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_assessment_question_versions (id, versionId, questionId, position, dimensionCode, required) VALUES ('question-version-duplicate-0000000000000000001', 'version-0000000000000000000000000000001', 'question-00000000000000000000000000001', 2, 'R', 1)"),
    'assessment question version rejects duplicate (versionId, questionId)'
);
ai_extensions_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_assessment_answers (id, attemptId, questionId, answerJson) VALUES ('answer-duplicate-0000000000000000000000001', 'attempt-0000000000000000000000000000001', 'question-00000000000000000000000000001', '{\"choice\":\"B\"}')"),
    'assessment answers reject duplicate (attemptId, questionId)'
);
ai_extensions_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status) VALUES ('attempt-metadata-cross-test-00000000000000001', 'attempt-0000000000000000000000000000001', 'version-0000000000000000000000000000002', 'in_progress')"),
    'attempt metadata rejects a version from another test'
);
ai_extensions_expect_constraint(
    static fn (): int|false => $pdo->exec("UPDATE learner_assessment_attempt_metadata SET versionId = 'version-0000000000000000000000000000002' WHERE id = 'attempt-metadata-00000000000000000000000001'"),
    'attempt metadata rejects a later cross-test version change'
);
ai_extensions_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_assessment_answers (id, attemptId, questionId, answerJson) VALUES ('answer-cross-version-00000000000000000000001', 'attempt-0000000000000000000000000000001', 'question-00000000000000000000000000002', '{\"choice\":\"A\"}')"),
    'assessment answers reject a question absent from the selected version'
);
ai_extensions_expect_constraint(
    static fn (): int|false => $pdo->exec("UPDATE learner_assessment_answers SET questionId = 'question-00000000000000000000000000002' WHERE id = 'answer-00000000000000000000000000000001'"),
    'assessment answers reject a later question outside the selected version'
);
ai_extensions_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_ai_consent_events (id, studentId, scope, action, policyVersion, occurredAt, requestId) VALUES ('consent-invalid-action-0000000000000000000001', 'student-000000000000000000000000000001', 'assessment', 'unknown', 'policy-1.0.0', '2026-08-16 00:00:01', 'request-invalid-action-00000000000000000001')"),
    'consent action is constrained to granted or revoked'
);
$pdo->exec("INSERT INTO learner_ai_consent_events (id, studentId, scope, action, policyVersion, occurredAt, requestId) VALUES ('consent-000000000000000000000000000002', 'student-000000000000000000000000000001', 'assessment', 'revoked', 'policy-1.0.0', '2026-08-16 00:00:01', 'request-000000000000000000000000000002')");
ai_extensions_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_ai_consent_events (id, studentId, scope, action, policyVersion, occurredAt, requestId) VALUES ('consent-duplicate-00000000000000000000001', 'student-000000000000000000000000000001', 'assessment', 'granted', 'policy-1.0.0', '2026-08-16 00:00:01', 'request-000000000000000000000000000002')"),
    'consent events reject duplicate ordering keys'
);
ai_extensions_expect_constraint(
    static fn (): int|false => $pdo->exec("UPDATE learner_ai_consent_events SET action = 'revoked' WHERE id = 'consent-000000000000000000000000000001'"),
    'consent events reject updates'
);
ai_extensions_expect_constraint(
    static fn (): int|false => $pdo->exec("DELETE FROM learner_ai_consent_events WHERE id = 'consent-000000000000000000000000000001'"),
    'consent events reject deletes'
);
$latestAction = $pdo->query("SELECT action FROM learner_ai_consent_events WHERE studentId = 'student-000000000000000000000000000001' AND scope = 'assessment' ORDER BY occurredAt DESC, requestId DESC LIMIT 1")->fetchColumn();
ai_extensions_assert($latestAction === 'revoked', 'append-only consent ordering resolves the latest event deterministically');
ai_extensions_assert($runner->migrateApproved(['003_create_ai_input_extensions']) === [], 'second approved extension run is a no-op');
ai_extensions_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_forward_migrations WHERE version = '003_create_ai_input_extensions'")->fetchColumn() === 1, 'extension registry has one record');

$missingParentPdo = ai_extensions_fixture();
$missingParentInspector = new SchemaInspector($missingParentPdo, 'main');
$missingParentRunner = new LearnerForwardMigrationRunner($missingParentPdo, dirname($migrationPath), $missingParentInspector);
ai_extensions_expect_exception(
    static fn (): array => $missingParentRunner->migrateApproved(['003_create_ai_input_extensions']),
    'Learner migration preflight requires verified Task 3 migration: 002_create_ai_input_foundation'
);
ai_extensions_assert(!$missingParentInspector->hasTable('learner_forward_migrations'), 'missing Task 3 parent creates no migration registry');

$checksumMismatchPdo = ai_extensions_fixture();
$checksumMismatchInspector = new SchemaInspector($checksumMismatchPdo, 'main');
$checksumMismatchRunner = new LearnerForwardMigrationRunner($checksumMismatchPdo, dirname($migrationPath), $checksumMismatchInspector);
ai_extensions_assert($checksumMismatchRunner->migrateApproved(['002_create_ai_input_foundation']) === ['002_create_ai_input_foundation'], 'foundation applies for checksum preflight fixture');
$checksumMismatchPdo->exec("UPDATE learner_forward_migrations SET checksum = '0000000000000000000000000000000000000000000000000000000000000000' WHERE version = '002_create_ai_input_foundation'");
ai_extensions_expect_exception(
    static function () use ($migration, $checksumMismatchInspector): void {
        $migration->assertBeforeApply($checksumMismatchInspector);
    },
    'Learner migration preflight requires verified Task 3 migration: 002_create_ai_input_foundation'
);

echo "learner_ai_input_extensions_schema_test: OK\n";
