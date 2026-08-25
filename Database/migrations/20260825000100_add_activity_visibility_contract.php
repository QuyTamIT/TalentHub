<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Add school-owned activity visibility contract';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('activities');
        $columns = $context->pdo()->prepare(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name='activities' AND column_name='visibility'"
        );
        $columns->execute();
        if ((int) $columns->fetchColumn() !== 0) {
            throw new RuntimeException('activities.visibility already exists; partial visibility migration is not supported.');
        }
        if ($context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn() !== '+00:00') {
            throw new RuntimeException('Activity visibility migration requires MySQL session time zone +00:00.');
        }
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
            ALTER TABLE activities
                ADD COLUMN visibility VARCHAR(20) NOT NULL DEFAULT 'school_only' AFTER status,
                ADD CONSTRAINT chk_activities_visibility CHECK (visibility IN ('school_only', 'public')),
                ADD INDEX idx_activities_school_visibility_status (schoolId, visibility, status)
        SQL);
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only: changing visibility semantics can expose cross-school data if rolled back.
    }
};
