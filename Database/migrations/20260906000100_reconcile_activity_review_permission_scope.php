<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;
use TalentHub\Support\Uuid;

return new class extends AbstractMigration {
    private const PERMISSION = 'activity.review_school';

    public function description(): string
    {
        return 'Restrict school activity review permission to the school role';
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['roles', 'permissions', 'role_permissions'] as $table) {
            $context->assertTableExists($table);
        }

        foreach (['school', 'platform_admin'] as $roleCode) {
            $statement = $context->pdo()->prepare('SELECT COUNT(*) FROM roles WHERE code = :code');
            $statement->execute(['code' => $roleCode]);
            if ((int) $statement->fetchColumn() !== 1) {
                throw new RuntimeException("Canonical {$roleCode} role is required for activity review permission reconciliation.");
            }
        }
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();
        $permission = $pdo->prepare(
            'INSERT IGNORE INTO permissions (id, code, description) VALUES (:id, :code, :description)'
        );
        $permission->execute([
            'id' => Uuid::v4(),
            'code' => self::PERMISSION,
            'description' => 'Review activities owned by the authenticated school',
        ]);

        $grant = $pdo->prepare(
            'INSERT IGNORE INTO role_permissions (roleId, permissionId)
             SELECT r.id, p.id
             FROM roles r
             INNER JOIN permissions p ON p.code = :permission
             WHERE r.code = :role'
        );
        $grant->execute(['permission' => self::PERMISSION, 'role' => 'school']);

        $revoke = $pdo->prepare(
            'DELETE rp
             FROM role_permissions rp
             INNER JOIN roles r ON r.id = rp.roleId
             INNER JOIN permissions p ON p.id = rp.permissionId
             WHERE r.code = :role AND p.code = :permission'
        );
        $revoke->execute(['role' => 'platform_admin', 'permission' => self::PERMISSION]);

        $check = $pdo->prepare(
            'SELECT r.code, COUNT(*) AS mappingCount
             FROM role_permissions rp
             INNER JOIN roles r ON r.id = rp.roleId
             INNER JOIN permissions p ON p.id = rp.permissionId
             WHERE r.code IN (:school, :admin) AND p.code = :permission
             GROUP BY r.code'
        );
        $check->execute([
            'school' => 'school',
            'admin' => 'platform_admin',
            'permission' => self::PERMISSION,
        ]);
        $mappings = $check->fetchAll(PDO::FETCH_KEY_PAIR);
        if ((int) ($mappings['school'] ?? 0) !== 1 || (int) ($mappings['platform_admin'] ?? 0) !== 0) {
            throw new RuntimeException('Activity review permission scope is inconsistent after reconciliation.');
        }
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Activity review permission reconciliation is forward-only.');
    }
};
