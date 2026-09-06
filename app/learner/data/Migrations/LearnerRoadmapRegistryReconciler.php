<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Migrations;

use PDO;
use RuntimeException;
use TalentHub\Learner\Data\Database\SchemaInspector;

/**
 * Reconciles the roadmap store created by the legacy deployment migration with
 * the forward-only learner migration registry. It never creates, alters or
 * deletes roadmap data; it only records migration 005 after verifying that the
 * existing schema satisfies the canonical contract.
 */
final class LearnerRoadmapRegistryReconciler
{
    private const VERSION = '005_create_ai_roadmap_store';
    private const NAME = 'Create versioned learner AI roadmap store';

    /** @return array<string,array{columns:list<string>,indexes:list<string>}> */
    private static function expectedSchema(): array
    {
        return [
            'learner_ai_roadmaps' => [
                'columns' => ['id', 'studentId', 'runId', 'versionNumber', 'contractVersion', 'status', 'executiveSummary', 'primaryDirectionJson', 'alternativeDirectionsJson', 'insightsJson', 'confidenceBand', 'evidenceSummaryJson', 'providerRequestId', 'responseHash', 'generatedAt', 'supersededAt', 'createdAt'],
                'indexes' => ['uq_learner_ai_roadmaps_student_version', 'uq_learner_ai_roadmaps_run', 'uq_learner_ai_roadmaps_id_student', 'idx_learner_ai_roadmaps_student_status_generated'],
            ],
            'learner_ai_roadmap_phases' => [
                'columns' => ['id', 'roadmapId', 'position', 'startDay', 'endDay', 'code', 'title', 'goal', 'skillFocus', 'deliverable', 'effortLabel', 'metricLabel', 'evidenceJson', 'createdAt'],
                'indexes' => ['uq_learner_ai_roadmap_phases_position', 'idx_learner_ai_roadmap_phases_roadmap'],
            ],
            'learner_ai_roadmap_tasks' => [
                'columns' => ['id', 'phaseId', 'position', 'title', 'description', 'estimatedMinutes', 'actionType', 'targetType', 'targetId', 'evidenceJson', 'createdAt'],
                'indexes' => ['uq_learner_ai_roadmap_tasks_position', 'idx_learner_ai_roadmap_tasks_phase'],
            ],
            'learner_ai_roadmap_task_events' => [
                'columns' => ['id', 'taskId', 'studentId', 'status', 'requestId', 'occurredAt', 'createdAt'],
                'indexes' => ['uq_learner_ai_roadmap_task_events_request', 'idx_learner_ai_roadmap_task_events_task_occurred', 'idx_learner_ai_roadmap_task_events_student_created'],
            ],
        ];
    }

    public static function reconcile(PDO $pdo, SchemaInspector $schemaInspector, string $migrationPath): void
    {
        self::assertExistingSchema($schemaInspector);
        $pdo->exec('CREATE TABLE IF NOT EXISTS learner_forward_migrations (version VARCHAR(191) PRIMARY KEY, name VARCHAR(255) NOT NULL, checksum CHAR(64) NOT NULL, description TEXT NOT NULL, appliedAt VARCHAR(40) NOT NULL)');

        $checksum = LearnerMigrationChecksum::canonical($migrationPath);
        $lookup = $pdo->prepare('SELECT checksum FROM learner_forward_migrations WHERE version = :version');
        $lookup->execute(['version' => self::VERSION]);
        $existing = $lookup->fetchColumn();
        if ($existing !== false) {
            if (!hash_equals($checksum, (string) $existing)) {
                throw new RuntimeException('Existing roadmap migration registry checksum drift.');
            }
            return;
        }

        $insert = $pdo->prepare('INSERT INTO learner_forward_migrations (version, name, checksum, description, appliedAt) VALUES (:version, :name, :checksum, :description, :appliedAt)');
        $insert->execute([
            'version' => self::VERSION,
            'name' => self::NAME,
            'checksum' => $checksum,
            'description' => self::NAME,
            'appliedAt' => gmdate('c'),
        ]);
    }

    public static function assertExistingSchema(SchemaInspector $schemaInspector): void
    {
        foreach (self::expectedSchema() as $table => $expected) {
            if (!$schemaInspector->hasTable($table)) {
                throw new RuntimeException('Existing roadmap schema is incomplete: missing table ' . $table);
            }
            foreach ($expected['columns'] as $column) {
                if (!$schemaInspector->hasColumn($table, $column)) {
                    throw new RuntimeException('Existing roadmap schema is incomplete: missing column ' . $table . '.' . $column);
                }
            }
            foreach ($expected['indexes'] as $index) {
                if (!$schemaInspector->hasIndex($table, $index)) {
                    throw new RuntimeException('Existing roadmap schema is incomplete: missing index ' . $table . '.' . $index);
                }
            }
            if ($schemaInspector->isMySql() && !$schemaInspector->hasMySqlTableOptions($table, 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci')) {
                throw new RuntimeException('Existing roadmap schema has incompatible table options: ' . $table);
            }
        }
    }
}
