<?php
declare(strict_types=1);

use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
use TalentHub\Learner\Data\Migrations\LearnerMigrationChecksum;

return new ForwardMigrationDefinition(
    '018_create_learner_sync_tracking',
    'Create learner sync tracking tables and revision tracking',
    __FILE__,
    LearnerMigrationChecksum::canonical(__FILE__),
    new class implements LearnerForwardMigration {
        public function version(): string
        {
            return '018_create_learner_sync_tracking';
        }

        public function description(): string
        {
            return 'Create learner sync tracking tables and revision tracking';
        }

        /** @return list<string> */
        public function statements(string $driver): array
        {
            return match (strtolower($driver)) {
                'mysql' => [
                    <<<'SQL'
CREATE TABLE IF NOT EXISTS learner_data_revisions (
  studentId CHAR(36) NOT NULL,
  dataRevision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (studentId),
  CONSTRAINT fk_learner_data_revisions_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                    <<<'SQL'
CREATE TABLE IF NOT EXISTS learner_participation_events (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  sourceType VARCHAR(32) NOT NULL,
  sourceId CHAR(36) NOT NULL,
  sourceVersion BIGINT NOT NULL DEFAULT 1,
  fromStatus VARCHAR(32) NULL,
  toStatus VARCHAR(32) NOT NULL,
  actorUserId CHAR(36) NOT NULL,
  reason VARCHAR(1000) NULL,
  evidenceJson LONGTEXT NULL,
  eventKey CHAR(64) NOT NULL,
  occurredAt DATETIME(6) NOT NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_learner_part_events_event_key (eventKey),
  KEY idx_learner_part_events_student_time (studentId, occurredAt, id),
  KEY idx_learner_part_events_source (sourceType, sourceId, sourceVersion),
  CONSTRAINT fk_learner_part_events_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_learner_part_events_actor FOREIGN KEY (actorUserId) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                    "ALTER TABLE learner_ai_data_outbox ADD COLUMN revisionMapJson LONGTEXT NULL",
                    "ALTER TABLE learner_ai_data_outbox ADD COLUMN eventKey CHAR(64) NULL UNIQUE",
                    "ALTER TABLE learner_ai_refresh_jobs ADD COLUMN inputRevision BIGINT NULL",
                    "ALTER TABLE learner_ai_refresh_jobs ADD COLUMN generationEpoch BIGINT NOT NULL DEFAULT 1",
                    "ALTER TABLE learner_ai_roadmaps ADD COLUMN inputRevision BIGINT NULL",
                    "ALTER TABLE learner_ai_roadmaps ADD COLUMN validatedAt DATETIME(6) NULL",
                    "ALTER TABLE learner_recommendation_runs ADD COLUMN inputRevision BIGINT NULL",
                    "ALTER TABLE learner_recommendation_runs ADD COLUMN validatedAt DATETIME(6) NULL",
                    "ALTER TABLE learner_ai_capability_profiles ADD COLUMN inputRevision BIGINT NULL",
                    "ALTER TABLE learner_ai_capability_profiles ADD COLUMN validatedAt DATETIME(6) NULL",
                ],
                default => throw new RuntimeException('Unsupported driver for learner sync migration: ' . $driver),
            };
        }

        /** @return array<string, array{columns: list<string>, indexes: list<string>}> */
        public function expectedSchema(): array
        {
            return [
                'learner_data_revisions' => [
                    'columns' => ['studentId', 'dataRevision', 'createdAt', 'updatedAt'],
                    'indexes' => ['PRIMARY'],
                ],
                'learner_participation_events' => [
                    'columns' => [
                        'id', 'studentId', 'sourceType', 'sourceId', 'sourceVersion',
                        'fromStatus', 'toStatus', 'actorUserId', 'reason',
                        'evidenceJson', 'eventKey', 'occurredAt', 'createdAt',
                    ],
                    'indexes' => ['PRIMARY'],
                ],
            ];
        }
    }
);