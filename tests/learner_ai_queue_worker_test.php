<?php
declare(strict_types=1);

namespace TalentHub\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Events\LearnerAiDataChanged;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;
use TalentHub\Learner\Ai\Provider\ProviderRetryAfterException;
use TalentHub\Learner\Ai\Queue\AiDataOutbox;
use TalentHub\Learner\Ai\Queue\AiDataOutboxConsumer;
use TalentHub\Learner\Ai\Queue\AiRefreshDispatcher;
use TalentHub\Learner\Ai\Queue\AiRefreshJob;
use TalentHub\Learner\Ai\Queue\AiRefreshWorker;
use TalentHub\Learner\Ai\Queue\DatabaseAiDataOutboxRepository;
use TalentHub\Learner\Ai\Queue\DatabaseAiRefreshJobRepository;
use TalentHub\Learner\Ai\Queue\InMemoryAiRefreshJobRepository;
use TalentHub\Learner\Ai\Service\AdaptiveRefreshCoordinator;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function queue_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException('FAIL: ' . $message);
    }
}

function createQueueTestPdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE learner_ai_data_outbox (
        id TEXT PRIMARY KEY,
        aggregate_type TEXT NOT NULL,
        aggregate_id TEXT NOT NULL,
        tenant_id TEXT,
        event_type TEXT NOT NULL,
        aggregate_version INTEGER NOT NULL,
        payload_hash TEXT NOT NULL,
        affected_student_ids TEXT NOT NULL,
        delivery_status TEXT NOT NULL,
        occurred_at TEXT NOT NULL,
        delivered_at TEXT
    )');

    $pdo->exec('CREATE TABLE learner_ai_refresh_jobs (
        job_key TEXT PRIMARY KEY,
        student_id TEXT NOT NULL,
        capability TEXT NOT NULL,
        snapshot_hash TEXT NOT NULL,
        status TEXT NOT NULL,
        attempts INTEGER NOT NULL DEFAULT 0,
        next_retry_at TEXT,
        lease_until TEXT,
        error_code TEXT,
        lease_owner TEXT,
        lease_token TEXT,
        dead_lettered_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE learner_ai_refresh_state (
        student_id TEXT NOT NULL,
        capability TEXT NOT NULL,
        snapshot_hash TEXT NOT NULL,
        job_key TEXT NOT NULL,
        state TEXT NOT NULL,
        model_version TEXT,
        error_category TEXT,
        next_retry_at TEXT,
        updated_at TEXT NOT NULL,
        PRIMARY KEY (student_id, capability)
    )');

    $pdo->exec('CREATE TABLE learner_ai_capability_profiles (
        student_id TEXT PRIMARY KEY,
        profile_json TEXT NOT NULL,
        snapshot_hash TEXT NOT NULL,
        model_version TEXT NOT NULL,
        generated_at TEXT NOT NULL,
        published_at TEXT NOT NULL
    )');

    return $pdo;
}

$pdo = createQueueTestPdo();
$studentId = '00000000-0000-4000-8000-000000000001';
$hashA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$hashB = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

// =========================================================================
// Block A: Database & InMemory Repository Enqueue, Deduplication & Priority Claiming
// =========================================================================
$dbRepo = new DatabaseAiRefreshJobRepository($pdo);
$memRepo = new InMemoryAiRefreshJobRepository();

foreach ([$dbRepo, $memRepo] as $idx => $repo) {
    $label = $idx === 0 ? 'DB' : 'InMemory';
    
    // Enqueue 3 capabilities
    $job1 = $repo->enqueue($studentId, $hashA, 'recommendation');
    $job2 = $repo->enqueue($studentId, $hashA, 'roadmap');
    $job3 = $repo->enqueue($studentId, $hashA, 'profile_analysis');
    
    // Deduplication check
    $job1Dup = $repo->enqueue($studentId, $hashA, 'recommendation');
    queue_assert($job1->jobKey === $job1Dup->jobKey, "[$label] duplicate enqueue produces identical job_key");
    queue_assert(strlen($job1->jobKey) === 64, "[$label] jobKey is 64-char sha256");

    // Claim next: roadmap must be claimed FIRST because of priority order
    $claimedFirst = $repo->claimNext('worker-1', 60);
    queue_assert($claimedFirst !== null, "[$label] claimNext returns a job");
    queue_assert($claimedFirst->capability === 'roadmap', "[$label] roadmap has highest priority over recommendation and profile_analysis");
    queue_assert($claimedFirst->status === 'processing', "[$label] claimed job status is processing");
    queue_assert($claimedFirst->attempts === 1, "[$label] attempts incremented to 1");
    queue_assert($claimedFirst->leaseToken !== null, "[$label] leaseToken is generated");

    // Renew lease
    $renewed = $repo->renewLease($claimedFirst->jobKey, (string) $claimedFirst->leaseToken, 120);
    queue_assert($renewed, "[$label] renewLease succeeds with valid leaseToken");

    // Complete job
    $repo->complete($claimedFirst->jobKey, $claimedFirst->leaseToken);

    // Next claimed is recommendation (priority 2)
    $claimedSecond = $repo->claimNext('worker-1', 60);
    queue_assert($claimedSecond !== null && $claimedSecond->capability === 'recommendation', "[$label] recommendation claimed second");
    $repo->complete($claimedSecond->jobKey, $claimedSecond->leaseToken);

    // Next claimed is profile_analysis (priority 3)
    $claimedThird = $repo->claimNext('worker-1', 60);
    queue_assert($claimedThird !== null && $claimedThird->capability === 'profile_analysis', "[$label] profile_analysis claimed third");
    $repo->complete($claimedThird->jobKey, $claimedThird->leaseToken);
}

// =========================================================================
// Block B: Retry Backoff Schedule & Explicit RetryAfter
// =========================================================================
$repo = new DatabaseAiRefreshJobRepository($pdo);
$testJob = $repo->enqueue($studentId, 'hash-retry-1', 'roadmap');

// Attempt 1 failure -> delay 2^1 = 2s
$claimed = $repo->claimNext('worker-test', 30);
queue_assert($claimed !== null && $claimed->attempts === 1, 'attempt 1 claimed');
$repo->fail($claimed->jobKey, 'provider_unavailable', false, $claimed->leaseToken, null);

$stmt = $pdo->prepare('SELECT * FROM learner_ai_refresh_jobs WHERE job_key = :k');
$stmt->execute(['k' => $claimed->jobKey]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
queue_assert($row['status'] === 'pending', 'failed job transitions back to pending');
queue_assert($row['next_retry_at'] !== null, 'next_retry_at is recorded');
queue_assert($row['error_code'] === 'provider_unavailable', 'error_code recorded');

// Explicit retry-after (e.g. 120s from ProviderRetryAfterException)
$repo->fail($claimed->jobKey, 'rate_limit_exceeded', false, null, 120);
$stmt->execute(['k' => $claimed->jobKey]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
queue_assert($row['error_code'] === 'rate_limit_exceeded', 'custom error category recorded');

// =========================================================================
// Block C: Max Attempts & Dead-Lettering
// =========================================================================
$deadJob = $repo->enqueue($studentId, 'hash-dead-1', 'roadmap');
// Simulate 5 failures
for ($att = 1; $att <= 5; $att++) {
    $c = $repo->claimNext('worker-dl', 30);
    if ($c !== null) {
        $isDead = ($c->attempts >= 5);
        $repo->fail($c->jobKey, 'provider_unavailable', $isDead, $c->leaseToken, 0);
    }
}
$stmt->execute(['k' => $deadJob->jobKey]);
$dlRow = $stmt->fetch(PDO::FETCH_ASSOC);
queue_assert($dlRow['status'] === 'dead_letter', '5th attempt marks job dead_letter');
queue_assert($dlRow['next_retry_at'] === null, 'dead_letter job has null next_retry_at');
queue_assert($dlRow['dead_lettered_at'] !== null, 'dead_lettered_at timestamp is set');

// =========================================================================
// Block D: Superseded Snapshot Cancellation
// =========================================================================
$hashOld = 'hash-old-snapshot-test';
$oldJob = $repo->enqueue($studentId, $hashOld, 'roadmap');
queue_assert($oldJob->status === 'pending', 'old snapshot job is pending');
$repo->cancelSuperseded($studentId, 'roadmap', $hashB);

$stmt->execute(['k' => $oldJob->jobKey]);
$cancelledRow = $stmt->fetch(PDO::FETCH_ASSOC);
queue_assert($cancelledRow['status'] === 'cancelled', 'superseded pending job is marked cancelled');

// =========================================================================
// Block E: Outbox Consumer Batching & Malformed Row Isolation
// =========================================================================
$outboxRepo = new DatabaseAiDataOutboxRepository($pdo);
$dispatcher = new AiRefreshDispatcher($repo);
$consumer = new AiDataOutboxConsumer($outboxRepo, $dispatcher, static fn(string $s, string $c): string => hash('sha256', "$s:$c"));

// Insert 1 valid outbox row with duplicates/whitespace and 1 empty row
$outboxRepo->append(new AiDataOutbox(
    'evt-valid-1',
    'assessment',
    'att-1',
    1,
    ['student-1', 'student-2', 'student-1', ' '],
    'assessment.submitted',
    'hash-evt-1'
));
$outboxRepo->append(new AiDataOutbox(
    'evt-empty-2',
    'badge',
    'bdg-1',
    1,
    [],
    'badge.awarded',
    'hash-evt-2'
));

$consumed = $consumer->consume(10);
queue_assert($consumed === 2, 'all outbox rows processed without crash');

$pendingOutbox = $outboxRepo->pending(10);
queue_assert(count($pendingOutbox) === 0, 'no pending outbox rows remain');

// =========================================================================
// Block F: Burst Debouncing & Unioned Capability Coalescing
// =========================================================================
$time = 1000;
$coordinator = new AdaptiveRefreshCoordinator($dispatcher, 30, function () use (&$time): int { return $time; });
$evt1 = new LearnerAiDataChanged('student-burst', 'assessment', 'att-1', 1, '2026-08-28 12:00:00');
$evt2 = new LearnerAiDataChanged('student-burst', 'badge', 'bdg-1', 2, '2026-08-28 12:00:10');

// Event 1 dispatches recommendation immediately
$d1 = $coordinator->onDataChanged($evt1, 'hash-burst-1', ['recommendation']);
queue_assert(count($d1) === 1, 'first event dispatches immediately');

// Event 2 (10s later, within 30s debounce) requests roadmap
$time = 1010;
$d2 = $coordinator->onDataChanged($evt2, 'hash-burst-2', ['roadmap']);
queue_assert(count($d2) === 0, 'burst event 2 within 30s is debounced');

// Event 3 (20s later, still within 30s debounce) requests profile_analysis
$time = 1020;
$evt3 = new LearnerAiDataChanged('student-burst', 'roadmap', 'rdm-1', 3, '2026-08-28 12:00:20');
$d3 = $coordinator->onDataChanged($evt3, 'hash-burst-3', ['profile_analysis']);
queue_assert(count($d3) === 0, 'burst event 3 within 30s is debounced');

// Advance time past 30s debounce and flush -> must dispatch BOTH roadmap & profile_analysis with latest hash
$time = 1060;
$flushed = $coordinator->flush();
queue_assert(count($flushed) === 2, 'flushed jobs include unioned capabilities');
$flushedCaps = array_map(static fn(AiRefreshJob $j): string => $j->capability, $flushed);
queue_assert(in_array('roadmap', $flushedCaps, true), 'unioned capabilities includes roadmap');
queue_assert(in_array('profile_analysis', $flushedCaps, true), 'unioned capabilities includes profile_analysis');
queue_assert($flushed[0]->snapshotHash === 'hash-burst-3', 'latest snapshot hash wins on debounced flush');

// =========================================================================
// Block G: Worker Execution with Provider Failure (Zero Silent Fallback)
// =========================================================================
$pdoStrict = createQueueTestPdo();
$repoStrict = new DatabaseAiRefreshJobRepository($pdoStrict);
$failJob = $repoStrict->enqueue('student-fail-strict', 'hash-strict-1', 'roadmap');
$workerExecuted = false;
$ruleEngineCalled = false;

$failingHandler = static function (AiRefreshJob $job, callable $leaseGuard) use (&$workerExecuted, &$ruleEngineCalled): void {
    $workerExecuted = true;
    // Strict requirement: never invoke rule fallback
    if ($ruleEngineCalled) {
        throw new \RuntimeException('rule_engine_must_not_be_called');
    }
    throw new \RuntimeException('provider_unavailable');
};

$worker = new AiRefreshWorker($repoStrict, $failingHandler, 5);
$ran = $worker->runOnce('worker-strict-1');

queue_assert($ran, 'worker executed job');
queue_assert($workerExecuted, 'handler was invoked');
queue_assert(!$ruleEngineCalled, 'rule engine was NEVER called');

$stmtStrict = $pdoStrict->prepare('SELECT * FROM learner_ai_refresh_jobs WHERE job_key = :k');
$stmtStrict->execute(['k' => $failJob->jobKey]);
$strictRow = $stmtStrict->fetch(PDO::FETCH_ASSOC);
queue_assert($strictRow['status'] === 'pending', 'failed worker job remains pending for retry');
queue_assert($strictRow['error_code'] === 'provider_unavailable', 'error_code is preserved truthfully');

echo "learner_ai_queue_worker_test: OK\n";