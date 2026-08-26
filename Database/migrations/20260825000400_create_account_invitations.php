<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create one-time school account invitations';
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('users');
        $context->assertTableExists('schools');
        $context->assertTableAbsent('account_invitations');
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
CREATE TABLE account_invitations (
  id CHAR(36) NOT NULL,
  userId CHAR(36) NOT NULL,
  invitedByUserId CHAR(36) NOT NULL,
  schoolId CHAR(36) NOT NULL,
  accountRole VARCHAR(20) NOT NULL,
  tokenHash CHAR(64) NOT NULL,
  expiresAt DATETIME(6) NOT NULL,
  acceptedAt DATETIME(6) NULL,
  revokedAt DATETIME(6) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_account_invitations_token_hash (tokenHash),
  KEY idx_account_invitations_user_active (userId, acceptedAt, revokedAt, expiresAt),
  KEY idx_account_invitations_school_created (schoolId, createdAt),
  CONSTRAINT fk_account_invitations_user FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_account_invitations_inviter FOREIGN KEY (invitedByUserId) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_account_invitations_school FOREIGN KEY (schoolId) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT chk_account_invitations_role CHECK (accountRole IN ('teacher', 'student')),
  CONSTRAINT chk_account_invitations_expiry CHECK (expiresAt > createdAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Account invitation audit history must be retained.');
    }
};
