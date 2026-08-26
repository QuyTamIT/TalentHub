<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Persistence\DatabaseRoadmapRepository;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
use TalentHub\Learner\Ai\Rules\RuleRoadmapEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Validation\RoadmapAnalysisValidator;
use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once __DIR__ . '/fixtures/learner_ai_roadmap_v1.php';

function roadmap_repository_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

function roadmap_repository_expect(callable $operation, string $message): void
{
    try { $operation(); } catch (InvalidArgumentException|RuntimeException|PDOException) { return; }
    throw new RuntimeException('Assertion failed: ' . $message);
}

function roadmap_repository_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
    foreach (['student_profiles','activities','activity_registrations'] as $table) $pdo->exec("CREATE TABLE {$table} (id CHAR(36) NOT NULL PRIMARY KEY)");
    $runner = new LearnerForwardMigrationRunner($pdo, dirname(__DIR__) . '/Database/migrations/learner', new SchemaInspector($pdo, 'main'));
    foreach (['002_create_ai_input_foundation','003_create_ai_input_extensions','004_create_recommendation_store','005_create_ai_roadmap_store'] as $version) $runner->migrateApproved([$version]);
    return $pdo;
}

/** @return array{input:RecommendationInput,map:array<string,array{source_type:string,source_id:string}>} */
function roadmap_repository_input(string $suffix): array
{
    $codes = ['holland','mbti','disc','multiple_intelligence'];
    $records = $evidence = $map = [];
    foreach ($codes as $index => $code) {
        $record = ['test_type' => $code, 'result_code' => strtoupper(substr($code, 0, 3)), 'dimension_scores' => ['A' => 70 + $index], 'submitted_at' => '2026-08-20T00:00:00+00:00'];
        $sourceId = sprintf('%s0000000-0000-4000-8000-%012d', $suffix, $index + 1);
        $records[] = $record;
        $evidence[] = ['source_type' => 'assessment', 'source_id' => $sourceId, 'observed_at' => $record['submitted_at'], 'safe_value' => $record];
        $map[sprintf('evidence-%03d', $index + 1)] = ['source_type' => 'assessment', 'source_id' => $sourceId];
    }
    return [
        'input' => new RecommendationInput(['profile' => ['study_status' => 'active'], 'assessments' => $records, 'skills' => [], 'activities' => [], 'evaluations' => [], 'opportunities' => []], ['assessment' => '2026-08-20T00:00:00+00:00'], ['allowed_scopes' => ['assessment'], 'missing_consent_scopes' => ['activity','evaluation','skills']], $evidence),
        'map' => $map,
    ];
}

function roadmap_repository_seed_run(PDO $pdo, string $studentId, string $snapshotId, string $runId, array $fixture, string $origin = 'model'): void
{
    $pdo->prepare('INSERT OR IGNORE INTO student_profiles (id) VALUES (?)')->execute([$studentId]);
    $input = $fixture['input'];
    $pdo->prepare('INSERT INTO learner_recommendation_input_snapshots (id,studentId,schemaVersion,contentHash,consentScopesJson,qualityFlagsJson,payloadJson,sourceUpdatedAt) VALUES (?,?,?,?,?,?,?,?)')->execute([$snapshotId,$studentId,'1.0',$input->contentHash(),'["assessment"]',json_encode($input->qualityFlags()),json_encode($input->payload()),json_encode($input->sourceUpdatedAt())]);
    foreach ($input->evidenceReferences() as $index => $reference) {
        $pdo->prepare('INSERT INTO learner_recommendation_snapshot_evidence (id,snapshotId,sourceType,sourceId,observedAt,safeValueJson) VALUES (?,?,?,?,?,?)')->execute([sprintf('evidence-row-%s-%02d', substr($runId, 0, 8), $index),$snapshotId,$reference['source_type'],$reference['source_id'],$reference['observed_at'],json_encode($reference['safe_value'])]);
    }
    if ($origin === 'model') {
        $pdo->prepare("INSERT INTO learner_recommendation_runs (id,studentId,snapshotId,idempotencyKey,engineType,status,provider,modelVersion,promptVersion,startedAt,completedAt) VALUES (?,?,?,?,?,'completed',?,?,?,'2026-08-24','2026-08-24')")->execute([$runId,$studentId,$snapshotId,'idem-'.$runId,'model','9router_gemini','ag/gemini-3.7-flash-high','learner-roadmap-prompt-1.1.0']);
    } else {
        $pdo->prepare("INSERT INTO learner_recommendation_runs (id,studentId,snapshotId,idempotencyKey,engineType,status,ruleVersion,fallbackReason,startedAt,completedAt) VALUES (?,?,?,?,?,'fallback',?,?,'2026-08-24','2026-08-24')")->execute([$runId,$studentId,$snapshotId,'idem-'.$runId,'rule',RuleRoadmapEngine::VERSION,'provider_unavailable']);
    }
}

roadmap_repository_assert(class_exists(DatabaseRoadmapRepository::class), 'database roadmap repository is loaded');
$pdo = roadmap_repository_fixture();
$studentA = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$studentB = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$fixtureA = roadmap_repository_input('a');
$snapshotA = '11111111-1111-4111-8111-111111111111';
$runA = '22222222-2222-4222-8222-222222222222';
roadmap_repository_seed_run($pdo, $studentA, $snapshotA, $runA, $fixtureA, 'model');
$validator = new RoadmapAnalysisValidator(array_keys($fixtureA['map']), []);
$analysisA = $validator->fromProviderPayload(learner_ai_roadmap_provider_fixture(), [
    'origin' => 'model', 'provider' => '9router_gemini', 'model_version' => 'ag/gemini-3.7-flash-high',
    'prompt_version' => 'learner-roadmap-prompt-1.1.0', 'confidence_band' => 'high',
    'provider_request_id' => null, 'response_hash' => str_repeat('a', 64),
]);
$clockValues = ['2026-08-24T01:00:00.000000+00:00','2026-08-24T01:01:00.000000+00:00','2026-08-24T01:02:00.000000+00:00','2026-08-24T01:03:00.000000+00:00','2026-08-24T01:04:00.000000+00:00'];
$clockIndex = 0;
$repository = new DatabaseRoadmapRepository($pdo, static function () use (&$clockValues, &$clockIndex): string { return $clockValues[min($clockIndex++, count($clockValues) - 1)]; });
$auditA = ['provider_request_id' => null, 'response_hash' => str_repeat('a', 64), 'evidence_reference_map' => $fixtureA['map']];
$savedA = $repository->saveCompleted($studentA, $runA, $analysisA, $auditA);
roadmap_repository_assert($savedA['version'] === 1 && $savedA['status'] === 'active', 'first roadmap is active version one');
roadmap_repository_assert($savedA['analysis_origin'] === 'model', 'model provenance is returned truthfully');
roadmap_repository_assert($savedA['input_hash'] === $fixtureA['input']->contentHash(), 'roadmap exposes its immutable snapshot hash for refresh reuse');
roadmap_repository_assert(count($savedA['phases']) === 3 && count($savedA['phases'][0]['tasks']) === 3, 'roadmap phases and tasks are persisted');
$reusedA = $repository->saveCompleted($studentA, $runA, $analysisA, $auditA);
roadmap_repository_assert($reusedA['roadmap_id'] === $savedA['roadmap_id'] && $reusedA['reused'] === true, 'same completed run is idempotently reused');
roadmap_repository_assert((int) $pdo->query('SELECT COUNT(*) FROM learner_ai_roadmaps')->fetchColumn() === 1, 'idempotent reuse does not duplicate rows');

roadmap_repository_expect(fn () => $repository->saveCompleted($studentA, $runA, $analysisA, array_merge($auditA, ['response_hash' => str_repeat('b', 64)])), 'provider audit mismatch is rejected');
roadmap_repository_assert($repository->latestForStudent($studentB) === null, 'another learner cannot read the roadmap');

$fixtureB = roadmap_repository_input('b');
$snapshotB = '33333333-3333-4333-8333-333333333333';
$runB = '44444444-4444-4444-8444-444444444444';
roadmap_repository_seed_run($pdo, $studentA, $snapshotB, $runB, $fixtureB, 'rule');
$fallback = (new RuleRoadmapEngine())->generate($fixtureB['input'], new RecommendationContext(['assessment'], null, null, $studentA))->withFallbackReason('provider_unavailable');
$savedB = $repository->saveCompleted($studentA, $runB, $fallback, ['evidence_reference_map' => $fixtureB['map']]);
roadmap_repository_assert($savedB['version'] === 2 && $savedB['analysis_origin'] === 'rule_fallback', 'fallback roadmap receives the next version and explicit origin');
roadmap_repository_assert($pdo->query("SELECT status FROM learner_ai_roadmaps WHERE id='" . $savedA['roadmap_id'] . "'")->fetchColumn() === 'superseded', 'new version supersedes previous active roadmap');

$taskId = $savedB['phases'][0]['tasks'][0]['task_id'];
$started = $repository->appendTaskEvent($studentA, $taskId, 'in_progress', 'progress-request-1');
roadmap_repository_assert($started['status'] === 'in_progress' && $started['reused'] === false, 'task can start');
$completed = $repository->appendTaskEvent($studentA, $taskId, 'completed', 'progress-request-2');
roadmap_repository_assert($completed['status'] === 'completed', 'started task can complete');
$duplicate = $repository->appendTaskEvent($studentA, $taskId, 'completed', 'progress-request-2');
roadmap_repository_assert($duplicate['event_id'] === $completed['event_id'] && $duplicate['reused'] === true, 'duplicate progress request is reused');
roadmap_repository_expect(fn () => $repository->appendTaskEvent($studentA, $taskId, 'skipped', 'progress-request-3'), 'completed task rejects later transitions');
roadmap_repository_expect(fn () => $repository->appendTaskEvent($studentB, $taskId, 'in_progress', 'cross-owner-progress'), 'another learner cannot update task progress');
$latest = $repository->latestForStudent($studentA);
roadmap_repository_assert($latest['roadmap_id'] === $savedB['roadmap_id'] && $latest['phases'][0]['tasks'][0]['status'] === 'completed', 'latest roadmap restores persisted task progress');
roadmap_repository_assert($latest['progress']['completed_tasks'] === 1 && $latest['progress']['total_tasks'] === 9, 'overall progress is derived from latest events');

$badFixture = learner_ai_roadmap_provider_fixture();
$badActivityId = '99999999-9999-4999-8999-999999999999';
$badFixture['phases'][0]['tasks'][0]['action'] = ['type' => 'register_activity', 'activity_source_id' => $badActivityId];
$badFixture['recommended_activity_source_ids'] = [$badActivityId];
$badValidator = new RoadmapAnalysisValidator(array_keys($fixtureA['map']), [$badActivityId]);
$badAnalysis = $badValidator->fromProviderPayload($badFixture, [
    'origin' => 'model', 'provider' => '9router_gemini', 'model_version' => 'ag/gemini-3.7-flash-high',
    'prompt_version' => 'learner-roadmap-prompt-1.1.0', 'confidence_band' => 'high',
    'provider_request_id' => 'router_req_bad', 'response_hash' => str_repeat('d', 64),
]);
$snapshotC = '55555555-5555-4555-8555-555555555555';
$runC = '66666666-6666-4666-8666-666666666666';
$fixtureC = roadmap_repository_input('c');
roadmap_repository_seed_run($pdo, $studentA, $snapshotC, $runC, $fixtureC, 'model');
$beforeCount = (int) $pdo->query('SELECT COUNT(*) FROM learner_ai_roadmaps')->fetchColumn();
roadmap_repository_expect(fn () => $repository->saveCompleted($studentA, $runC, $badAnalysis, ['provider_request_id' => 'router_req_bad', 'response_hash' => str_repeat('d', 64), 'evidence_reference_map' => $fixtureC['map']]), 'unknown activity target is rejected');
roadmap_repository_assert((int) $pdo->query('SELECT COUNT(*) FROM learner_ai_roadmaps')->fetchColumn() === $beforeCount, 'failed save rolls the transaction back');
roadmap_repository_assert($repository->latestForStudent($studentA)['roadmap_id'] === $savedB['roadmap_id'], 'failed save preserves prior active roadmap');

$studentD = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
$pdo->prepare('INSERT INTO student_profiles (id) VALUES (?)')->execute([$studentD]);
$fixtureD = roadmap_repository_input('d');
$recommendations = new DatabaseRecommendationRepository($pdo, static fn (): string => '2026-08-24T02:00:00.000000+00:00');
$contextD = new RecommendationContext(['assessment'], 'request-roadmap-d', 'idempotency-roadmap-d', $studentD);
$pendingD = $recommendations->createPendingRoadmapRun($studentD, $fixtureD['input'], $contextD);
roadmap_repository_assert(($repository->latestPendingForStudent($studentD)['state'] ?? null) === 'pending', 'roadmap run marker makes pending state owner-readable');
$validatorD = new RoadmapAnalysisValidator(array_keys($fixtureD['map']), []);
$analysisD = $validatorD->fromProviderPayload(learner_ai_roadmap_provider_fixture(), [
    'origin' => 'model', 'provider' => '9router_gemini', 'model_version' => 'ag/gemini-3.7-flash-high',
    'prompt_version' => 'learner-roadmap-prompt-1.1.0', 'confidence_band' => 'high',
    'provider_request_id' => 'router_req_d', 'response_hash' => str_repeat('e', 64),
]);
$completedRunD = $recommendations->completeRoadmapRun($studentD, $pendingD['runId'], $analysisD);
roadmap_repository_assert($completedRunD['status'] === 'completed' && $completedRunD['engineType'] === 'model', 'recommendation repository completes a roadmap model run without recommendation items');
roadmap_repository_assert($repository->latestPendingForStudent($studentD) === null, 'completed roadmap run is no longer pending');
$savedD = $repository->saveCompleted($studentD, $pendingD['runId'], $analysisD, ['provider_request_id'=>'router_req_d','response_hash'=>str_repeat('e',64),'evidence_reference_map'=>$fixtureD['map']]);
roadmap_repository_assert($savedD['version'] === 1, 'completed roadmap run can be atomically persisted');

echo "learner_ai_roadmap_repository_test: OK\n";
