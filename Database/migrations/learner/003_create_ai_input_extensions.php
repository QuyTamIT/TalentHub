<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
use TalentHub\Learner\Data\Migrations\LearnerMigrationPreflight;

return new ForwardMigrationDefinition(
    '003_create_ai_input_extensions',
    'Create versioned learner AI input extensions',
    __FILE__,
    hash_file('sha256', __FILE__),
    new class implements LearnerForwardMigration, LearnerMigrationPreflight {
        private const FOUNDATION_VERSION = '002_create_ai_input_foundation';
        private const FOUNDATION_CHECKSUM = 'f1c7d125c475fddad946448b9a320ae6207ea5903eaa2d652fb456d505a929bc';
        private const EXPECTED_SCHEMA = [
            'learner_assessment_versions' => ['columns' => ['id', 'testId', 'version', 'scoringVersion', 'schemaHash', 'status', 'publishedAt', 'createdAt'], 'indexes' => ['uq_learner_assessment_versions_test_version', 'idx_learner_assessment_versions_test_status']],
            'learner_assessment_question_versions' => ['columns' => ['id', 'versionId', 'questionId', 'position', 'dimensionCode', 'required', 'createdAt'], 'indexes' => ['uq_learner_assessment_question_versions_version_question', 'uq_learner_assessment_question_versions_version_position', 'idx_learner_assessment_question_versions_question']],
            'learner_assessment_attempt_metadata' => ['columns' => ['id', 'attemptId', 'versionId', 'status', 'expiresAt', 'submittedAt', 'inputHash', 'createdAt', 'updatedAt'], 'indexes' => ['uq_learner_assessment_attempt_metadata_attempt', 'idx_learner_assessment_attempt_metadata_version_status']],
            'learner_assessment_answers' => ['columns' => ['id', 'attemptId', 'questionId', 'answerJson', 'answeredAt'], 'indexes' => ['uq_learner_assessment_answers_attempt_question', 'idx_learner_assessment_answers_question']],
            'learner_skill_evidence' => ['columns' => ['id', 'studentSkillId', 'evidenceType', 'evidenceRef', 'verificationStatus', 'observedAt', 'createdAt'], 'indexes' => ['idx_learner_skill_evidence_student_skill_observed', 'idx_learner_skill_evidence_evidence_ref']],
            'learner_ai_consent_events' => ['columns' => ['id', 'studentId', 'scope', 'action', 'policyVersion', 'occurredAt', 'requestId'], 'indexes' => ['uq_learner_ai_consent_events_student_scope_occurred_request', 'idx_learner_ai_consent_events_student_scope_occurred']],
        ];

        public function version(): string
        {
            return '003_create_ai_input_extensions';
        }

        public function description(): string
        {
            return 'Create versioned learner AI input extensions';
        }

        public function assertBeforeApply(SchemaInspector $schemaInspector): void
        {
            $foundationChecksum = $schemaInspector->migrationChecksum(self::FOUNDATION_VERSION);
            if ($foundationChecksum === null || !hash_equals(self::FOUNDATION_CHECKSUM, $foundationChecksum)) {
                throw new RuntimeException('Learner migration preflight requires verified Task 3 migration: ' . self::FOUNDATION_VERSION);
            }

            foreach (['talent_tests', 'test_questions', 'test_attempts', 'student_skills', 'student_profiles'] as $parent) {
                if (!$schemaInspector->hasTable($parent)) {
                    throw new RuntimeException('Learner migration preflight missing required Task 3 parent: ' . $parent);
                }
                if ($schemaInspector->columnType($parent, 'id') !== 'CHAR(36)') {
                    throw new RuntimeException('Learner migration preflight requires CHAR(36) Task 3 parent id: ' . $parent . '.id');
                }
                if (!$schemaInspector->hasPrimaryKey($parent, 'id')) {
                    throw new RuntimeException('Learner migration preflight requires primary-key Task 3 parent id: ' . $parent . '.id');
                }
                if ($schemaInspector->isMySql() && !$schemaInspector->hasMySqlTableOptions($parent, 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci')) {
                    throw new RuntimeException('Learner migration preflight requires InnoDB utf8mb4 utf8mb4_unicode_ci Task 3 parent table: ' . $parent);
                }
            }

            if ($schemaInspector->isMySql() && $schemaInspector->mysqlSessionTimeZone() !== '+00:00') {
                throw new RuntimeException('Learner migration preflight requires MySQL session time zone +00:00');
            }

            foreach (array_keys(self::EXPECTED_SCHEMA) as $table) {
                if ($schemaInspector->hasTable($table)) {
                    throw new RuntimeException('Learner migration preflight rejected existing canonical extension target: ' . $table);
                }
            }
        }

        public function statements(string $driver): array
        {
            return match (strtolower($driver)) {
                'mysql' => self::mysqlStatements(),
                'sqlite' => self::sqliteStatements(),
                default => throw new RuntimeException('Unsupported learner migration driver: ' . $driver),
            };
        }

        public function expectedSchema(): array
        {
            return self::EXPECTED_SCHEMA;
        }

        /** @return list<string> */
        private static function mysqlStatements(): array
        {
            return [
                <<<'SQL'
CREATE TABLE learner_assessment_versions (
  id CHAR(36) NOT NULL, testId CHAR(36) NOT NULL, version VARCHAR(100) NOT NULL, scoringVersion VARCHAR(100) NOT NULL, schemaHash CHAR(64) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'draft', publishedAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_assessment_versions_test_version (testId, version), KEY idx_learner_assessment_versions_test_status (testId, status),
  CONSTRAINT fk_learner_assessment_versions_test FOREIGN KEY (testId) REFERENCES talent_tests(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_assessment_versions_status CHECK (status IN ('draft','published','retired')),
  CONSTRAINT chk_learner_assessment_versions_published_at CHECK ((status = 'draft' AND publishedAt IS NULL) OR (status IN ('published','retired') AND publishedAt IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_assessment_question_versions (
  id CHAR(36) NOT NULL, versionId CHAR(36) NOT NULL, questionId CHAR(36) NOT NULL, position INT UNSIGNED NOT NULL, dimensionCode VARCHAR(100) NOT NULL, required TINYINT UNSIGNED NOT NULL DEFAULT 1, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_assessment_question_versions_version_question (versionId, questionId), UNIQUE KEY uq_learner_assessment_question_versions_version_position (versionId, position), KEY idx_learner_assessment_question_versions_question (questionId),
  CONSTRAINT fk_learner_assessment_question_versions_version FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_assessment_question_versions_question FOREIGN KEY (questionId) REFERENCES test_questions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_assessment_question_versions_position CHECK (position >= 1),
  CONSTRAINT chk_learner_assessment_question_versions_required CHECK (required IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_assessment_attempt_metadata (
  id CHAR(36) NOT NULL, attemptId CHAR(36) NOT NULL, versionId CHAR(36) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'in_progress', expiresAt DATETIME(6) NULL, submittedAt DATETIME(6) NULL, inputHash CHAR(64) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_assessment_attempt_metadata_attempt (attemptId), KEY idx_learner_assessment_attempt_metadata_version_status (versionId, status),
  CONSTRAINT fk_learner_assessment_attempt_metadata_attempt FOREIGN KEY (attemptId) REFERENCES test_attempts(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_assessment_attempt_metadata_version FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_assessment_attempt_metadata_status CHECK (status IN ('in_progress','submitted','expired')),
  CONSTRAINT chk_learner_assessment_attempt_metadata_submission CHECK ((status = 'submitted' AND submittedAt IS NOT NULL AND inputHash IS NOT NULL) OR (status <> 'submitted' AND submittedAt IS NULL AND inputHash IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_assessment_answers (
  id CHAR(36) NOT NULL, attemptId CHAR(36) NOT NULL, questionId CHAR(36) NOT NULL, answerJson LONGTEXT NOT NULL, answeredAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_learner_assessment_answers_attempt_question (attemptId, questionId), KEY idx_learner_assessment_answers_question (questionId),
  CONSTRAINT fk_learner_assessment_answers_attempt FOREIGN KEY (attemptId) REFERENCES learner_assessment_attempt_metadata(attemptId) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_learner_assessment_answers_question FOREIGN KEY (questionId) REFERENCES test_questions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_assessment_answers_json CHECK (JSON_VALID(answerJson))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_skill_evidence (
  id CHAR(36) NOT NULL, studentSkillId CHAR(36) NOT NULL, evidenceType VARCHAR(50) NOT NULL, evidenceRef VARCHAR(191) NOT NULL, verificationStatus VARCHAR(50) NOT NULL DEFAULT 'pending', observedAt DATETIME(6) NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), KEY idx_learner_skill_evidence_student_skill_observed (studentSkillId, observedAt), KEY idx_learner_skill_evidence_evidence_ref (evidenceRef),
  CONSTRAINT fk_learner_skill_evidence_student_skill FOREIGN KEY (studentSkillId) REFERENCES student_skills(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_skill_evidence_verification CHECK (verificationStatus IN ('self_declared','pending','verified','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE learner_ai_consent_events (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, scope VARCHAR(50) NOT NULL, action VARCHAR(50) NOT NULL, policyVersion VARCHAR(100) NOT NULL, occurredAt DATETIME(6) NOT NULL, requestId CHAR(36) NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_learner_ai_consent_events_student_scope_occurred_request (studentId, scope, occurredAt, requestId), KEY idx_learner_ai_consent_events_student_scope_occurred (studentId, scope, occurredAt),
  CONSTRAINT fk_learner_ai_consent_events_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_ai_consent_events_scope CHECK (scope IN ('assessment','skills','activity','evaluation')),
  CONSTRAINT chk_learner_ai_consent_events_action CHECK (action IN ('granted','revoked'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TRIGGER trg_learner_assessment_attempt_metadata_test_match_insert
BEFORE INSERT ON learner_assessment_attempt_metadata
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM test_attempts AS attempts
    INNER JOIN learner_assessment_versions AS versions ON versions.id = NEW.versionId
    WHERE attempts.id = NEW.attemptId AND attempts.testId = versions.testId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment attempt version test mismatch';
  END IF;
END;
SQL,
                <<<'SQL'
CREATE TRIGGER trg_learner_assessment_attempt_metadata_test_match_update
BEFORE UPDATE ON learner_assessment_attempt_metadata
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM test_attempts AS attempts
    INNER JOIN learner_assessment_versions AS versions ON versions.id = NEW.versionId
    WHERE attempts.id = NEW.attemptId AND attempts.testId = versions.testId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment attempt version test mismatch';
  END IF;
END;
SQL,
                <<<'SQL'
CREATE TRIGGER trg_learner_assessment_answers_version_match_insert
BEFORE INSERT ON learner_assessment_answers
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM learner_assessment_attempt_metadata AS metadata
    INNER JOIN learner_assessment_question_versions AS question_versions ON question_versions.versionId = metadata.versionId
    WHERE metadata.attemptId = NEW.attemptId AND question_versions.questionId = NEW.questionId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment answer question version mismatch';
  END IF;
END;
SQL,
                <<<'SQL'
CREATE TRIGGER trg_learner_assessment_answers_version_match_update
BEFORE UPDATE ON learner_assessment_answers
FOR EACH ROW
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM learner_assessment_attempt_metadata AS metadata
    INNER JOIN learner_assessment_question_versions AS question_versions ON question_versions.versionId = metadata.versionId
    WHERE metadata.attemptId = NEW.attemptId AND question_versions.questionId = NEW.questionId
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'assessment answer question version mismatch';
  END IF;
END;
SQL,
                <<<'SQL'
CREATE TRIGGER trg_learner_ai_consent_events_append_only_update
BEFORE UPDATE ON learner_ai_consent_events
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only consent event';
END;
SQL,
                <<<'SQL'
CREATE TRIGGER trg_learner_ai_consent_events_append_only_delete
BEFORE DELETE ON learner_ai_consent_events
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'append-only consent event';
END;
SQL,
            ];
        }

        /** @return list<string> */
        private static function sqliteStatements(): array
        {
            return [
                "CREATE TABLE learner_assessment_versions (id CHAR(36) NOT NULL PRIMARY KEY, testId CHAR(36) NOT NULL, version VARCHAR(100) NOT NULL, scoringVersion VARCHAR(100) NOT NULL, schemaHash CHAR(64) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'draft', publishedAt TEXT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (testId) REFERENCES talent_tests(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (status IN ('draft','published','retired')), CHECK ((status = 'draft' AND publishedAt IS NULL) OR (status IN ('published','retired') AND publishedAt IS NOT NULL)))",
                "CREATE TABLE learner_assessment_question_versions (id CHAR(36) NOT NULL PRIMARY KEY, versionId CHAR(36) NOT NULL, questionId CHAR(36) NOT NULL, position INTEGER NOT NULL, dimensionCode VARCHAR(100) NOT NULL, required INTEGER NOT NULL DEFAULT 1, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (questionId) REFERENCES test_questions(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (position >= 1), CHECK (required IN (0,1)))",
                "CREATE TABLE learner_assessment_attempt_metadata (id CHAR(36) NOT NULL PRIMARY KEY, attemptId CHAR(36) NOT NULL, versionId CHAR(36) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'in_progress', expiresAt TEXT NULL, submittedAt TEXT NULL, inputHash CHAR(64) NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (attemptId) REFERENCES test_attempts(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (status IN ('in_progress','submitted','expired')), CHECK ((status = 'submitted' AND submittedAt IS NOT NULL AND inputHash IS NOT NULL) OR (status <> 'submitted' AND submittedAt IS NULL AND inputHash IS NULL)))",
                "CREATE TABLE learner_assessment_answers (id CHAR(36) NOT NULL PRIMARY KEY, attemptId CHAR(36) NOT NULL, questionId CHAR(36) NOT NULL, answerJson TEXT NOT NULL, answeredAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (attemptId) REFERENCES learner_assessment_attempt_metadata(attemptId) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (questionId) REFERENCES test_questions(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (json_valid(answerJson)))",
                "CREATE TABLE learner_skill_evidence (id CHAR(36) NOT NULL PRIMARY KEY, studentSkillId CHAR(36) NOT NULL, evidenceType VARCHAR(50) NOT NULL, evidenceRef VARCHAR(191) NOT NULL, verificationStatus VARCHAR(50) NOT NULL DEFAULT 'pending', observedAt TEXT NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (studentSkillId) REFERENCES student_skills(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (verificationStatus IN ('self_declared','pending','verified','rejected')))",
                "CREATE TABLE learner_ai_consent_events (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, scope VARCHAR(50) NOT NULL, action VARCHAR(50) NOT NULL, policyVersion VARCHAR(100) NOT NULL, occurredAt TEXT NOT NULL, requestId CHAR(36) NOT NULL, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (scope IN ('assessment','skills','activity','evaluation')), CHECK (action IN ('granted','revoked')))",
                "CREATE TRIGGER trg_learner_assessment_attempt_metadata_test_match_insert BEFORE INSERT ON learner_assessment_attempt_metadata FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM test_attempts AS attempts INNER JOIN learner_assessment_versions AS versions ON versions.id = NEW.versionId WHERE attempts.id = NEW.attemptId AND attempts.testId = versions.testId) BEGIN SELECT RAISE(ABORT, 'assessment attempt version test mismatch'); END",
                "CREATE TRIGGER trg_learner_assessment_attempt_metadata_test_match_update BEFORE UPDATE OF attemptId, versionId ON learner_assessment_attempt_metadata FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM test_attempts AS attempts INNER JOIN learner_assessment_versions AS versions ON versions.id = NEW.versionId WHERE attempts.id = NEW.attemptId AND attempts.testId = versions.testId) BEGIN SELECT RAISE(ABORT, 'assessment attempt version test mismatch'); END",
                "CREATE TRIGGER trg_learner_assessment_answers_version_match_insert BEFORE INSERT ON learner_assessment_answers FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_assessment_attempt_metadata AS metadata INNER JOIN learner_assessment_question_versions AS question_versions ON question_versions.versionId = metadata.versionId WHERE metadata.attemptId = NEW.attemptId AND question_versions.questionId = NEW.questionId) BEGIN SELECT RAISE(ABORT, 'assessment answer question version mismatch'); END",
                "CREATE TRIGGER trg_learner_assessment_answers_version_match_update BEFORE UPDATE OF attemptId, questionId ON learner_assessment_answers FOR EACH ROW WHEN NOT EXISTS (SELECT 1 FROM learner_assessment_attempt_metadata AS metadata INNER JOIN learner_assessment_question_versions AS question_versions ON question_versions.versionId = metadata.versionId WHERE metadata.attemptId = NEW.attemptId AND question_versions.questionId = NEW.questionId) BEGIN SELECT RAISE(ABORT, 'assessment answer question version mismatch'); END",
                "CREATE TRIGGER trg_learner_ai_consent_events_append_only_update BEFORE UPDATE ON learner_ai_consent_events FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only consent event'); END",
                "CREATE TRIGGER trg_learner_ai_consent_events_append_only_delete BEFORE DELETE ON learner_ai_consent_events FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'append-only consent event'); END",
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_learner_assessment_versions_test_version ON learner_assessment_versions (testId, version)',
                'CREATE INDEX IF NOT EXISTS idx_learner_assessment_versions_test_status ON learner_assessment_versions (testId, status)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_learner_assessment_question_versions_version_question ON learner_assessment_question_versions (versionId, questionId)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_learner_assessment_question_versions_version_position ON learner_assessment_question_versions (versionId, position)',
                'CREATE INDEX IF NOT EXISTS idx_learner_assessment_question_versions_question ON learner_assessment_question_versions (questionId)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_learner_assessment_attempt_metadata_attempt ON learner_assessment_attempt_metadata (attemptId)',
                'CREATE INDEX IF NOT EXISTS idx_learner_assessment_attempt_metadata_version_status ON learner_assessment_attempt_metadata (versionId, status)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_learner_assessment_answers_attempt_question ON learner_assessment_answers (attemptId, questionId)',
                'CREATE INDEX IF NOT EXISTS idx_learner_assessment_answers_question ON learner_assessment_answers (questionId)',
                'CREATE INDEX IF NOT EXISTS idx_learner_skill_evidence_student_skill_observed ON learner_skill_evidence (studentSkillId, observedAt)',
                'CREATE INDEX IF NOT EXISTS idx_learner_skill_evidence_evidence_ref ON learner_skill_evidence (evidenceRef)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_learner_ai_consent_events_student_scope_occurred_request ON learner_ai_consent_events (studentId, scope, occurredAt, requestId)',
                'CREATE INDEX IF NOT EXISTS idx_learner_ai_consent_events_student_scope_occurred ON learner_ai_consent_events (studentId, scope, occurredAt)',
            ];
        }
    },
);
