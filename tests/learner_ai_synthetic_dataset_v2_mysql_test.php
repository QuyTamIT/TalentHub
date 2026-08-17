<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/learner/data/bootstrap.php';
require_once $root . '/Database/seeds/learner/Staging/LearnerAiPilotSeeder.php';
require_once $root . '/Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2.php';

$seederFile = $root . '/Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2Seeder.php';
if (!is_file($seederFile)) {
    throw new RuntimeException('Assertion failed: V2 seeder exists');
}
require_once $seederFile;

require_once $root . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Seeds\Staging\LearnerAiPilotSeeder;
use TalentHub\Learner\Seeds\Staging\LearnerAiSyntheticDatasetV2;
use TalentHub\Learner\Seeds\Staging\LearnerAiSyntheticDatasetV2Seeder;

function v2_mysql_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function v2_mysql_expect_exception(callable $operation, string $expectedMessagePart = ''): void
{
    try {
        $operation();
    } catch (\Throwable $e) {
        if ($expectedMessagePart !== '' && !str_contains($e->getMessage(), $expectedMessagePart)) {
            throw new RuntimeException(
                "Expected exception containing '{$expectedMessagePart}', got: '{$e->getMessage()}'"
            );
        }
        return;
    }
    throw new RuntimeException('Expected exception was not thrown.');
}

// 1. Safe Environment Guards (fail closed before any PDO connection or DB access)
$appEnv = getenv('APP_ENV');
v2_mysql_assert($appEnv === 'test', 'V2 MySQL test requires APP_ENV=test');

$schema = (string) getenv('LEARNER_MYSQL_TEST_SCHEMA');
v2_mysql_assert(
    preg_match('/^talenthub_ai_backup_verify_[A-Za-z0-9_]+$/', $schema) === 1,
    'V2 MySQL test requires an explicitly named disposable verification schema'
);
v2_mysql_assert(
    $schema === 'talenthub_ai_backup_verify_004_20260816',
    'V2 MySQL test requires exact schema talenthub_ai_backup_verify_004_20260816'
);

$configRoot = (string) getenv('TALENTHUB_DB_CONFIG_ROOT');
v2_mysql_assert(
    $configRoot !== '' && is_file($configRoot . '/bin/bootstrap.php') && is_file($configRoot . '/config/database.php'),
    'V2 MySQL test requires an external local configuration root'
);

// 2. Open PDO connection to disposable schema only
require_once $configRoot . '/bin/bootstrap.php';
$config = require $configRoot . '/config/database.php';
$config['database'] = $schema;
$pdo = (new TalentHub\Database\Connection($config))->connect();
v2_mysql_assert(
    (string) $pdo->query('SELECT DATABASE()')->fetchColumn() === $schema,
    'V2 MySQL test connection is pinned to the requested disposable schema'
);

// 3. Capture baseline non-reserved counts on every touched table
$reservedPrefix = LearnerAiSyntheticDatasetV2::RESERVED_PREFIX;
$touchedTables = LearnerAiSyntheticDatasetV2::touchedTables();
$baselineNonReservedCounts = [];

foreach ($touchedTables as $table) {
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
        throw new RuntimeException('Unsafe table name: ' . $table);
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE id NOT LIKE :reserved_prefix');
    $stmt->execute(['reserved_prefix' => $reservedPrefix . '%']);
    $baselineNonReservedCounts[$table] = (int) $stmt->fetchColumn();
}

// 4. Assert production/local schema is rejected before any write
v2_mysql_expect_exception(static function () use ($pdo): void {
    $forbiddenSeeder = new LearnerAiSyntheticDatasetV2Seeder(
        $pdo,
        'talenthub_local',
        LearnerAiSyntheticDatasetV2::contentHash()
    );
    $forbiddenSeeder->seed();
}, 'disposable');

// 5. Assert mismatched content hash is rejected
v2_mysql_expect_exception(static function () use ($pdo, $schema): void {
    $badHashSeeder = new LearnerAiSyntheticDatasetV2Seeder(
        $pdo,
        $schema,
        '0000000000000000000000000000000000000000000000000000000000000000'
    );
    $badHashSeeder->seed();
});

// 6. Run V1 seeder first and assert inserted = 0 (prerequisite V1 data is present)
$v1Seeder = new LearnerAiPilotSeeder($pdo, $schema);
$v1Result = $v1Seeder->seed();
v2_mysql_assert($v1Result['inserted'] === 0, 'V1 prerequisite data must already be present (inserted=0)');

// 7. V2 First Call
$seeder = new LearnerAiSyntheticDatasetV2Seeder(
    $pdo,
    $schema,
    LearnerAiSyntheticDatasetV2::contentHash()
);

$first = $seeder->seed();
v2_mysql_assert($first['declared'] === 1116, 'V2 declares the approved row count');
v2_mysql_assert($first['inserted'] + $first['existing'] === 1116, 'first call inserts or verifies every V2 row');
v2_mysql_assert(in_array($first['existing'], [0, 1116], true), 'transactional V2 state is either absent or complete');
v2_mysql_assert(
    $first['students'] === 24 && $first['complete'] === 18 && $first['edge'] === 6,
    'participant totals are exact'
);

// 8. V2 Second Call (Idempotency)
$second = $seeder->seed();
v2_mysql_assert($second === [
    'declared' => 1116,
    'inserted' => 0,
    'existing' => 1116,
    'students' => 24,
    'complete' => 18,
    'edge' => 6,
], 'second V2 seed is an idempotent no-op');

// 9. Non-reserved row isolation
foreach ($touchedTables as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE id NOT LIKE :reserved_prefix');
    $stmt->execute(['reserved_prefix' => $reservedPrefix . '%']);
    $afterCount = (int) $stmt->fetchColumn();
    v2_mysql_assert(
        $afterCount === $baselineNonReservedCounts[$table],
        'Non-reserved count for table ' . $table . ' must remain unchanged'
    );
}

// 10. Assert NO recommendation snapshots/runs/items/evidence/feedback/audit are persisted by seeder
$recommendationTables = [
    'learner_recommendation_input_snapshots',
    'learner_recommendation_runs',
    'learner_recommendation_snapshot_evidence',
    'learner_recommendation_items',
    'learner_recommendation_evidence',
    'learner_recommendation_feedback',
    'learner_recommendation_audit_events',
];

foreach ($recommendationTables as $recTable) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $recTable . ' WHERE id LIKE :reserved_prefix');
    $stmt->execute(['reserved_prefix' => $reservedPrefix . '%']);
    $recCount = (int) $stmt->fetchColumn();
    v2_mysql_assert($recCount === 0, 'Seeder must not persist recommendation records in ' . $recTable);
}

echo "learner_ai_synthetic_dataset_v2_mysql_test: OK\n";
