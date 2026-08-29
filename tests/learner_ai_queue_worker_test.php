<?php
declare(strict_types=1);

namespace TalentHub\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Events\LearnerAiDataChanged;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;
use TalentHub\Learner\Ai\Provider\CircuitBreaker;
use TalentHub\Learner\Ai\Provider\DatabaseCircuitBreakerStore;
use TalentHub\Learner\Ai\Provider\ProviderRetryAfterException;
use TalentHub\Learner\Ai\Queue\AiDataOutbox;
use TalentHub\Learner\Ai\Queue\AiDataOutboxConsumer;
use TalentHub\Learner\Ai\Queue\AiRefreshDispatcher;
use TalentHub\Learner\Ai\Queue\AiRefreshJob;
use TalentHub\Learner\Ai\Queue\AiRefreshJobRepository;
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

    $pdo->exec('CREATE TABLE learner_ai_provider_health (
        provider_key TEXT PRIMARY KEY,
        state TEXT NOT NULL,
        failure_count INTEGER NOT NULL,
        opened_at INTEGER,
        updated_at TEXT NOT NULL
    )');

    return $pdo;
}

$pdo = createQueueTestPdo();
$studentId = '00000000-0000-4000-8000-000000000001';
$hashA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$hashB = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

// Mandatory parity case: capability priority must precede age in both repositories.
// Seed an older profile-analysis job before a newer roadmap job and require roadmap first.
foreach ([new DatabaseAiRefreshJobRepository($pdo), new InMemoryAiRefreshJobRepository()] as $priorityRepo) {
    $priorityRepo->enqueue('priority-student', 'priority-profile', 'profile_analysis');
    usleep(1100000);
    $priorityRepo->enqueue('priority-student', 'priority-roadmap', 'roadmap');
    $priorityClaim = $priorityRepo->claimNext('priority-worker', 60);
    queue_assert($priorityClaim !== null && $priorityClaim->capability === 'roadmap', 'roadmap priority wins over older profile job');
}

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
    queue_assert(!$repo->complete($claimedFirst->jobKey, 'wrong-lease-token'), "[$label] completion rejects the wrong lease");
    queue_assert($repo->complete($claimedFirst->jobKey, $claimedFirst->leaseToken), "[$label] completion atomically consumes the valid lease");

    // Next claimed is recommendation (priority 2)
    $claimedSecond = $repo->claimNext('worker-1', 60);
    queue_assert($claimedSecond !== null && $claimedSecond->capability === 'recommendation', "[$label] recommendation claimed second");
    queue_assert($repo->complete($claimedSecond->jobKey, $claimedSecond->leaseToken), "[$label] recommendation completion succeeds");

    // Next claimed is profile_analysis (priority 3)
    $claimedThird = $repo->claimNext('worker-1', 60);
    queue_assert($claimedThird !== null && $claimedThird->capability === 'profile_analysis', "[$label] profile_analysis claimed third");
    queue_assert($repo->complete($claimedThird->jobKey, $claimedThird->leaseToken), "[$label] profile completion succeeds");
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
$outboxMetrics = new AiMetricsCollector(100);
$transientHashFailures = 1;
$consumer = new AiDataOutboxConsumer(
    $outboxRepo,
    $dispatcher,
    static function(string $s, string $c) use (&$transientHashFailures): string {
        if ($s === 'transient-student' && $c === 'recommendation' && $transientHashFailures-- > 0) throw new RuntimeException('temporary_snapshot_store_failure');
        return hash('sha256', "$s:$c");
    },
    static fn(string $studentId): bool => $studentId !== 'missing-student',
    $outboxMetrics,
);

// Insert 1 valid outbox row with duplicates/whitespace and 1 empty row
$outboxRepo->append(new AiDataOutbox(
    'evt-valid-1',
    'assessment',
    'att-1',
    1,
    ['  student-1  ', 'student-2', 'student-1', ' ', ' student-2 '],
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
$outboxRepo->append(new AiDataOutbox(
    'evt-malformed-3',
    'badge',
    'bdg-2',
    1,
    ['student-ignored'],
    'badge.awarded',
    'hash-evt-3'
));
$pdo->prepare("UPDATE learner_ai_data_outbox SET affected_student_ids = 'not-json' WHERE id = 'evt-malformed-3'")->execute();
$outboxRepo->append(new AiDataOutbox(
    'evt-unresolvable-4',
    'badge',
    'bdg-3',
    1,
    ['missing-student'],
    'badge.awarded',
    'hash-evt-4'
));
$outboxRepo->append(new AiDataOutbox(
    'evt-transient-5',
    'assessment',
    'att-transient',
    1,
    ['transient-student'],
    'assessment.submitted',
    'hash-evt-5'
));

$consumed = $consumer->consume(10);
queue_assert($consumed === 1, 'only valid outbox rows are marked delivered');

$pendingOutbox = $outboxRepo->pending(10);
queue_assert(count($pendingOutbox) === 1 && $pendingOutbox[0]['id'] === 'evt-transient-5', 'transient dispatch failure remains pending for replay');
$errorRows = $pdo->query("SELECT id FROM learner_ai_data_outbox WHERE delivery_status='error' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
queue_assert($errorRows === ['evt-empty-2', 'evt-malformed-3', 'evt-unresolvable-4'], 'empty, malformed, and unresolvable rows remain visible as error evidence');
$outboxMetricEvents = $outboxMetrics->events();
queue_assert(count($outboxMetricEvents) === 4, 'each poison/transient outbox failure emits one metric');
queue_assert(isset($outboxMetricEvents[0]['queue_error']) && !isset($outboxMetricEvents[0]['provider_error']), 'outbox data errors do not inflate provider health metrics');
$transientJobsBeforeReplay = (int) $pdo->query("SELECT COUNT(*) FROM learner_ai_refresh_jobs WHERE student_id='transient-student'")->fetchColumn();
queue_assert($transientJobsBeforeReplay === 1, 'partial transient dispatch persists one idempotent roadmap job');
queue_assert($consumer->consume(10) === 1 && $outboxRepo->pending(10) === [], 'transient outbox failure succeeds on replay');
queue_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_ai_refresh_jobs WHERE student_id='transient-student'")->fetchColumn() === 3, 'outbox replay reuses the existing key and creates only missing capability jobs');

// Whitespace-wrapped duplicate IDs dispatch exactly once per normalized student/capability.
$studentJobKeys = [];
$studentRows = $pdo->query("SELECT student_id, capability FROM learner_ai_refresh_jobs WHERE student_id IN ('student-1','student-2')")->fetchAll(PDO::FETCH_ASSOC);
foreach ($studentRows as $queuedJob) {
    $studentJobKeys[$queuedJob['student_id'] . ':' . $queuedJob['capability']] = true;
}
queue_assert(count($studentJobKeys) === 6, 'normalized affected IDs dispatch once per capability');

// Test 5 source event types and school post-dispatch hook
$hookCalls = [];
$transientSchoolFail = 1;
$schoolConsumer = new AiDataOutboxConsumer(
    $outboxRepo,
    $dispatcher,
    static fn(string $s, string $c): string => hash('sha256', "$s:$c"),
    static fn(string $studentId): bool => true,
    $outboxMetrics,
    static function (array $students) use (&$hookCalls, &$transientSchoolFail): void {
        if ($transientSchoolFail > 0) {
            $transientSchoolFail--;
            throw new \RuntimeException('school_refresh_dispatch_failed');
        }
        $hookCalls[] = $students;
    }
);

$fiveEvents = [
    'assessment.submitted',
    'badge.awarded',
    'roadmap.progress_updated',
    'roadmap.feedback',
    'recommendation.feedback',
];
foreach ($fiveEvents as $idx => $evtType) {
    $outboxRepo->append(new AiDataOutbox(
        "evt-type-{$idx}",
        'aggregate',
        "agg-{$idx}",
        1,
        ["student-type-{$idx}"],
        $evtType,
        "hash-type-{$idx}"
    ));
}

// First consume: evt-type-0 hits transient hook failure
$consumed1 = $schoolConsumer->consume(10);
queue_assert($consumed1 === 4, '4 of 5 events delivered while first transient school error kept 1 event pending');
$pendingEvents = $outboxRepo->pending(10);
queue_assert(count($pendingEvents) === 1 && $pendingEvents[0]['id'] === 'evt-type-0', 'transient school dispatch failure leaves event pending');

// Check metrics for school_refresh_dispatch_failed
$schoolErrors = array_filter($outboxMetrics->events(), static fn(array $e): bool => ($e['queue_error'] ?? '') === 'school_refresh_dispatch_failed');
queue_assert(count($schoolErrors) === 1, 'school_refresh_dispatch_failed metric category is recorded');

// Replay pending event
$consumed2 = $schoolConsumer->consume(10);
queue_assert($consumed2 === 1 && $outboxRepo->pending(10) === [], 'replayed outbox event delivers after school dispatch recovers');
queue_assert(count($hookCalls) === 5, 'all 5 event types invoked the school post-dispatch hook');


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

// =========================================================================
// Block H: Superseded work is cancelled, never left processing for replay
// =========================================================================
$supersededRepo = new InMemoryAiRefreshJobRepository();
$superseded = $supersededRepo->enqueue('student-superseded', 'old-snapshot', 'roadmap');
$supersededWorker = new AiRefreshWorker(
    $supersededRepo,
    static function (AiRefreshJob $job, callable $leaseGuard): void {
        queue_assert($leaseGuard(), 'superseded handler initially owns its lease');
        throw new \RuntimeException('superseded_snapshot');
    },
    5,
);
queue_assert($supersededWorker->runOnce('worker-superseded'), 'superseded worker claims a job');
$supersededStored = $supersededRepo->all()[$superseded->jobKey] ?? null;
queue_assert($supersededStored instanceof AiRefreshJob && $supersededStored->status === 'cancelled', 'superseded claimed job is cancelled');
queue_assert($supersededStored->errorCode === 'superseded_snapshot', 'superseded cancellation keeps safe reason');

// Provider retry-after is classified safely and persisted for the next worker generation.
$retryRepo = new InMemoryAiRefreshJobRepository();
$retryJob = $retryRepo->enqueue('student-retry-after', 'retry-hash', 'recommendation');
$retryWorker = new AiRefreshWorker(
    $retryRepo,
    static function (AiRefreshJob $job, callable $leaseGuard): void {
        throw new ProviderRetryAfterException('rate_limit_exceeded', 999999);
    },
    5,
);
queue_assert($retryWorker->runOnce('worker-retry-after'), 'retry-after worker claims a job');
$retryStored = $retryRepo->all()[$retryJob->jobKey] ?? null;
queue_assert($retryStored instanceof AiRefreshJob && $retryStored->status === 'pending', 'provider retry-after remains pending');
queue_assert($retryStored->errorCode === 'rate_limit_exceeded' && $retryStored->nextRetryAt !== null, 'provider retry-after category and time are persisted');
queue_assert($retryRepo->enqueue('student-retry-after', 'retry-hash', 'recommendation')->jobKey === $retryJob->jobKey, 'worker restart reuses idempotent job key');

// A lost lease must stop provider work and remain explicitly retryable.
$leaseLossRepo = new class implements AiRefreshJobRepository {
    private InMemoryAiRefreshJobRepository $delegate;
    public function __construct() { $this->delegate = new InMemoryAiRefreshJobRepository(); }
    public function enqueue(string $studentId, string $snapshotHash, string $capability): AiRefreshJob { return $this->delegate->enqueue($studentId, $snapshotHash, $capability); }
    public function claimNext(string $workerId, int $leaseSeconds = 60): ?AiRefreshJob { return $this->delegate->claimNext($workerId, $leaseSeconds); }
    public function renewLease(string $jobKey, string $leaseToken, int $leaseSeconds = 60): bool { return false; }
    public function ownsLease(string $jobKey, string $leaseToken): bool { return $this->delegate->ownsLease($jobKey, $leaseToken); }
    public function complete(string $jobKey, ?string $leaseToken = null): bool { return $this->delegate->complete($jobKey, $leaseToken); }
    public function fail(string $jobKey, string $errorCode, bool $deadLetter = false, ?string $leaseToken = null, ?int $retryAfterSeconds = null): void { $this->delegate->fail($jobKey, $errorCode, $deadLetter, $leaseToken, $retryAfterSeconds); }
    public function cancelSuperseded(string $studentId, string $capability, string $currentSnapshotHash): void { $this->delegate->cancelSuperseded($studentId, $capability, $currentSnapshotHash); }
    public function cancel(string $jobKey, ?string $leaseToken = null): void { $this->delegate->cancel($jobKey, $leaseToken); }
    /** @return array<string,AiRefreshJob> */
    public function all(): array { return $this->delegate->all(); }
};
$leaseLossJob = $leaseLossRepo->enqueue('student-lease-loss', 'lease-loss-hash', 'roadmap');
$leaseLossHandlerCalled = false;
$leaseLossWorker = new AiRefreshWorker($leaseLossRepo, static function () use (&$leaseLossHandlerCalled): void { $leaseLossHandlerCalled = true; });
queue_assert($leaseLossWorker->runOnce('worker-lease-loss'), 'lease-loss worker claims the job');
$leaseLossStored = $leaseLossRepo->all()[$leaseLossJob->jobKey] ?? null;
queue_assert(!$leaseLossHandlerCalled, 'provider handler is not called after lease loss');
queue_assert($leaseLossStored instanceof AiRefreshJob && $leaseLossStored->status === 'pending', 'lease-loss job remains pending for retry');
queue_assert($leaseLossStored->errorCode === 'refresh_lease_lost', 'lease-loss uses the safe categorized error');

// Completion must be atomic: a lease lost between the guard and UPDATE cannot emit completed.
$completionRaceRepo = new class implements AiRefreshJobRepository {
    private InMemoryAiRefreshJobRepository $delegate;
    public function __construct() { $this->delegate = new InMemoryAiRefreshJobRepository(); }
    public function enqueue(string $studentId, string $snapshotHash, string $capability): AiRefreshJob { return $this->delegate->enqueue($studentId, $snapshotHash, $capability); }
    public function claimNext(string $workerId, int $leaseSeconds = 60): ?AiRefreshJob { return $this->delegate->claimNext($workerId, $leaseSeconds); }
    public function renewLease(string $jobKey, string $leaseToken, int $leaseSeconds = 60): bool { return $this->delegate->renewLease($jobKey, $leaseToken, $leaseSeconds); }
    public function ownsLease(string $jobKey, string $leaseToken): bool { return true; }
    public function complete(string $jobKey, ?string $leaseToken = null): bool { return false; }
    public function fail(string $jobKey, string $errorCode, bool $deadLetter = false, ?string $leaseToken = null, ?int $retryAfterSeconds = null): void { $this->delegate->fail($jobKey, $errorCode, $deadLetter, $leaseToken, $retryAfterSeconds); }
    public function cancelSuperseded(string $studentId, string $capability, string $currentSnapshotHash): void { $this->delegate->cancelSuperseded($studentId, $capability, $currentSnapshotHash); }
    public function cancel(string $jobKey, ?string $leaseToken = null): void { $this->delegate->cancel($jobKey, $leaseToken); }
};
$completionRaceMetrics = new AiMetricsCollector(100);
$completionRaceRepo->enqueue('student-completion-race', 'completion-race-hash', 'roadmap');
$completionRaceWorker = new AiRefreshWorker($completionRaceRepo, static function (): void {}, 5, null, $completionRaceMetrics);
queue_assert($completionRaceWorker->runOnce('worker-completion-race'), 'completion-race worker claims the job');
$completionEvents = array_column($completionRaceMetrics->events(), 'queue_event');
queue_assert(!in_array('completed', $completionEvents, true) && in_array('failed', $completionEvents, true), 'failed atomic completion never emits a completed metric');

// Provider health survives worker generations and permits one half-open probe.
$providerHealthPdo = createQueueTestPdo();
$providerClock = 1000;
$providerHealth = new CircuitBreaker(2, 30, static function () use (&$providerClock): int { return $providerClock; }, new DatabaseCircuitBreakerStore($providerHealthPdo), 'learner:test-provider');
queue_assert($providerHealth->allow(), 'closed provider circuit allows work');
$providerHealth->recordFailure();
queue_assert($providerHealth->state() === 'closed', 'first provider failure remains below threshold');
$providerHealth->recordFailure();
queue_assert($providerHealth->state() === 'open' && !$providerHealth->allow(), 'provider circuit opens at threshold');
$providerClock = 1031;
queue_assert($providerHealth->state() === 'half_open', 'provider circuit exposes half-open state after cooldown');
queue_assert($providerHealth->allow(), 'one worker acquires the persisted half-open probe');
queue_assert(!$providerHealth->allow(), 'second worker is blocked while half-open probe is active');
$providerHealth->recordSuccess();
queue_assert($providerHealth->state() === 'closed' && $providerHealth->allow(), 'successful probe closes persisted provider circuit');

// Bootstrap failures must not leak exception messages, provider payloads, or credentials.
$throwingBootstrap = tempnam(sys_get_temp_dir(), 'talenthub-worker-bootstrap-');
queue_assert(is_string($throwingBootstrap), 'worker bootstrap fixture is created');
file_put_contents($throwingBootstrap, "<?php throw new RuntimeException('secret-provider-payload');\n");
$workerCommand = [PHP_BINARY, dirname(__DIR__) . '/bin/worker-learner-ai-refresh.php'];
$workerDescriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$workerEnvironment = array_merge($_ENV, ['TALENTHUB_AI_WORKER_BOOTSTRAP' => $throwingBootstrap]);
$workerProcess = proc_open($workerCommand, $workerDescriptors, $workerPipes, dirname(__DIR__), $workerEnvironment);
queue_assert(is_resource($workerProcess), 'worker bootstrap failure process starts');
$workerStdout = stream_get_contents($workerPipes[1]);
$workerStderr = stream_get_contents($workerPipes[2]);
fclose($workerPipes[1]);
fclose($workerPipes[2]);
$workerExit = proc_close($workerProcess);
unlink($throwingBootstrap);
queue_assert($workerExit === 78, 'worker bootstrap failure uses configuration exit code');
queue_assert($workerStdout === '' && str_contains($workerStderr, 'failed safely'), 'worker reports a bounded bootstrap error');
queue_assert(!str_contains($workerStderr, 'secret-provider-payload'), 'worker never prints the underlying provider/bootstrap payload');

echo "learner_ai_queue_worker_test: OK\n";
