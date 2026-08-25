<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create school-scoped internship mentor assignments';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['internship_applications', 'teacher_profiles', 'users'] as $table) {
            $context->assertTableExists($table);
        }
        $context->assertTableAbsent('internship_mentor_assignments');
        if ($context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn() !== '+00:00') {
            throw new RuntimeException('Internship mentor migration requires MySQL session time zone +00:00.');
        }
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
            CREATE TABLE internship_mentor_assignments (
                id CHAR(36) NOT NULL,
                applicationId CHAR(36) NOT NULL,
                mentorTeacherId CHAR(36) NOT NULL,
                assignedByUserId CHAR(36) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                assignedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                endedAt DATETIME(6) NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_internship_mentor_application (applicationId),
                KEY idx_internship_mentor_teacher_status (mentorTeacherId, status),
                CONSTRAINT fk_internship_mentor_application FOREIGN KEY (applicationId) REFERENCES internship_applications(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_internship_mentor_teacher FOREIGN KEY (mentorTeacherId) REFERENCES teacher_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_internship_mentor_assigner FOREIGN KEY (assignedByUserId) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_internship_mentor_status CHECK (status IN ('active','ended')),
                CONSTRAINT chk_internship_mentor_ended_at CHECK ((status='active' AND endedAt IS NULL) OR (status='ended' AND endedAt IS NOT NULL))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only: mentor history must be preserved.
    }
};
