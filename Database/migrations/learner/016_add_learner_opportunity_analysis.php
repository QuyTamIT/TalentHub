<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;

return new ForwardMigrationDefinition(
    '016_add_learner_opportunity_analysis',
    'Persist learner opportunity fit-tier explanations',
    __FILE__,
    hash_file('sha256', __FILE__),
    new class implements LearnerForwardMigration {
        public function version(): string { return '016_add_learner_opportunity_analysis'; }
        public function description(): string { return 'Persist learner opportunity fit-tier explanations'; }

        /** @return list<string> */
        public function statements(string $driver): array
        {
            $jsonType = strtolower($driver) === 'mysql' ? 'LONGTEXT' : 'TEXT';
            return [
                "ALTER TABLE learner_recommendation_runs ADD COLUMN analysisJson {$jsonType} NULL",
            ];
        }

        /** @return array<string,array{columns:list<string>,indexes:list<string>}> */
        public function expectedSchema(): array
        {
            return [
                'learner_recommendation_runs' => ['columns' => ['analysisJson'], 'indexes' => []],
            ];
        }
    },
);
