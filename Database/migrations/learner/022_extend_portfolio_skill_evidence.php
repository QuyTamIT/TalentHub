<?php
declare(strict_types=1);

use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;

return new ForwardMigrationDefinition(
    '022_extend_portfolio_skill_evidence',
    'Store source-aware nullable scores for verified portfolio skills',
    __FILE__,
    hash_file('sha256', __FILE__),
    new class implements LearnerForwardMigration {
        public function version(): string { return '022_extend_portfolio_skill_evidence'; }
        public function description(): string { return 'Store source-aware nullable scores for verified portfolio skills'; }
        public function statements(string $driver): array
        {
            $sqlite = strtolower($driver) === 'sqlite';
            if ($sqlite) {
                return [
                    'ALTER TABLE learner_portfolio_skills ADD COLUMN score REAL NULL CHECK(score IS NULL OR (score >= 0 AND score <= 100))',
                    "ALTER TABLE learner_portfolio_skills ADD COLUMN sourceType VARCHAR(40) NOT NULL DEFAULT 'portfolio'",
                    "ALTER TABLE learner_portfolio_skills ADD COLUMN evidenceStatus VARCHAR(40) NOT NULL DEFAULT 'verified'",
                ];
            }
            return [
                'ALTER TABLE learner_portfolio_skills ADD COLUMN score DECIMAL(5,2) NULL',
                "ALTER TABLE learner_portfolio_skills ADD COLUMN sourceType VARCHAR(40) NOT NULL DEFAULT 'portfolio'",
                "ALTER TABLE learner_portfolio_skills ADD COLUMN evidenceStatus VARCHAR(40) NOT NULL DEFAULT 'verified'",
                'ALTER TABLE learner_portfolio_skills ADD CONSTRAINT chk_learner_portfolio_skills_score CHECK (score IS NULL OR (score >= 0 AND score <= 100))',
            ];
        }
        public function expectedSchema(): array
        {
            return ['learner_portfolio_skills' => [
                'columns' => ['kind','reportId','skillId','score','sourceType','evidenceStatus'],
                'indexes' => ['idx_portfolio_skills_skill'],
            ]];
        }
    }
);
