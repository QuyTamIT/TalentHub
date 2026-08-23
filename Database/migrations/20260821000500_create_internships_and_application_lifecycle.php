<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create internship posts, applications, status history, and application profile snapshots';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['users', 'student_profiles', 'enterprises', 'enterprise_members', 'privacy_consents', 'schema_migrations'] as $table) {
            $context->assertTableExists($table);
        }

        if (($context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn()) !== '+00:00') {
            throw new RuntimeException('Phase 7 migration requires MySQL session time zone +00:00.');
        }

        foreach (['internship_posts', 'internship_applications', 'application_status_history', 'application_profile_snapshots'] as $table) {
            if ($context->tableExists($table)) {
                throw new RuntimeException("{$table} already exists; partial Phase 7 state is not supported.");
            }
        }
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
            CREATE TABLE internship_posts (
                id CHAR(36) NOT NULL,
                enterpriseId CHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                field VARCHAR(150) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'draft',
                location VARCHAR(255) NOT NULL,
                workType VARCHAR(100) NOT NULL,
                duration VARCHAR(100) NOT NULL,
                educationLevel VARCHAR(150) NOT NULL,
                description TEXT NOT NULL,
                benefits TEXT NULL,
                skillsJson JSON NOT NULL,
                requirementsJson JSON NULL,
                slots INT UNSIGNED NOT NULL DEFAULT 1,
                deadline DATETIME(6) NOT NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                KEY idx_internship_posts_enterprise_status_deadline (enterpriseId, status, deadline),
                CONSTRAINT fk_internship_posts_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_internship_posts_status CHECK (status IN ('draft','active','closed','cancelled')),
                CONSTRAINT chk_internship_posts_skills_json CHECK (JSON_VALID(skillsJson)),
                CONSTRAINT chk_internship_posts_requirements_json CHECK (requirementsJson IS NULL OR JSON_VALID(requirementsJson)),
                CONSTRAINT chk_internship_posts_slots CHECK (slots > 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $context->execute(<<<'SQL'
            CREATE TABLE internship_applications (
                id CHAR(36) NOT NULL,
                postId CHAR(36) NOT NULL,
                studentId CHAR(36) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'submitted',
                message VARCHAR(500) NULL,
                cvUrl VARCHAR(500) NULL,
                reviewerNote TEXT NULL,
                reviewedAt DATETIME(6) NULL,
                reviewedBy CHAR(36) NULL,
                appliedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_internship_applications_post_student (postId, studentId),
                KEY idx_internship_applications_student_status (studentId, status, appliedAt),
                KEY idx_internship_applications_post_status (postId, status, appliedAt),
                CONSTRAINT fk_internship_applications_post FOREIGN KEY (postId) REFERENCES internship_posts(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_internship_applications_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                KEY idx_internship_applications_reviewed_by (reviewedBy),
                CONSTRAINT fk_internship_applications_reviewer FOREIGN KEY (reviewedBy) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT chk_internship_applications_status CHECK (status IN ('submitted','reviewing','interview','accepted','declined','withdrawn'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $context->execute(<<<'SQL'
            CREATE TABLE application_status_history (
                id CHAR(36) NOT NULL,
                applicationId CHAR(36) NOT NULL,
                fromStatus VARCHAR(50) NULL,
                toStatus VARCHAR(50) NOT NULL,
                changedByUserId CHAR(36) NOT NULL,
                changedByRole VARCHAR(50) NOT NULL,
                note TEXT NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                KEY idx_application_status_history_application (applicationId, createdAt),
                CONSTRAINT fk_application_status_history_application FOREIGN KEY (applicationId) REFERENCES internship_applications(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_application_status_history_user FOREIGN KEY (changedByUserId) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_application_status_history_status CHECK (toStatus IN ('submitted','reviewing','interview','accepted','declined','withdrawn'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $context->execute(<<<'SQL'
            CREATE TABLE application_profile_snapshots (
                id CHAR(36) NOT NULL,
                applicationId CHAR(36) NOT NULL,
                consentId CHAR(36) NOT NULL,
                schemaVersion VARCHAR(50) NOT NULL,
                snapshotPayload JSON NOT NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_application_profile_snapshots_application (applicationId),
                KEY idx_application_profile_snapshots_consent (consentId),
                CONSTRAINT fk_application_profile_snapshots_application FOREIGN KEY (applicationId) REFERENCES internship_applications(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_application_profile_snapshots_consent FOREIGN KEY (consentId) REFERENCES privacy_consents(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_application_profile_snapshots_payload CHECK (JSON_VALID(snapshotPayload))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(MigrationContext $context): void
    {
    }
};
