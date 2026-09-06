<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Grant school partnership review permissions'; }
    public function preflight(MigrationContext $context): void
    {
        foreach (['roles', 'permissions', 'role_permissions'] as $table) { $context->assertTableExists($table); }
    }
    public function up(MigrationContext $context): void
    {
        foreach (['partnership.read_own_school', 'partnership.review_own_school'] as $code) {
            $permission = $context->pdo()->prepare('INSERT IGNORE INTO permissions (id, code, description) VALUES (:id, :code, :description)');
            $permission->execute(['id' => \TalentHub\Support\Uuid::v4(), 'code' => $code, 'description' => 'School partnership permission: ' . $code]);
            $grant = $context->pdo()->prepare("INSERT IGNORE INTO role_permissions (roleId, permissionId) SELECT r.id, p.id FROM roles r INNER JOIN permissions p ON p.code = :code WHERE r.code = 'school'");
            $grant->execute(['code' => $code]);
        }
    }
    public function isReversible(): bool { return false; }
    public function down(MigrationContext $context): void { throw new RuntimeException('Permission grants are forward-only.'); }
};
