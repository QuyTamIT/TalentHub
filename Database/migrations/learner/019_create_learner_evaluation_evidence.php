<?php
declare(strict_types=1);

use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
use TalentHub\Learner\Data\Migrations\LearnerMigrationChecksum;

return new ForwardMigrationDefinition(
    '019_create_learner_evaluation_evidence',
    'Create learner evaluations, evaluation items, and extend skill evidence',
    __FILE__,
    LearnerMigrationChecksum::canonical(__FILE__),
    new class implements LearnerForwardMigration {
        public function version(): string
        {
            return '019_create_learner_evaluation_evidence';
        }

        public function description(): string
        {
            return 'Create learner evaluations, evaluation items, and extend skill evidence';
        }

        /** @return list<string> */
        public function statements(string $driver): array
        {
            return match (strtolower($driver)) {
                'mysql' => [
                    <<<'SQL'
CREATE TABLE IF NOT EXISTS learner_evaluations (
  id CHAR(36) NOT NULL,
  seriesId CHAR(36) NOT NULL,
  revision INT NOT NULL DEFAULT 1,
  studentId CHAR(36) NOT NULL,
  teacherId CHAR(36) NOT NULL,
  legacyAssessmentId CHAR(36) NULL,
  contextType VARCHAR(32) NOT NULL DEFAULT 'general',
  contextId CHAR(36) NULL,
  overallScore DECIMAL(5,2) NULL,
  comment VARCHAR(1000) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'draft',
  reason VARCHAR(1000) NULL,
  publishedAt DATETIME(6) NULL,
  actorUserId CHAR(36) NOT NULL,
  eventKey CHAR(64) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_learner_evaluations_series_revision (seriesId, revision),
  UNIQUE KEY uq_learner_evaluations_event_key (eventKey),
  KEY idx_learner_evaluations_student_status_published (studentId, status, publishedAt),
  KEY idx_learner_evaluations_legacy_revision (legacyAssessmentId, revision),
  CONSTRAINT fk_learner_evaluations_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_learner_evaluations_teacher FOREIGN KEY (teacherId) REFERENCES teacher_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_evaluations_legacy FOREIGN KEY (legacyAssessmentId) REFERENCES assessments(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_learner_evaluations_actor FOREIGN KEY (actorUserId) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                    <<<'SQL'
CREATE TABLE IF NOT EXISTS learner_evaluation_items (
  id CHAR(36) NOT NULL,
  evaluationId CHAR(36) NOT NULL,
  itemKind VARCHAR(32) NOT NULL,
  itemCode VARCHAR(100) NOT NULL,
  skillId CHAR(36) NULL,
  label VARCHAR(160) NOT NULL,
  score DECIMAL(5,2) NULL,
  maxScore DECIMAL(5,2) NULL,
  confirmed TINYINT(1) NOT NULL DEFAULT 0,
  comment VARCHAR(1000) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_learner_eval_items_eval_kind_code (evaluationId, itemKind, itemCode),
  KEY idx_learner_eval_items_eval (evaluationId),
  CONSTRAINT fk_learner_eval_items_eval FOREIGN KEY (evaluationId) REFERENCES learner_evaluations(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                    "ALTER TABLE learner_skill_evidence MODIFY COLUMN studentSkillId CHAR(36) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN studentId CHAR(36) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN skillId CHAR(36) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN evidenceKind VARCHAR(20) NOT NULL DEFAULT 'skill'",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN strengthCode VARCHAR(100) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN label VARCHAR(160) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN sourceType VARCHAR(32) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN sourceId CHAR(36) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN sourceVersion INT NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN score DECIMAL(5,2) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN comment VARCHAR(1000) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN actorUserId CHAR(36) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN expiresAt DATETIME(6) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN revokedAt DATETIME(6) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN supersedesId CHAR(36) NULL",
                    "ALTER TABLE learner_skill_evidence ADD COLUMN eventKey CHAR(64) NULL UNIQUE",
                    "ALTER TABLE learner_skill_evidence ADD KEY idx_learner_skill_evidence_student_observed (studentId, observedAt)",
                    "ALTER TABLE learner_skill_evidence ADD KEY idx_learner_skill_evidence_source (sourceType, sourceId, sourceVersion)",
                    "ALTER TABLE learner_skill_evidence ADD KEY idx_learner_skill_evidence_supersedes (supersedesId)",
                    "ALTER TABLE learner_skill_evidence ADD CONSTRAINT fk_learner_skill_evidence_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE",
                    "ALTER TABLE learner_skill_evidence ADD CONSTRAINT fk_learner_skill_evidence_actor FOREIGN KEY (actorUserId) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE",
                    "ALTER TABLE learner_skill_evidence ADD CONSTRAINT fk_learner_skill_evidence_supersedes FOREIGN KEY (supersedesId) REFERENCES learner_skill_evidence(id) ON DELETE SET NULL ON UPDATE CASCADE",
                ],
                default => throw new RuntimeException('Unsupported driver for learner sync migration: ' . $driver),
            };
        }

        /** @return array<string, array{columns: list<string>, indexes: list<string>}> */
        public function expectedSchema(): array
        {
            return [
                'learner_evaluations' => [
                    'columns' => [
                        'id', 'seriesId', 'revision', 'studentId', 'teacherId',
                        'legacyAssessmentId', 'contextType', 'contextId', 'overallScore',
                        'comment', 'status', 'reason', 'publishedAt', 'actorUserId',
                        'eventKey', 'createdAt', 'updatedAt',
                    ],
                    'indexes' => ['PRIMARY', 'uq_learner_evaluations_series_revision'],
                ],
                'learner_evaluation_items' => [
                    'columns' => [
                        'id', 'evaluationId', 'itemKind', 'itemCode', 'skillId',
                        'label', 'score', 'maxScore', 'confirmed', 'comment', 'createdAt',
                    ],
                    'indexes' => ['PRIMARY', 'uq_learner_eval_items_eval_kind_code'],
                ],
            ];
        }
    }
);
