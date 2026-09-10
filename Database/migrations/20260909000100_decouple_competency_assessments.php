<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

/** Handoff 018: use the main DDL runner; do not weaken the learner SQL policy. */
return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Decouple competency assessments and add explicit teacher class assignments';
    }

    public function isReversible(): bool { return false; }

    public function preflight(MigrationContext $context): void
    {
        foreach (['assessments','assessment_scores','assessment_criteria','teacher_profiles','student_profiles','classes','projects','activity_registrations'] as $table) {
            $context->assertTableExists($table);
        }
        $context->assertTableAbsent('teacher_class_assignments');
        $pdo = $context->pdo();
        $columns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assessments'")->fetchAll(PDO::FETCH_COLUMN);
        if (array_intersect(['classId','projectId'], $columns) !== []) {
            throw new RuntimeException('Assessment context columns already exist; inspect schema drift before applying.');
        }
        $checks = [
            'duplicate activity assessments' => 'SELECT COUNT(*) FROM (SELECT teacherId,studentId,activityId FROM assessments GROUP BY teacherId,studentId,activityId HAVING COUNT(*)>1) duplicates',
            'invalid assessment owner or registration' => 'SELECT COUNT(*) FROM assessments a LEFT JOIN teacher_profiles t ON t.id=a.teacherId LEFT JOIN student_profiles s ON s.id=a.studentId LEFT JOIN activity_registrations r ON r.activityId=a.activityId AND r.studentId=a.studentId WHERE t.id IS NULL OR s.id IS NULL OR r.id IS NULL OR a.activityId IS NULL',
            'invalid assessment lifecycle or score' => "SELECT COUNT(*) FROM assessments WHERE status NOT IN ('draft','published') OR version<1 OR overallScore<0 OR overallScore>100 OR (status='published' AND (publishedAt IS NULL OR overallScore IS NULL)) OR (status='draft' AND publishedAt IS NOT NULL)",
            'duplicate rubric scores' => 'SELECT COUNT(*) FROM (SELECT assessmentId,criteriaId FROM assessment_scores GROUP BY assessmentId,criteriaId HAVING COUNT(*)>1) duplicates',
            'orphan rubric scores' => 'SELECT COUNT(*) FROM assessment_scores s LEFT JOIN assessments a ON a.id=s.assessmentId LEFT JOIN assessment_criteria c ON c.id=s.criteriaId WHERE a.id IS NULL OR c.id IS NULL',
        ];
        foreach ($checks as $label => $sql) {
            if ((int) $pdo->query($sql)->fetchColumn() > 0) {
                throw new RuntimeException('Preflight failed: ' . $label . '; no rows were changed.');
            }
        }
        $fk = $pdo->query("SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='assessments' AND CONSTRAINT_NAME='fk_assessments_registration' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
        if (array_column($fk, 'COLUMN_NAME') !== ['activityId','studentId']
            || array_column($fk, 'REFERENCED_TABLE_NAME') !== ['activity_registrations','activity_registrations']
            || array_column($fk, 'REFERENCED_COLUMN_NAME') !== ['activityId','studentId']) {
            throw new RuntimeException('Unexpected registration foreign key; refusing to alter it.');
        }
    }

    public function up(MigrationContext $context): void
    {
        // One ALTER retains the composite registration FK. NULL exempts only non-activity contexts.
        $context->execute("ALTER TABLE assessments
            DROP FOREIGN KEY fk_assessments_registration,
            MODIFY activityId CHAR(36) NULL,
            ADD classId CHAR(36) NULL,
            ADD projectId CHAR(36) NULL,
            ADD CONSTRAINT fk_assessments_activity_registration FOREIGN KEY(activityId,studentId) REFERENCES activity_registrations(activityId,studentId) ON UPDATE CASCADE,
            ADD CONSTRAINT fk_assessments_student FOREIGN KEY(studentId) REFERENCES student_profiles(id) ON UPDATE CASCADE,
            ADD CONSTRAINT fk_assessments_class FOREIGN KEY(classId) REFERENCES classes(id) ON UPDATE CASCADE,
            ADD CONSTRAINT fk_assessments_project FOREIGN KEY(projectId) REFERENCES projects(id) ON UPDATE CASCADE,
            ADD UNIQUE KEY uq_assessments_teacher_student_class(teacherId,studentId,classId),
            ADD UNIQUE KEY uq_assessments_teacher_student_project(teacherId,studentId,projectId),
            ADD CONSTRAINT chk_assessments_draft_timestamp CHECK(status <> 'draft' OR publishedAt IS NULL)");
        // MySQL disallows CHECK on FK columns with cascading referential actions.
        // Retain the existing registration FK semantics and enforce exactly one context with triggers.
        foreach (['INSERT','UPDATE'] as $event) {
            $name = 'trg_assessments_one_context_' . strtolower($event);
            $context->execute("CREATE TRIGGER $name BEFORE $event ON assessments FOR EACH ROW
                BEGIN
                    IF ((NEW.activityId IS NOT NULL)+(NEW.classId IS NOT NULL)+(NEW.projectId IS NOT NULL)) <> 1 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Assessment requires exactly one context';
                    END IF;
                END");
        }
        $context->execute("CREATE TABLE teacher_class_assignments (
            teacherId CHAR(36) NOT NULL,
            classId CHAR(36) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            PRIMARY KEY(teacherId,classId),
            CONSTRAINT fk_teacher_class_teacher FOREIGN KEY(teacherId) REFERENCES teacher_profiles(id),
            CONSTRAINT fk_teacher_class_class FOREIGN KEY(classId) REFERENCES classes(id),
            CONSTRAINT chk_teacher_class_status CHECK(status IN ('active','revoked'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Competency assessment decoupling is forward-only; preserve assessment history.');
    }
};
