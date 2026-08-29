<?php
declare(strict_types=1);

use TalentHub\Modules\School\Repository\DatabaseSchoolAiRefreshJobRepository;
use TalentHub\Modules\School\Repository\SchoolAiAggregateRepository;
use TalentHub\Modules\School\Service\SchoolAiRefreshCoordinator;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/LearnerForwardMigration.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/ForwardMigrationDefinition.php';
require_once dirname(__DIR__) . '/src/Modules/School/Repository/DatabaseSchoolAiRefreshJobRepository.php';
require_once dirname(__DIR__) . '/src/Modules/School/Repository/SchoolAiAggregateRepository.php';
require_once dirname(__DIR__) . '/src/Modules/School/Service/SchoolAiRefreshCoordinator.php';

function school_queue_assert(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Run migration 013 to create school_ai_refresh_jobs
$definition = require dirname(__DIR__) . '/database/migrations/learner/013_create_school_ai_refresh_jobs.php';
foreach ($definition->migration->statements('sqlite') as $sql) {
    $pdo->exec($sql);
}

// Create school schema for aggregate and coordinator
$pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel INTEGER)');
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT, studyStatus TEXT DEFAULT \'active\')');
$pdo->exec('CREATE TABLE privacy_consents (id TEXT, studentId TEXT, scope TEXT, isGranted INTEGER, revokedAt TEXT)');
$pdo->exec('CREATE TABLE learner_ai_consent_events (id TEXT, studentId TEXT, scope TEXT, action TEXT, occurredAt TEXT, requestId TEXT)');
$pdo->exec('CREATE TABLE learner_ai_capability_profiles (id TEXT, student_id TEXT, status TEXT, talent_map_json TEXT, trend_signals_json TEXT, evidence_json TEXT DEFAULT \'[]\', generated_at TEXT, superseded_at TEXT)');
$pdo->exec('CREATE TABLE school_ai_insights (id TEXT PRIMARY KEY, school_id TEXT, aggregate_hash TEXT, payload_json TEXT, model_version TEXT, generated_at TEXT, stale_since TEXT, UNIQUE(school_id, aggregate_hash, model_version))');

$queue = new DatabaseSchoolAiRefreshJobRepository($pdo);
$hash = str_repeat('a', 64);
$firstJobId = $queue->enqueue('school-a', $hash);
$duplicatePending = $queue->enqueue('school-a', $hash);
school_queue_assert((int) $pdo->query('SELECT COUNT(*) FROM school_ai_refresh_jobs')->fetchColumn() === 1, 'queue enqueue is idempotent by school and aggregate hash');
school_queue_assert($firstJobId !== null && $duplicatePending === null, 'same-hash pending job is an idempotent no-op');

for ($attempt = 1; $attempt <= 3; $attempt++) {
    $job = $queue->claim();
    school_queue_assert(is_array($job), 'retryable job can be claimed');
    $queue->fail((int) $job['id']);
    if ($attempt < 3) {
        $pdo->exec("UPDATE school_ai_refresh_jobs SET next_retry_at='2000-01-01 00:00:00'");
    }
}
school_queue_assert($pdo->query("SELECT status FROM school_ai_refresh_jobs")->fetchColumn() === 'dead_letter', 'third failed attempt moves school insight refresh to dead-letter');
school_queue_assert($queue->enqueue('school-a', $hash) === null, 'same-hash dead-letter job is an idempotent no-op');

// Test cancelSuperseded, complete, and cancel
$hash1 = str_repeat('1', 64);
$hash2 = str_repeat('2', 64);
$queue->enqueue('school-b', $hash1);
$queue->cancelSuperseded('school-b', $hash2);
$cancelled = $pdo->query("SELECT status FROM school_ai_refresh_jobs WHERE school_id='school-b' AND aggregate_hash='$hash1'")->fetchColumn();
school_queue_assert($cancelled === 'cancelled', 'cancelSuperseded marks older pending hash as cancelled');
school_queue_assert($queue->enqueue('school-b', $hash1) === null, 'same-hash cancelled job is an idempotent no-op');

$job2Id = $queue->enqueue('school-b', $hash2);
school_queue_assert($job2Id !== null, 'new aggregate hash enqueues new job');
$claim2 = $queue->claim();
school_queue_assert($claim2 !== null && (int) $claim2['id'] === $job2Id, 'new job can be claimed');
$queue->complete((int) $claim2['id']);
$completedStatus = $pdo->query("SELECT status FROM school_ai_refresh_jobs WHERE id=$job2Id")->fetchColumn();
school_queue_assert($completedStatus === 'completed', 'complete marks job as completed');
school_queue_assert($queue->enqueue('school-b', $hash2) === null, 'same-hash completed job is an idempotent no-op without unique-key failure');

// Test SchoolAiRefreshCoordinator
$pdo->exec("INSERT INTO classes VALUES ('c1', 'school-coord-1', '10A1', 10)");
for ($i = 1; $i <= 6; $i++) {
    $stu = "student-coord-$i";
    $pdo->exec("INSERT INTO student_profiles VALUES ('$stu', 'c1', 'active')");
    $pdo->exec("INSERT INTO learner_ai_capability_profiles VALUES ('prof-$stu', '$stu', 'ready_model', '[{\"field\":\"Toan\",\"score\":80}]', '[]', '[]', '2026-08-28 00:00:00', NULL)");
    foreach (['assessment', 'skills', 'activity', 'evaluation'] as $scope) {
        $pdo->exec("INSERT INTO learner_ai_consent_events VALUES ('evt-$stu-$scope', '$stu', '$scope', 'granted', '2026-08-28 00:00:00', 'req')");
    }
}

$aggregates = new SchoolAiAggregateRepository($pdo);
$coordinator = new SchoolAiRefreshCoordinator($pdo, $aggregates, $queue, 5);

$dispatchResult = $coordinator->dispatchForStudents(['student-coord-1', 'student-coord-2']);
school_queue_assert(count($dispatchResult['school_ids']) === 1 && $dispatchResult['school_ids'][0] === 'school-coord-1', 'coordinator resolves school from students');
school_queue_assert($dispatchResult['job_count'] === 1, 'coordinator enqueues 1 job for the school');

// Redundant dispatch for same aggregate state should not create new row
$dispatchResult2 = $coordinator->dispatchForStudents(['student-coord-3']);
school_queue_assert($dispatchResult2['job_count'] === 0, 'unchanged aggregate hash does not create duplicate pending job');

$coordinatorJob = $queue->claim();
school_queue_assert($coordinatorJob !== null, 'coordinator job can be claimed for terminal dedupe coverage');
$queue->complete((int) $coordinatorJob['id']);
$dispatchResult3 = $coordinator->dispatchForStudents(['student-coord-4']);
school_queue_assert($dispatchResult3['job_count'] === 0, 'coordinator delegates terminal same-hash dedupe to repository and counts no row');

echo "school_ai_refresh_queue_test: OK\n";
