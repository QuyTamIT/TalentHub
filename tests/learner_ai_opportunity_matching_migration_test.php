<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function migration_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "migration_contract_violation={$message}\n");
        exit(1);
    }
}

function migration_contract_expect_error(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (PDOException) {
        return;
    }
    migration_contract_assert(false, $message);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE learner_ai_catalog_items (catalog_id TEXT PRIMARY KEY, item_type VARCHAR(32) NOT NULL, category VARCHAR(64) NOT NULL, title VARCHAR(255) NOT NULL, summary TEXT NOT NULL, publish_status VARCHAR(20) NOT NULL, deadline_at TEXT NULL, eligibility_json TEXT NOT NULL, capacity INTEGER NOT NULL, enrolled_count INTEGER NOT NULL DEFAULT 0, url TEXT NOT NULL, action_json TEXT NOT NULL, school_id TEXT NULL, tenant_id TEXT NULL, updated_at TEXT NOT NULL)");

$pdo->exec(<<<'SQL'
CREATE TABLE learner_recommendation_runs (
  id TEXT PRIMARY KEY, studentId TEXT NOT NULL, snapshotId TEXT NOT NULL, idempotencyKey VARCHAR(100) NOT NULL,
  engineType VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending',
  ruleVersion VARCHAR(100) NULL, provider VARCHAR(100) NULL, modelVersion VARCHAR(100) NULL, promptVersion VARCHAR(100) NULL,
  fallbackReason VARCHAR(100) NULL, safeErrorCode VARCHAR(100) NULL,
  startedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, completedAt TEXT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (engineType IN ('rule','model')),
  CHECK (status IN ('pending','completed','failed','fallback')),
  CHECK ((engineType = 'rule' AND ruleVersion IS NOT NULL AND provider IS NULL AND modelVersion IS NULL AND promptVersion IS NULL) OR (engineType = 'model' AND ruleVersion IS NULL AND provider IS NOT NULL AND modelVersion IS NOT NULL AND promptVersion IS NOT NULL)),
  CHECK ((status = 'pending' AND completedAt IS NULL) OR (status IN ('completed','failed','fallback') AND completedAt IS NOT NULL))
)
SQL);

$pdo->exec(<<<'SQL'
CREATE TABLE learner_recommendation_items (
  id TEXT PRIMARY KEY, runId TEXT NOT NULL, itemType VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL,
  summary VARCHAR(1000) NOT NULL, priority INTEGER NOT NULL, confidenceBand VARCHAR(50) NOT NULL,
  actionJson TEXT NOT NULL, lifecycleStatus VARCHAR(50) NOT NULL DEFAULT 'active', createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (itemType IN ('strength','improvement','development','activity','roadmap')),
  CHECK (priority BETWEEN 1 AND 100),
  CHECK (confidenceBand IN ('low','medium','high')),
  CHECK (json_valid(actionJson)),
  CHECK (lifecycleStatus IN ('active','superseded'))
)
SQL);

$definition = require dirname(__DIR__) . '/Database/migrations/learner/015_extend_learner_opportunity_matching.php';
migration_contract_assert($definition instanceof \TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition, 'migration 015 must return a ForwardMigrationDefinition');
migration_contract_assert($definition->version === '015_extend_learner_opportunity_matching', 'migration 015 version');
migration_contract_assert($definition->migration->version() === '015_extend_learner_opportunity_matching', 'migration 015 inner version');

foreach ($definition->migration->statements('sqlite') as $sql) {
    $pdo->exec($sql);
}

$columns = static function (PDO $pdo, string $table): array {
    return array_column($pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC), 'name');
};

foreach (['provider_name', 'location', 'difficulty', 'required_skills_json', 'learning_outcomes_json', 'education_bands_json'] as $column) {
    migration_contract_assert(in_array($column, $columns($pdo, 'learner_ai_catalog_items'), true), "learner_ai_catalog_items.{$column}");
}
migration_contract_assert(in_array('capability', $columns($pdo, 'learner_recommendation_runs'), true), 'learner_recommendation_runs.capability');
foreach (['catalogId', 'rankPosition', 'structuredScore', 'geminiScore', 'matchScore', 'analysisJson'] as $column) {
    migration_contract_assert(in_array($column, $columns($pdo, 'learner_recommendation_items'), true), "learner_recommendation_items.{$column}");
}

$index = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'index' AND name = 'idx_learner_recommendation_runs_student_capability_created'")->fetchColumn();
migration_contract_assert($index === 'idx_learner_recommendation_runs_student_capability_created', 'idx_learner_recommendation_runs_student_capability_created');

$mysqlStatements = implode("\n", $definition->migration->statements('mysql'));
$sqliteStatements = implode("\n", $definition->migration->statements('sqlite'));
migration_contract_assert(str_contains($mysqlStatements, "required_skills_json LONGTEXT NULL"), 'mysql json columns use LONGTEXT');
migration_contract_assert(str_contains($mysqlStatements, "capability VARCHAR(50) NOT NULL DEFAULT 'recommendation'"), 'mysql runs capability default');
migration_contract_assert(!str_contains($sqliteStatements, 'LONGTEXT'), 'sqlite statements must not use LONGTEXT');

$runId = 'run-opportunity-match-0001';
$pdo->prepare("INSERT INTO learner_recommendation_runs (id, studentId, snapshotId, idempotencyKey, engineType, status, provider, modelVersion, promptVersion, capability, startedAt, completedAt, createdAt) VALUES (:id, 'student-1', 'snapshot-1', 'idempotency-0001-key', 'model', 'completed', '9router_gemini', 'gemini-3-flash', 'learner-opportunity-match-1.0.0', 'opportunity_match', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)")
    ->execute(['id' => $runId]);

$pdo->prepare("INSERT INTO learner_recommendation_items (id, runId, itemType, title, summary, priority, confidenceBand, actionJson, lifecycleStatus, catalogId, rankPosition, structuredScore, geminiScore, matchScore, analysisJson) VALUES (:id, :runId, 'activity', 'Smart Campus IoT', 'Grounded match summary.', 1, 'high', :actionJson, 'active', 'internship-1', 1, 86, 95, 89, :analysisJson)")
    ->execute([
        'id' => 'item-opportunity-match-0001',
        'runId' => $runId,
        'actionJson' => json_encode(['type' => 'open_catalog_item', 'catalog_id' => 'internship-1'], JSON_THROW_ON_ERROR),
        'analysisJson' => json_encode(['why_fit' => 'Python va IoT phu hop voi ho so cua ban.'], JSON_THROW_ON_ERROR),
    ]);

$row = $pdo->query("SELECT itemType, catalogId, rankPosition, structuredScore, geminiScore, matchScore, analysisJson FROM learner_recommendation_items WHERE id = 'item-opportunity-match-0001'")->fetch(PDO::FETCH_ASSOC);
migration_contract_assert(is_array($row) && $row['itemType'] === 'activity', 'opportunity-match item persists with itemType activity');
migration_contract_assert($row['catalogId'] === 'internship-1' && (int) $row['rankPosition'] === 1, 'match item catalog/rank round-trip');
migration_contract_assert((int) $row['structuredScore'] === 86 && (int) $row['geminiScore'] === 95 && (int) $row['matchScore'] === 89, 'match component/final scores respect the 70/30 formula and round-trip');
migration_contract_assert($row['analysisJson'] !== null && json_decode((string) $row['analysisJson'], true) !== null, 'analysisJson round-trips as JSON');

$capability = $pdo->query("SELECT capability FROM learner_recommendation_runs WHERE id = '{$runId}'")->fetchColumn();
migration_contract_assert($capability === 'opportunity_match', 'run capability round-trip');

migration_contract_expect_error(
    static fn (): bool => (bool) $pdo->exec("INSERT INTO learner_recommendation_items (id, runId, itemType, title, summary, priority, confidenceBand, actionJson) VALUES ('item-bad-type', '{$runId}', 'opportunity_match', 'x', 'y', 1, 'high', '{}')"),
    'chk_learner_recommendation_items_type must keep rejecting non-canonical item types'
);

migration_contract_expect_error(
    static fn (): bool => (bool) $pdo->exec("INSERT INTO learner_recommendation_items (id, runId, itemType, title, summary, priority, confidenceBand, actionJson) VALUES ('item-bad-json', '{$runId}', 'activity', 'x', 'y', 1, 'high', 'not-json')"),
    'chk_learner_recommendation_items_action_json must keep enforcing JSON validity'
);

echo "learner_ai_opportunity_matching_migration_test: OK\n";
