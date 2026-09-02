<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;

return new ForwardMigrationDefinition(
    '017_create_learner_roadmap_refinements',
    'Create expiring learner roadmap refinement previews',
    __FILE__,
    hash_file('sha256', __FILE__),
    new class implements LearnerForwardMigration {
        public function version(): string { return '017_create_learner_roadmap_refinements'; }
        public function description(): string { return 'Create expiring learner roadmap refinement previews'; }

        /** @return list<string> */
        public function statements(string $driver): array
        {
            return match (strtolower($driver)) {
                'mysql' => [<<<'SQL'
CREATE TABLE learner_ai_roadmap_refinements (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  roadmapId CHAR(36) NOT NULL,
  baseVersion INT UNSIGNED NOT NULL,
  learnerDraftHash CHAR(64) NOT NULL,
  learnerDraftJson LONGTEXT NOT NULL,
  aiDraftHash CHAR(64) NOT NULL,
  aiDraftJson LONGTEXT NOT NULL,
  provider VARCHAR(80) NOT NULL,
  modelVersion VARCHAR(128) NOT NULL,
  promptVersion VARCHAR(128) NOT NULL,
  providerRequestId VARCHAR(128) NOT NULL,
  responseHash CHAR(64) NOT NULL,
  expiresAt DATETIME(6) NOT NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_learner_ai_roadmap_refinements_owner_expiry (studentId, expiresAt),
  KEY idx_learner_ai_roadmap_refinements_base (roadmapId, studentId, baseVersion),
  CONSTRAINT fk_learner_ai_roadmap_refinements_roadmap_owner FOREIGN KEY (roadmapId, studentId) REFERENCES learner_ai_roadmaps(id, studentId) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_ai_roadmap_refinements_base_version CHECK (baseVersion >= 1),
  CONSTRAINT chk_learner_ai_roadmap_refinements_learner_json CHECK (JSON_VALID(learnerDraftJson)),
  CONSTRAINT chk_learner_ai_roadmap_refinements_ai_json CHECK (JSON_VALID(aiDraftJson)),
  CONSTRAINT chk_learner_ai_roadmap_refinements_learner_hash CHECK (learnerDraftHash REGEXP '^[a-f0-9]{64}$'),
  CONSTRAINT chk_learner_ai_roadmap_refinements_ai_hash CHECK (aiDraftHash REGEXP '^[a-f0-9]{64}$'),
  CONSTRAINT chk_learner_ai_roadmap_refinements_response_hash CHECK (responseHash REGEXP '^[a-f0-9]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL],
                'sqlite' => [
                    "CREATE TABLE learner_ai_roadmap_refinements (id TEXT NOT NULL PRIMARY KEY, studentId TEXT NOT NULL, roadmapId TEXT NOT NULL, baseVersion INTEGER NOT NULL, learnerDraftHash TEXT NOT NULL, learnerDraftJson TEXT NOT NULL, aiDraftHash TEXT NOT NULL, aiDraftJson TEXT NOT NULL, provider TEXT NOT NULL, modelVersion TEXT NOT NULL, promptVersion TEXT NOT NULL, providerRequestId TEXT NOT NULL, responseHash TEXT NOT NULL, expiresAt TEXT NOT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (roadmapId, studentId) REFERENCES learner_ai_roadmaps(id, studentId) ON DELETE RESTRICT ON UPDATE CASCADE, CHECK (baseVersion >= 1), CHECK (json_valid(learnerDraftJson)), CHECK (json_valid(aiDraftJson)), CHECK (length(learnerDraftHash) = 64 AND learnerDraftHash NOT GLOB '*[^a-f0-9]*'), CHECK (length(aiDraftHash) = 64 AND aiDraftHash NOT GLOB '*[^a-f0-9]*'), CHECK (length(responseHash) = 64 AND responseHash NOT GLOB '*[^a-f0-9]*'))",
                    'CREATE INDEX idx_learner_ai_roadmap_refinements_owner_expiry ON learner_ai_roadmap_refinements (studentId, expiresAt)',
                    'CREATE INDEX idx_learner_ai_roadmap_refinements_base ON learner_ai_roadmap_refinements (roadmapId, studentId, baseVersion)',
                ],
                default => throw new RuntimeException('Unsupported learner roadmap refinement migration driver: ' . $driver),
            };
        }

        /** @return array<string,array{columns:list<string>,indexes:list<string>}> */
        public function expectedSchema(): array
        {
            return ['learner_ai_roadmap_refinements' => [
                'columns' => ['id','studentId','roadmapId','baseVersion','learnerDraftHash','learnerDraftJson','aiDraftHash','aiDraftJson','provider','modelVersion','promptVersion','providerRequestId','responseHash','expiresAt','createdAt'],
                'indexes' => ['idx_learner_ai_roadmap_refinements_owner_expiry','idx_learner_ai_roadmap_refinements_base'],
            ]];
        }
    },
);
