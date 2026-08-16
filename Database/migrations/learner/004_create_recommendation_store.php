<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
use TalentHub\Learner\Data\Migrations\LearnerMigrationPreflight;

return new ForwardMigrationDefinition(
    '004_create_recommendation_store',
    'Create learner recommendation store',
    __FILE__,
    hash_file('sha256', __FILE__),
    new class implements LearnerForwardMigration, LearnerMigrationPreflight {
        private const FOUNDATION_VERSION = '002_create_ai_input_foundation';
        private const FOUNDATION_CHECKSUM = 'f1c7d125c475fddad946448b9a320ae6207ea5903eaa2d652fb456d505a929bc';
        private const EXTENSIONS_VERSION = '003_create_ai_input_extensions';
        private const EXTENSIONS_CHECKSUM = '6b2c5674e4da5d98bc7540881f90ce5fab421d2cf52e41b7899f51a87d563c38';
        private const EXPECTED_SCHEMA = [
            'learner_recommendation_input_snapshots' => ['columns' => ['id', 'studentId', 'schemaVersion', 'contentHash', 'consentScopesJson', 'qualityFlagsJson', 'payloadJson', 'sourceUpdatedAt', 'createdAt'], 'indexes' => ['uq_learner_recommendation_input_snapshots_student_hash', 'idx_learner_recommendation_input_snapshots_student_created']],
            'learner_recommendation_runs' => ['columns' => ['id', 'studentId', 'snapshotId', 'idempotencyKey', 'engineType', 'status', 'ruleVersion', 'provider', 'modelVersion', 'promptVersion', 'fallbackReason', 'safeErrorCode', 'startedAt', 'completedAt', 'createdAt'], 'indexes' => ['uq_learner_recommendation_runs_student_idempotency', 'idx_learner_recommendation_runs_student_created', 'idx_learner_recommendation_runs_snapshot']],
            'learner_recommendation_items' => ['columns' => ['id', 'runId', 'itemType', 'title', 'summary', 'priority', 'confidenceBand', 'actionJson', 'lifecycleStatus', 'createdAt'], 'indexes' => ['idx_learner_recommendation_items_run_lifecycle_priority']],
            'learner_recommendation_evidence' => ['columns' => ['id', 'itemId', 'sourceType', 'sourceId', 'observedAt', 'contributionLabel', 'safeValueJson', 'createdAt'], 'indexes' => ['uq_learner_recommendation_evidence_item_source', 'idx_learner_recommendation_evidence_source']],
            'learner_recommendation_feedback' => ['columns' => ['id', 'studentId', 'itemId', 'verdict', 'reasonCode', 'safeComment', 'createdAt'], 'indexes' => ['idx_learner_recommendation_feedback_student_created', 'idx_learner_recommendation_feedback_item']],
            'learner_recommendation_audit_events' => ['columns' => ['id', 'runId', 'studentId', 'requestId', 'actorType', 'action', 'engineMetadataJson', 'status', 'createdAt'], 'indexes' => ['idx_learner_recommendation_audit_events_student_created', 'idx_learner_recommendation_audit_events_run_created']],
        ];

        public function version(): string
        {
            return '004_create_recommendation_store';
        }

        public function description(): string
        {
            return 'Create learner recommendation store';
        }

        public function assertBeforeApply(SchemaInspector $schemaInspector): void
        {
            $foundationChecksum = $schemaInspector->migrationChecksum(self::FOUNDATION_VERSION);
            if ($foundationChecksum === null || !hash_equals(self::FOUNDATION_CHECKSUM, $foundationChecksum)) {
                throw new RuntimeException('Learner migration preflight requires verified Task 3 migration: ' . self::FOUNDATION_VERSION);
            }

            $extensionsChecksum = $schemaInspector->migrationChecksum(self::EXTENSIONS_VERSION);
            if ($extensionsChecksum === null || !hash_equals(self::EXTENSIONS_CHECKSUM, $extensionsChecksum)) {
                throw new RuntimeException('Learner migration preflight requires verified Task 4 migration: ' . self::EXTENSIONS_VERSION);
            }

            if (!$schemaInspector->hasTable('student_profiles')) {
                throw new RuntimeException('Learner migration preflight missing required shared parent: student_profiles');
            }
            if ($schemaInspector->columnType('student_profiles', 'id') !== 'CHAR(36)') {
                throw new RuntimeException('Learner migration preflight requires CHAR(36) shared parent id: student_profiles.id');
            }
            if (!$schemaInspector->hasPrimaryKey('student_profiles', 'id')) {
                throw new RuntimeException('Learner migration preflight requires primary-key shared parent id: student_profiles.id');
            }
            if ($schemaInspector->isMySql() && !$schemaInspector->hasMySqlTableOptions('student_profiles', 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci')) {
                throw new RuntimeException('Learner migration preflight requires InnoDB utf8mb4 utf8mb4_unicode_ci shared parent table: student_profiles');
            }
            if ($schemaInspector->isMySql() && $schemaInspector->mysqlSessionTimeZone() !== '+00:00') {
                throw new RuntimeException('Learner migration preflight requires MySQL session time zone +00:00');
            }

            foreach (array_keys(self::EXPECTED_SCHEMA) as $table) {
                if ($schemaInspector->hasTable($table)) {
                    throw new RuntimeException('Learner migration preflight rejected existing canonical recommendation target: ' . $table);
                }
            }
        }

        /** @return list<string> */
        public function statements(string $driver): array
        {
            return match (strtolower($driver)) {
                'mysql' => self::mysqlStatements(),
                'sqlite' => self::sqliteStatements(),
                default => throw new RuntimeException('Unsupported learner migration driver: ' . $driver),
            };
        }

        /** @return array<string,array{columns:list<string>,indexes:list<string>}> */
        public function expectedSchema(): array
        {
            return self::EXPECTED_SCHEMA;
        }

        /** @return list<string> */
        private static function mysqlStatements(): array
        {
            return [
                <<<'SQL'
CREATE TABLE learner_recommendation_input_snapshots (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, schemaVersion VARCHAR(100) NOT NULL, contentHash CHAR(64) NOT NULL, consentScopesJson LONGTEXT NOT NULL, qualityFlagsJson LONGTEXT NOT NULL, payloadJson LONGTEXT NOT NULL, sourceUpdatedAt LONGTEXT NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_recommendation_input_snapshots_student_hash (studentId, contentHash), KEY idx_learner_recommendation_input_snapshots_student_created (studentId, createdAt),
  CONSTRAINT fk_learner_recommendation_input_snapshots_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_input_snapshots_consent_json CHECK (JSON_VALID(consentScopesJson)),
  CONSTRAINT chk_learner_recommendation_input_snapshots_quality_json CHECK (JSON_VALID(qualityFlagsJson)),
  CONSTRAINT chk_learner_recommendation_input_snapshots_payload_json CHECK (JSON_VALID(payloadJson)),
  CONSTRAINT chk_learner_recommendation_input_snapshots_source_updated_json CHECK (JSON_VALID(sourceUpdatedAt))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_recommendation_runs (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, snapshotId CHAR(36) NOT NULL, idempotencyKey VARCHAR(100) NOT NULL, engineType VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending', ruleVersion VARCHAR(100) NULL, provider VARCHAR(100) NULL, modelVersion VARCHAR(100) NULL, promptVersion VARCHAR(100) NULL, fallbackReason VARCHAR(100) NULL, safeErrorCode VARCHAR(100) NULL, startedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), completedAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_recommendation_runs_student_idempotency (studentId, idempotencyKey), KEY idx_learner_recommendation_runs_student_created (studentId, createdAt), KEY idx_learner_recommendation_runs_snapshot (snapshotId),
  CONSTRAINT fk_learner_recommendation_runs_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_recommendation_runs_snapshot FOREIGN KEY (snapshotId) REFERENCES learner_recommendation_input_snapshots(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_runs_engine CHECK (engineType IN ('rule','model')),
  CONSTRAINT chk_learner_recommendation_runs_status CHECK (status IN ('pending','completed','failed','fallback')),
  CONSTRAINT chk_learner_recommendation_runs_engine_versions CHECK ((engineType = 'rule' AND ruleVersion IS NOT NULL AND provider IS NULL AND modelVersion IS NULL AND promptVersion IS NULL) OR (engineType = 'model' AND ruleVersion IS NULL AND provider IS NOT NULL AND modelVersion IS NOT NULL AND promptVersion IS NOT NULL)),
  CONSTRAINT chk_learner_recommendation_runs_completion CHECK ((status = 'pending' AND completedAt IS NULL) OR (status IN ('completed','failed','fallback') AND completedAt IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_recommendation_items (
  id CHAR(36) NOT NULL, runId CHAR(36) NOT NULL, itemType VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, summary VARCHAR(1000) NOT NULL, priority TINYINT UNSIGNED NOT NULL, confidenceBand VARCHAR(50) NOT NULL, actionJson LONGTEXT NOT NULL, lifecycleStatus VARCHAR(50) NOT NULL DEFAULT 'active', createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), KEY idx_learner_recommendation_items_run_lifecycle_priority (runId, lifecycleStatus, priority),
  CONSTRAINT fk_learner_recommendation_items_run FOREIGN KEY (runId) REFERENCES learner_recommendation_runs(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_items_type CHECK (itemType IN ('strength','improvement','development','activity','roadmap')),
  CONSTRAINT chk_learner_recommendation_items_priority CHECK (priority BETWEEN 1 AND 100),
  CONSTRAINT chk_learner_recommendation_items_confidence CHECK (confidenceBand IN ('low','medium','high')),
  CONSTRAINT chk_learner_recommendation_items_action_json CHECK (JSON_VALID(actionJson)),
  CONSTRAINT chk_learner_recommendation_items_lifecycle CHECK (lifecycleStatus IN ('active','superseded'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_recommendation_evidence (
  id CHAR(36) NOT NULL, itemId CHAR(36) NOT NULL, sourceType VARCHAR(50) NOT NULL, sourceId CHAR(36) NOT NULL, observedAt DATETIME(6) NULL, contributionLabel VARCHAR(100) NOT NULL, safeValueJson LONGTEXT NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_recommendation_evidence_item_source (itemId, sourceType, sourceId), KEY idx_learner_recommendation_evidence_source (sourceType, sourceId),
  CONSTRAINT fk_learner_recommendation_evidence_item FOREIGN KEY (itemId) REFERENCES learner_recommendation_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_evidence_source_type CHECK (sourceType IN ('skill','assessment','activity_experience','evaluation','opportunity')),
  CONSTRAINT chk_learner_recommendation_evidence_safe_value_json CHECK (JSON_VALID(safeValueJson))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_recommendation_feedback (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, itemId CHAR(36) NOT NULL, verdict VARCHAR(50) NOT NULL, reasonCode VARCHAR(100) NOT NULL, safeComment VARCHAR(500) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), KEY idx_learner_recommendation_feedback_student_created (studentId, createdAt), KEY idx_learner_recommendation_feedback_item (itemId),
  CONSTRAINT fk_learner_recommendation_feedback_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_recommendation_feedback_item FOREIGN KEY (itemId) REFERENCES learner_recommendation_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_feedback_verdict CHECK (verdict IN ('helpful','not_helpful','not_relevant','unsafe')),
  CONSTRAINT chk_learner_recommendation_feedback_safe_comment CHECK (safeComment IS NULL OR CHAR_LENGTH(safeComment) <= 500)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_recommendation_audit_events (
  id CHAR(36) NOT NULL, runId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, requestId CHAR(36) NOT NULL, actorType VARCHAR(50) NOT NULL, action VARCHAR(100) NOT NULL, engineMetadataJson LONGTEXT NOT NULL, status VARCHAR(50) NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), KEY idx_learner_recommendation_audit_events_student_created (studentId, createdAt), KEY idx_learner_recommendation_audit_events_run_created (runId, createdAt),
  CONSTRAINT fk_learner_recommendation_audit_events_run FOREIGN KEY (runId) REFERENCES learner_recommendation_runs(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_recommendation_audit_events_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_recommendation_audit_events_actor CHECK (actorType IN ('learner','system','service')),
  CONSTRAINT chk_learner_recommendation_audit_events_metadata_json CHECK (JSON_VALID(engineMetadataJson)),
  CONSTRAINT chk_learner_recommendation_audit_events_status CHECK (status IN ('pending','completed','failed','fallback'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TRIGGER trg_learner_recommendation_feedback_append_only_update
BEFORE UPDATE ON learner_recommendation_feedback
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only recommendation feedback';
END;
SQL,
                <<<'SQL'
CREATE TRIGGER trg_learner_recommendation_feedback_append_only_delete
BEFORE DELETE ON learner_recommendation_feedback
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only recommendation feedback';
END;
SQL,
                <<<'SQL'
CREATE TRIGGER trg_learner_recommendation_audit_events_append_only_update
BEFORE UPDATE ON learner_recommendation_audit_events
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only recommendation audit event';
END;
SQL,
                <<<'SQL'
CREATE TRIGGER trg_learner_recommendation_audit_events_append_only_delete
BEFORE DELETE ON learner_recommendation_audit_events
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only recommendation audit event';
END;
SQL,
            ];
        }

        /** @return list<string> */
        private static function sqliteStatements(): array
        {
            return [
                "CREATE TABLE learner_recommendation_input_snapshots (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, schemaVersion VARCHAR(100) NOT NULL, contentHash CHAR(64) NOT NULL, consentScopesJson TEXT NOT NULL, qualityFlagsJson TEXT NOT NULL, payloadJson TEXT NOT NULL, sourceUpdatedAt TEXT NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (json_valid(consentScopesJson)), CHECK (json_valid(qualityFlagsJson)), CHECK (json_valid(payloadJson)), CHECK (json_valid(sourceUpdatedAt)))",
                "CREATE TABLE learner_recommendation_runs (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, snapshotId CHAR(36) NOT NULL, idempotencyKey VARCHAR(100) NOT NULL, engineType VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending', ruleVersion VARCHAR(100) NULL, provider VARCHAR(100) NULL, modelVersion VARCHAR(100) NULL, promptVersion VARCHAR(100) NULL, fallbackReason VARCHAR(100) NULL, safeErrorCode VARCHAR(100) NULL, startedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, completedAt TEXT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (snapshotId) REFERENCES learner_recommendation_input_snapshots(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (engineType IN ('rule','model')), CHECK (status IN ('pending','completed','failed','fallback')), CHECK ((engineType = 'rule' AND ruleVersion IS NOT NULL AND provider IS NULL AND modelVersion IS NULL AND promptVersion IS NULL) OR (engineType = 'model' AND ruleVersion IS NULL AND provider IS NOT NULL AND modelVersion IS NOT NULL AND promptVersion IS NOT NULL)), CHECK ((status = 'pending' AND completedAt IS NULL) OR (status IN ('completed','failed','fallback') AND completedAt IS NOT NULL)))",
                "CREATE TABLE learner_recommendation_items (id CHAR(36) NOT NULL PRIMARY KEY, runId CHAR(36) NOT NULL, itemType VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, summary VARCHAR(1000) NOT NULL, priority INTEGER NOT NULL, confidenceBand VARCHAR(50) NOT NULL, actionJson TEXT NOT NULL, lifecycleStatus VARCHAR(50) NOT NULL DEFAULT 'active', createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (runId) REFERENCES learner_recommendation_runs(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (itemType IN ('strength','improvement','development','activity','roadmap')), CHECK (priority BETWEEN 1 AND 100), CHECK (confidenceBand IN ('low','medium','high')), CHECK (json_valid(actionJson)), CHECK (lifecycleStatus IN ('active','superseded')))",
                "CREATE TABLE learner_recommendation_evidence (id CHAR(36) NOT NULL PRIMARY KEY, itemId CHAR(36) NOT NULL, sourceType VARCHAR(50) NOT NULL, sourceId CHAR(36) NOT NULL, observedAt TEXT NULL, contributionLabel VARCHAR(100) NOT NULL, safeValueJson TEXT NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (itemId) REFERENCES learner_recommendation_items(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (sourceType IN ('skill','assessment','activity_experience','evaluation','opportunity')), CHECK (json_valid(safeValueJson)))",
                "CREATE TABLE learner_recommendation_feedback (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, itemId CHAR(36) NOT NULL, verdict VARCHAR(50) NOT NULL, reasonCode VARCHAR(100) NOT NULL, safeComment VARCHAR(500) NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (itemId) REFERENCES learner_recommendation_items(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (verdict IN ('helpful','not_helpful','not_relevant','unsafe')), CHECK (safeComment IS NULL OR length(safeComment) <= 500))",
                "CREATE TABLE learner_recommendation_audit_events (id CHAR(36) NOT NULL PRIMARY KEY, runId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, requestId CHAR(36) NOT NULL, actorType VARCHAR(50) NOT NULL, action VARCHAR(100) NOT NULL, engineMetadataJson TEXT NOT NULL, status VARCHAR(50) NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (runId) REFERENCES learner_recommendation_runs(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (actorType IN ('learner','system','service')), CHECK (json_valid(engineMetadataJson)), CHECK (status IN ('pending','completed','failed','fallback')))",
                "CREATE TRIGGER trg_learner_recommendation_feedback_append_only_update BEFORE UPDATE ON learner_recommendation_feedback FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only recommendation feedback'); END",
                "CREATE TRIGGER trg_learner_recommendation_feedback_append_only_delete BEFORE DELETE ON learner_recommendation_feedback FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only recommendation feedback'); END",
                "CREATE TRIGGER trg_learner_recommendation_audit_events_append_only_update BEFORE UPDATE ON learner_recommendation_audit_events FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only recommendation audit event'); END",
                "CREATE TRIGGER trg_learner_recommendation_audit_events_append_only_delete BEFORE DELETE ON learner_recommendation_audit_events FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only recommendation audit event'); END",
                'CREATE UNIQUE INDEX uq_learner_recommendation_input_snapshots_student_hash ON learner_recommendation_input_snapshots (studentId, contentHash)',
                'CREATE INDEX idx_learner_recommendation_input_snapshots_student_created ON learner_recommendation_input_snapshots (studentId, createdAt)',
                'CREATE UNIQUE INDEX uq_learner_recommendation_runs_student_idempotency ON learner_recommendation_runs (studentId, idempotencyKey)',
                'CREATE INDEX idx_learner_recommendation_runs_student_created ON learner_recommendation_runs (studentId, createdAt)',
                'CREATE INDEX idx_learner_recommendation_runs_snapshot ON learner_recommendation_runs (snapshotId)',
                'CREATE INDEX idx_learner_recommendation_items_run_lifecycle_priority ON learner_recommendation_items (runId, lifecycleStatus, priority)',
                'CREATE UNIQUE INDEX uq_learner_recommendation_evidence_item_source ON learner_recommendation_evidence (itemId, sourceType, sourceId)',
                'CREATE INDEX idx_learner_recommendation_evidence_source ON learner_recommendation_evidence (sourceType, sourceId)',
                'CREATE INDEX idx_learner_recommendation_feedback_student_created ON learner_recommendation_feedback (studentId, createdAt)',
                'CREATE INDEX idx_learner_recommendation_feedback_item ON learner_recommendation_feedback (itemId)',
                'CREATE INDEX idx_learner_recommendation_audit_events_student_created ON learner_recommendation_audit_events (studentId, createdAt)',
                'CREATE INDEX idx_learner_recommendation_audit_events_run_created ON learner_recommendation_audit_events (runId, createdAt)',
            ];
        }
    },
);
