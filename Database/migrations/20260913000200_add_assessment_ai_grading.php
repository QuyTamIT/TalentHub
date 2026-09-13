<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

/**
 * Sync flow for the 4 aptitude tests across Student -> AI -> Teacher -> School.
 * Adds AI grading and Teacher verification state to the canonical test_results table
 * without weakening existing foreign keys or learner scoring policy.
 *
 * AI_GRADED (default): produced automatically at submit time.
 * TEACHER_VERIFIED: teacher reviewed and adjusted dimension scores / comment.
 */
return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Add AI grading and teacher verification state to test_results';
    }

    public function isReversible(): bool
    {
        return false; // Preserve grading history; forward-only.
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['test_results', 'test_attempts', 'users'] as $table) {
            $context->assertTableExists($table);
        }

        $columns = $context->pdo()->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'test_results'"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('gradingStatus', $columns, true)) {
            throw new RuntimeException('test_results.gradingStatus already exists; inspect schema drift before applying.');
        }
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
ALTER TABLE test_results
    ADD COLUMN gradingStatus VARCHAR(20) NOT NULL DEFAULT 'ai_graded' AFTER summary,
    ADD COLUMN aiSummary TEXT NULL AFTER gradingStatus,
    ADD COLUMN teacherSummary TEXT NULL AFTER aiSummary,
    ADD COLUMN teacherComment VARCHAR(4000) NULL AFTER teacherSummary,
    ADD COLUMN teacherScoreOverrideJson LONGTEXT NULL AFTER teacherComment,
    ADD COLUMN teacherGradedBy CHAR(36) NULL AFTER teacherScoreOverrideJson,
    ADD COLUMN teacherGradedAt DATETIME(6) NULL AFTER teacherGradedBy,
    ADD CONSTRAINT chk_test_results_grading_status CHECK (gradingStatus IN ('ai_graded','teacher_verified')),
    ADD CONSTRAINT chk_test_results_teacher_override_json CHECK (
        teacherScoreOverrideJson IS NULL OR JSON_VALID(teacherScoreOverrideJson)
    ),
    ADD CONSTRAINT fk_test_results_teacher FOREIGN KEY (teacherGradedBy)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD KEY idx_test_results_grading_status (gradingStatus, createdAt)
SQL);
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Assessment AI grading migration is forward-only; preserve grading history.');
    }
};