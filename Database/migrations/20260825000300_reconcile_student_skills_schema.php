<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Reconcile legacy learner passport tables with the canonical read-model schema';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('student_skills');
        $context->assertTableExists('student_profiles');
        $context->assertTableExists('skills');
        $context->assertTableExists('experience_logs');
        $context->assertTableExists('talent_tests');
        $context->assertTableExists('test_attempts');
        $context->assertTableExists('test_results');

        if ($this->hasColumn($context, 'student_skills', 'level') && !$this->hasColumn($context, 'student_skills', 'levelScore')) {
            $invalid = (int) $context->pdo()->query(
                "SELECT COUNT(*) FROM student_skills WHERE level IS NULL OR level NOT REGEXP '^[0-9]+([.][0-9]+)?$' OR CAST(level AS DECIMAL(10,2)) < 0 OR CAST(level AS DECIMAL(10,2)) > 100"
            )->fetchColumn();
            if ($invalid > 0) {
                throw new RuntimeException('student_skills.level contains values that cannot be converted to levelScore.');
            }
        }
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();

        if (!$this->hasColumn($context, 'skills', 'code')) {
            $pdo->exec("ALTER TABLE skills ADD COLUMN code VARCHAR(100) NOT NULL AFTER id");
        }
        if (!$this->hasColumn($context, 'skills', 'status')) {
            $pdo->exec("ALTER TABLE skills ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'active' AFTER category");
        }
        if (!$this->hasColumn($context, 'skills', 'createdAt')) {
            $pdo->exec('ALTER TABLE skills ADD COLUMN createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER status');
        }
        if (!$this->hasColumn($context, 'skills', 'updatedAt')) {
            $pdo->exec('ALTER TABLE skills ADD COLUMN updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) AFTER createdAt');
        }
        if (!$this->hasIndex($context, 'skills', 'uq_skills_code')) {
            $pdo->exec('ALTER TABLE skills ADD UNIQUE INDEX uq_skills_code (code)');
        }
        if (!$this->hasIndex($context, 'skills', 'idx_skills_status_category')) {
            $pdo->exec('ALTER TABLE skills ADD INDEX idx_skills_status_category (status, category)');
        }

        if ($this->hasColumn($context, 'student_skills', 'level') && !$this->hasColumn($context, 'student_skills', 'levelScore')) {
            $pdo->exec('ALTER TABLE student_skills CHANGE COLUMN level levelScore DECIMAL(5,2) NOT NULL');
        }
        if ($this->hasColumn($context, 'student_skills', 'verifiedStatus') && !$this->hasColumn($context, 'student_skills', 'verificationStatus')) {
            $pdo->exec("ALTER TABLE student_skills CHANGE COLUMN verifiedStatus verificationStatus VARCHAR(50) NOT NULL DEFAULT 'self_declared'");
        }
        if (!$this->hasColumn($context, 'student_skills', 'sourceType')) {
            $pdo->exec("ALTER TABLE student_skills ADD COLUMN sourceType VARCHAR(50) NOT NULL DEFAULT 'import' AFTER levelScore");
        }
        if (!$this->hasColumn($context, 'student_skills', 'createdAt')) {
            $pdo->exec('ALTER TABLE student_skills ADD COLUMN createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER verifiedAt');
        }
        if (!$this->hasColumn($context, 'student_skills', 'updatedAt')) {
            $pdo->exec('ALTER TABLE student_skills ADD COLUMN updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) AFTER createdAt');
        }

        if (!$this->hasIndex($context, 'student_skills', 'uq_student_skills_student_skill_source')) {
            $pdo->exec('ALTER TABLE student_skills ADD UNIQUE INDEX uq_student_skills_student_skill_source (studentId, skillId, sourceType)');
        }
        if (!$this->hasIndex($context, 'student_skills', 'idx_student_skills_skill')) {
            $pdo->exec('ALTER TABLE student_skills ADD INDEX idx_student_skills_skill (skillId)');
        }
        if (!$this->hasIndex($context, 'student_skills', 'idx_student_skills_student_verification')) {
            $pdo->exec('ALTER TABLE student_skills ADD INDEX idx_student_skills_student_verification (studentId, verificationStatus)');
        }

        if (!$this->hasColumn($context, 'experience_logs', 'status')) {
            $pdo->exec("ALTER TABLE experience_logs ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER hours");
        }
        if (!$this->hasColumn($context, 'experience_logs', 'confirmedAt')) {
            $pdo->exec('ALTER TABLE experience_logs ADD COLUMN confirmedAt DATETIME(6) NULL AFTER auditReason');
        }
        if (!$this->hasColumn($context, 'experience_logs', 'createdAt')) {
            $pdo->exec('ALTER TABLE experience_logs ADD COLUMN createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER confirmedAt');
        }
        $pdo->exec('ALTER TABLE experience_logs MODIFY COLUMN auditReason VARCHAR(500) NULL');

        if ($this->hasColumn($context, 'test_attempts', 'completedAt') && !$this->hasColumn($context, 'test_attempts', 'submittedAt')) {
            $pdo->exec('ALTER TABLE test_attempts CHANGE COLUMN completedAt submittedAt DATETIME(6) NULL');
        }
        if ($this->hasColumn($context, 'test_results', 'dimensionScores') && !$this->hasColumn($context, 'test_results', 'dimensionScoresJson')) {
            $pdo->exec('ALTER TABLE test_results CHANGE COLUMN dimensionScores dimensionScoresJson LONGTEXT NOT NULL');
        }

        foreach ([
            ['talent_tests', 'code', "VARCHAR(100) NOT NULL AFTER id"],
            ['talent_tests', 'status', "VARCHAR(50) NOT NULL DEFAULT 'draft' AFTER type"],
            ['talent_tests', 'createdAt', 'DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER status'],
            ['talent_tests', 'updatedAt', 'DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) AFTER createdAt'],
            ['test_attempts', 'status', "VARCHAR(50) NOT NULL DEFAULT 'in_progress' AFTER studentId"],
            ['test_attempts', 'createdAt', 'DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER submittedAt'],
            ['test_attempts', 'updatedAt', 'DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) AFTER createdAt'],
            ['test_results', 'scoringVersion', "VARCHAR(100) NOT NULL DEFAULT 'legacy' AFTER dimensionScoresJson"],
            ['test_results', 'createdAt', 'DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER scoringVersion'],
            ['assessments', 'publishedAt', 'DATETIME(6) NULL AFTER status'],
            ['assessments', 'version', 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER publishedAt'],
            ['assessment_criteria', 'code', 'VARCHAR(100) NOT NULL AFTER id'],
            ['assessment_criteria', 'displayOrder', 'INT NOT NULL DEFAULT 0 AFTER maxScore'],
        ] as [$table, $column, $definition]) {
            if (!$this->hasColumn($context, $table, $column)) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
        $pdo->exec('ALTER TABLE test_results MODIFY COLUMN summary VARCHAR(4000) NOT NULL');
    }

    public function down(MigrationContext $context): void
    {
    }

    private function hasColumn(MigrationContext $context, string $table, string $column): bool
    {
        $statement = $context->pdo()->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1'
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (bool) $statement->fetchColumn();
    }

    private function hasIndex(MigrationContext $context, string $table, string $index): bool
    {
        $statement = $context->pdo()->prepare(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index LIMIT 1'
        );
        $statement->execute(['table' => $table, 'index' => $index]);
        return (bool) $statement->fetchColumn();
    }
};
