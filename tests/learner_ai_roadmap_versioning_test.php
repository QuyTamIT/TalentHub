<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Validation\RoadmapAnalysisValidator;

require __DIR__ . '/learner_ai_roadmap_repository_test.php';

roadmap_repository_assert(method_exists($repository, 'historyForStudent'), 'repository exposes owner-scoped version history');
roadmap_repository_assert(method_exists($repository, 'versionForStudent'), 'repository exposes owner-scoped historical versions');
roadmap_repository_assert(method_exists($repository, 'appendRoadmapFeedback'), 'repository persists roadmap feedback without a new table');
roadmap_repository_assert(method_exists($repository, 'feedbackSignalsForStudent'), 'repository exposes allowlisted aggregate feedback signals');

$history = $repository->historyForStudent($studentA);
roadmap_repository_assert(array_column($history, 'version') === [2, 1], 'history is newest first');
roadmap_repository_assert($history[0]['roadmap_id'] === $savedB['roadmap_id'], 'history belongs to the learner');
roadmap_repository_assert(in_array('analysis_origin', $history[0]['changed_sections'], true), 'history explains which sections changed');
roadmap_repository_assert($repository->historyForStudent($studentB) === [], 'another learner cannot read roadmap history');

$older = $repository->versionForStudent($studentA, 1);
roadmap_repository_assert(($older['roadmap_id'] ?? null) === $savedA['roadmap_id'], 'learner can reopen an owned historical version');
roadmap_repository_assert($repository->versionForStudent($studentB, 1) === null, 'historical version lookup is owner scoped');

$feedbackRequest = '77777777-7777-4777-8777-777777777777';
$feedback = $repository->appendRoadmapFeedback($studentA, $savedB['roadmap_id'], 'not_helpful', 'too_generic', $feedbackRequest);
roadmap_repository_assert(($feedback['state'] ?? null) === 'feedback_saved', 'roadmap feedback is saved');
roadmap_repository_assert(!array_key_exists('safe_comment', $feedback), 'roadmap feedback never returns or stores free-form comments');
$feedbackReplay = $repository->appendRoadmapFeedback($studentA, $savedB['roadmap_id'], 'not_helpful', 'too_generic', $feedbackRequest);
roadmap_repository_assert(($feedbackReplay['reused'] ?? false) === true, 'feedback request is idempotent');
roadmap_repository_expect(
    fn () => $repository->appendRoadmapFeedback($studentB, $savedB['roadmap_id'], 'helpful', 'useful_direction', '88888888-8888-4888-8888-888888888888'),
    'another learner cannot submit feedback for a roadmap',
);
roadmap_repository_expect(
    fn () => $repository->appendRoadmapFeedback($studentA, $savedB['roadmap_id'], 'not_helpful', 'inject-anything', '99999999-9999-4999-8999-999999999999'),
    'non-allowlisted feedback reason is rejected',
);

$signals = $repository->feedbackSignalsForStudent($studentA);
roadmap_repository_assert($signals === [['verdict' => 'not_helpful', 'reason_code' => 'too_generic', 'count' => 1]], 'only aggregate allowlisted feedback reaches the next AI snapshot');

// Registration links are resolved from current eligibility, never invented by the browser.
$pdo->exec('ALTER TABLE student_profiles ADD COLUMN classId CHAR(36) NULL');
$pdo->exec('CREATE TABLE schools (id CHAR(36) PRIMARY KEY, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE classes (id CHAR(36) PRIMARY KEY, schoolId CHAR(36) NOT NULL)');
foreach ([
    'schoolId CHAR(36) NULL', 'title TEXT NULL', 'category TEXT NULL', 'startAt TEXT NULL',
    'endAt TEXT NULL', 'capacity INTEGER NULL', 'status TEXT NULL',
] as $column) $pdo->exec('ALTER TABLE activities ADD COLUMN ' . $column);
foreach (['activityId CHAR(36) NULL','studentId CHAR(36) NULL','status TEXT NULL'] as $column) $pdo->exec('ALTER TABLE activity_registrations ADD COLUMN ' . $column);
$schoolId = '10101010-1010-4010-8010-101010101010';
$classId = '20202020-2020-4020-8020-202020202020';
$activityId = '30303030-3030-4030-8030-303030303030';
$pdo->prepare('INSERT INTO schools VALUES (?,?)')->execute([$schoolId, 'Trường thử nghiệm']);
$pdo->prepare('INSERT INTO classes VALUES (?,?)')->execute([$classId, $schoolId]);
$pdo->prepare('UPDATE student_profiles SET classId = ? WHERE id = ?')->execute([$classId, $studentA]);
$pdo->prepare("INSERT INTO activities (id,schoolId,title,category,startAt,endAt,capacity,status) VALUES (?,?,?,?,?,?,?,'published')")
    ->execute([$activityId,$schoolId,'Phòng lab sản phẩm','technology','2026-09-01','2027-09-01',1]);

$fixtureE = roadmap_repository_input('e');
$payloadE = $fixtureE['input']->payload();
$payloadE['opportunities'] = [['title'=>'Phòng lab sản phẩm','category'=>'technology','location'=>'Trường thử nghiệm','deadline_at'=>'2027-09-01T00:00:00+00:00','opportunity_type'=>'activity','status'=>'published']];
$evidenceE = $fixtureE['input']->evidenceReferences();
$evidenceE[] = ['source_type'=>'opportunity','source_id'=>$activityId,'observed_at'=>'2026-08-24T00:00:00+00:00','safe_value'=>$payloadE['opportunities'][0]];
$inputE = new RecommendationInput($payloadE, $fixtureE['input']->sourceUpdatedAt(), $fixtureE['input']->qualityFlags(), $evidenceE);
$fixtureE = ['input'=>$inputE,'map'=>$fixtureE['map'] + ['evidence-005'=>['source_type'=>'opportunity','source_id'=>$activityId]]];
$providerE = learner_ai_roadmap_provider_fixture();
$providerE['phases'][0]['tasks'][0]['action'] = ['type'=>'register_activity','activity_source_id'=>$activityId];
$providerE['recommended_activity_source_ids'] = [$activityId];
$analysisE = (new RoadmapAnalysisValidator(array_keys($fixtureE['map']), [$activityId]))->fromProviderPayload($providerE, [
    'origin'=>'model','provider'=>'9router_gemini','model_version'=>'ag/gemini-3.7-flash-high',
    'prompt_version'=>'learner-roadmap-prompt-1.1.0','confidence_band'=>'high','provider_request_id'=>'router_req_e','response_hash'=>str_repeat('f',64),
]);
$snapshotE = '40404040-4040-4040-8040-404040404040';
$runE = '50505050-5050-4050-8050-505050505050';
roadmap_repository_seed_run($pdo, $studentA, $snapshotE, $runE, $fixtureE, 'model');
$savedE = $repository->saveCompleted($studentA, $runE, $analysisE, ['provider_request_id'=>'router_req_e','response_hash'=>str_repeat('f',64),'evidence_reference_map'=>$fixtureE['map']]);
$actionE = $savedE['phases'][0]['tasks'][0]['action'];
roadmap_repository_assert(($actionE['registration_path'] ?? null) === '/app/learner/activity-detail.php?id=' . $activityId, 'eligible activity receives a server-validated registration path');
$pdo->prepare("INSERT INTO activity_registrations (id,activityId,studentId,status) VALUES (?,?,?,'approved')")
    ->execute(['60606060-6060-4060-8060-606060606060',$activityId,'other-student']);
$fullAction = $repository->latestForStudent($studentA)['phases'][0]['tasks'][0]['action'];
roadmap_repository_assert(!isset($fullAction['registration_path']) && $fullAction['availability'] === 'unavailable', 'full activity does not expose a registration link');
$pdo->exec('DELETE FROM activity_registrations');
$pdo->prepare("UPDATE activities SET status = 'closed' WHERE id = ?")->execute([$activityId]);
$closedAction = $repository->latestForStudent($studentA)['phases'][0]['tasks'][0]['action'];
roadmap_repository_assert(!isset($closedAction['registration_path']), 'closed activity does not expose a registration link');

echo "learner_ai_roadmap_versioning_test: OK\n";
