<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner;
use TalentHub\Learner\Data\Readiness\PhaseRequirements;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function roadmap_schema_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

function roadmap_schema_reject(PDO $pdo, string $sql, string $message): void
{
    try { $pdo->exec($sql); } catch (PDOException) { return; }
    throw new RuntimeException('Assertion failed: ' . $message);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
foreach ([
    'student_profiles' => 'id CHAR(36) NOT NULL PRIMARY KEY',
    'activities' => 'id CHAR(36) NOT NULL PRIMARY KEY',
    'activity_registrations' => 'id CHAR(36) NOT NULL PRIMARY KEY',
] as $table => $columns) $pdo->exec("CREATE TABLE {$table} ({$columns})");

$directory = dirname(__DIR__) . '/Database/migrations/learner';
$inspector = new SchemaInspector($pdo, 'main');
$runner = new LearnerForwardMigrationRunner($pdo, $directory, $inspector);
foreach (['002_create_ai_input_foundation','003_create_ai_input_extensions','004_create_recommendation_store','005_create_ai_roadmap_store'] as $version) {
    roadmap_schema_assert($runner->migrateApproved([$version]) === [$version], "first apply runs {$version}");
}
roadmap_schema_assert($runner->migrateApproved(['005_create_ai_roadmap_store']) === [], 'second roadmap migration apply is a no-op');

$phase12 = (new PhaseRequirements())->forPhase(12);
foreach (['learner_ai_roadmaps','learner_ai_roadmap_phases','learner_ai_roadmap_tasks','learner_ai_roadmap_task_events'] as $table) {
    roadmap_schema_assert(in_array($table, $phase12['tables'], true), "phase 12 readiness includes {$table}");
}

$contract = [
    'learner_ai_roadmaps' => ['uq_learner_ai_roadmaps_student_version','uq_learner_ai_roadmaps_run','uq_learner_ai_roadmaps_id_student','idx_learner_ai_roadmaps_student_status_generated'],
    'learner_ai_roadmap_phases' => ['uq_learner_ai_roadmap_phases_position','idx_learner_ai_roadmap_phases_roadmap'],
    'learner_ai_roadmap_tasks' => ['uq_learner_ai_roadmap_tasks_position','idx_learner_ai_roadmap_tasks_phase'],
    'learner_ai_roadmap_task_events' => ['uq_learner_ai_roadmap_task_events_request','idx_learner_ai_roadmap_task_events_task_occurred','idx_learner_ai_roadmap_task_events_student_created'],
];
foreach ($contract as $table => $indexes) {
    roadmap_schema_assert($inspector->hasTable($table), "table exists: {$table}");
    foreach ($indexes as $index) roadmap_schema_assert($inspector->hasIndex($table, $index), "index exists: {$index}");
}

$studentA = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$studentB = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$snapshotA = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$snapshotB = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
$runA = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
$runB = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
$roadmapA = '11111111-1111-4111-8111-111111111111';
$phaseA = '22222222-2222-4222-8222-222222222222';
$taskA = '33333333-3333-4333-8333-333333333333';
$pdo->exec("INSERT INTO student_profiles (id) VALUES ('{$studentA}'),('{$studentB}')");
$pdo->exec("INSERT INTO learner_recommendation_input_snapshots (id,studentId,schemaVersion,contentHash,consentScopesJson,qualityFlagsJson,payloadJson,sourceUpdatedAt) VALUES ('{$snapshotA}','{$studentA}','1.0','" . str_repeat('a', 64) . "','[\"assessment\"]','{}','{}','{}'),('{$snapshotB}','{$studentB}','1.0','" . str_repeat('b', 64) . "','[\"assessment\"]','{}','{}','{}')");
$pdo->exec("INSERT INTO learner_recommendation_runs (id,studentId,snapshotId,idempotencyKey,engineType,status,provider,modelVersion,promptVersion,startedAt,completedAt) VALUES ('{$runA}','{$studentA}','{$snapshotA}','roadmap-a','model','completed','9router','model-test','prompt-test','2026-08-24','2026-08-24'),('{$runB}','{$studentB}','{$snapshotB}','roadmap-b','model','completed','9router','model-test','prompt-test','2026-08-24','2026-08-24')");
$roadmapInsert = "INSERT INTO learner_ai_roadmaps (id,studentId,runId,versionNumber,contractVersion,status,executiveSummary,primaryDirectionJson,alternativeDirectionsJson,insightsJson,confidenceBand,evidenceSummaryJson,providerRequestId,responseHash,generatedAt) VALUES ('{$roadmapA}','{$studentA}','{$runA}',1,'learner-roadmap-1.0.0','active','Tóm tắt lộ trình','{\"code\":\"technology\"}','[]','[]','high','{\"assessment_count\":4}','router_req','" . str_repeat('c', 64) . "','2026-08-24')";
$pdo->exec($roadmapInsert);
$pdo->exec("INSERT INTO learner_ai_roadmap_phases (id,roadmapId,position,startDay,endDay,code,title,goal,skillFocus,deliverable,effortLabel,metricLabel,evidenceJson) VALUES ('{$phaseA}','{$roadmapA}',1,0,30,'discover','Khám phá','Hoàn thành thử nghiệm','Tư duy','Bản demo','3 giờ/tuần','Hai phản hồi','[\"evidence-001\"]')");
$pdo->exec("INSERT INTO learner_ai_roadmap_tasks (id,phaseId,position,title,description,estimatedMinutes,actionType,targetType,targetId,evidenceJson) VALUES ('{$taskA}','{$phaseA}',1,'Chọn vấn đề','Mô tả vấn đề thực tế',45,'self_task',NULL,NULL,'[\"evidence-001\"]')");
$pdo->exec("INSERT INTO learner_ai_roadmap_task_events (id,taskId,studentId,status,requestId,occurredAt) VALUES ('44444444-4444-4444-8444-444444444444','{$taskA}','{$studentA}','completed','request-1','2026-08-24')");

roadmap_schema_reject($pdo, str_replace("'{$studentA}','{$runA}',1", "'{$studentB}','{$runA}',2", str_replace("'{$roadmapA}'", "'55555555-5555-4555-8555-555555555555'", $roadmapInsert)), 'roadmap rejects cross-learner run ownership');
roadmap_schema_reject($pdo, str_replace("'{$roadmapA}'", "'66666666-6666-4666-8666-666666666666'", $roadmapInsert), 'roadmap rejects duplicate learner version and run');
roadmap_schema_reject($pdo, "UPDATE learner_ai_roadmaps SET executiveSummary='Nội dung bị đổi' WHERE id='{$roadmapA}'", 'generated roadmap content is immutable');
$pdo->exec("UPDATE learner_ai_roadmaps SET status='superseded', supersededAt='2026-08-25' WHERE id='{$roadmapA}'");
roadmap_schema_assert($pdo->query("SELECT status FROM learner_ai_roadmaps WHERE id='{$roadmapA}'")->fetchColumn() === 'superseded', 'one-way supersede transition is allowed');
roadmap_schema_reject($pdo, "UPDATE learner_ai_roadmaps SET status='active', supersededAt=NULL WHERE id='{$roadmapA}'", 'superseded roadmap cannot reactivate');
roadmap_schema_reject($pdo, "UPDATE learner_ai_roadmap_phases SET title='Đổi' WHERE id='{$phaseA}'", 'phase is immutable');
roadmap_schema_reject($pdo, "UPDATE learner_ai_roadmap_tasks SET title='Đổi' WHERE id='{$taskA}'", 'task is immutable');
roadmap_schema_reject($pdo, "INSERT INTO learner_ai_roadmap_task_events (id,taskId,studentId,status,requestId,occurredAt) VALUES ('77777777-7777-4777-8777-777777777777','{$taskA}','{$studentB}','completed','request-cross','2026-08-24')", 'task events reject cross-learner ownership');
roadmap_schema_reject($pdo, "INSERT INTO learner_ai_roadmap_task_events (id,taskId,studentId,status,requestId,occurredAt) VALUES ('88888888-8888-4888-8888-888888888888','{$taskA}','{$studentA}','completed','request-1','2026-08-24')", 'task events are idempotent per request');
roadmap_schema_reject($pdo, "UPDATE learner_ai_roadmap_task_events SET status='reopened' WHERE requestId='request-1'", 'task events reject updates');
roadmap_schema_reject($pdo, "DELETE FROM learner_ai_roadmap_task_events WHERE requestId='request-1'", 'task events reject deletes');
roadmap_schema_reject($pdo, "INSERT INTO learner_ai_roadmap_phases (id,roadmapId,position,startDay,endDay,code,title,goal,skillFocus,deliverable,effortLabel,metricLabel,evidenceJson) VALUES ('99999999-9999-4999-8999-999999999999','{$roadmapA}',2,30,60,'bad','Sai','Sai','Sai','Sai','Sai','Sai','[]')", 'phase ranges must be exact');

echo "learner_ai_roadmap_schema_test: OK\n";
