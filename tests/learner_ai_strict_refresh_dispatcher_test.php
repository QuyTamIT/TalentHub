<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Persistence\DatabaseAiRefreshStateRepository;
use TalentHub\Learner\Ai\Queue\AiRefreshDispatcher;
use TalentHub\Learner\Ai\Queue\InMemoryAiRefreshJobRepository;
use TalentHub\Learner\Ai\Service\RecommendationResponseMapper;
use TalentHub\Learner\Ai\Service\StrictRecommendationRefreshDispatcher;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function strict_refresh_dispatcher_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE learner_recommendation_runs (
    id TEXT PRIMARY KEY,
    studentId TEXT NOT NULL,
    engineType TEXT NOT NULL,
    status TEXT NOT NULL,
    createdAt TEXT NOT NULL,
    freshness_status TEXT,
    snapshot_hash TEXT,
    refresh_job_id TEXT,
    stale_since TEXT,
    last_refresh_error TEXT,
    next_retry_at TEXT,
    model_version TEXT
)');
$pdo->exec("INSERT INTO learner_recommendation_runs (id, studentId, engineType, status, createdAt) VALUES ('model-run-1', 'student-1', 'model', 'completed', '2026-08-29 00:00:00')");

$jobs = new InMemoryAiRefreshJobRepository();
$dispatcher = new StrictRecommendationRefreshDispatcher(
    new AiRefreshDispatcher($jobs),
    new DatabaseAiRefreshStateRepository($pdo),
);
$created = $dispatcher->dispatch('student-1', 'snapshot-hash-1');
strict_refresh_dispatcher_assert(count($created) === 1, 'strict provider failure dispatches exactly one recommendation refresh job');

$state = $pdo->query("SELECT freshness_status, snapshot_hash, refresh_job_id, stale_since FROM learner_recommendation_runs WHERE id = 'model-run-1'")->fetch(PDO::FETCH_ASSOC);
strict_refresh_dispatcher_assert(($state['freshness_status'] ?? null) === 'stale_model', 'strict provider failure persists stale_model on the last known good model run');
strict_refresh_dispatcher_assert(($state['snapshot_hash'] ?? null) === 'snapshot-hash-1', 'persisted stale state is bound to the failed snapshot hash');
strict_refresh_dispatcher_assert(($state['refresh_job_id'] ?? null) === $created[0]->jobKey, 'persisted stale state references the queued recovery job');
strict_refresh_dispatcher_assert(is_string($state['stale_since'] ?? null) && $state['stale_since'] !== '', 'persisted stale state has a timestamp');

$mapped = (new RecommendationResponseMapper())->run([
    'status' => 'completed',
    'engineType' => 'model',
    'freshness_status' => $state['freshness_status'],
    'snapshotId' => 'snapshot-id',
    'runId' => 'model-run-1',
    'items' => [],
]);
strict_refresh_dispatcher_assert(($mapped['state'] ?? null) === 'stale_model' && ($mapped['last_known_good'] ?? false) === true, 'a later GET maps persisted freshness to stale_model instead of ready_model');

echo "learner_ai_strict_refresh_dispatcher_test: OK\n";
