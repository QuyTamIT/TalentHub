<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create mandatory learner onboarding state';
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('student_profiles');
        $context->assertTableAbsent('learner_onboarding_states');
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
CREATE TABLE learner_onboarding_states (
  studentId CHAR(36) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  acceptedAt DATETIME(6) NULL,
  completedAt DATETIME(6) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (studentId),
  KEY idx_learner_onboarding_states_status (status, updatedAt),
  CONSTRAINT fk_learner_onboarding_states_student
    FOREIGN KEY (studentId) REFERENCES student_profiles(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_onboarding_states_status
    CHECK (status IN ('pending', 'accepted', 'completed')),
  CONSTRAINT chk_learner_onboarding_states_timestamps CHECK (
    (status = 'pending' AND acceptedAt IS NULL AND completedAt IS NULL) OR
    (status = 'accepted' AND acceptedAt IS NOT NULL AND completedAt IS NULL) OR
    (status = 'completed' AND acceptedAt IS NOT NULL AND completedAt IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        throw new \RuntimeException('Learner onboarding state migration is irreversible.');
    }
};
