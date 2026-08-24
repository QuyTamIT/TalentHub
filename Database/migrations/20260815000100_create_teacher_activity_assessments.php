<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create teacher activity registration and assessment tables';
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['activities', 'activity_registrations', 'assessment_criteria', 'assessments', 'assessment_scores'] as $table) {
            $context->assertTableAbsent($table);
        }
    }

    public function up(MigrationContext $context): void
    {
        $context->execute("CREATE TABLE activities (
            id CHAR(36) NOT NULL,
            schoolId CHAR(36) NOT NULL,
            createdByTeacherId CHAR(36) NOT NULL,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            startAt DATETIME(6) NOT NULL,
            endAt DATETIME(6) NULL,
            capacity INT UNSIGNED NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'draft',
            createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            PRIMARY KEY(id),
            KEY idx_activities_teacher_status(createdByTeacherId, status),
            KEY idx_activities_school_start(schoolId, startAt),
            CONSTRAINT fk_activities_school FOREIGN KEY(schoolId) REFERENCES schools(id) ON UPDATE CASCADE,
            CONSTRAINT fk_activities_teacher FOREIGN KEY(createdByTeacherId) REFERENCES teacher_profiles(id) ON UPDATE CASCADE,
            CONSTRAINT chk_activities_time CHECK(endAt IS NULL OR endAt >= startAt),
            CONSTRAINT chk_activities_status CHECK(status IN('draft','published','ongoing','completed','archived'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $context->execute("CREATE TABLE activity_registrations (
            id CHAR(36) NOT NULL,
            activityId CHAR(36) NOT NULL,
            studentId CHAR(36) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            registeredAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            PRIMARY KEY(id),
            UNIQUE KEY uq_activity_registrations_activity_student(activityId, studentId),
            KEY idx_activity_registrations_student_status(studentId, status),
            CONSTRAINT fk_activity_registrations_activity FOREIGN KEY(activityId) REFERENCES activities(id) ON UPDATE CASCADE,
            CONSTRAINT fk_activity_registrations_student FOREIGN KEY(studentId) REFERENCES student_profiles(id) ON UPDATE CASCADE,
            CONSTRAINT chk_activity_registrations_status CHECK(status IN('pending','approved','rejected','cancelled','attended'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $context->execute("CREATE TABLE assessment_criteria (
            id CHAR(36) NOT NULL,
            code VARCHAR(100) NOT NULL,
            name VARCHAR(150) NOT NULL,
            description VARCHAR(500) NULL,
            minScore DECIMAL(7,2) NOT NULL DEFAULT 0,
            maxScore DECIMAL(7,2) NOT NULL,
            displayOrder INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            PRIMARY KEY(id),
            UNIQUE KEY uq_assessment_criteria_code(code),
            KEY idx_assessment_criteria_status_order(status, displayOrder),
            CONSTRAINT chk_assessment_criteria_range CHECK(minScore >= 0 AND maxScore >= minScore),
            CONSTRAINT chk_assessment_criteria_status CHECK(status IN('active','inactive'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $context->execute("CREATE TABLE assessments (
            id CHAR(36) NOT NULL,
            teacherId CHAR(36) NOT NULL,
            studentId CHAR(36) NOT NULL,
            activityId CHAR(36) NOT NULL,
            overallScore DECIMAL(5,2) NULL,
            comment VARCHAR(1000) NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'draft',
            publishedAt DATETIME(6) NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            PRIMARY KEY(id),
            UNIQUE KEY uq_assessments_teacher_student_activity(teacherId, studentId, activityId),
            KEY idx_assessments_teacher_status_updated(teacherId, status, updatedAt),
            KEY idx_assessments_student_status(studentId, status),
            KEY idx_assessments_registration(activityId, studentId),
            CONSTRAINT fk_assessments_teacher FOREIGN KEY(teacherId) REFERENCES teacher_profiles(id) ON UPDATE CASCADE,
            CONSTRAINT fk_assessments_registration FOREIGN KEY(activityId, studentId) REFERENCES activity_registrations(activityId, studentId) ON UPDATE CASCADE,
            CONSTRAINT chk_assessments_overall_score CHECK(overallScore IS NULL OR (overallScore >= 0 AND overallScore <= 100)),
            CONSTRAINT chk_assessments_status CHECK(status IN('draft','published')),
            CONSTRAINT chk_assessments_publish_data CHECK(status = 'draft' OR (overallScore IS NOT NULL AND publishedAt IS NOT NULL))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $context->execute("CREATE TABLE assessment_scores (
            id CHAR(36) NOT NULL,
            assessmentId CHAR(36) NOT NULL,
            criteriaId CHAR(36) NOT NULL,
            score DECIMAL(7,2) NOT NULL,
            createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            PRIMARY KEY(id),
            UNIQUE KEY uq_assessment_scores_assessment_criteria(assessmentId, criteriaId),
            KEY idx_assessment_scores_criteria(criteriaId),
            CONSTRAINT fk_assessment_scores_assessment FOREIGN KEY(assessmentId) REFERENCES assessments(id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_assessment_scores_criteria FOREIGN KEY(criteriaId) REFERENCES assessment_criteria(id) ON UPDATE CASCADE,
            CONSTRAINT chk_assessment_scores_non_negative CHECK(score >= 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(MigrationContext $context): void
    {
        $context->execute('DROP TABLE assessment_scores');
        $context->execute('DROP TABLE assessments');
        $context->execute('DROP TABLE assessment_criteria');
        $context->execute('DROP TABLE activity_registrations');
        $context->execute('DROP TABLE activities');
    }
};
