<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create learner assessment canonical schema';
    }

    public function preflight(MigrationContext $context): void
    {
        $tables = [
            'talent_tests',
            'test_questions',
            'learner_assessment_versions',
            'learner_assessment_question_versions',
            'test_attempts',
            'learner_assessment_attempt_metadata',
            'learner_assessment_answers',
            'test_results',
        ];
        $existing = array_values(array_filter($tables, $context->tableExists(...)));
        if ($existing !== [] && count($existing) !== count($tables)) {
            throw new \RuntimeException(
                'Learner assessment schema reconciliation requires all 8 assessment tables to exist together; found partial schema: '
                . implode(', ', $existing) . '.',
            );
        }

        // test_attempts.studentId is the only external foreign key in this migration.
        $context->assertTableExists('student_profiles');
        $this->assertStudentProfilesContract($context);

        $timeZoneStatement = $context->pdo()->query('SELECT @@session.time_zone');
        $timeZone = $timeZoneStatement === false ? false : $timeZoneStatement->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new \RuntimeException('Learner assessment schema migration requires MySQL session time zone +00:00.');
        }

        if ($existing !== []) {
            $this->assertExistingAssessmentSchemaContract($context);
            if (!$this->hasCheckConstraint($context, 'talent_tests', 'chk_talent_tests_type')) {
                throw new \RuntimeException(
                    'Existing talent_tests schema is incompatible; named type CHECK constraint is missing.',
                );
            }
        }
    }

    private function assertStudentProfilesContract(MigrationContext $context): void
    {
        $columnStatement = $context->pdo()->query(<<<'SQL'
            SELECT
                columns.DATA_TYPE AS data_type,
                columns.CHARACTER_MAXIMUM_LENGTH AS character_maximum_length,
                columns.IS_NULLABLE AS is_nullable,
                columns.CHARACTER_SET_NAME AS character_set_name,
                columns.COLLATION_NAME AS collation_name,
                tables.ENGINE AS engine,
                tables.TABLE_COLLATION AS table_collation
            FROM information_schema.columns AS columns
            INNER JOIN information_schema.tables AS tables
                ON tables.TABLE_SCHEMA = columns.TABLE_SCHEMA
                AND tables.TABLE_NAME = columns.TABLE_NAME
            WHERE columns.TABLE_SCHEMA = DATABASE()
                AND columns.TABLE_NAME = 'student_profiles'
                AND columns.COLUMN_NAME = 'id'
            SQL);
        $column = $columnStatement === false ? false : $columnStatement->fetch(\PDO::FETCH_ASSOC);

        if (
            !is_array($column)
            || strtolower((string) $column['data_type']) !== 'char'
            || (int) $column['character_maximum_length'] !== 36
            || strtoupper((string) $column['is_nullable']) !== 'NO'
            || strtolower((string) $column['character_set_name']) !== 'utf8mb4'
            || strtolower((string) $column['collation_name']) !== 'utf8mb4_unicode_ci'
            || strtolower((string) $column['engine']) !== 'innodb'
            || strtolower((string) $column['table_collation']) !== 'utf8mb4_unicode_ci'
        ) {
            throw new \RuntimeException(
                'Learner assessment schema migration requires student_profiles.id CHAR(36) NOT NULL on InnoDB utf8mb4_unicode_ci.',
            );
        }

        $primaryStatement = $context->pdo()->query(<<<'SQL'
            SELECT COLUMN_NAME AS column_name, SEQ_IN_INDEX AS sequence_in_index
            FROM information_schema.statistics
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'student_profiles'
                AND INDEX_NAME = 'PRIMARY'
            ORDER BY SEQ_IN_INDEX
            SQL);
        $primaryColumns = $primaryStatement === false ? [] : $primaryStatement->fetchAll(\PDO::FETCH_ASSOC);
        if (
            count($primaryColumns) !== 1
            || (string) $primaryColumns[0]['column_name'] !== 'id'
            || (int) $primaryColumns[0]['sequence_in_index'] !== 1
        ) {
            throw new \RuntimeException(
                'Learner assessment schema migration requires student_profiles.id to be the single-column primary key.',
            );
        }
    }

    private function assertExistingAssessmentSchemaContract(MigrationContext $context): void
    {
        $requiredColumns = [
            'talent_tests' => ['id', 'code', 'name', 'type', 'status', 'createdAt', 'updatedAt'],
            'test_questions' => ['id', 'testId', 'code', 'content', 'optionsJson', 'status', 'createdAt', 'updatedAt'],
            'learner_assessment_versions' => ['id', 'testId', 'version', 'scoringVersion', 'schemaHash', 'status', 'publishedAt', 'createdAt'],
            'learner_assessment_question_versions' => ['id', 'versionId', 'questionId', 'position', 'dimensionCode', 'required', 'createdAt'],
            'test_attempts' => ['id', 'testId', 'studentId', 'status', 'startedAt', 'submittedAt', 'createdAt', 'updatedAt'],
            'learner_assessment_attempt_metadata' => ['id', 'attemptId', 'versionId', 'status', 'expiresAt', 'submittedAt', 'inputHash', 'createdAt', 'updatedAt'],
            'learner_assessment_answers' => ['id', 'attemptId', 'questionId', 'answerJson', 'answeredAt'],
            'test_results' => ['id', 'attemptId', 'resultCode', 'summary', 'dimensionScoresJson', 'scoringVersion', 'createdAt'],
        ];

        $statement = $context->pdo()->prepare(
            'SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
        );
        foreach ($requiredColumns as $table => $columns) {
            $statement->execute(['table' => $table]);
            $actual = array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
            $missing = array_values(array_diff($columns, $actual));
            if ($missing !== []) {
                throw new \RuntimeException(
                    "Existing {$table} schema is incompatible; missing columns: " . implode(', ', $missing) . '.',
                );
            }
        }
    }

    private function hasIndex(MigrationContext $context, string $table, string $index): bool
    {
        $statement = $context->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index',
        );
        $statement->execute(['table' => $table, 'index' => $index]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function hasCheckConstraint(MigrationContext $context, string $table, string $constraint): bool
    {
        $statement = $context->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.table_constraints WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint AND CONSTRAINT_TYPE = \'CHECK\'',
        );
        $statement->execute(['table' => $table, 'constraint' => $constraint]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function reconcileExistingSchema(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
            ALTER TABLE talent_tests
            DROP CHECK chk_talent_tests_type,
            ADD CONSTRAINT chk_talent_tests_type CHECK (
                type IN ('holland', 'mbti', 'disc', 'multiple_intelligence', 'interest', 'aptitude', 'personality', 'skills')
            )
            SQL);

        if (!$this->hasIndex($context, 'learner_assessment_versions', 'idx_learner_assessment_versions_published')) {
            $context->execute(
                'ALTER TABLE learner_assessment_versions ADD KEY idx_learner_assessment_versions_published (status, publishedAt)',
            );
        }
        if (!$this->hasIndex($context, 'learner_assessment_question_versions', 'idx_learner_assessment_question_versions_version')) {
            $context->execute(
                'ALTER TABLE learner_assessment_question_versions ADD KEY idx_learner_assessment_question_versions_version (versionId)',
            );
        }
        if (!$this->hasIndex($context, 'test_attempts', 'idx_test_attempts_student_test')) {
            $context->execute(
                'ALTER TABLE test_attempts ADD KEY idx_test_attempts_student_test (studentId, testId, status)',
            );
        }
    }

    public function up(MigrationContext $context): void
    {
        if ($context->tableExists('talent_tests')) {
            $this->reconcileExistingSchema($context);
            return;
        }

        // ── talent_tests ──────────────────────────────────────────────────────────
        // 12 definitions: holland, mbti, disc, multiple_intelligence × middle/high/college
        // Schema verified against learner_assessment_catalog_test.php fixture and existing DB.
        $context->execute("
            CREATE TABLE talent_tests (
                id CHAR(36) NOT NULL,
                code VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'draft',
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_talent_tests_code (code),
                KEY idx_talent_tests_status_type (status, type),
                CONSTRAINT chk_talent_tests_type CHECK (type IN ('holland', 'mbti', 'disc', 'multiple_intelligence', 'interest', 'aptitude', 'personality', 'skills')),
                CONSTRAINT chk_talent_tests_status CHECK (status IN ('draft', 'published', 'retired'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── test_questions ───────────────────────────────────────────────────────
        // optionsJson format: [{"value":1,"label":"Hoàn toàn không đồng ý"},...]
        // VARCHAR(4000) for content per existing schema; LONGTEXT for optionsJson.
        $context->execute("
            CREATE TABLE test_questions (
                id CHAR(36) NOT NULL,
                testId CHAR(36) NOT NULL,
                code VARCHAR(100) NOT NULL,
                content VARCHAR(4000) NOT NULL,
                optionsJson LONGTEXT NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'draft',
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_test_questions_test_code (testId, code),
                KEY idx_test_questions_test_status (testId, status),
                CONSTRAINT fk_test_questions_test FOREIGN KEY (testId) REFERENCES talent_tests(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_test_questions_options_json CHECK (JSON_VALID(optionsJson)),
                CONSTRAINT chk_test_questions_status CHECK (status IN ('draft', 'published', 'retired'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── learner_assessment_versions ──────────────────────────────────────────
        // UNIQUE(testId, version): prevents duplicate version per test.
        // CHECK(publishedAt): enforces publishedAt NOT NULL when status is published/retired.
        // Status values: draft → published → retired.
        $context->execute("
            CREATE TABLE learner_assessment_versions (
                id CHAR(36) NOT NULL,
                testId CHAR(36) NOT NULL,
                version VARCHAR(100) NOT NULL,
                scoringVersion VARCHAR(100) NOT NULL,
                schemaHash CHAR(64) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'draft',
                publishedAt DATETIME(6) NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_learner_assessment_versions_test_version (testId, version),
                KEY idx_learner_assessment_versions_test_status (testId, status),
                KEY idx_learner_assessment_versions_published (status, publishedAt),
                CONSTRAINT fk_learner_assessment_versions_test FOREIGN KEY (testId) REFERENCES talent_tests(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_learner_assessment_versions_published_at CHECK (
                    (status = 'draft' AND publishedAt IS NULL) OR
                    (status IN ('published', 'retired') AND publishedAt IS NOT NULL)
                ),
                CONSTRAINT chk_learner_assessment_versions_status CHECK (status IN ('draft', 'published', 'retired'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── learner_assessment_question_versions ─────────────────────────────────
        // Binds a question to a published version with frozen dimension code and sort position.
        // dimensionCode format: scorer-specific (e.g. R:+, EI:E, D, LOGI:-)
        // CHECK(position >= 1): enforces 1-based position.
        $context->execute("
            CREATE TABLE learner_assessment_question_versions (
                id CHAR(36) NOT NULL,
                versionId CHAR(36) NOT NULL,
                questionId CHAR(36) NOT NULL,
                position INT UNSIGNED NOT NULL,
                dimensionCode VARCHAR(100) NOT NULL,
                required TINYINT UNSIGNED NOT NULL DEFAULT 1,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_learner_assessment_question_versions_version_question (versionId, questionId),
                UNIQUE KEY uq_learner_assessment_question_versions_version_position (versionId, position),
                KEY idx_learner_assessment_question_versions_version (versionId),
                KEY idx_learner_assessment_question_versions_question (questionId),
                CONSTRAINT fk_learner_assessment_question_versions_version FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_learner_assessment_question_versions_question FOREIGN KEY (questionId) REFERENCES test_questions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_learner_assessment_question_versions_position CHECK (position >= 1),
                CONSTRAINT chk_learner_assessment_question_versions_required CHECK (required IN (0, 1))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── test_attempts ───────────────────────────────────────────────────────
        // Lifecycle: in_progress → submitted | expired | abandoned
        // submittedAt IS NOT NULL only when status = 'submitted'
        // Index (studentId, testId, status) added per existing schema.
        $context->execute("
            CREATE TABLE test_attempts (
                id CHAR(36) NOT NULL,
                testId CHAR(36) NOT NULL,
                studentId CHAR(36) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'in_progress',
                startedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                submittedAt DATETIME(6) NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                KEY idx_test_attempts_test (testId),
                KEY idx_test_attempts_student_status (studentId, status),
                KEY idx_test_attempts_student_test (studentId, testId, status),
                CONSTRAINT fk_test_attempts_test FOREIGN KEY (testId) REFERENCES talent_tests(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_test_attempts_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_test_attempts_status CHECK (status IN ('in_progress', 'submitted', 'expired', 'abandoned')),
                CONSTRAINT chk_test_attempts_submitted_at CHECK (
                    (status = 'submitted' AND submittedAt IS NOT NULL) OR
                    (status <> 'submitted' AND submittedAt IS NULL)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── learner_assessment_attempt_metadata ───────────────────────────────────
        // Binds an attempt to a published version; owns attempt lifecycle metadata.
        // UNIQUE(attemptId): one metadata row per attempt.
        // inputHash: SHA-256 of (version + scoring + answers) for auditability.
        // CHECK(submission): submittedAt AND inputHash NOT NULL only when status = 'submitted'.
        // Combined index on (versionId, status) per existing schema.
        $context->execute("
            CREATE TABLE learner_assessment_attempt_metadata (
                id CHAR(36) NOT NULL,
                attemptId CHAR(36) NOT NULL UNIQUE,
                versionId CHAR(36) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'in_progress',
                expiresAt DATETIME(6) NULL,
                submittedAt DATETIME(6) NULL,
                inputHash CHAR(64) NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                KEY idx_learner_assessment_attempt_metadata_version_status (versionId, status),
                CONSTRAINT fk_learner_assessment_attempt_metadata_attempt FOREIGN KEY (attemptId) REFERENCES test_attempts(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_learner_assessment_attempt_metadata_version FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_learner_assessment_attempt_metadata_status CHECK (status IN ('in_progress', 'submitted', 'expired')),
                CONSTRAINT chk_learner_assessment_attempt_metadata_submission CHECK (
                    (status = 'submitted' AND submittedAt IS NOT NULL AND inputHash IS NOT NULL) OR
                    (status <> 'submitted' AND submittedAt IS NULL AND inputHash IS NULL)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── learner_assessment_answers ────────────────────────────────────────────
        // Autosave one answer per (attempt, question); upserted on each save.
        // UNIQUE(attemptId, questionId): prevents duplicate answers for same question.
        // FK to attempt metadata (not test_attempts) to preserve version binding.
        $context->execute("
            CREATE TABLE learner_assessment_answers (
                id CHAR(36) NOT NULL,
                attemptId CHAR(36) NOT NULL,
                questionId CHAR(36) NOT NULL,
                answerJson LONGTEXT NOT NULL,
                answeredAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_learner_assessment_answers_attempt_question (attemptId, questionId),
                KEY idx_learner_assessment_answers_question (questionId),
                CONSTRAINT fk_learner_assessment_answers_attempt FOREIGN KEY (attemptId) REFERENCES learner_assessment_attempt_metadata(attemptId) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_learner_assessment_answers_question FOREIGN KEY (questionId) REFERENCES test_questions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_learner_assessment_answers_json CHECK (JSON_VALID(answerJson))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── test_results ────────────────────────────────────────────────────────
        // Immutable scored result per submitted attempt.
        // UNIQUE(attemptId): one result per attempt.
        // dimensionScoresJson stores all dimension scores (scorer-specific shape).
        $context->execute("
            CREATE TABLE test_results (
                id CHAR(36) NOT NULL,
                attemptId CHAR(36) NOT NULL UNIQUE,
                resultCode VARCHAR(100) NOT NULL,
                summary VARCHAR(4000) NOT NULL,
                dimensionScoresJson LONGTEXT NOT NULL,
                scoringVersion VARCHAR(100) NOT NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                KEY idx_test_results_scoring_version (scoringVersion),
                CONSTRAINT fk_test_results_attempt FOREIGN KEY (attemptId) REFERENCES test_attempts(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_test_results_dimension_scores_json CHECK (JSON_VALID(dimensionScoresJson))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        throw new \RuntimeException('Learner assessment canonical schema migration is irreversible.');
    }
};
