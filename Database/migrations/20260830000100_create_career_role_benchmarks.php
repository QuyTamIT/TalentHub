<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create career role benchmarks, skill requirements, and assessment signals';
    }

    public function isReversible(): bool
    {
        return true;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('skills');

        foreach (['career_role_benchmarks', 'career_role_skill_requirements', 'career_role_assessment_signals'] as $table) {
            $context->assertTableAbsent($table);
        }

        if (($context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn()) !== '+00:00') {
            throw new RuntimeException('Career role benchmark migration requires MySQL session time zone +00:00.');
        }
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
            CREATE TABLE career_role_benchmarks (
                id CHAR(36) NOT NULL,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(150) NOT NULL,
                category VARCHAR(100) NOT NULL,
                isActive TINYINT(1) NOT NULL DEFAULT 1,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_career_role_benchmarks_code (code),
                KEY idx_career_role_benchmarks_active_category (isActive, category),
                CONSTRAINT chk_career_role_benchmarks_active CHECK (isActive IN (0,1))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $context->execute(<<<'SQL'
            CREATE TABLE career_role_skill_requirements (
                id CHAR(36) NOT NULL,
                roleId CHAR(36) NOT NULL,
                skillId CHAR(36) NOT NULL,
                minimumScore DECIMAL(5,2) NOT NULL,
                weight DECIMAL(5,2) NOT NULL,
                isRequired TINYINT(1) NOT NULL DEFAULT 1,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_career_role_skill_requirements_role_skill (roleId, skillId),
                KEY idx_career_role_skill_requirements_skill (skillId),
                CONSTRAINT fk_career_role_skill_requirements_role FOREIGN KEY (roleId) REFERENCES career_role_benchmarks(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_career_role_skill_requirements_skill FOREIGN KEY (skillId) REFERENCES skills(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_career_role_skill_requirements_required CHECK (isRequired IN (0,1)),
                CONSTRAINT chk_career_role_skill_requirements_minimum CHECK (minimumScore >= 0 AND minimumScore <= 100),
                CONSTRAINT chk_career_role_skill_requirements_weight CHECK (weight > 0 AND weight <= 100)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $context->execute(<<<'SQL'
            CREATE TABLE career_role_assessment_signals (
                id CHAR(36) NOT NULL,
                roleId CHAR(36) NOT NULL,
                assessmentFamily VARCHAR(50) NOT NULL,
                dimensionCode VARCHAR(100) NOT NULL,
                targetScore DECIMAL(5,2) NOT NULL,
                weight DECIMAL(5,2) NOT NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_career_role_assessment_signals_role_family_dimension (roleId, assessmentFamily, dimensionCode),
                KEY idx_career_role_assessment_signals_family (assessmentFamily, dimensionCode),
                CONSTRAINT fk_career_role_assessment_signals_role FOREIGN KEY (roleId) REFERENCES career_role_benchmarks(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT chk_career_role_assessment_signals_family CHECK (assessmentFamily IN ('holland','mbti','disc','multiple_intelligence')),
                CONSTRAINT chk_career_role_assessment_signals_target CHECK (targetScore >= 0 AND targetScore <= 100),
                CONSTRAINT chk_career_role_assessment_signals_weight CHECK (weight > 0 AND weight <= 100)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(MigrationContext $context): void
    {
        $context->execute('DROP TABLE IF EXISTS career_role_assessment_signals');
        $context->execute('DROP TABLE IF EXISTS career_role_skill_requirements');
        $context->execute('DROP TABLE IF EXISTS career_role_benchmarks');
    }
};
