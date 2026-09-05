<?php

declare(strict_types=1);

return new class extends \TalentHub\Database\Migration\AbstractMigration {
    private const PERMISSIONS = [
        'partnership.read_own_school',
        'partnership.review_own_school',
    ];

    public function description(): string
    {
        return 'Reconcile canonical school partnership permission mappings';
    }

    public function preflight(\TalentHub\Database\Migration\MigrationContext $context): void
    {
        foreach (['roles', 'permissions', 'role_permissions'] as $table) {
            $context->assertTableExists($table);
        }

        $role = $context->pdo()->query("SELECT id FROM roles WHERE code = 'school' LIMIT 1")->fetchColumn();
        if (!is_string($role) || $role === '') {
            throw new RuntimeException('Canonical school role is required for partnership permission reconciliation.');
        }
    }

    public function up(\TalentHub\Database\Migration\MigrationContext $context): void
    {
        $pdo = $context->pdo();
        $role = $pdo->query("SELECT id FROM roles WHERE code = 'school' LIMIT 1")->fetchColumn();
        if (!is_string($role) || $role === '') {
            throw new RuntimeException('Canonical school role is required for partnership permission reconciliation.');
        }

        $permission = $pdo->prepare(
            'INSERT IGNORE INTO permissions (id, code, description) VALUES (:id, :code, :description)'
        );
        $mapping = $pdo->prepare(
            'INSERT IGNORE INTO role_permissions (roleId, permissionId)
             SELECT :roleId, id FROM permissions WHERE code = :code'
        );

        foreach (self::PERMISSIONS as $code) {
            $permission->execute([
                'id' => \TalentHub\Support\Uuid::v4(),
                'code' => $code,
                'description' => 'School partnership permission: ' . $code,
            ]);
            $mapping->execute(['roleId' => $role, 'code' => $code]);
        }

        $check = $pdo->prepare(
            'SELECT COUNT(*) FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permissionId
             WHERE rp.roleId = :roleId AND p.code IN (:readCode, :reviewCode)'
        );
        $check->execute([
            'roleId' => $role,
            'readCode' => self::PERMISSIONS[0],
            'reviewCode' => self::PERMISSIONS[1],
        ]);
        if ((int) $check->fetchColumn() !== count(self::PERMISSIONS)) {
            throw new RuntimeException('School partnership permission mappings are incomplete after reconciliation.');
        }
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(\TalentHub\Database\Migration\MigrationContext $context): void
    {
        throw new RuntimeException('School partnership permission reconciliation is forward-only.');
    }
};
