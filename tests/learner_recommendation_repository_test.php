<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
use TalentHub\Learner\Ai\Persistence\RecommendationRepository;

require_once dirname(__DIR__) . '/app/learner/ai/Domain/RecommendationInput.php';
require_once dirname(__DIR__) . '/app/learner/ai/Domain/RecommendationContext.php';
require_once dirname(__DIR__) . '/app/learner/ai/Domain/RecommendationEvidence.php';
require_once dirname(__DIR__) . '/app/learner/ai/Domain/RecommendationItem.php';
require_once dirname(__DIR__) . '/app/learner/ai/Domain/RecommendationResult.php';
require_once dirname(__DIR__) . '/app/learner/ai/Persistence/RecommendationRepository.php';
require_once dirname(__DIR__) . '/app/learner/ai/Persistence/DatabaseRecommendationRepository.php';

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
    $pdo->exec("CREATE TABLE learner_recommendation_input_snapshots (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, schemaVersion VARCHAR(100) NOT NULL, contentHash CHAR(64) NOT NULL, consentScopesJson TEXT NOT NULL, qualityFlagsJson TEXT NOT NULL, payloadJson TEXT NOT NULL, sourceUpdatedAt TEXT NOT NULL, createdAt TEXT NOT NULL, UNIQUE (studentId, contentHash), UNIQUE (id, studentId), FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE)");
    $pdo->exec("CREATE TABLE learner_recommendation_runs (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, snapshotId CHAR(36) NOT NULL, idempotencyKey VARCHAR(100) NOT NULL, engineType VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, ruleVersion VARCHAR(100) NULL, provider VARCHAR(100) NULL, modelVersion VARCHAR(100) NULL, promptVersion VARCHAR(100) NULL, fallbackReason VARCHAR(100) NULL, safeErrorCode VARCHAR(100) NULL, startedAt TEXT NOT NULL, completedAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE (studentId, idempotencyKey), FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (snapshotId, studentId) REFERENCES learner_recommendation_input_snapshots(id, studentId) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (engineType IN ('rule','model')), CHECK (status IN ('pending','completed','failed','fallback')), CHECK ((engineType = 'rule' AND ruleVersion IS NOT NULL AND provider IS NULL AND modelVersion IS NULL AND promptVersion IS NULL) OR (engineType = 'model' AND ruleVersion IS NULL AND provider IS NOT NULL AND modelVersion IS NOT NULL AND promptVersion IS NOT NULL)), CHECK ((status = 'pending' AND completedAt IS NULL) OR (status IN ('completed','failed','fallback') AND completedAt IS NOT NULL)))");
    $pdo->exec("CREATE TABLE learner_recommendation_snapshot_evidence (id CHAR(36) NOT NULL PRIMARY KEY, snapshotId CHAR(36) NOT NULL, sourceType VARCHAR(50) NOT NULL, sourceId CHAR(36) NOT NULL, observedAt TEXT NULL, safeValueJson TEXT NOT NULL, createdAt TEXT NOT NULL, UNIQUE (snapshotId, sourceType, sourceId), FOREIGN KEY (snapshotId) REFERENCES learner_recommendation_input_snapshots(id) ON DELETE RESTRICT ON UPDATE CASCADE)");
    $pdo->exec("CREATE TABLE learner_recommendation_items (id CHAR(36) NOT NULL PRIMARY KEY, runId CHAR(36) NOT NULL, itemType VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, summary VARCHAR(1000) NOT NULL, priority INTEGER NOT NULL, confidenceBand VARCHAR(50) NOT NULL, actionJson TEXT NOT NULL, lifecycleStatus VARCHAR(50) NOT NULL, createdAt TEXT NOT NULL, FOREIGN KEY (runId) REFERENCES learner_recommendation_runs(id) ON DELETE RESTRICT ON UPDATE CASCADE)");
    $pdo->exec("CREATE TABLE learner_recommendation_evidence (id CHAR(36) NOT NULL PRIMARY KEY, itemId CHAR(36) NOT NULL, snapshotEvidenceId CHAR(36) NOT NULL, sourceType VARCHAR(50) NOT NULL, sourceId CHAR(36) NOT NULL, observedAt TEXT NULL, contributionLabel VARCHAR(100) NOT NULL, safeValueJson TEXT NOT NULL, createdAt TEXT NOT NULL, UNIQUE (itemId, sourceType, sourceId), FOREIGN KEY (itemId) REFERENCES learner_recommendation_items(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (snapshotEvidenceId) REFERENCES learner_recommendation_snapshot_evidence(id) ON DELETE RESTRICT ON UPDATE CASCADE)");
    $pdo->exec("CREATE TABLE learner_recommendation_feedback (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, itemId CHAR(36) NOT NULL, verdict VARCHAR(50) NOT NULL, reasonCode VARCHAR(100) NOT NULL, safeComment VARCHAR(500) NULL, createdAt TEXT NOT NULL, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (itemId) REFERENCES learner_recommendation_items(id) ON DELETE RESTRICT ON UPDATE CASCADE)");
    $pdo->exec("CREATE TABLE learner_recommendation_audit_events (id CHAR(36) NOT NULL PRIMARY KEY, runId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, requestId CHAR(36) NOT NULL, actorType VARCHAR(50) NOT NULL, action VARCHAR(100) NOT NULL, engineMetadataJson TEXT NOT NULL, status VARCHAR(50) NOT NULL, createdAt TEXT NOT NULL, FOREIGN KEY (runId) REFERENCES learner_recommendation_runs(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE)");
    $pdo->exec("CREATE TRIGGER trg_learner_recommendation_evidence_snapshot_match_insert BEFORE INSERT ON learner_recommendation_evidence FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_recommendation_items AS items INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId INNER JOIN learner_recommendation_snapshot_evidence AS snapshot_evidence ON snapshot_evidence.id = NEW.snapshotEvidenceId WHERE items.id = NEW.itemId AND runs.snapshotId = snapshot_evidence.snapshotId AND NEW.sourceType = snapshot_evidence.sourceType AND NEW.sourceId = snapshot_evidence.sourceId) BEGIN SELECT RAISE(ABORT, 'recommendation evidence snapshot mismatch'); END");
    $pdo->exec("CREATE TRIGGER trg_learner_recommendation_feedback_owner_match_insert BEFORE INSERT ON learner_recommendation_feedback FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_recommendation_items AS items INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE items.id = NEW.itemId AND runs.studentId = NEW.studentId) BEGIN SELECT RAISE(ABORT, 'recommendation feedback learner ownership mismatch'); END");
    $pdo->exec("CREATE TRIGGER trg_learner_recommendation_feedback_append_only_update BEFORE UPDATE ON learner_recommendation_feedback FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only recommendation feedback'); END");
    $pdo->exec("CREATE TRIGGER trg_learner_recommendation_feedback_append_only_delete BEFORE DELETE ON learner_recommendation_feedback FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only recommendation feedback'); END");
    $pdo->exec("CREATE TRIGGER trg_learner_recommendation_audit_events_owner_match_insert BEFORE INSERT ON learner_recommendation_audit_events FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_recommendation_runs AS runs WHERE runs.id = NEW.runId AND runs.studentId = NEW.studentId) BEGIN SELECT RAISE(ABORT, 'recommendation audit learner ownership mismatch'); END");
    return $pdo;
}

function recommendation_repository_input(string $skillId, string $assessmentId): RecommendationInput
{
    return new RecommendationInput(
        ['profile' => ['study_status' => 'active'], 'skills' => [['code' => 'iot', 'level_score' => 82]]],
        ['skill' => '2026-08-16T00:00:00.000000+00:00'],
        ['allowed_scopes' => ['assessment', 'skills']],
        [
            ['source_type' => 'skill', 'source_id' => $skillId, 'observed_at' => '2026-08-16T00:00:00.000000+00:00'],
            ['source_type' => 'assessment', 'source_id' => $assessmentId, 'observed_at' => '2026-08-15T00:00:00.000000+00:00'],
        ],
    );
}

function recommendation_repository_result(string $skillId, string $assessmentId, bool $duplicateEvidence = false): RecommendationResult
{
    $evidence = [new RecommendationEvidence('skill', $skillId, '2026-08-16T00:00:00.000000+00:00', 'verified_skill', ['verification_status' => 'verified'])];
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

$studentA = 'student-000000000000000000000000000001';
$studentB = 'student-000000000000000000000000000002';
$skillA = 'student-skill-000000000000000000000001';
$assessmentA = 'result-00000000000000000000000000000001';
$pdo = recommendation_repository_fixture();
$pdo->exec("INSERT INTO student_profiles (id) VALUES ('{$studentA}'), ('{$studentB}')");
$repository = new DatabaseRecommendationRepository($pdo, static fn (): string => '2026-08-16T12:00:00.000000+00:00');
recommendation_repository_assert($repository instanceof RecommendationRepository, 'database repository implements the recommendation contract');

$inputA = recommendation_repository_input($skillA, $assessmentA);
$contextA = new RecommendationContext(['skills', 'assessment'], 'request-00000000000000000000000000000001', 'idempotency-000000000000000000000000000001');
$pendingA = $repository->createPendingRun($studentA, $inputA, $contextA);
recommendation_repository_assert($pendingA['status'] === 'pending' && $pendingA['reused'] === false, 'first request creates a pending learner-owned run');
recommendation_repository_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_input_snapshots WHERE studentId = '{$studentA}'")->fetchColumn() === 1, 'pending run persists one immutable input snapshot');
recommendation_repository_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_snapshot_evidence WHERE snapshotId = '" . $pendingA['snapshotId'] . "'")->fetchColumn() === 2, 'pending run normalizes every Task 7 evidence reference');
recommendation_repository_assert($pdo->query("SELECT startedAt FROM learner_recommendation_runs WHERE id = '" . $pendingA['runId'] . "'")->fetchColumn() === '2026-08-16 12:00:00.000000', 'run timestamps are normalized for MySQL DATETIME(6) storage');
recommendation_repository_assert($pdo->query("SELECT observedAt FROM learner_recommendation_snapshot_evidence WHERE snapshotId = '" . $pendingA['snapshotId'] . "' AND sourceType = 'skill'")->fetchColumn() === '2026-08-16 00:00:00.000000', 'snapshot evidence timestamps are normalized for MySQL DATETIME(6) storage');
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
$latestA = $repository->latestForStudent($studentA);
$latestB = $repository->latestForStudent($studentB);
recommendation_repository_assert($latestA !== null && $latestA['runId'] === $pendingA['runId'], 'learner A can load only its latest completed run');
recommendation_repository_assert($latestB !== null && $latestB['runId'] === $pendingB['runId'], 'learner B cannot load learner A run through its latest query');
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

$contextMembership = new RecommendationContext(['skills', 'assessment'], 'request-00000000000000000000000000000003', 'idempotency-000000000000000000000000000003');
$pendingMembership = $repository->createPendingRun($studentA, $inputA, $contextMembership);
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
