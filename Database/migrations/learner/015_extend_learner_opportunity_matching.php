<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;

return new ForwardMigrationDefinition(
    '015_extend_learner_opportunity_matching',
    'Extend learner catalog and recommendation rows for opportunity matching',
    __FILE__,
    hash_file('sha256', __FILE__),
    new class implements LearnerForwardMigration {
        public function version(): string { return '015_extend_learner_opportunity_matching'; }
        public function description(): string { return 'Extend learner catalog and recommendation rows for opportunity matching'; }

        /** @return list<string> */
        public function statements(string $driver): array
        {
            $jsonType = strtolower($driver) === 'mysql' ? 'LONGTEXT' : 'TEXT';
            return [
                "ALTER TABLE learner_ai_catalog_items ADD COLUMN provider_name VARCHAR(255) NOT NULL DEFAULT ''",
                "ALTER TABLE learner_ai_catalog_items ADD COLUMN location VARCHAR(255) NOT NULL DEFAULT ''",
                "ALTER TABLE learner_ai_catalog_items ADD COLUMN difficulty VARCHAR(32) NOT NULL DEFAULT 'introductory'",
                "ALTER TABLE learner_ai_catalog_items ADD COLUMN required_skills_json {$jsonType} NULL",
                "ALTER TABLE learner_ai_catalog_items ADD COLUMN learning_outcomes_json {$jsonType} NULL",
                "ALTER TABLE learner_ai_catalog_items ADD COLUMN education_bands_json {$jsonType} NULL",
                "ALTER TABLE learner_recommendation_runs ADD COLUMN capability VARCHAR(50) NOT NULL DEFAULT 'recommendation'",
                'ALTER TABLE learner_recommendation_items ADD COLUMN catalogId VARCHAR(128) NULL',
                'ALTER TABLE learner_recommendation_items ADD COLUMN rankPosition INTEGER NULL',
                'ALTER TABLE learner_recommendation_items ADD COLUMN structuredScore INTEGER NULL',
                'ALTER TABLE learner_recommendation_items ADD COLUMN geminiScore INTEGER NULL',
                'ALTER TABLE learner_recommendation_items ADD COLUMN matchScore INTEGER NULL',
                "ALTER TABLE learner_recommendation_items ADD COLUMN analysisJson {$jsonType} NULL",
                'CREATE INDEX idx_learner_recommendation_runs_student_capability_created ON learner_recommendation_runs (studentId, capability, createdAt)',
            ];
        }

        /** @return array<string,array{columns:list<string>,indexes:list<string>}> */
        public function expectedSchema(): array
        {
            return [
                'learner_ai_catalog_items' => ['columns' => ['provider_name', 'location', 'difficulty', 'required_skills_json', 'learning_outcomes_json', 'education_bands_json'], 'indexes' => []],
                'learner_recommendation_runs' => ['columns' => ['capability'], 'indexes' => ['idx_learner_recommendation_runs_student_capability_created']],
                'learner_recommendation_items' => ['columns' => ['catalogId', 'rankPosition', 'structuredScore', 'geminiScore', 'matchScore', 'analysisJson'], 'indexes' => []],
            ];
        }
    },
);
