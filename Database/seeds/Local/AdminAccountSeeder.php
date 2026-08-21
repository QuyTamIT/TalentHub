<?php
declare(strict_types=1);

namespace TalentHub\Database\Seeds\Local;

use PDO;
use RuntimeException;
use TalentHub\Support\Uuid;
use Throwable;

final class AdminAccountSeeder
{
    public const PASSWORD_ENV = 'TALENTHUB_ADMIN_PASSWORD';
    public const EMAIL = 'admin@admin.com';
    private const ROLE = 'platform_admin';
    private const PERMISSIONS = [
        'admin.dashboard.read', 'admin.user.read', 'admin.user.create', 'admin.user.update', 'admin.user.delete', 'admin.user.suspend', 'admin.user.restore',
        'admin.organization.read', 'admin.organization.verify', 'admin.organization.suspend',
        'admin.rbac.read', 'admin.rbac.update', 'admin.audit.read', 'admin.audit.export',
        'admin.incident.manage', 'admin.payment.read', 'admin.payment.reconcile',
        'admin.system.health.read',
    ];

    public function run(PDO $pdo, string $environment, string $password): void
    {
        if (!in_array(strtolower($environment), ['local', 'test'], true)) {
            throw new RuntimeException('Admin account seed is allowed only in local/test.');
        }
        if (strlen($password) < 12) {
            throw new RuntimeException('Admin seed password must contain at least 12 characters.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new RuntimeException('Unable to hash admin password.');
        }

        $pdo->beginTransaction();
        try {
            $legacy = $this->columnExists($pdo, 'users', 'roles');
            $roleId = $legacy ? $this->seedLegacyCatalog($pdo) : $this->normalizedRoleId($pdo);
            $select = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1 FOR UPDATE');
            $select->execute([self::EMAIL]);
            $userId = $select->fetchColumn();

            if (is_string($userId) && $userId !== '') {
                $sql = $legacy
                    ? 'UPDATE users SET passwordHash=?, fullName=?, roles=?, status=? WHERE id=?'
                    : 'UPDATE users SET passwordHash=?, fullName=?, roleId=?, status=?, updatedAt=UTC_TIMESTAMP(6) WHERE id=?';
                $pdo->prepare($sql)->execute([$hash, 'TalentHub Admin', $legacy ? self::ROLE : $roleId, 'active', $userId]);
            } else {
                $userId = Uuid::v4();
                $sql = $legacy
                    ? 'INSERT INTO users(id,email,passwordHash,fullName,roles,status) VALUES(?,?,?,?,?,?)'
                    : 'INSERT INTO users(id,roleId,email,passwordHash,fullName,status) VALUES(?,?,?,?,?,?)';
                $values = $legacy
                    ? [$userId, self::EMAIL, $hash, 'TalentHub Admin', self::ROLE, 'active']
                    : [$userId, $roleId, self::EMAIL, $hash, 'TalentHub Admin', 'active'];
                $pdo->prepare($sql)->execute($values);
            }

            if ($legacy && $this->tableExists($pdo, 'audit_logs')) {
                $pdo->prepare("INSERT INTO audit_logs(id,userId,action,entityType,entityId) VALUES(?,?,'admin.account_seeded','user',?)")
                    ->execute([Uuid::v4(), $userId, $userId]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function seedLegacyCatalog(PDO $pdo): string
    {
        $pdo->prepare('INSERT INTO roles(name,description) VALUES(?,?) ON DUPLICATE KEY UPDATE description=VALUES(description)')
            ->execute([self::ROLE, 'TalentHub platform administrator']);
        $role = $pdo->prepare('SELECT id FROM roles WHERE name=?');
        $role->execute([self::ROLE]);
        $roleId = (string) $role->fetchColumn();
        foreach (self::PERMISSIONS as $permission) {
            $pdo->prepare('INSERT INTO permissions(name,description) VALUES(?,?) ON DUPLICATE KEY UPDATE description=VALUES(description)')
                ->execute([$permission, 'TalentHub permission: ' . $permission]);
            $permissionId = $pdo->prepare('SELECT id FROM permissions WHERE name=?');
            $permissionId->execute([$permission]);
            $pdo->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id) VALUES(?,?)')
                ->execute([$roleId, $permissionId->fetchColumn()]);
        }
        return $roleId;
    }

    private function normalizedRoleId(PDO $pdo): string
    {
        $id = $pdo->query("SELECT id FROM roles WHERE code='platform_admin' LIMIT 1")->fetchColumn();
        if (!is_string($id) || $id === '') {
            throw new RuntimeException('Role platform_admin is missing. Run RolePermissionSeeder first.');
        }
        return $id;
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $statement->execute([$table, $column]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $statement->execute([$table]);
        return (int) $statement->fetchColumn() === 1;
    }
}
