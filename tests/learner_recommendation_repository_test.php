<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
use TalentHub\Learner\Ai\Persistence\RecommendationRepository;
use TalentHub\Learner\Ai\Quality\DataQualityResult;
use TalentHub\Learner\Ai\Service\RecommendationResponseMapper;
use TalentHub\Learner\Ai\Service\RecommendationService;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;

require_once dirname(__DIR__) . '/app/learner/ai/Domain/RecommendationInput.php';
require_once dirname(__DIR__) . '/app/learner/ai/Domain/RecommendationContext.php';
require_once dirname(__DIR__) . '/app/learner/ai/Domain/RecommendationEvidence.php';
require_once dirname(__DIR__) . '/app/learner/ai/Domain/RecommendationItem.php';
require_once dirname(__DIR__) . '/app/learner/ai/Domain/RecommendationResult.php';
require_once dirname(__DIR__) . '/app/learner/ai/Persistence/RecommendationRepository.php';
require_once dirname(__DIR__) . '/app/learner/ai/Persistence/DatabaseRecommendationRepository.php';
require_once dirname(__DIR__) . '/app/learner/ai/Contracts/RecommendationEngine.php';
require_once dirname(__DIR__) . '/app/learner/ai/Quality/DataQualityResult.php';
require_once dirname(__DIR__) . '/app/learner/ai/Validation/RecommendationResultValidator.php';
require_once dirname(__DIR__) . '/app/learner/ai/Service/RecommendationResponseMapper.php';
require_once dirname(__DIR__) . '/app/learner/ai/Service/RecommendationService.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function recommendation_repository_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function recommendation_repository_expect_exception(callable $callback, string $expectedMessage): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        recommendation_repository_assert($exception->getMessage() === $expectedMessage, "exception is {$expectedMessage}");
        return;
    }

    recommendation_repository_assert(false, "expected RuntimeException: {$expectedMessage}");
}

function recommendation_repository_expect_pdo_exception(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (PDOException) {
        return;
    }

    recommendation_repository_assert(false, $message);
}

function recommendation_repository_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE student_profiles (id CHAR(36) NOT NULL PRIMARY KEY)');
    $definition = require dirname(__DIR__) . '/Database/migrations/learner/004_create_recommendation_store.php';
    foreach ($definition->migration->statements('sqlite') as $statement) {
        $pdo->exec($statement);
    }
    return $pdo;
}

function recommendation_repository_input(string $skillId, string $assessmentId): RecommendationInput
{
    return new RecommendationInput(
        ['profile' => ['study_status' => 'active'], 'skills' => [['code' => 'iot', 'level_score' => 82]]],
        ['skill' => '2026-08-16T00:00:00.000000+00:00'],
        ['allowed_scopes' => ['assessment', 'skills']],
        [
            ['source_type' => 'skill', 'source_id' => $skillId, 'observed_at' => '2026-08-16T00:00:00.000000+00:00', 'safe_value' => ['level_score' => 82, 'verification_status' => 'verified']],
            ['source_type' => 'assessment', 'source_id' => $assessmentId, 'observed_at' => '2026-08-15T00:00:00.000000+00:00', 'safe_value' => ['result_code' => 'high_iot']],
        ],
    );
}

function recommendation_repository_result(string $skillId, string $assessmentId, bool $duplicateEvidence = false): RecommendationResult
{
    $evidence = [new RecommendationEvidence('skill', $skillId, '2020-01-01T00:00:00.000000+00:00', 'verified_skill', ['engine_claim' => 'must_not_replace_snapshot'])];
    if ($duplicateEvidence) {
        $evidence[] = new RecommendationEvidence('skill', $skillId, '2026-08-16T00:00:00.000000+00:00', 'duplicate_skill', []);
    }
    return new RecommendationResult(
        'rule',
        'learner-rules-1.0.0',
        null,
        null,
        null,
        null,
        [new RecommendationItem('strength', 'IoT strength', 'Your verified IoT skill is a current strength.', 80, 'high', ['type' => 'view_skill'], $evidence)]
    );
}

function recommendation_repository_model_result(string $skillId): RecommendationResult
{
    return new RecommendationResult(
        'model',
        null,
        'fake-provider',
        'fake-model',
        'fake-prompt',
        null,
        [new RecommendationItem(
            'development',
            'Model development step',
            'Practice a development step supported by current evidence.',
            60,
            'medium',
            ['type' => 'develop_skill', 'skill_code' => 'iot'],
            [new RecommendationEvidence('skill', $skillId, '2026-08-16T00:00:00.000000+00:00', 'model_source', [])],
        )],
    );
}

final class RecommendationRepositoryUnusedEngine implements RecommendationEngine
{
    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult
    {
        throw new RuntimeException('Latest reads must not invoke an engine.');
    }
}

$studentA = 'student-000000000000000000000000000001';
$studentB = 'student-000000000000000000000000000002';
$skillA = 'student-skill-000000000000000000000001';
$assessmentA = 'result-00000000000000000000000000000001';
$pdo = recommendation_repository_fixture();
$pdo->exec("INSERT INTO student_profiles (id) VALUES ('{$studentA}'), ('{$studentB}')");
$repositoryNow = '2026-08-16T12:00:00.000000+00:00';
$repository = new DatabaseRecommendationRepository(
    $pdo,
    static function () use (&$repositoryNow): string {
        return $repositoryNow;
    },
);
recommendation_repository_assert($repository instanceof RecommendationRepository, 'database repository implements the recommendation contract');

$inputA = recommendation_repository_input($skillA, $assessmentA);
$mismatchedContext = new RecommendationContext(['skills'], 'request-00000000000000000000000000000000', 'idempotency-consent-mismatch-000000000000001');
recommendation_repository_expect_exception(
    static fn (): array => $repository->createPendingRun($studentA, $inputA, $mismatchedContext),
    'Recommendation context scopes do not match input snapshot'
);
recommendation_repository_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_input_snapshots WHERE studentId = '{$studentA}'")->fetchColumn() === 0, 'scope mismatch persists no snapshot');
$contextA = new RecommendationContext(['skills', 'assessment'], 'request-00000000000000000000000000000001', 'idempotency-000000000000000000000000000001');
$pendingA = $repository->createPendingRun($studentA, $inputA, $contextA);
recommendation_repository_assert($pendingA['status'] === 'pending' && $pendingA['reused'] === false, 'first request creates a pending learner-owned run');
recommendation_repository_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_input_snapshots WHERE studentId = '{$studentA}'")->fetchColumn() === 1, 'pending run persists one immutable input snapshot');
recommendation_repository_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_snapshot_evidence WHERE snapshotId = '" . $pendingA['snapshotId'] . "'")->fetchColumn() === 2, 'pending run normalizes every Task 7 evidence reference');
recommendation_repository_assert($pdo->query("SELECT startedAt FROM learner_recommendation_runs WHERE id = '" . $pendingA['runId'] . "'")->fetchColumn() === '2026-08-16 12:00:00.000000', 'run timestamps are normalized for MySQL DATETIME(6) storage');
recommendation_repository_assert($pdo->query("SELECT observedAt FROM learner_recommendation_snapshot_evidence WHERE snapshotId = '" . $pendingA['snapshotId'] . "' AND sourceType = 'skill'")->fetchColumn() === '2026-08-16 00:00:00.000000', 'snapshot evidence timestamps are normalized for MySQL DATETIME(6) storage');
$snapshotEvidence = $pdo->query("SELECT safeValueJson FROM learner_recommendation_snapshot_evidence WHERE snapshotId = '" . $pendingA['snapshotId'] . "' AND sourceType = 'skill'")->fetchColumn();
recommendation_repository_assert($snapshotEvidence === '{"level_score":82,"verification_status":"verified"}', 'snapshot evidence persists the exact normalized safe value from input');
recommendation_repository_expect_pdo_exception(
    static fn (): int|false => $pdo->exec("UPDATE learner_recommendation_input_snapshots SET payloadJson = '{}' WHERE id = '" . $pendingA['snapshotId'] . "'"),
    'actual 004 SQLite fixture rejects snapshot rewrites'
);
recommendation_repository_expect_pdo_exception(
    static fn (): int|false => $pdo->exec("DELETE FROM learner_recommendation_input_snapshots WHERE id = '" . $pendingA['snapshotId'] . "'"),
    'actual 004 SQLite fixture rejects snapshot deletion'
);
recommendation_repository_expect_pdo_exception(
    static fn (): int|false => $pdo->exec("UPDATE learner_recommendation_snapshot_evidence SET safeValueJson = '{}' WHERE snapshotId = '" . $pendingA['snapshotId'] . "'"),
    'actual 004 SQLite fixture rejects snapshot evidence rewrites'
);
recommendation_repository_expect_pdo_exception(
    static fn (): int|false => $pdo->exec("DELETE FROM learner_recommendation_snapshot_evidence WHERE snapshotId = '" . $pendingA['snapshotId'] . "'"),
    'actual 004 SQLite fixture rejects snapshot evidence deletion'
);
$pendingARepeat = $repository->createPendingRun($studentA, $inputA, $contextA);
recommendation_repository_assert($pendingARepeat['runId'] === $pendingA['runId'] && $pendingARepeat['reused'] === true, 'same learner idempotency key returns the existing run');
recommendation_repository_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_runs WHERE studentId = '{$studentA}'")->fetchColumn() === 1, 'idempotent request does not create a second run');

$contextB = new RecommendationContext(['skills', 'assessment'], 'request-00000000000000000000000000000002', 'idempotency-000000000000000000000000000002');
$pendingB = $repository->createPendingRun($studentB, $inputA, $contextB);
recommendation_repository_assert($pendingB['runId'] !== $pendingA['runId'], 'another learner receives an independent run');

$completedA = $repository->completeRun($studentA, $pendingA['runId'], recommendation_repository_result($skillA, $assessmentA));
recommendation_repository_assert($completedA['status'] === 'completed' && count($completedA['items']) === 1, 'completion persists a validated recommendation item');
$itemA = $completedA['items'][0];
recommendation_repository_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_evidence WHERE itemId = '" . $itemA['itemId'] . "'")->fetchColumn() === 1, 'completion persists an evidence link to the immutable input snapshot');
$itemEvidence = $pdo->query("SELECT observedAt, safeValueJson FROM learner_recommendation_evidence WHERE itemId = '" . $itemA['itemId'] . "'")->fetch(PDO::FETCH_ASSOC);
recommendation_repository_assert(($itemEvidence['observedAt'] ?? null) === '2026-08-16 00:00:00.000000' && ($itemEvidence['safeValueJson'] ?? null) === '{"level_score":82,"verification_status":"verified"}', 'completion copies canonical snapshot evidence instead of engine-supplied stale values');
$repositoryNow = '2026-08-16T12:01:00.000000+00:00';
$shadowContext = new RecommendationContext(
    ['skills', 'assessment'],
    'request-00000000000000000000000000000005',
    'shadow-' . hash('sha256', $inputA->contentHash()),
    $studentA,
);
$pendingShadow = $repository->createPendingRun($studentA, $inputA, $shadowContext);
$repository->completeRun($studentA, $pendingShadow['runId'], recommendation_repository_model_result($skillA));
$latestA = $repository->latestForStudent($studentA);
$latestB = $repository->latestForStudent($studentB);
recommendation_repository_assert($latestA !== null && $latestA['runId'] === $pendingA['runId'], 'learner A latest excludes a newer stable shadow run');
recommendation_repository_assert($latestB !== null && $latestB['runId'] === $pendingB['runId'], 'learner B cannot load learner A run through its latest query');
$latestService = new RecommendationService(
    $repository,
    new RecommendationRepositoryUnusedEngine(),
    new RecommendationResultValidator(),
    new RecommendationResponseMapper(),
    static fn (string $candidate): bool => hash_equals($studentA, $candidate),
    static fn (string $candidate): array => [],
    static fn (string $candidate, array $scopes): RecommendationInput => $inputA,
    static fn (RecommendationInput $input): DataQualityResult => new DataQualityResult('ready'),
    static fn (RecommendationInput $input): bool => true,
);
$latestServiceResult = $latestService->latest($studentA);
recommendation_repository_assert(
    $latestServiceResult !== null && $latestServiceResult['state'] === 'ready_rule' && $latestServiceResult['run_id'] === $pendingA['runId'],
    'RecommendationService latest remains the visible rule run after shadow completion',
);
$repositoryNow = '2026-08-16T12:02:00.000000+00:00';
$visibleModelContext = new RecommendationContext(
    ['skills', 'assessment'],
    'request-00000000000000000000000000000006',
    'model-' . hash('sha256', $inputA->contentHash() . ':visible'),
    $studentA,
);
$pendingVisibleModel = $repository->createPendingRun($studentA, $inputA, $visibleModelContext);
$repository->completeRun($studentA, $pendingVisibleModel['runId'], recommendation_repository_model_result($skillA));
$latestVisibleModel = $repository->latestForStudent($studentA);
recommendation_repository_assert(
    $latestVisibleModel !== null && $latestVisibleModel['runId'] === $pendingVisibleModel['runId'] && $latestVisibleModel['engineType'] === 'model',
    'latest preserves a newer future visible model run in the model namespace',
);
recommendation_repository_expect_exception(
    static fn (): array => $repository->completeRun($studentB, $pendingA['runId'], recommendation_repository_result($skillA, $assessmentA)),
    'Recommendation run not found for learner'
);
recommendation_repository_expect_exception(
    static fn (): array => $repository->appendFeedback($studentB, $itemA['itemId'], 'helpful', 'relevant', null),
    'Recommendation item not found for learner'
);
$feedback = $repository->appendFeedback($studentA, $itemA['itemId'], 'helpful', 'relevant', 'Useful next step');
recommendation_repository_assert($feedback['studentId'] === $studentA && $feedback['itemId'] === $itemA['itemId'], 'owner can append feedback without modifying existing events');
recommendation_repository_expect_pdo_exception(
    static fn (): int|false => $pdo->exec("UPDATE learner_recommendation_feedback SET verdict = 'not_helpful' WHERE id = '" . $feedback['feedbackId'] . "'"),
    'database feedback trigger preserves append-only history'
);
recommendation_repository_expect_pdo_exception(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_recommendation_feedback (id, studentId, itemId, verdict, reasonCode, safeComment, createdAt) VALUES ('00000000-0000-4000-8000-000000000301', '{$studentB}', '" . $itemA['itemId'] . "', 'helpful', 'relevant', NULL, '2026-08-16 12:00:00.000000')"),
    'actual 004 SQLite fixture rejects feedback whose learner does not own its item'
);
recommendation_repository_expect_pdo_exception(
    static fn (): int|false => $pdo->exec("INSERT INTO learner_recommendation_audit_events (id, runId, studentId, requestId, actorType, action, engineMetadataJson, status, createdAt) VALUES ('00000000-0000-4000-8000-000000000302', '" . $pendingA['runId'] . "', '{$studentB}', '00000000-0000-4000-8000-000000000303', 'system', 'cross_learner', '{}', 'pending', '2026-08-16 12:00:00.000000')"),
    'actual 004 SQLite fixture rejects audit records whose learner does not own its run'
);

$contextMembership = new RecommendationContext(['skills', 'assessment'], 'request-00000000000000000000000000000003', 'idempotency-000000000000000000000000000003');
$pendingMembership = $repository->createPendingRun($studentA, $inputA, $contextMembership);
recommendation_repository_assert($pendingMembership['snapshotId'] === $pendingA['snapshotId'] && $pendingMembership['runId'] !== $pendingA['runId'], 'a retry with another idempotency key reuses the same learner snapshot and creates one run');
recommendation_repository_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_input_snapshots WHERE studentId = '{$studentA}' AND contentHash = '" . $inputA->contentHash() . "'")->fetchColumn() === 1, 'snapshot deduplication retains one canonical learner snapshot');
$missingSnapshotEvidence = new RecommendationResult('rule', 'learner-rules-1.0.0', null, null, null, null, [new RecommendationItem('development', 'Communication', 'Practice communication.', 60, 'medium', ['type' => 'roadmap'], [new RecommendationEvidence('evaluation', 'evaluation-000000000000000000000000000001', '2026-08-16T00:00:00.000000+00:00', 'missing', [])])]);
recommendation_repository_expect_exception(
    static fn (): array => $repository->completeRun($studentA, $pendingMembership['runId'], $missingSnapshotEvidence),
    'Recommendation evidence is not part of run snapshot'
);
recommendation_repository_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_items WHERE runId = '" . $pendingMembership['runId'] . "'")->fetchColumn() === 0, 'snapshot-membership rejection leaves no partial item');

$contextRollback = new RecommendationContext(['skills', 'assessment'], 'request-00000000000000000000000000000004', 'idempotency-000000000000000000000000000004');
$pendingRollback = $repository->createPendingRun($studentA, $inputA, $contextRollback);
recommendation_repository_expect_pdo_exception(
    static fn (): array => $repository->completeRun($studentA, $pendingRollback['runId'], recommendation_repository_result($skillA, $assessmentA, true)),
    'duplicate evidence causes the whole completion transaction to roll back'
);
recommendation_repository_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_items WHERE runId = '" . $pendingRollback['runId'] . "'")->fetchColumn() === 0, 'forced evidence insert failure leaves no item');
recommendation_repository_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_evidence AS evidence INNER JOIN learner_recommendation_items AS items ON items.id = evidence.itemId WHERE items.runId = '" . $pendingRollback['runId'] . "'")->fetchColumn() === 0, 'forced evidence insert failure leaves no evidence');
recommendation_repository_assert($pdo->query("SELECT status FROM learner_recommendation_runs WHERE id = '" . $pendingRollback['runId'] . "'")->fetchColumn() === 'pending', 'rollback leaves run status pending');

$repository->failRun($studentB, $pendingB['runId'], 'engine_timeout');
recommendation_repository_assert($pdo->query("SELECT status FROM learner_recommendation_runs WHERE id = '" . $pendingB['runId'] . "'")->fetchColumn() === 'failed', 'owner can mark its pending run failed with a safe code');
recommendation_repository_expect_exception(
    static fn (): null => $repository->failRun($studentA, $pendingB['runId'], 'engine_timeout'),
    'Recommendation run not found for learner'
);

echo "learner_recommendation_repository_test: OK\n";
