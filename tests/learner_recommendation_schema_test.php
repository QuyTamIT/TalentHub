<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner;
use TalentHub\Learner\Data\Readiness\AiScopePolicy;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

const RECOMMENDATION_STORE_DDL_SHA256 = '4ae5975f4225d7727518da43f8d0b72cef97ded92828c5ec38ff9a084ad3d80a';

function recommendation_schema_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function recommendation_schema_expect_constraint(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (PDOException) {
        return;
    }

    recommendation_schema_assert(false, $message);
}

function recommendation_schema_expect_exception(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        recommendation_schema_assert($exception->getMessage() === $message, "exception is {$message}");
        return;
    }

    recommendation_schema_assert(false, "expected RuntimeException: {$message}");
}

/** @return array<string,array{columns:list<string>,indexes:list<string>,foreignKeys:array<string,array{table:string,from:string,to:string}>}> */
function recommendation_schema_contract(): array
{
    return [
        'learner_recommendation_input_snapshots' => [
            'columns' => ['id', 'studentId', 'schemaVersion', 'contentHash', 'consentScopesJson', 'qualityFlagsJson', 'payloadJson', 'sourceUpdatedAt', 'createdAt'],
            'indexes' => ['uq_learner_recommendation_input_snapshots_student_hash', 'idx_learner_recommendation_input_snapshots_student_created'],
            'foreignKeys' => ['fk_learner_recommendation_input_snapshots_student' => ['table' => 'student_profiles', 'from' => 'studentId', 'to' => 'id']],
        ],
        'learner_recommendation_runs' => [
            'columns' => ['id', 'studentId', 'snapshotId', 'idempotencyKey', 'engineType', 'status', 'ruleVersion', 'provider', 'modelVersion', 'promptVersion', 'fallbackReason', 'safeErrorCode', 'startedAt', 'completedAt', 'createdAt'],
            'indexes' => ['uq_learner_recommendation_runs_student_idempotency', 'idx_learner_recommendation_runs_student_created', 'idx_learner_recommendation_runs_snapshot'],
            'foreignKeys' => [
                'fk_learner_recommendation_runs_student' => ['table' => 'student_profiles', 'from' => 'studentId', 'to' => 'id'],
                'fk_learner_recommendation_runs_snapshot' => ['table' => 'learner_recommendation_input_snapshots', 'from' => 'snapshotId', 'to' => 'id'],
            ],
        ],
        'learner_recommendation_items' => [
            'columns' => ['id', 'runId', 'itemType', 'title', 'summary', 'priority', 'confidenceBand', 'actionJson', 'lifecycleStatus', 'createdAt'],
            'indexes' => ['idx_learner_recommendation_items_run_lifecycle_priority'],
            'foreignKeys' => ['fk_learner_recommendation_items_run' => ['table' => 'learner_recommendation_runs', 'from' => 'runId', 'to' => 'id']],
        ],
        'learner_recommendation_evidence' => [
            'columns' => ['id', 'itemId', 'sourceType', 'sourceId', 'observedAt', 'contributionLabel', 'safeValueJson', 'createdAt'],
            'indexes' => ['uq_learner_recommendation_evidence_item_source', 'idx_learner_recommendation_evidence_source'],
            'foreignKeys' => ['fk_learner_recommendation_evidence_item' => ['table' => 'learner_recommendation_items', 'from' => 'itemId', 'to' => 'id']],
        ],
        'learner_recommendation_feedback' => [
            'columns' => ['id', 'studentId', 'itemId', 'verdict', 'reasonCode', 'safeComment', 'createdAt'],
            'indexes' => ['idx_learner_recommendation_feedback_student_created', 'idx_learner_recommendation_feedback_item'],
            'foreignKeys' => [
                'fk_learner_recommendation_feedback_student' => ['table' => 'student_profiles', 'from' => 'studentId', 'to' => 'id'],
                'fk_learner_recommendation_feedback_item' => ['table' => 'learner_recommendation_items', 'from' => 'itemId', 'to' => 'id'],
            ],
        ],
        'learner_recommendation_audit_events' => [
            'columns' => ['id', 'runId', 'studentId', 'requestId', 'actorType', 'action', 'engineMetadataJson', 'status', 'createdAt'],
            'indexes' => ['idx_learner_recommendation_audit_events_student_created', 'idx_learner_recommendation_audit_events_run_created'],
            'foreignKeys' => [
                'fk_learner_recommendation_audit_events_run' => ['table' => 'learner_recommendation_runs', 'from' => 'runId', 'to' => 'id'],
                'fk_learner_recommendation_audit_events_student' => ['table' => 'student_profiles', 'from' => 'studentId', 'to' => 'id'],
            ],
        ],
    ];
}

function recommendation_schema_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE student_profiles (id CHAR(36) NOT NULL PRIMARY KEY)');
    $pdo->exec('CREATE TABLE activities (id CHAR(36) NOT NULL PRIMARY KEY)');
    $pdo->exec('CREATE TABLE activity_registrations (id CHAR(36) NOT NULL PRIMARY KEY)');
    return $pdo;
}

/** @param array{table:string,from:string,to:string} $foreignKey */
function recommendation_schema_assert_restrict_cascade(PDO $pdo, string $table, array $foreignKey): void
{
    $rows = $pdo->query('PRAGMA foreign_key_list(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (($row['table'] ?? null) === $foreignKey['table']
            && ($row['from'] ?? null) === $foreignKey['from']
            && ($row['to'] ?? null) === $foreignKey['to']) {
            recommendation_schema_assert(($row['on_delete'] ?? null) === 'RESTRICT', "{$table}.{$foreignKey['from']} deletes are restricted");
            recommendation_schema_assert(($row['on_update'] ?? null) === 'CASCADE', "{$table}.{$foreignKey['from']} updates cascade");
            return;
        }
    }

    recommendation_schema_assert(false, "foreign key is available for action check: {$table}.{$foreignKey['from']}");
}

function recommendation_schema_assert_trigger(PDO $pdo, string $name): void
{
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'trigger' AND name = :name");
    $statement->execute(['name' => $name]);
    recommendation_schema_assert($statement->fetchColumn() !== false, "SQLite trigger exists: {$name}");
}

function recommendation_schema_apply_foundation_and_extensions(PDO $pdo, string $migrationDirectory): LearnerForwardMigrationRunner
{
    $inspector = new SchemaInspector($pdo, 'main');
    $runner = new LearnerForwardMigrationRunner($pdo, $migrationDirectory, $inspector);
    recommendation_schema_assert($runner->migrateApproved(['002_create_ai_input_foundation']) === ['002_create_ai_input_foundation'], 'Task 3 foundation applies before Task 8');
    recommendation_schema_assert($runner->migrateApproved(['003_create_ai_input_extensions']) === ['003_create_ai_input_extensions'], 'Task 4 extensions apply before Task 8');
    return $runner;
}

function recommendation_schema_insert_rows(PDO $pdo): void
{
    $pdo->exec("INSERT INTO student_profiles (id) VALUES ('student-000000000000000000000000000001')");
    $pdo->exec("INSERT INTO learner_recommendation_input_snapshots (id, studentId, schemaVersion, contentHash, consentScopesJson, qualityFlagsJson, payloadJson, sourceUpdatedAt) VALUES ('snapshot-000000000000000000000000000001', 'student-000000000000000000000000000001', '1.0', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '[\"activity\",\"assessment\",\"evaluation\",\"skills\"]', '{\"source_counts\":{\"skills\":1}}', '{\"profile\":{\"study_status\":\"active\"}}', '{\"skills\":\"2026-08-16T00:00:00+00:00\"}')");
    $pdo->exec("INSERT INTO learner_recommendation_runs (id, studentId, snapshotId, idempotencyKey, engineType, status, ruleVersion) VALUES ('run-000000000000000000000000000000001', 'student-000000000000000000000000000001', 'snapshot-000000000000000000000000000001', 'idempotency-000000000000000000000000001', 'rule', 'pending', 'learner-rules-1.0.0')");
    $pdo->exec("INSERT INTO learner_recommendation_items (id, runId, itemType, title, summary, priority, confidenceBand, actionJson, lifecycleStatus) VALUES ('item-00000000000000000000000000000001', 'run-000000000000000000000000000000001', 'strength', 'IoT', 'Verified IoT skill.', 80, 'high', '{\"type\":\"view_skill\"}', 'active')");
    $pdo->exec("INSERT INTO learner_recommendation_evidence (id, itemId, sourceType, sourceId, observedAt, contributionLabel, safeValueJson) VALUES ('evidence-000000000000000000000000000001', 'item-00000000000000000000000000000001', 'skill', 'student-skill-000000000000000000000000001', '2026-08-16 00:00:00', 'verified_skill', '{\"verification_status\":\"verified\"}')");
    $pdo->exec("INSERT INTO learner_recommendation_feedback (id, studentId, itemId, verdict, reasonCode, safeComment) VALUES ('feedback-000000000000000000000000000001', 'student-000000000000000000000000000001', 'item-00000000000000000000000000000001', 'helpful', 'relevant', 'Useful next step')");
    $pdo->exec("INSERT INTO learner_recommendation_audit_events (id, runId, studentId, requestId, actorType, action, engineMetadataJson, status) VALUES ('audit-0000000000000000000000000000000001', 'run-000000000000000000000000000000001', 'student-000000000000000000000000000001', 'request-000000000000000000000000000001', 'system', 'run_created', '{\"engine\":\"rule\"}', 'pending')");
}

$repositoryRoot = dirname(__DIR__);
$migrationPath = $repositoryRoot . '/Database/migrations/learner/004_create_recommendation_store.php';
$dcrPath = $repositoryRoot . '/docs/superpowers/database-change-requests/2026-08-16-recommendation-store.md';
recommendation_schema_assert(is_file($dcrPath), 'Task 8 DCR exists before recommendation-store migration source');
recommendation_schema_assert(is_file($migrationPath), 'Task 8 migration source exists after the approved DCR');

$dcr = (string) file_get_contents($dcrPath);
recommendation_schema_assert(str_contains($dcr, 'APPROVAL REQUIRED: do not execute migration 004 against a shared database'), 'DCR keeps the shared-database execution gate visible');
recommendation_schema_assert(preg_match('/```sql\n(.*?)\n```/s', $dcr, $match) === 1, 'DCR contains exact MySQL SQL code fence');
$dcrSql = $match[1];
recommendation_schema_assert(hash('sha256', $dcrSql) === RECOMMENDATION_STORE_DDL_SHA256, 'DCR SQL fingerprint is approved');

$definition = require $migrationPath;
$migration = $definition->migration;
recommendation_schema_assert($definition->version === '004_create_recommendation_store', 'migration definition has approved version');
recommendation_schema_assert($migration->version() === '004_create_recommendation_store', 'migration implementation has approved version');
recommendation_schema_assert(implode("\n\n", $migration->statements('mysql')) === $dcrSql, 'MySQL statements exactly reproduce approved DCR fence');
recommendation_schema_assert(hash('sha256', implode("\n\n", $migration->statements('mysql'))) === RECOMMENDATION_STORE_DDL_SHA256, 'MySQL statement fingerprint is approved');
recommendation_schema_assert(!str_contains(implode("\n\n", $migration->statements('mysql')), 'CREATE TABLE IF NOT EXISTS'), 'recommendation migration refuses pre-existing targets instead of accepting an unchecked shape');
recommendation_schema_assert((new AiScopePolicy())->inspectMigrationText((string) file_get_contents($migrationPath)) === [], 'migration source has no destructive SQL token');

$combinedPdo = recommendation_schema_fixture();
$combinedInspector = new SchemaInspector($combinedPdo, 'main');
$combinedRunner = new LearnerForwardMigrationRunner($combinedPdo, dirname($migrationPath), $combinedInspector);
recommendation_schema_expect_exception(
    static fn (): array => $combinedRunner->migrateApproved(['002_create_ai_input_foundation', '003_create_ai_input_extensions', '004_create_recommendation_store']),
    'Learner migration preflight requires verified Task 3 migration: 002_create_ai_input_foundation'
);
recommendation_schema_assert(!$combinedInspector->hasTable('learner_forward_migrations'), 'combined migration call fails closed before creating a registry');

$pdo = recommendation_schema_fixture();
$inspector = new SchemaInspector($pdo, 'main');
$runner = recommendation_schema_apply_foundation_and_extensions($pdo, dirname($migrationPath));
recommendation_schema_assert($runner->migrateApproved(['004_create_recommendation_store']) === ['004_create_recommendation_store'], 'first approved run applies exactly migration 004');

foreach (recommendation_schema_contract() as $table => $contract) {
    recommendation_schema_assert($inspector->hasTable($table), "recommendation table exists: {$table}");
    foreach ($contract['columns'] as $column) {
        recommendation_schema_assert($inspector->hasColumn($table, $column), "required recommendation column exists: {$table}.{$column}");
    }
    foreach ($contract['indexes'] as $index) {
        recommendation_schema_assert($inspector->hasIndex($table, $index), "named recommendation index exists: {$table}.{$index}");
    }
    foreach ($contract['foreignKeys'] as $name => $foreignKey) {
        recommendation_schema_assert($inspector->hasForeignKey($table, $foreignKey['table'], $foreignKey['from'], $foreignKey['to']), "SQLite foreign key exists: {$name}");
        recommendation_schema_assert_restrict_cascade($pdo, $table, $foreignKey);
    }
}
foreach ([
    'trg_learner_recommendation_feedback_append_only_update',
    'trg_learner_recommendation_feedback_append_only_delete',
    'trg_learner_recommendation_audit_events_append_only_update',
    'trg_learner_recommendation_audit_events_append_only_delete',
] as $trigger) {
    recommendation_schema_assert_trigger($pdo, $trigger);
}

recommendation_schema_insert_rows($pdo);
recommendation_schema_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_recommendation_input_snapshots (id, studentId, schemaVersion, contentHash, consentScopesJson, qualityFlagsJson, payloadJson, sourceUpdatedAt) VALUES ('snapshot-duplicate-000000000000000000000001', 'student-000000000000000000000000000001', '1.0', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '[]', '{}', '{}', '{}')"),
    'input snapshots reject duplicate student content hashes'
);
recommendation_schema_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_recommendation_runs (id, studentId, snapshotId, idempotencyKey, engineType, status, ruleVersion) VALUES ('run-duplicate-00000000000000000000000000001', 'student-000000000000000000000000000001', 'snapshot-000000000000000000000000000001', 'idempotency-000000000000000000000000001', 'rule', 'pending', 'learner-rules-1.0.0')"),
    'runs reject duplicate learner idempotency keys'
);
recommendation_schema_expect_constraint(
    static fn (): int|false => $pdo->exec("UPDATE learner_recommendation_runs SET status = 'completed' WHERE id = 'run-000000000000000000000000000000001'"),
    'completed runs require a completion time'
);
recommendation_schema_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_recommendation_runs (id, studentId, snapshotId, idempotencyKey, engineType, status, provider, modelVersion, promptVersion) VALUES ('run-invalid-rule-0000000000000000000000001', 'student-000000000000000000000000000001', 'snapshot-000000000000000000000000000001', 'idempotency-invalid-rule-00000000000000001', 'rule', 'pending', 'provider', 'model', 'prompt')"),
    'rule runs reject provider and prompt metadata'
);
recommendation_schema_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_recommendation_items (id, runId, itemType, title, summary, priority, confidenceBand, actionJson, lifecycleStatus) VALUES ('item-invalid-000000000000000000000000000001', 'run-000000000000000000000000000000001', 'unknown', 'Invalid', 'Invalid', 101, 'unknown', '{}', 'active')"),
    'items enforce type priority and confidence allowlists'
);
recommendation_schema_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_recommendation_evidence (id, itemId, sourceType, sourceId, observedAt, contributionLabel, safeValueJson) VALUES ('evidence-duplicate-000000000000000000000001', 'item-00000000000000000000000000000001', 'skill', 'student-skill-000000000000000000000000001', '2026-08-16 00:00:00', 'again', '{}')"),
    'evidence rejects duplicate item source references'
);
recommendation_schema_expect_constraint(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_recommendation_feedback (id, studentId, itemId, verdict, reasonCode) VALUES ('feedback-invalid-00000000000000000000000001', 'student-000000000000000000000000000001', 'item-00000000000000000000000000000001', 'unknown', 'invalid')"),
    'feedback verdict is allowlisted'
);
recommendation_schema_expect_constraint(
    static fn (): int|false => $pdo->exec("UPDATE learner_recommendation_feedback SET verdict = 'not_helpful' WHERE id = 'feedback-000000000000000000000000000001'"),
    'feedback is append-only and rejects updates'
);
recommendation_schema_expect_constraint(
    static fn (): int|false => $pdo->exec("DELETE FROM learner_recommendation_feedback WHERE id = 'feedback-000000000000000000000000000001'"),
    'feedback is append-only and rejects deletes'
);
recommendation_schema_expect_constraint(
    static fn (): int|false => $pdo->exec("UPDATE learner_recommendation_audit_events SET status = 'completed' WHERE id = 'audit-0000000000000000000000000000000001'"),
    'audit events are append-only and reject updates'
);
recommendation_schema_expect_constraint(
    static fn (): int|false => $pdo->exec("DELETE FROM learner_recommendation_audit_events WHERE id = 'audit-0000000000000000000000000000000001'"),
    'audit events are append-only and reject deletes'
);
recommendation_schema_assert($runner->migrateApproved(['004_create_recommendation_store']) === [], 'second approved recommendation-store run is a no-op');
recommendation_schema_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_forward_migrations WHERE version = '004_create_recommendation_store'")->fetchColumn() === 1, 'recommendation-store registry has one record');

$missingParentPdo = recommendation_schema_fixture();
$missingParentInspector = new SchemaInspector($missingParentPdo, 'main');
$missingParentDefinition = require $migrationPath;
recommendation_schema_expect_exception(
    static function () use ($missingParentDefinition, $missingParentInspector): void {
        $missingParentDefinition->migration->assertBeforeApply($missingParentInspector);
    },
    'Learner migration preflight requires verified Task 3 migration: 002_create_ai_input_foundation'
);

$checksumMismatchPdo = recommendation_schema_fixture();
$checksumMismatchInspector = new SchemaInspector($checksumMismatchPdo, 'main');
recommendation_schema_apply_foundation_and_extensions($checksumMismatchPdo, dirname($migrationPath));
$checksumMismatchPdo->exec("UPDATE learner_forward_migrations SET checksum = '0000000000000000000000000000000000000000000000000000000000000000' WHERE version = '003_create_ai_input_extensions'");
recommendation_schema_expect_exception(
    static function () use ($migration, $checksumMismatchInspector): void {
        $migration->assertBeforeApply($checksumMismatchInspector);
    },
    'Learner migration preflight requires verified Task 4 migration: 003_create_ai_input_extensions'
);

$targetConflictPdo = recommendation_schema_fixture();
$targetConflictInspector = new SchemaInspector($targetConflictPdo, 'main');
recommendation_schema_apply_foundation_and_extensions($targetConflictPdo, dirname($migrationPath));
$targetConflictPdo->exec('CREATE TABLE learner_recommendation_runs (id CHAR(36) PRIMARY KEY)');
recommendation_schema_expect_exception(
    static function () use ($migration, $targetConflictInspector): void {
        $migration->assertBeforeApply($targetConflictInspector);
    },
    'Learner migration preflight rejected existing canonical recommendation target: learner_recommendation_runs'
);

echo "learner_recommendation_schema_test: OK\n";
