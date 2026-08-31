<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Cleanup and archive obsolete or untracked activities'; }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('activities');
    }

    public function up(MigrationContext $context): void
    {
        $context->execute("
            UPDATE activities
            SET status = 'archived'
            WHERE title LIKE '%Triển lãm Công nghệ AI & Robotics%'
        ");
    }

    public function isReversible(): bool { return false; }
    public function down(MigrationContext $context): void { throw new RuntimeException('Activity cleanup migration is irreversible.'); }
};
