<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\LearnerMigrationChecksum;
use TalentHub\Learner\Data\Migrations\LearnerRoadmapRegistryReconciler;

$root = dirname(__DIR__);
require_once $root . '/app/learner/data/Database/SchemaInspector.php';
require_once $root . '/app/learner/data/Migrations/LearnerMigrationChecksum.php';
require_once $root . '/app/learner/data/Migrations/LearnerRoadmapRegistryReconciler.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new \RuntimeException('Assertion failed: ' . $message);
};

$pdo = new \PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE learner_ai_roadmaps (id CHAR(36) PRIMARY KEY, studentId CHAR(36), runId CHAR(36), versionNumber INT, contractVersion TEXT, status TEXT, executiveSummary TEXT, primaryDirectionJson TEXT, alternativeDirectionsJson TEXT, insightsJson TEXT, confidenceBand TEXT, evidenceSummaryJson TEXT, providerRequestId TEXT, responseHash CHAR(64), generatedAt TEXT, supersededAt TEXT, createdAt TEXT)');
$pdo->exec('CREATE TABLE learner_ai_roadmap_phases (id CHAR(36) PRIMARY KEY, roadmapId CHAR(36), position INT, startDay INT, endDay INT, code TEXT, title TEXT, goal TEXT, skillFocus TEXT, deliverable TEXT, effortLabel TEXT, metricLabel TEXT, evidenceJson TEXT, createdAt TEXT)');
$pdo->exec('CREATE TABLE learner_ai_roadmap_tasks (id CHAR(36) PRIMARY KEY, phaseId CHAR(36), position INT, title TEXT, description TEXT, estimatedMinutes INT, actionType TEXT, targetType TEXT, targetId CHAR(36), evidenceJson TEXT, createdAt TEXT)');
$pdo->exec('CREATE TABLE learner_ai_roadmap_task_events (id CHAR(36) PRIMARY KEY, taskId CHAR(36), studentId CHAR(36), status TEXT, requestId TEXT, occurredAt TEXT, createdAt TEXT)');
$pdo->exec('CREATE UNIQUE INDEX uq_learner_ai_roadmaps_student_version ON learner_ai_roadmaps(studentId, versionNumber)');
$pdo->exec('CREATE UNIQUE INDEX uq_learner_ai_roadmaps_run ON learner_ai_roadmaps(runId)');
$pdo->exec('CREATE UNIQUE INDEX uq_learner_ai_roadmaps_id_student ON learner_ai_roadmaps(id, studentId)');
$pdo->exec('CREATE INDEX idx_learner_ai_roadmaps_student_status_generated ON learner_ai_roadmaps(studentId, status, generatedAt)');
$pdo->exec('CREATE UNIQUE INDEX uq_learner_ai_roadmap_phases_position ON learner_ai_roadmap_phases(roadmapId, position)');
$pdo->exec('CREATE INDEX idx_learner_ai_roadmap_phases_roadmap ON learner_ai_roadmap_phases(roadmapId)');
$pdo->exec('CREATE UNIQUE INDEX uq_learner_ai_roadmap_tasks_position ON learner_ai_roadmap_tasks(phaseId, position)');
$pdo->exec('CREATE INDEX idx_learner_ai_roadmap_tasks_phase ON learner_ai_roadmap_tasks(phaseId)');
$pdo->exec('CREATE UNIQUE INDEX uq_learner_ai_roadmap_task_events_request ON learner_ai_roadmap_task_events(taskId, requestId)');
$pdo->exec('CREATE INDEX idx_learner_ai_roadmap_task_events_task_occurred ON learner_ai_roadmap_task_events(taskId, occurredAt)');
$pdo->exec('CREATE INDEX idx_learner_ai_roadmap_task_events_student_created ON learner_ai_roadmap_task_events(studentId, createdAt)');

$migrationPath = $root . '/Database/migrations/learner/005_create_ai_roadmap_store.php';
$schema = new SchemaInspector($pdo, 'main');
LearnerRoadmapRegistryReconciler::reconcile($pdo, $schema, $migrationPath);

$row = $pdo->query("SELECT version, name, checksum FROM learner_forward_migrations WHERE version = '005_create_ai_roadmap_store'")->fetch(PDO::FETCH_ASSOC);
$assert(is_array($row), 'reconciliation records the pre-existing roadmap migration');
$assert(($row['checksum'] ?? '') === LearnerMigrationChecksum::canonical($migrationPath), 'reconciliation records the canonical migration checksum');

LearnerRoadmapRegistryReconciler::reconcile($pdo, $schema, $migrationPath);
$count = (int) $pdo->query("SELECT COUNT(*) FROM learner_forward_migrations WHERE version = '005_create_ai_roadmap_store'")->fetchColumn();
$assert($count === 1, 'reconciliation is idempotent');

echo "learner_ai_roadmap_registry_reconciliation_test: OK\n";
