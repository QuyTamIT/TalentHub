<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
use TalentHub\Learner\Data\Migrations\LearnerMigrationPreflight;

return new ForwardMigrationDefinition(
    '005_create_ai_roadmap_store',
    'Create versioned learner AI roadmap store',
    __FILE__,
    hash_file('sha256', __FILE__),
    new class implements LearnerForwardMigration, LearnerMigrationPreflight {
        private const RECOMMENDATION_VERSION = '004_create_recommendation_store';
        private const RECOMMENDATION_CHECKSUM = '48d7eaf7122cae13d5dbcb1dbaa2e157c34f2f4cea8f0c430914f193be48f0be';
        private const EXPECTED_SCHEMA = [
            'learner_ai_roadmaps' => ['columns' => ['id','studentId','runId','versionNumber','contractVersion','status','executiveSummary','primaryDirectionJson','alternativeDirectionsJson','insightsJson','confidenceBand','evidenceSummaryJson','providerRequestId','responseHash','generatedAt','supersededAt','createdAt'], 'indexes' => ['uq_learner_ai_roadmaps_student_version','uq_learner_ai_roadmaps_run','uq_learner_ai_roadmaps_id_student','idx_learner_ai_roadmaps_student_status_generated']],
            'learner_ai_roadmap_phases' => ['columns' => ['id','roadmapId','position','startDay','endDay','code','title','goal','skillFocus','deliverable','effortLabel','metricLabel','evidenceJson','createdAt'], 'indexes' => ['uq_learner_ai_roadmap_phases_position','idx_learner_ai_roadmap_phases_roadmap']],
            'learner_ai_roadmap_tasks' => ['columns' => ['id','phaseId','position','title','description','estimatedMinutes','actionType','targetType','targetId','evidenceJson','createdAt'], 'indexes' => ['uq_learner_ai_roadmap_tasks_position','idx_learner_ai_roadmap_tasks_phase']],
            'learner_ai_roadmap_task_events' => ['columns' => ['id','taskId','studentId','status','requestId','occurredAt','createdAt'], 'indexes' => ['uq_learner_ai_roadmap_task_events_request','idx_learner_ai_roadmap_task_events_task_occurred','idx_learner_ai_roadmap_task_events_student_created']],
        ];

        public function version(): string { return '005_create_ai_roadmap_store'; }
        public function description(): string { return 'Create versioned learner AI roadmap store'; }

        public function assertBeforeApply(SchemaInspector $schemaInspector): void
        {
            $checksum = $schemaInspector->migrationChecksum(self::RECOMMENDATION_VERSION);
            if ($checksum === null || !hash_equals(self::RECOMMENDATION_CHECKSUM, $checksum)) {
                throw new RuntimeException('Learner roadmap migration requires verified recommendation store: ' . self::RECOMMENDATION_VERSION);
            }
            foreach (['student_profiles', 'learner_recommendation_runs'] as $parent) {
                if (!$schemaInspector->hasTable($parent) || $schemaInspector->columnType($parent, 'id') !== 'CHAR(36)' || !$schemaInspector->hasPrimaryKey($parent, 'id')) {
                    throw new RuntimeException('Learner roadmap migration requires CHAR(36) primary parent: ' . $parent . '.id');
                }
                if ($schemaInspector->isMySql() && !$schemaInspector->hasMySqlTableOptions($parent, 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci')) {
                    throw new RuntimeException('Learner roadmap migration requires compatible MySQL parent: ' . $parent);
                }
            }
            if (!$schemaInspector->hasColumn('learner_recommendation_runs', 'studentId')) {
                throw new RuntimeException('Learner roadmap migration requires recommendation run owner column.');
            }
            if ($schemaInspector->isMySql() && $schemaInspector->mysqlSessionTimeZone() !== '+00:00') {
                throw new RuntimeException('Learner roadmap migration requires MySQL session time zone +00:00');
            }
            foreach (array_keys(self::EXPECTED_SCHEMA) as $table) {
                if ($schemaInspector->hasTable($table)) throw new RuntimeException('Learner roadmap migration rejected existing target: ' . $table);
            }
        }

        public function statements(string $driver): array
        {
            return match (strtolower($driver)) {
                'mysql' => self::mysqlStatements(),
                'sqlite' => self::sqliteStatements(),
                default => throw new RuntimeException('Unsupported learner roadmap migration driver: ' . $driver),
            };
        }

        public function expectedSchema(): array { return self::EXPECTED_SCHEMA; }

        private static function mysqlStatements(): array
        {
            return [
                <<<'SQL'
CREATE TABLE learner_ai_roadmaps (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, runId CHAR(36) NOT NULL, versionNumber INT UNSIGNED NOT NULL, contractVersion VARCHAR(100) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active', executiveSummary VARCHAR(2000) NOT NULL, primaryDirectionJson LONGTEXT NOT NULL, alternativeDirectionsJson LONGTEXT NOT NULL, insightsJson LONGTEXT NOT NULL, confidenceBand VARCHAR(20) NOT NULL, evidenceSummaryJson LONGTEXT NOT NULL, providerRequestId VARCHAR(128) NULL, responseHash CHAR(64) NULL, generatedAt DATETIME(6) NOT NULL, supersededAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_ai_roadmaps_student_version (studentId, versionNumber), UNIQUE KEY uq_learner_ai_roadmaps_run (runId), UNIQUE KEY uq_learner_ai_roadmaps_id_student (id, studentId), KEY idx_learner_ai_roadmaps_student_status_generated (studentId, status, generatedAt),
  CONSTRAINT fk_learner_ai_roadmaps_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_ai_roadmaps_run FOREIGN KEY (runId) REFERENCES learner_recommendation_runs(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_ai_roadmaps_version CHECK (versionNumber >= 1), CONSTRAINT chk_learner_ai_roadmaps_status CHECK (status IN ('active','superseded')), CONSTRAINT chk_learner_ai_roadmaps_confidence CHECK (confidenceBand IN ('low','medium','high')),
  CONSTRAINT chk_learner_ai_roadmaps_primary_json CHECK (JSON_VALID(primaryDirectionJson)), CONSTRAINT chk_learner_ai_roadmaps_alternatives_json CHECK (JSON_VALID(alternativeDirectionsJson)), CONSTRAINT chk_learner_ai_roadmaps_insights_json CHECK (JSON_VALID(insightsJson)), CONSTRAINT chk_learner_ai_roadmaps_evidence_summary_json CHECK (JSON_VALID(evidenceSummaryJson)),
  CONSTRAINT chk_learner_ai_roadmaps_response_hash CHECK (responseHash IS NULL OR responseHash REGEXP '^[a-f0-9]{64}$'), CONSTRAINT chk_learner_ai_roadmaps_superseded CHECK ((status = 'active' AND supersededAt IS NULL) OR (status = 'superseded' AND supersededAt IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_ai_roadmap_phases (
  id CHAR(36) NOT NULL, roadmapId CHAR(36) NOT NULL, position TINYINT UNSIGNED NOT NULL, startDay TINYINT UNSIGNED NOT NULL, endDay TINYINT UNSIGNED NOT NULL, code VARCHAR(64) NOT NULL, title VARCHAR(255) NOT NULL, goal VARCHAR(1000) NOT NULL, skillFocus VARCHAR(500) NOT NULL, deliverable VARCHAR(1000) NOT NULL, effortLabel VARCHAR(255) NOT NULL, metricLabel VARCHAR(1000) NOT NULL, evidenceJson LONGTEXT NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_ai_roadmap_phases_position (roadmapId, position), KEY idx_learner_ai_roadmap_phases_roadmap (roadmapId),
  CONSTRAINT fk_learner_ai_roadmap_phases_roadmap FOREIGN KEY (roadmapId) REFERENCES learner_ai_roadmaps(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_ai_roadmap_phases_range CHECK ((position = 1 AND startDay = 0 AND endDay = 30) OR (position = 2 AND startDay = 31 AND endDay = 60) OR (position = 3 AND startDay = 61 AND endDay = 90)), CONSTRAINT chk_learner_ai_roadmap_phases_evidence_json CHECK (JSON_VALID(evidenceJson))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_ai_roadmap_tasks (
  id CHAR(36) NOT NULL, phaseId CHAR(36) NOT NULL, position TINYINT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, description VARCHAR(1500) NOT NULL, estimatedMinutes SMALLINT UNSIGNED NOT NULL, actionType VARCHAR(30) NOT NULL, targetType VARCHAR(30) NULL, targetId CHAR(36) NULL, evidenceJson LONGTEXT NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_ai_roadmap_tasks_position (phaseId, position), KEY idx_learner_ai_roadmap_tasks_phase (phaseId),
  CONSTRAINT fk_learner_ai_roadmap_tasks_phase FOREIGN KEY (phaseId) REFERENCES learner_ai_roadmap_phases(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_ai_roadmap_tasks_position CHECK (position BETWEEN 1 AND 5), CONSTRAINT chk_learner_ai_roadmap_tasks_minutes CHECK (estimatedMinutes BETWEEN 5 AND 1440), CONSTRAINT chk_learner_ai_roadmap_tasks_action CHECK ((actionType = 'self_task' AND targetType IS NULL AND targetId IS NULL) OR (actionType = 'register_activity' AND targetType = 'activity' AND targetId IS NOT NULL)), CONSTRAINT chk_learner_ai_roadmap_tasks_evidence_json CHECK (JSON_VALID(evidenceJson))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_ai_roadmap_task_events (
  id CHAR(36) NOT NULL, taskId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, status VARCHAR(20) NOT NULL, requestId VARCHAR(100) NOT NULL, occurredAt DATETIME(6) NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_ai_roadmap_task_events_request (taskId, requestId), KEY idx_learner_ai_roadmap_task_events_task_occurred (taskId, occurredAt), KEY idx_learner_ai_roadmap_task_events_student_created (studentId, createdAt),
  CONSTRAINT fk_learner_ai_roadmap_task_events_task FOREIGN KEY (taskId) REFERENCES learner_ai_roadmap_tasks(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT fk_learner_ai_roadmap_task_events_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_ai_roadmap_task_events_status CHECK (status IN ('completed','reopened'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TRIGGER trg_learner_ai_roadmaps_owner_insert BEFORE INSERT ON learner_ai_roadmaps FOR EACH ROW
BEGIN
  IF NOT EXISTS (SELECT 1 FROM learner_recommendation_runs AS runs WHERE runs.id = NEW.runId AND runs.studentId = NEW.studentId) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'roadmap learner ownership mismatch';
  END IF;
END;
SQL,
                <<<'SQL'
CREATE TRIGGER trg_learner_ai_roadmaps_lifecycle_update BEFORE UPDATE ON learner_ai_roadmaps FOR EACH ROW
BEGIN
  IF NOT (OLD.status = 'active' AND NEW.status = 'superseded' AND OLD.supersededAt IS NULL AND NEW.supersededAt IS NOT NULL
    AND OLD.id <=> NEW.id AND OLD.studentId <=> NEW.studentId AND OLD.runId <=> NEW.runId AND OLD.versionNumber <=> NEW.versionNumber AND OLD.contractVersion <=> NEW.contractVersion
    AND OLD.executiveSummary <=> NEW.executiveSummary AND OLD.primaryDirectionJson <=> NEW.primaryDirectionJson AND OLD.alternativeDirectionsJson <=> NEW.alternativeDirectionsJson AND OLD.insightsJson <=> NEW.insightsJson
    AND OLD.confidenceBand <=> NEW.confidenceBand AND OLD.evidenceSummaryJson <=> NEW.evidenceSummaryJson AND OLD.providerRequestId <=> NEW.providerRequestId AND OLD.responseHash <=> NEW.responseHash AND OLD.generatedAt <=> NEW.generatedAt AND OLD.createdAt <=> NEW.createdAt) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'roadmap generated content is immutable';
  END IF;
END;
SQL,
                "CREATE TRIGGER trg_learner_ai_roadmaps_immutable_delete BEFORE DELETE ON learner_ai_roadmaps FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'roadmap generated content is immutable'; END;",
                "CREATE TRIGGER trg_learner_ai_roadmap_phases_immutable_update BEFORE UPDATE ON learner_ai_roadmap_phases FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'roadmap phase is immutable'; END;",
                "CREATE TRIGGER trg_learner_ai_roadmap_phases_immutable_delete BEFORE DELETE ON learner_ai_roadmap_phases FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'roadmap phase is immutable'; END;",
                "CREATE TRIGGER trg_learner_ai_roadmap_tasks_immutable_update BEFORE UPDATE ON learner_ai_roadmap_tasks FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'roadmap task is immutable'; END;",
                "CREATE TRIGGER trg_learner_ai_roadmap_tasks_immutable_delete BEFORE DELETE ON learner_ai_roadmap_tasks FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'roadmap task is immutable'; END;",
                <<<'SQL'
CREATE TRIGGER trg_learner_ai_roadmap_task_events_owner_insert BEFORE INSERT ON learner_ai_roadmap_task_events FOR EACH ROW
BEGIN
  IF NOT EXISTS (SELECT 1 FROM learner_ai_roadmap_tasks AS tasks INNER JOIN learner_ai_roadmap_phases AS phases ON phases.id = tasks.phaseId INNER JOIN learner_ai_roadmaps AS roadmaps ON roadmaps.id = phases.roadmapId WHERE tasks.id = NEW.taskId AND roadmaps.studentId = NEW.studentId) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'roadmap task event learner ownership mismatch';
  END IF;
END;
SQL,
                "CREATE TRIGGER trg_learner_ai_roadmap_task_events_append_update BEFORE UPDATE ON learner_ai_roadmap_task_events FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only roadmap task event'; END;",
                "CREATE TRIGGER trg_learner_ai_roadmap_task_events_append_delete BEFORE DELETE ON learner_ai_roadmap_task_events FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only roadmap task event'; END;",
            ];
        }

        private static function sqliteStatements(): array
        {
            return [
                "CREATE TABLE learner_ai_roadmaps (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, runId CHAR(36) NOT NULL, versionNumber INTEGER NOT NULL, contractVersion VARCHAR(100) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active', executiveSummary VARCHAR(2000) NOT NULL, primaryDirectionJson TEXT NOT NULL, alternativeDirectionsJson TEXT NOT NULL, insightsJson TEXT NOT NULL, confidenceBand VARCHAR(20) NOT NULL, evidenceSummaryJson TEXT NOT NULL, providerRequestId VARCHAR(128) NULL, responseHash CHAR(64) NULL, generatedAt TEXT NOT NULL, supersededAt TEXT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (runId) REFERENCES learner_recommendation_runs(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (versionNumber >= 1), CHECK (status IN ('active','superseded')), CHECK (confidenceBand IN ('low','medium','high')), CHECK (json_valid(primaryDirectionJson)), CHECK (json_valid(alternativeDirectionsJson)), CHECK (json_valid(insightsJson)), CHECK (json_valid(evidenceSummaryJson)), CHECK (responseHash IS NULL OR (length(responseHash) = 64 AND responseHash NOT GLOB '*[^a-f0-9]*')), CHECK ((status = 'active' AND supersededAt IS NULL) OR (status = 'superseded' AND supersededAt IS NOT NULL)))",
                "CREATE TABLE learner_ai_roadmap_phases (id CHAR(36) NOT NULL PRIMARY KEY, roadmapId CHAR(36) NOT NULL, position INTEGER NOT NULL, startDay INTEGER NOT NULL, endDay INTEGER NOT NULL, code VARCHAR(64) NOT NULL, title VARCHAR(255) NOT NULL, goal VARCHAR(1000) NOT NULL, skillFocus VARCHAR(500) NOT NULL, deliverable VARCHAR(1000) NOT NULL, effortLabel VARCHAR(255) NOT NULL, metricLabel VARCHAR(1000) NOT NULL, evidenceJson TEXT NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (roadmapId) REFERENCES learner_ai_roadmaps(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK ((position = 1 AND startDay = 0 AND endDay = 30) OR (position = 2 AND startDay = 31 AND endDay = 60) OR (position = 3 AND startDay = 61 AND endDay = 90)), CHECK (json_valid(evidenceJson)))",
                "CREATE TABLE learner_ai_roadmap_tasks (id CHAR(36) NOT NULL PRIMARY KEY, phaseId CHAR(36) NOT NULL, position INTEGER NOT NULL, title VARCHAR(255) NOT NULL, description VARCHAR(1500) NOT NULL, estimatedMinutes INTEGER NOT NULL, actionType VARCHAR(30) NOT NULL, targetType VARCHAR(30) NULL, targetId CHAR(36) NULL, evidenceJson TEXT NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (phaseId) REFERENCES learner_ai_roadmap_phases(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (position BETWEEN 1 AND 5), CHECK (estimatedMinutes BETWEEN 5 AND 1440), CHECK ((actionType = 'self_task' AND targetType IS NULL AND targetId IS NULL) OR (actionType = 'register_activity' AND targetType = 'activity' AND targetId IS NOT NULL)), CHECK (json_valid(evidenceJson)))",
                "CREATE TABLE learner_ai_roadmap_task_events (id CHAR(36) NOT NULL PRIMARY KEY, taskId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, status VARCHAR(20) NOT NULL, requestId VARCHAR(100) NOT NULL, occurredAt TEXT NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (taskId) REFERENCES learner_ai_roadmap_tasks(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (status IN ('completed','reopened')))" ,
                'CREATE UNIQUE INDEX uq_learner_ai_roadmaps_student_version ON learner_ai_roadmaps (studentId, versionNumber)',
                'CREATE UNIQUE INDEX uq_learner_ai_roadmaps_run ON learner_ai_roadmaps (runId)',
                'CREATE UNIQUE INDEX uq_learner_ai_roadmaps_id_student ON learner_ai_roadmaps (id, studentId)',
                'CREATE INDEX idx_learner_ai_roadmaps_student_status_generated ON learner_ai_roadmaps (studentId, status, generatedAt)',
                'CREATE UNIQUE INDEX uq_learner_ai_roadmap_phases_position ON learner_ai_roadmap_phases (roadmapId, position)',
                'CREATE INDEX idx_learner_ai_roadmap_phases_roadmap ON learner_ai_roadmap_phases (roadmapId)',
                'CREATE UNIQUE INDEX uq_learner_ai_roadmap_tasks_position ON learner_ai_roadmap_tasks (phaseId, position)',
                'CREATE INDEX idx_learner_ai_roadmap_tasks_phase ON learner_ai_roadmap_tasks (phaseId)',
                'CREATE UNIQUE INDEX uq_learner_ai_roadmap_task_events_request ON learner_ai_roadmap_task_events (taskId, requestId)',
                'CREATE INDEX idx_learner_ai_roadmap_task_events_task_occurred ON learner_ai_roadmap_task_events (taskId, occurredAt)',
                'CREATE INDEX idx_learner_ai_roadmap_task_events_student_created ON learner_ai_roadmap_task_events (studentId, createdAt)',
                "CREATE TRIGGER trg_learner_ai_roadmaps_owner_insert BEFORE INSERT ON learner_ai_roadmaps FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_recommendation_runs AS runs WHERE runs.id = NEW.runId AND runs.studentId = NEW.studentId) BEGIN SELECT RAISE(ABORT, 'roadmap learner ownership mismatch'); END",
                "CREATE TRIGGER trg_learner_ai_roadmaps_lifecycle_update BEFORE UPDATE ON learner_ai_roadmaps FOR EACH ROW WHEN NOT (OLD.status = 'active' AND NEW.status = 'superseded' AND OLD.supersededAt IS NULL AND NEW.supersededAt IS NOT NULL AND OLD.id IS NEW.id AND OLD.studentId IS NEW.studentId AND OLD.runId IS NEW.runId AND OLD.versionNumber IS NEW.versionNumber AND OLD.contractVersion IS NEW.contractVersion AND OLD.executiveSummary IS NEW.executiveSummary AND OLD.primaryDirectionJson IS NEW.primaryDirectionJson AND OLD.alternativeDirectionsJson IS NEW.alternativeDirectionsJson AND OLD.insightsJson IS NEW.insightsJson AND OLD.confidenceBand IS NEW.confidenceBand AND OLD.evidenceSummaryJson IS NEW.evidenceSummaryJson AND OLD.providerRequestId IS NEW.providerRequestId AND OLD.responseHash IS NEW.responseHash AND OLD.generatedAt IS NEW.generatedAt AND OLD.createdAt IS NEW.createdAt) BEGIN SELECT RAISE(ABORT, 'roadmap generated content is immutable'); END",
                "CREATE TRIGGER trg_learner_ai_roadmaps_immutable_delete BEFORE DELETE ON learner_ai_roadmaps FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'roadmap generated content is immutable'); END",
                "CREATE TRIGGER trg_learner_ai_roadmap_phases_immutable_update BEFORE UPDATE ON learner_ai_roadmap_phases FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'roadmap phase is immutable'); END",
                "CREATE TRIGGER trg_learner_ai_roadmap_phases_immutable_delete BEFORE DELETE ON learner_ai_roadmap_phases FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'roadmap phase is immutable'); END",
                "CREATE TRIGGER trg_learner_ai_roadmap_tasks_immutable_update BEFORE UPDATE ON learner_ai_roadmap_tasks FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'roadmap task is immutable'); END",
                "CREATE TRIGGER trg_learner_ai_roadmap_tasks_immutable_delete BEFORE DELETE ON learner_ai_roadmap_tasks FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'roadmap task is immutable'); END",
                "CREATE TRIGGER trg_learner_ai_roadmap_task_events_owner_insert BEFORE INSERT ON learner_ai_roadmap_task_events FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_ai_roadmap_tasks AS tasks INNER JOIN learner_ai_roadmap_phases AS phases ON phases.id = tasks.phaseId INNER JOIN learner_ai_roadmaps AS roadmaps ON roadmaps.id = phases.roadmapId WHERE tasks.id = NEW.taskId AND roadmaps.studentId = NEW.studentId) BEGIN SELECT RAISE(ABORT, 'roadmap task event learner ownership mismatch'); END",
                "CREATE TRIGGER trg_learner_ai_roadmap_task_events_append_update BEFORE UPDATE ON learner_ai_roadmap_task_events FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only roadmap task event'); END",
                "CREATE TRIGGER trg_learner_ai_roadmap_task_events_append_delete BEFORE DELETE ON learner_ai_roadmap_task_events FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only roadmap task event'); END",
            ];
        }
    },
);
