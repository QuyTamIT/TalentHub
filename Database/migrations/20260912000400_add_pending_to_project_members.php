<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Add pending and rejected statuses to project_members check constraint';
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('project_members');
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if (!$isSqlite) {
            $pdo->exec(<<<'SQL'
                ALTER TABLE project_members MODIFY COLUMN joinedAt DATETIME(6) NULL DEFAULT NULL;
                ALTER TABLE project_members DROP CONSTRAINT chk_project_members_status;
                ALTER TABLE project_members ADD CONSTRAINT chk_project_members_status 
                    CHECK (status IN ('active', 'left', 'removed', 'pending', 'rejected'));
            SQL);
        }
    }

    public function isReversible(): bool
    {
        return true;
    }

    public function down(MigrationContext $context): void
    {
        $pdo = $context->pdo();
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if (!$isSqlite) {
            $pdo->exec(<<<'SQL'
                ALTER TABLE project_members DROP CONSTRAINT chk_project_members_status;
                ALTER TABLE project_members ADD CONSTRAINT chk_project_members_status 
                    CHECK (status IN ('active', 'left', 'removed'));
                UPDATE project_members SET joinedAt = CURRENT_TIMESTAMP(6) WHERE joinedAt IS NULL;
                ALTER TABLE project_members MODIFY COLUMN joinedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6);
            SQL);
        }
    }
};
