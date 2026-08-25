<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create one-time school account invitations';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['users', 'schools'] as $table) {
            $context->assertTableExists($table);
        }
        $context->assertTableAbsent('account_invitations');

        if ($context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn() !== '+00:00') {
            throw new RuntimeException('Account invitations migration requires MySQL session time zone +00:00.');
        }
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
            CREATE TABLE account_invitations (
                id CHAR(36) NOT NULL,
                userId CHAR(36) NOT NULL,
                invitedByUserId CHAR(36) NOT NULL,
                schoolId CHAR(36) NOT NULL,
                tokenHash CHAR(64) NOT NULL,
                expiresAt DATETIME(6) NOT NULL,
                acceptedAt DATETIME(6) NULL,
                revokedAt DATETIME(6) NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_account_invitations_token_hash (tokenHash),
                KEY idx_account_invitations_user_pending (userId, acceptedAt, revokedAt, expiresAt),
                KEY idx_account_invitations_school_created (schoolId, createdAt),
                CONSTRAINT fk_account_invitations_user FOREIGN KEY (userId) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_account_invitations_inviter FOREIGN KEY (invitedByUserId) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_account_invitations_school FOREIGN KEY (schoolId) REFERENCES schools(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_account_invitations_lifecycle CHECK (acceptedAt IS NULL OR revokedAt IS NULL),
                CONSTRAINT chk_account_invitations_expiry CHECK (expiresAt > createdAt)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only: invitation audit history must be retained.
    }
};
