<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;

return new ForwardMigrationDefinition(
    '018_decouple_competency_assessments',
    'Allow competency assessments to target a class or project without workshop attendance',
    __FILE__,
    hash_file('sha256', __FILE__),
    new class implements LearnerForwardMigration {
        public function version(): string { return '018_decouple_competency_assessments'; }
        public function description(): string { return 'Allow competency assessments to target a class or project without workshop attendance'; }

        public function statements(string $driver): array
        {
            if (strtolower($driver) === 'mysql') {
                return [
                    'ALTER TABLE assessments DROP FOREIGN KEY fk_assessments_registration',
                    'ALTER TABLE assessments MODIFY activityId CHAR(36) NULL',
                    'ALTER TABLE assessments ADD COLUMN classId CHAR(36) NULL AFTER studentId',
                    'ALTER TABLE assessments ADD COLUMN projectId CHAR(36) NULL AFTER classId',
                    'ALTER TABLE assessments ADD INDEX idx_assessments_student_context (studentId, classId, projectId, status)',
                    'ALTER TABLE assessments ADD CONSTRAINT chk_assessments_context CHECK (activityId IS NOT NULL OR classId IS NOT NULL OR projectId IS NOT NULL)',
                ];
            }

            return [
                'ALTER TABLE assessments ADD COLUMN classId TEXT NULL',
                'ALTER TABLE assessments ADD COLUMN projectId TEXT NULL',
                'CREATE INDEX idx_assessments_student_context ON assessments (studentId, classId, projectId, status)',
            ];
        }

        public function expectedSchema(): array
        {
            return ['assessments' => [
                'columns' => ['classId', 'projectId'],
                'indexes' => ['idx_assessments_student_context'],
            ]];
        }
    },
);
