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

use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Quality\DataQualityGate;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;
use TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder;
use TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseAssessmentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseConsentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;
use TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseStudentProfileSource;
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

function v2_snapshot_builder(PDO $pdo): RecommendationSnapshotBuilder
{
    return new RecommendationSnapshotBuilder(
        new DatabaseStudentProfileSource($pdo),
        new DatabaseSkillSource($pdo),
        new DatabaseAssessmentSource($pdo),
        new DatabaseActivityExperienceSource($pdo),
        new DatabasePublishedEvaluationSource($pdo),
        new DatabaseOpportunitySource($pdo),
    );
}

/**
 * @return array{
 *     engineType: string,
 *     fallbackReason: ?string,
 *     items: list<array{
 *         itemType: string,
 *         title: string,
 *         priority: int,
 *         actionJson: string,
 *         evidence: list<array{sourceType: string, sourceId: string}>
 *     }>
 * }
 */
function v2_canonical_signature(RecommendationResult $result): array
{
    $items = [];
    foreach ($result->items() as $item) {
        $evidence = [];
        foreach ($item->evidence() as $ev) {
            $evidence[] = [
                'sourceType' => $ev->sourceType(),
                'sourceId' => $ev->sourceId(),
            ];
        }
        $items[] = [
            'itemType' => $item->itemType(),
            'title' => $item->title(),
            'priority' => $item->priority(),
            'actionJson' => $item->actionJson(),
            'evidence' => $evidence,
        ];
    }

    return [
        'engineType' => $result->engineType(),
        'fallbackReason' => $result->fallbackReason(),
        'items' => $items,
    ];
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

// --- PURE TEST C: Canonical Signature & Determinism Helper ---
$mockItem = new RecommendationItem(
    'strength',
    'Test Title',
    'Test Summary',
    20,
    'high',
    ['type' => 'test_action', 'key' => 'val'],
    [new RecommendationEvidence('skill', '00000000-0000-4000-8000-000000200001', '2026-08-16T00:00:00.000000+00:00', 'test', ['k' => 'v'])],
);
$mockResult = new RecommendationResult('rule', 'learner-rules-1.0.0', null, null, null, null, [$mockItem]);
$mockSig = v2_canonical_signature($mockResult);
v2_mysql_assert($mockSig['engineType'] === 'rule', 'signature captures engineType');
v2_mysql_assert($mockSig['fallbackReason'] === null, 'signature captures fallbackReason');
v2_mysql_assert(count($mockSig['items']) === 1, 'signature captures items');
v2_mysql_assert($mockSig['items'][0]['itemType'] === 'strength', 'signature item captures itemType');
v2_mysql_assert($mockSig['items'][0]['title'] === 'Test Title', 'signature item captures title');
v2_mysql_assert($mockSig['items'][0]['priority'] === 20, 'signature item captures priority');
v2_mysql_assert($mockSig['items'][0]['evidence'][0]['sourceType'] === 'skill', 'signature item captures evidence sourceType');
v2_mysql_assert($mockSig['items'][0]['evidence'][0]['sourceId'] === '00000000-0000-4000-8000-000000200001', 'signature item captures evidence sourceId');

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

// ============================================================================
// TASK 4: REAL PIPELINE VERIFICATION (24 LEARNERS)
// ============================================================================

// 12. Pipeline Factory & Fixed Verification Clock
$fixedClock = new DateTimeImmutable('2026-08-17T00:00:00.000000+00:00', new DateTimeZone('UTC'));
$consent = new ConsentPolicy(new DatabaseConsentSource($pdo));
$builder = v2_snapshot_builder($pdo);
$quality = new DataQualityGate($fixedClock);
$engine = new RuleRecommendationEngine();

$participants = LearnerAiSyntheticDatasetV2::participants();
v2_mysql_assert(count($participants) === 24, 'Task 4 evaluates exactly 24 participants');

// Preload student-scoped ownership maps for evidence boundary assertions
$skillOwnershipStmt = $pdo->prepare('SELECT id, studentId FROM student_skills WHERE id LIKE :prefix');
$skillOwnershipStmt->execute(['prefix' => $reservedPrefix . '%']);
$skillOwnership = $skillOwnershipStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$assessmentOwnershipStmt = $pdo->prepare('SELECT tr.id, ta.studentId FROM test_results tr JOIN test_attempts ta ON ta.id = tr.attemptId WHERE tr.id LIKE :prefix');
$assessmentOwnershipStmt->execute(['prefix' => $reservedPrefix . '%']);
$assessmentOwnership = $assessmentOwnershipStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$activityOwnershipStmt = $pdo->prepare('SELECT id, studentId FROM experience_logs WHERE id LIKE :prefix');
$activityOwnershipStmt->execute(['prefix' => $reservedPrefix . '%']);
$activityOwnership = $activityOwnershipStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$evaluationOwnershipStmt = $pdo->prepare('SELECT id, studentId FROM assessments WHERE id LIKE :prefix');
$evaluationOwnershipStmt->execute(['prefix' => $reservedPrefix . '%']);
$evaluationOwnership = $evaluationOwnershipStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 13. Data Quality Gate & Evidence Ownership Assertions across all 24 learners
$stateTotals = ['ready' => 0, 'insufficient_data' => 0, 'consent_required' => 0];

foreach ($participants as $p) {
    $studentId = $p['student_id'];
    $seq = $p['sequence'];
    $allowedScopes = $consent->allowedScopes($studentId);
    $snapshot = $builder->build($studentId, $allowedScopes);
    $qualityResult = $quality->evaluate($snapshot);
    $state = $qualityResult->state();

    v2_mysql_assert(isset($stateTotals[$state]), "Known quality state {$state} for learner {$seq}");
    $stateTotals[$state]++;

    // Assert source_counts.opportunities === 0 for every learner
    v2_mysql_assert(
        ($snapshot->qualityFlags()['source_counts']['opportunities'] ?? null) === 0,
        "Learner {$seq} source_counts.opportunities must be 0"
    );

    // Verify exact expected state and missing fields per scenario
    if ($seq === 104) {
        v2_mysql_assert($state === 'insufficient_data', 'learner 104 state is insufficient_data');
        v2_mysql_assert($qualityResult->missingCategories() === ['skills'], 'learner 104 missing categories is exactly [skills]');
    } elseif ($seq === 108) {
        v2_mysql_assert($state === 'insufficient_data', 'learner 108 state is insufficient_data');
        v2_mysql_assert($qualityResult->missingCategories() === ['experience'], 'learner 108 missing categories is exactly [experience]');
    } elseif ($seq === 112) {
        v2_mysql_assert($state === 'insufficient_data', 'learner 112 state is insufficient_data');
        v2_mysql_assert($qualityResult->missingCategories() === ['assessment'], 'learner 112 missing categories is exactly [assessment]');
    } elseif ($seq === 116) {
        v2_mysql_assert($state === 'insufficient_data', 'learner 116 state is insufficient_data');
        v2_mysql_assert($qualityResult->missingCategories() === ['evaluations'], 'learner 116 missing categories is exactly [evaluations]');
    } elseif ($seq === 120) {
        v2_mysql_assert($state === 'consent_required', 'learner 120 state is consent_required');
        v2_mysql_assert($qualityResult->missingConsentScopes() === ['evaluation'], 'learner 120 missing consent is exactly [evaluation]');
    } elseif ($seq === 124) {
        v2_mysql_assert($state === 'consent_required', 'learner 124 state is consent_required');
        v2_mysql_assert($qualityResult->missingConsentScopes() === ['activity'], 'learner 124 missing consent is exactly [activity]');
    } else {
        v2_mysql_assert($state === 'ready', "learner {$seq} state must be ready");
        v2_mysql_assert($qualityResult->missingCategories() === [], "learner {$seq} has no missing categories");
        v2_mysql_assert($qualityResult->missingConsentScopes() === [], "learner {$seq} has no missing consent scopes");
    }

    // Verify that every evidence reference in the snapshot belongs strictly to this student
    foreach ($snapshot->evidenceReferences() as $ref) {
        $srcType = $ref['source_type'];
        $srcId = $ref['source_id'];
        $ownerStudentId = match ($srcType) {
            'skill' => $skillOwnership[$srcId] ?? null,
            'assessment' => $assessmentOwnership[$srcId] ?? null,
            'activity_experience' => $activityOwnership[$srcId] ?? null,
            'evaluation' => $evaluationOwnership[$srcId] ?? null,
            'opportunity' => null,
            default => null,
        };
        if ($srcType !== 'opportunity') {
            v2_mysql_assert(
                $ownerStudentId === $studentId,
                "Snapshot evidence {$srcType}:{$srcId} must belong to student {$studentId}, got {$ownerStudentId}"
            );
        }
    }
}

v2_mysql_assert(
    $stateTotals === ['ready' => 18, 'insufficient_data' => 4, 'consent_required' => 2],
    'Task 4 quality state totals must be exactly ready=18, insufficient_data=4, consent_required=2'
);

// 14. Deterministic Rule Recommendations for Ready Learners
$seenItemTypes = [];
$readyCount = 0;
$totalItemCount = 0;
$totalEvidenceCount = 0;
$itemTypeCounts = [
    'strength' => 0,
    'activity' => 0,
    'roadmap' => 0,
];

foreach ($participants as $p) {
    if ($p['expected_state'] !== 'ready') {
        continue;
    }
    $readyCount++;
    $studentId = $p['student_id'];
    $seq = $p['sequence'];
    $allowedScopes = $consent->allowedScopes($studentId);
    $snapshot = $builder->build($studentId, $allowedScopes);

    $snapshotEvidenceIds = [];
    foreach ($snapshot->evidenceReferences() as $ref) {
        $snapshotEvidenceIds[$ref['source_type'] . "\0" . $ref['source_id']] = true;
    }

    $context1 = new RecommendationContext(
        $allowedScopes,
        '00000000-0000-4000-8000-' . str_pad((string) (910000 + $seq), 12, '0', STR_PAD_LEFT),
        'v2-gen-1-' . $seq,
        $studentId,
    );
    $context2 = new RecommendationContext(
        $allowedScopes,
        '00000000-0000-4000-8000-' . str_pad((string) (910000 + $seq), 12, '0', STR_PAD_LEFT),
        'v2-gen-2-' . $seq,
        $studentId,
    );

    $res1 = $engine->generate($snapshot, $context1);
    $res2 = $engine->generate($snapshot, $context2);

    $sig1 = v2_canonical_signature($res1);
    $sig2 = v2_canonical_signature($res2);
    v2_mysql_assert($sig1 === $sig2, "Learner {$seq} recommendation generation must be strictly deterministic");
    v2_mysql_assert($res1->items() !== [], "Ready learner {$seq} must produce at least one recommendation item");
    v2_mysql_assert($res1->fallbackReason() === null, "Ready learner {$seq} must have null fallbackReason");

    // Only count the first generate result ($res1)
    foreach ($res1->items() as $item) {
        $totalItemCount++;
        $type = $item->itemType();
        $seenItemTypes[$type] = true;
        v2_mysql_assert(isset($itemTypeCounts[$type]), "Known recommendation item type {$type} for learner {$seq}");
        $itemTypeCounts[$type]++;

        $evidenceList = $item->evidence();
        v2_mysql_assert($evidenceList !== [], "Item in learner {$seq} must have evidence");
        $totalEvidenceCount += count($evidenceList);

        foreach ($evidenceList as $ev) {
            $evKey = $ev->sourceType() . "\0" . $ev->sourceId();
            v2_mysql_assert(
                isset($snapshotEvidenceIds[$evKey]),
                "Item evidence {$evKey} must belong to learner {$seq} snapshot"
            );
        }
    }

    if ($seq === 101) {
        $itemTypes101 = array_map(static fn (RecommendationItem $item): string => $item->itemType(), $res1->items());
        v2_mysql_assert(
            in_array('roadmap', $itemTypes101, true),
            'Learner 101 must generate a roadmap recommendation due to two low presentation scores'
        );
    }
}

// Exact deterministic metric assertions
v2_mysql_assert($readyCount === 18, 'Ready learner count must be exactly 18');
v2_mysql_assert($totalItemCount === 34, 'Total recommendation item count across ready learners must be exactly 34');
v2_mysql_assert($totalEvidenceCount === 81, 'Total evidence count across ready learners must be exactly 81');
v2_mysql_assert($itemTypeCounts['strength'] === 20, 'Strength recommendation count must be exactly 20');
v2_mysql_assert($itemTypeCounts['activity'] === 13, 'Activity recommendation count must be exactly 13');
v2_mysql_assert($itemTypeCounts['roadmap'] === 1, 'Roadmap recommendation count must be exactly 1');
v2_mysql_assert($itemTypeCounts['strength'] + $itemTypeCounts['activity'] + $itemTypeCounts['roadmap'] === $totalItemCount, 'Sum of item type counts must equal total item count');

// Whole dataset produces at least one strength, activity, and roadmap item
v2_mysql_assert(isset($seenItemTypes['strength']), 'Dataset must produce at least one strength recommendation item');
v2_mysql_assert(isset($seenItemTypes['activity']), 'Dataset must produce at least one activity recommendation item');
v2_mysql_assert(isset($seenItemTypes['roadmap']), 'Dataset must produce at least one roadmap recommendation item');

// 15. Read-only opportunity check: internship_posts table must NOT exist
$internshipTables = $pdo->query("SHOW TABLES LIKE 'internship_posts'")->fetchAll();
v2_mysql_assert(count($internshipTables) === 0, 'internship_posts table must not exist in disposable schema');

// 16. Recommendation tables isolation (verification pipeline must be purely in-memory)
foreach ($recommendationTables as $recTable) {
    $stmt = $pdo->query('SELECT COUNT(*) FROM ' . $recTable);
    $finalCount = (int) $stmt->fetchColumn();
    v2_mysql_assert(
        $finalCount === $baselineRecommendationCounts[$recTable],
        'Recommendation table count for ' . $recTable . ' must remain unchanged after pipeline verification'
    );
}

// 17. Sanitized summary output without secrets or PII
echo sprintf(
    "V2_TASK4_METRICS ready=%d items=%d evidence=%d strength=%d activity=%d roadmap=%d\n",
    $readyCount,
    $totalItemCount,
    $totalEvidenceCount,
    $itemTypeCounts['strength'],
    $itemTypeCounts['activity'],
    $itemTypeCounts['roadmap']
);

echo "learner_ai_synthetic_dataset_v2_mysql_test: OK\n";
