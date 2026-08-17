<?php
declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Normalize the legacy business role code to enterprise';
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('roles');
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(
            "UPDATE roles SET code='enterprise', name='Enterprise' " .
            "WHERE code='business' AND NOT EXISTS (SELECT 1 FROM (SELECT code FROM roles) r WHERE r.code='enterprise')"
        );
    }

    public function down(MigrationContext $context): void
    {
        $context->execute(
            "UPDATE roles SET code='business', name='Business' " .
            "WHERE code='enterprise' AND NOT EXISTS (SELECT 1 FROM (SELECT code FROM roles) r WHERE r.code='business')"
        );
    }
};
