<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
use TalentHub\Learner\Data\Migrations\LearnerMigrationPreflight;

return new ForwardMigrationDefinition(
    '002_create_ai_input_foundation',
    'Create canonical learner AI input foundation',
    __FILE__,
    hash_file('sha256', __FILE__),
    new class implements LearnerForwardMigration, LearnerMigrationPreflight {
        private const EXPECTED_SCHEMA = [
            'skills' => ['columns' => ['id', 'code', 'name', 'category', 'status', 'createdAt', 'updatedAt'], 'indexes' => ['uq_skills_code', 'idx_skills_status_category']],
            'student_skills' => ['columns' => ['id', 'studentId', 'skillId', 'levelScore', 'sourceType', 'verificationStatus', 'verifiedAt', 'createdAt', 'updatedAt'], 'indexes' => ['uq_student_skills_student_skill_source', 'idx_student_skills_skill', 'idx_student_skills_student_verification']],
            'talent_tests' => ['columns' => ['id', 'code', 'name', 'type', 'status', 'createdAt', 'updatedAt'], 'indexes' => ['uq_talent_tests_code', 'idx_talent_tests_status_type']],
            'test_questions' => ['columns' => ['id', 'testId', 'code', 'content', 'optionsJson', 'status', 'createdAt', 'updatedAt'], 'indexes' => ['uq_test_questions_test_code', 'idx_test_questions_test_status']],
            'test_attempts' => ['columns' => ['id', 'testId', 'studentId', 'status', 'startedAt', 'submittedAt', 'createdAt', 'updatedAt'], 'indexes' => ['idx_test_attempts_test', 'idx_test_attempts_student_status']],
            'test_results' => ['columns' => ['id', 'attemptId', 'resultCode', 'summary', 'dimensionScoresJson', 'scoringVersion', 'createdAt'], 'indexes' => ['uq_test_results_attempt']],
            'privacy_consents' => ['columns' => ['id', 'studentId', 'scope', 'isGranted', 'policyVersion', 'grantedAt', 'revokedAt', 'createdAt'], 'indexes' => ['uq_privacy_consents_student_scope_policy_created', 'idx_privacy_consents_student_scope_granted']],
            'activity_qr_tokens' => ['columns' => ['id', 'activityId', 'tokenHash', 'validFrom', 'validUntil', 'status', 'createdAt'], 'indexes' => ['uq_activity_qr_tokens_token_hash', 'idx_activity_qr_tokens_activity_status']],
            'checkins' => ['columns' => ['id', 'registrationId', 'qrTokenId', 'status', 'checkedInAt', 'confirmedAt', 'createdAt'], 'indexes' => ['uq_checkins_registration', 'idx_checkins_qr_token']],
            'experience_logs' => ['columns' => ['id', 'studentId', 'activityId', 'checkinId', 'hours', 'status', 'auditReason', 'confirmedAt', 'createdAt'], 'indexes' => ['uq_experience_logs_checkin', 'idx_experience_logs_student_status', 'idx_experience_logs_activity']],
        ];

        public function version(): string
        {
            return '002_create_ai_input_foundation';
        }

        public function description(): string
        {
            return 'Create canonical learner AI input foundation';
        }

        public function assertBeforeApply(SchemaInspector $schemaInspector): void
        {
            foreach (['student_profiles', 'activities', 'activity_registrations'] as $parent) {
                if (!$schemaInspector->hasTable($parent)) {
                    throw new RuntimeException('Learner migration preflight missing required parent table: ' . $parent);
                }
                if ($schemaInspector->columnType($parent, 'id') !== 'CHAR(36)') {
                    throw new RuntimeException('Learner migration preflight requires CHAR(36) parent id: ' . $parent . '.id');
                }
            }

            foreach (array_keys(self::EXPECTED_SCHEMA) as $table) {
                if ($schemaInspector->hasTable($table)) {
                    throw new RuntimeException('Learner migration preflight rejected existing canonical target: ' . $table);
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
CREATE TABLE IF NOT EXISTS skills (
  id CHAR(36) NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(150) NOT NULL, category VARCHAR(100) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'active',
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_skills_code (code), KEY idx_skills_status_category (status, category), CONSTRAINT chk_skills_status CHECK (status IN ('active','inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE IF NOT EXISTS student_skills (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, skillId CHAR(36) NOT NULL, levelScore DECIMAL(5,2) NOT NULL, sourceType VARCHAR(50) NOT NULL, verificationStatus VARCHAR(50) NOT NULL DEFAULT 'self_declared', verifiedAt DATETIME(6) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_student_skills_student_skill_source (studentId, skillId, sourceType), KEY idx_student_skills_skill (skillId), KEY idx_student_skills_student_verification (studentId, verificationStatus),
  CONSTRAINT fk_student_skills_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT fk_student_skills_skill FOREIGN KEY (skillId) REFERENCES skills(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_student_skills_level_score CHECK (levelScore >= 0 AND levelScore <= 100), CONSTRAINT chk_student_skills_source_type CHECK (sourceType IN ('self_declared','assessment','teacher','activity','import')), CONSTRAINT chk_student_skills_verification CHECK (verificationStatus IN ('self_declared','pending','verified','rejected')), CONSTRAINT chk_student_skills_verified_at CHECK ((verificationStatus = 'verified' AND verifiedAt IS NOT NULL) OR (verificationStatus <> 'verified' AND verifiedAt IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE IF NOT EXISTS talent_tests (
  id CHAR(36) NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'draft', createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_talent_tests_code (code), KEY idx_talent_tests_status_type (status, type), CONSTRAINT chk_talent_tests_type CHECK (type IN ('interest','aptitude','personality','skills')), CONSTRAINT chk_talent_tests_status CHECK (status IN ('draft','published','retired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE IF NOT EXISTS test_questions (
  id CHAR(36) NOT NULL, testId CHAR(36) NOT NULL, code VARCHAR(100) NOT NULL, content VARCHAR(4000) NOT NULL, optionsJson LONGTEXT NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'draft', createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_test_questions_test_code (testId, code), KEY idx_test_questions_test_status (testId, status), CONSTRAINT fk_test_questions_test FOREIGN KEY (testId) REFERENCES talent_tests(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_test_questions_options_json CHECK (JSON_VALID(optionsJson)), CONSTRAINT chk_test_questions_status CHECK (status IN ('draft','published','retired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE IF NOT EXISTS test_attempts (
  id CHAR(36) NOT NULL, testId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'in_progress', startedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), submittedAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), KEY idx_test_attempts_test (testId), KEY idx_test_attempts_student_status (studentId, status), CONSTRAINT fk_test_attempts_test FOREIGN KEY (testId) REFERENCES talent_tests(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT fk_test_attempts_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_test_attempts_status CHECK (status IN ('in_progress','submitted','expired','abandoned')), CONSTRAINT chk_test_attempts_submitted_at CHECK ((status = 'submitted' AND submittedAt IS NOT NULL) OR (status <> 'submitted' AND submittedAt IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE IF NOT EXISTS test_results (
  id CHAR(36) NOT NULL, attemptId CHAR(36) NOT NULL, resultCode VARCHAR(100) NOT NULL, summary VARCHAR(4000) NOT NULL, dimensionScoresJson LONGTEXT NOT NULL, scoringVersion VARCHAR(100) NOT NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_test_results_attempt (attemptId), CONSTRAINT fk_test_results_attempt FOREIGN KEY (attemptId) REFERENCES test_attempts(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_test_results_dimension_scores_json CHECK (JSON_VALID(dimensionScoresJson))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE IF NOT EXISTS privacy_consents (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, scope VARCHAR(50) NOT NULL, isGranted TINYINT UNSIGNED NOT NULL DEFAULT 0, policyVersion VARCHAR(100) NOT NULL, grantedAt DATETIME(6) NULL, revokedAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_privacy_consents_student_scope_policy_created (studentId, scope, policyVersion, createdAt), KEY idx_privacy_consents_student_scope_granted (studentId, scope, isGranted), CONSTRAINT fk_privacy_consents_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_privacy_consents_scope CHECK (scope IN ('assessment','skills','activity','evaluation')), CONSTRAINT chk_privacy_consents_granted CHECK (isGranted IN (0,1)), CONSTRAINT chk_privacy_consents_dates CHECK ((isGranted = 1 AND grantedAt IS NOT NULL AND revokedAt IS NULL) OR (isGranted = 0 AND grantedAt IS NULL AND revokedAt IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE IF NOT EXISTS activity_qr_tokens (
  id CHAR(36) NOT NULL, activityId CHAR(36) NOT NULL, tokenHash CHAR(64) NOT NULL, validFrom DATETIME(6) NOT NULL, validUntil DATETIME(6) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'active', createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_activity_qr_tokens_token_hash (tokenHash), KEY idx_activity_qr_tokens_activity_status (activityId, status), CONSTRAINT fk_activity_qr_tokens_activity FOREIGN KEY (activityId) REFERENCES activities(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_activity_qr_tokens_status CHECK (status IN ('active','revoked','expired')), CONSTRAINT chk_activity_qr_tokens_window CHECK (validUntil >= validFrom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE IF NOT EXISTS checkins (
  id CHAR(36) NOT NULL, registrationId CHAR(36) NOT NULL, qrTokenId CHAR(36) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending', checkedInAt DATETIME(6) NULL, confirmedAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_checkins_registration (registrationId), KEY idx_checkins_qr_token (qrTokenId), CONSTRAINT fk_checkins_registration FOREIGN KEY (registrationId) REFERENCES activity_registrations(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT fk_checkins_qr_token FOREIGN KEY (qrTokenId) REFERENCES activity_qr_tokens(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_checkins_status CHECK (status IN ('pending','checked_in','confirmed','rejected')), CONSTRAINT chk_checkins_checked_in_at CHECK ((status IN ('checked_in','confirmed') AND checkedInAt IS NOT NULL) OR (status IN ('pending','rejected') AND checkedInAt IS NULL)), CONSTRAINT chk_checkins_confirmed_at CHECK ((status = 'confirmed' AND confirmedAt IS NOT NULL) OR (status <> 'confirmed' AND confirmedAt IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
                <<<'SQL'
CREATE TABLE IF NOT EXISTS experience_logs (
  id CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, activityId CHAR(36) NOT NULL, checkinId CHAR(36) NOT NULL, hours DECIMAL(7,2) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending', auditReason VARCHAR(500) NULL, confirmedAt DATETIME(6) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id), UNIQUE KEY uq_experience_logs_checkin (checkinId), KEY idx_experience_logs_student_status (studentId, status), KEY idx_experience_logs_activity (activityId), CONSTRAINT fk_experience_logs_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT fk_experience_logs_activity FOREIGN KEY (activityId) REFERENCES activities(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT fk_experience_logs_checkin FOREIGN KEY (checkinId) REFERENCES checkins(id) ON DELETE RESTRICT ON UPDATE CASCADE, CONSTRAINT chk_experience_logs_hours CHECK (hours >= 0 AND hours <= 24), CONSTRAINT chk_experience_logs_status CHECK (status IN ('pending','confirmed','rejected')), CONSTRAINT chk_experience_logs_confirmed_at CHECK ((status = 'confirmed' AND confirmedAt IS NOT NULL) OR (status <> 'confirmed' AND confirmedAt IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
            ];
        }

        /** @return list<string> */
        private static function sqliteStatements(): array
        {
            return [
                "CREATE TABLE IF NOT EXISTS skills (id CHAR(36) NOT NULL PRIMARY KEY, code VARCHAR(100) NOT NULL, name VARCHAR(150) NOT NULL, category VARCHAR(100) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'active', createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, CHECK (status IN ('active','inactive')))",
                "CREATE TABLE IF NOT EXISTS student_skills (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, skillId CHAR(36) NOT NULL, levelScore DECIMAL(5,2) NOT NULL, sourceType VARCHAR(50) NOT NULL, verificationStatus VARCHAR(50) NOT NULL DEFAULT 'self_declared', verifiedAt TEXT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (skillId) REFERENCES skills(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (levelScore >= 0 AND levelScore <= 100), CHECK (sourceType IN ('self_declared','assessment','teacher','activity','import')), CHECK (verificationStatus IN ('self_declared','pending','verified','rejected')), CHECK ((verificationStatus = 'verified' AND verifiedAt IS NOT NULL) OR (verificationStatus <> 'verified' AND verifiedAt IS NULL)))",
                "CREATE TABLE IF NOT EXISTS talent_tests (id CHAR(36) NOT NULL PRIMARY KEY, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'draft', createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, CHECK (type IN ('interest','aptitude','personality','skills')), CHECK (status IN ('draft','published','retired')))",
                "CREATE TABLE IF NOT EXISTS test_questions (id CHAR(36) NOT NULL PRIMARY KEY, testId CHAR(36) NOT NULL, code VARCHAR(100) NOT NULL, content VARCHAR(4000) NOT NULL, optionsJson TEXT NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'draft', createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (testId) REFERENCES talent_tests(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (json_valid(optionsJson)), CHECK (status IN ('draft','published','retired')))",
                "CREATE TABLE IF NOT EXISTS test_attempts (id CHAR(36) NOT NULL PRIMARY KEY, testId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'in_progress', startedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, submittedAt TEXT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (testId) REFERENCES talent_tests(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (status IN ('in_progress','submitted','expired','abandoned')), CHECK ((status = 'submitted' AND submittedAt IS NOT NULL) OR (status <> 'submitted' AND submittedAt IS NULL)))",
                "CREATE TABLE IF NOT EXISTS test_results (id CHAR(36) NOT NULL PRIMARY KEY, attemptId CHAR(36) NOT NULL, resultCode VARCHAR(100) NOT NULL, summary VARCHAR(4000) NOT NULL, dimensionScoresJson TEXT NOT NULL, scoringVersion VARCHAR(100) NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (attemptId) REFERENCES test_attempts(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (json_valid(dimensionScoresJson)))",
                "CREATE TABLE IF NOT EXISTS privacy_consents (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, scope VARCHAR(50) NOT NULL, isGranted INTEGER NOT NULL DEFAULT 0, policyVersion VARCHAR(100) NOT NULL, grantedAt TEXT NULL, revokedAt TEXT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (scope IN ('assessment','skills','activity','evaluation')), CHECK (isGranted IN (0,1)), CHECK ((isGranted = 1 AND grantedAt IS NOT NULL AND revokedAt IS NULL) OR (isGranted = 0 AND grantedAt IS NULL AND revokedAt IS NOT NULL)))",
                "CREATE TABLE IF NOT EXISTS activity_qr_tokens (id CHAR(36) NOT NULL PRIMARY KEY, activityId CHAR(36) NOT NULL, tokenHash CHAR(64) NOT NULL, validFrom TEXT NOT NULL, validUntil TEXT NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'active', createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (activityId) REFERENCES activities(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (status IN ('active','revoked','expired')), CHECK (validUntil >= validFrom))",
                "CREATE TABLE IF NOT EXISTS checkins (id CHAR(36) NOT NULL PRIMARY KEY, registrationId CHAR(36) NOT NULL, qrTokenId CHAR(36) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending', checkedInAt TEXT NULL, confirmedAt TEXT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (registrationId) REFERENCES activity_registrations(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (qrTokenId) REFERENCES activity_qr_tokens(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (status IN ('pending','checked_in','confirmed','rejected')), CHECK ((status IN ('checked_in','confirmed') AND checkedInAt IS NOT NULL) OR (status IN ('pending','rejected') AND checkedInAt IS NULL)), CHECK ((status = 'confirmed' AND confirmedAt IS NOT NULL) OR (status <> 'confirmed' AND confirmedAt IS NULL)))",
                "CREATE TABLE IF NOT EXISTS experience_logs (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL, activityId CHAR(36) NOT NULL, checkinId CHAR(36) NOT NULL, hours DECIMAL(7,2) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'pending', auditReason VARCHAR(500) NULL, confirmedAt TEXT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (activityId) REFERENCES activities(id) ON DELETE RESTRICT ON UPDATE CASCADE, FOREIGN KEY (checkinId) REFERENCES checkins(id) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (hours >= 0 AND hours <= 24), CHECK (status IN ('pending','confirmed','rejected')), CHECK ((status = 'confirmed' AND confirmedAt IS NOT NULL) OR (status <> 'confirmed' AND confirmedAt IS NULL)))",
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_skills_code ON skills (code)',
                'CREATE INDEX IF NOT EXISTS idx_skills_status_category ON skills (status, category)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_student_skills_student_skill_source ON student_skills (studentId, skillId, sourceType)',
                'CREATE INDEX IF NOT EXISTS idx_student_skills_skill ON student_skills (skillId)',
                'CREATE INDEX IF NOT EXISTS idx_student_skills_student_verification ON student_skills (studentId, verificationStatus)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_talent_tests_code ON talent_tests (code)',
                'CREATE INDEX IF NOT EXISTS idx_talent_tests_status_type ON talent_tests (status, type)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_test_questions_test_code ON test_questions (testId, code)',
                'CREATE INDEX IF NOT EXISTS idx_test_questions_test_status ON test_questions (testId, status)',
                'CREATE INDEX IF NOT EXISTS idx_test_attempts_test ON test_attempts (testId)',
                'CREATE INDEX IF NOT EXISTS idx_test_attempts_student_status ON test_attempts (studentId, status)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_test_results_attempt ON test_results (attemptId)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_privacy_consents_student_scope_policy_created ON privacy_consents (studentId, scope, policyVersion, createdAt)',
                'CREATE INDEX IF NOT EXISTS idx_privacy_consents_student_scope_granted ON privacy_consents (studentId, scope, isGranted)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_activity_qr_tokens_token_hash ON activity_qr_tokens (tokenHash)',
                'CREATE INDEX IF NOT EXISTS idx_activity_qr_tokens_activity_status ON activity_qr_tokens (activityId, status)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_checkins_registration ON checkins (registrationId)',
                'CREATE INDEX IF NOT EXISTS idx_checkins_qr_token ON checkins (qrTokenId)',
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_experience_logs_checkin ON experience_logs (checkinId)',
                'CREATE INDEX IF NOT EXISTS idx_experience_logs_student_status ON experience_logs (studentId, status)',
                'CREATE INDEX IF NOT EXISTS idx_experience_logs_activity ON experience_logs (activityId)',
            ];
        }
    },
);
