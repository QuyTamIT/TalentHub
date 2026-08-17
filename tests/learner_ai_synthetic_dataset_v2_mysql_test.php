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

// ============================================================================
// PURE IN-MEMORY TESTS (No MySQL Connection Required)
// ============================================================================

// --- PURE TEST A: External Transaction Guard ---
$sqlitePdo = new PDO('sqlite::memory:');
$sqlitePdo->beginTransaction();
$externalTxSeeder = new LearnerAiSyntheticDatasetV2Seeder(
    $sqlitePdo,
    'talenthub_ai_backup_verify_004_20260816',
    LearnerAiSyntheticDatasetV2::contentHash()
);
v2_mysql_expect_exception(
    static fn () => $externalTxSeeder->seed(),
    'externally owned transaction'
);
$sqlitePdo->rollBack();

// --- PURE TEST B: DCR Parsing & Validation Logic ---
$approvedSchema = 'talenthub_ai_backup_verify_004_20260816';
$approvedFingerprint = 'c6e417b69a06b9bf93a5762b03850b90c79fd88b716a1b3de48cb5097cf75b6f';

$baseApprovedDcr = <<<MD
# Database Change Request: Synthetic Learner AI Dataset V2

**Status:** APPROVED — DISPOSABLE SCHEMA ONLY

## Scope, safety boundary, and ownership

- **Authorized Target Schema:** `talenthub_ai_backup_verify_004_20260816`
- **Shared / Production Schemas:** Strictly forbidden. `talenthub_local` is never approved.
- **Dataset Fingerprint (SHA-256):** `c6e417b69a06b9bf93a5762b03850b90c79fd88b716a1b3de48cb5097cf75b6f`
- **Total Declared V2 Rows:** `1116`

## Approval & Execution Log

- **Approval Status:** APPROVED — DISPOSABLE SCHEMA ONLY
- **Approved By:** Lead Architect
- **Approved At:** 2026-08-17 12:00:00 UTC
- **Execution Status:** NOT EXECUTED
MD;

// 1. Proposed document is rejected
$proposedDcr = str_replace(
    'APPROVED — DISPOSABLE SCHEMA ONLY',
    'PROPOSED — DISPOSABLE SCHEMA ONLY',
    $baseApprovedDcr
);
v2_mysql_expect_exception(
    static fn () => LearnerAiSyntheticDatasetV2Seeder::validateDcr($proposedDcr, $approvedSchema, $approvedFingerprint),
    'approved'
);

// 2. Approved By: Pending is rejected
$pendingApproverDcr = str_replace('Approved By:** Lead Architect', 'Approved By:** Pending user explicit approval gate', $baseApprovedDcr);
v2_mysql_expect_exception(
    static fn () => LearnerAiSyntheticDatasetV2Seeder::validateDcr($pendingApproverDcr, $approvedSchema, $approvedFingerprint),
    'Approved By'
);

// 3. Approved At: Pending is rejected
$pendingDateDcr = str_replace('Approved At:** 2026-08-17 12:00:00 UTC', 'Approved At:** Pending', $baseApprovedDcr);
v2_mysql_expect_exception(
    static fn () => LearnerAiSyntheticDatasetV2Seeder::validateDcr($pendingDateDcr, $approvedSchema, $approvedFingerprint),
    'Approved At'
);

// 4. Schema mismatch is rejected
$badSchemaDcr = str_replace('talenthub_ai_backup_verify_004_20260816', 'talenthub_ai_backup_verify_999_20260817', $baseApprovedDcr);
v2_mysql_expect_exception(
    static fn () => LearnerAiSyntheticDatasetV2Seeder::validateDcr($badSchemaDcr, $approvedSchema, $approvedFingerprint),
    'schema'
);

// 4b. talenthub_local in DCR is rejected
$localSchemaDcr = str_replace('talenthub_ai_backup_verify_004_20260816', 'talenthub_local', $baseApprovedDcr);
v2_mysql_expect_exception(
    static fn () => LearnerAiSyntheticDatasetV2Seeder::validateDcr($localSchemaDcr, 'talenthub_local', $approvedFingerprint),
    'disposable'
);

// 5. Fingerprint mismatch is rejected
$badFingerprintDcr = str_replace(
    'c6e417b69a06b9bf93a5762b03850b90c79fd88b716a1b3de48cb5097cf75b6f',
    '0000000000000000000000000000000000000000000000000000000000000000',
    $baseApprovedDcr
);
v2_mysql_expect_exception(
    static fn () => LearnerAiSyntheticDatasetV2Seeder::validateDcr($badFingerprintDcr, $approvedSchema, $approvedFingerprint),
    'fingerprint'
);

// 6. Row count mismatch is rejected
$badRowCountDcr = str_replace('`1116`', '`1000`', $baseApprovedDcr);
v2_mysql_expect_exception(
    static fn () => LearnerAiSyntheticDatasetV2Seeder::validateDcr($badRowCountDcr, $approvedSchema, $approvedFingerprint),
    'row count'
);

// 7. Valid approved document with NOT EXECUTED is accepted
$parsedApproved = LearnerAiSyntheticDatasetV2Seeder::validateDcr($baseApprovedDcr, $approvedSchema, $approvedFingerprint);
v2_mysql_assert($parsedApproved['execution_status'] === 'not_executed', 'approved DCR parses not_executed status');
v2_mysql_assert($parsedApproved['target_schema'] === $approvedSchema, 'approved DCR matches schema');
v2_mysql_assert($parsedApproved['fingerprint'] === $approvedFingerprint, 'approved DCR matches fingerprint');
v2_mysql_assert($parsedApproved['total_rows'] === 1116, 'approved DCR matches row count');

// 8. Valid approved document with EXECUTED is accepted
$executedDcr = str_replace('Execution Status:** NOT EXECUTED', 'Execution Status:** EXECUTED (2026-08-17)', $baseApprovedDcr);
$parsedExecuted = LearnerAiSyntheticDatasetV2Seeder::validateDcr($executedDcr, $approvedSchema, $approvedFingerprint);
v2_mysql_assert($parsedExecuted['execution_status'] === 'executed', 'approved DCR parses executed status');

echo "learner_ai_synthetic_dataset_v2_mysql_test: PURE IN-MEMORY TESTS OK\n";

// ============================================================================
// MYSQL INTEGRATION GATE (Fails closed when APP_ENV != test)
// ============================================================================

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

// 2. Require explicit DCR approval before reading DB config or opening PDO
$dcrContent = (string) file_get_contents($root . '/' . LearnerAiSyntheticDatasetV2Seeder::DCR_RELATIVE_PATH);
$dcr = LearnerAiSyntheticDatasetV2Seeder::validateDcr(
    $dcrContent,
    $schema,
    LearnerAiSyntheticDatasetV2::contentHash()
);

// 3. Load external config only after approval
$configRoot = (string) getenv('TALENTHUB_DB_CONFIG_ROOT');
v2_mysql_assert(
    $configRoot !== '' && is_file($configRoot . '/bin/bootstrap.php') && is_file($configRoot . '/config/database.php'),
    'V2 MySQL test requires an external local configuration root'
);

// 4. Open PDO connection to disposable schema only
require_once $configRoot . '/bin/bootstrap.php';
$config = require $configRoot . '/config/database.php';
$config['database'] = $schema;
$pdo = (new TalentHub\Database\Connection($config))->connect();
v2_mysql_assert(
    (string) $pdo->query('SELECT DATABASE()')->fetchColumn() === $schema,
    'V2 MySQL test connection is pinned to the requested disposable schema'
);

// 5. Capture baseline counts:
// 5a. Non-reserved counts on every touched table
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

// 5b. Recommendation table baseline total counts (before any seed)
$recommendationTables = [
    'learner_recommendation_input_snapshots',
    'learner_recommendation_runs',
    'learner_recommendation_snapshot_evidence',
    'learner_recommendation_items',
    'learner_recommendation_evidence',
    'learner_recommendation_feedback',
    'learner_recommendation_audit_events',
];
$baselineRecommendationCounts = [];
foreach ($recommendationTables as $recTable) {
    $stmt = $pdo->query('SELECT COUNT(*) FROM ' . $recTable);
    $baselineRecommendationCounts[$recTable] = (int) $stmt->fetchColumn();
}

// 5. Assert production/local schema is rejected before any write
v2_mysql_expect_exception(static function () use ($pdo): void {
    $forbiddenSeeder = new LearnerAiSyntheticDatasetV2Seeder(
        $pdo,
        'talenthub_local',
        LearnerAiSyntheticDatasetV2::contentHash()
    );
    $forbiddenSeeder->seed();
}, 'disposable');

// 6. Assert mismatched content hash is rejected
v2_mysql_expect_exception(static function () use ($pdo, $schema): void {
    $badHashSeeder = new LearnerAiSyntheticDatasetV2Seeder(
        $pdo,
        $schema,
        '0000000000000000000000000000000000000000000000000000000000000000'
    );
    $badHashSeeder->seed();
});

// 7. Run V1 seeder first and assert inserted = 0 (prerequisite V1 data is present)
$v1Seeder = new LearnerAiPilotSeeder($pdo, $schema);
$v1Result = $v1Seeder->seed();
v2_mysql_assert($v1Result['inserted'] === 0, 'V1 prerequisite data must already be present (inserted=0)');

// 8. V2 First Call (Exact outcome based on DCR execution status)
$seeder = new LearnerAiSyntheticDatasetV2Seeder(
    $pdo,
    $schema,
    LearnerAiSyntheticDatasetV2::contentHash()
);

$first = $seeder->seed();
if ($dcr['execution_status'] === 'not_executed') {
    v2_mysql_assert($first === [
        'declared' => 1116,
        'inserted' => 1116,
        'existing' => 0,
        'students' => 24,
        'complete' => 18,
        'edge' => 6,
    ], 'first call under unexecuted DCR must report inserted=1116 and existing=0');
} else {
    v2_mysql_assert($first === [
        'declared' => 1116,
        'inserted' => 0,
        'existing' => 1116,
        'students' => 24,
        'complete' => 18,
        'edge' => 6,
    ], 'first call under executed DCR must report inserted=0 and existing=1116');
}

// 9. V2 Second Call (Idempotency)
$second = $seeder->seed();
v2_mysql_assert($second === [
    'declared' => 1116,
    'inserted' => 0,
    'existing' => 1116,
    'students' => 24,
    'complete' => 18,
    'edge' => 6,
], 'second V2 seed is an idempotent no-op');

// 10. Non-reserved row isolation
foreach ($touchedTables as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE id NOT LIKE :reserved_prefix');
    $stmt->execute(['reserved_prefix' => $reservedPrefix . '%']);
    $afterCount = (int) $stmt->fetchColumn();
    v2_mysql_assert(
        $afterCount === $baselineNonReservedCounts[$table],
        'Non-reserved count for table ' . $table . ' must remain unchanged'
    );
}

// 11. Recommendation tables total count isolation (before/after unchanged)
foreach ($recommendationTables as $recTable) {
    $stmt = $pdo->query('SELECT COUNT(*) FROM ' . $recTable);
    $afterCount = (int) $stmt->fetchColumn();
    v2_mysql_assert(
        $afterCount === $baselineRecommendationCounts[$recTable],
        'Recommendation table count for ' . $recTable . ' must remain unchanged'
    );
}

echo "learner_ai_synthetic_dataset_v2_mysql_test: OK\n";
