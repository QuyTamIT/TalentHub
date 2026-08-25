<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Seed canonical role and permission mappings required by schema validators';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['roles', 'permissions', 'role_permissions'] as $table) {
            $context->assertTableExists($table);
        }
    }

    public function up(MigrationContext $context): void
    {
        require_once dirname(__DIR__) . '/seeds/System/RolePermissionSeeder.php';
        (new RolePermissionSeeder())->run($context->pdo());
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only system catalog data is required by authorization and later validators.
    }
};
