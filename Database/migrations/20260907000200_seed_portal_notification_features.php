<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    private const UUID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
    public function description(): string { return 'Grant shared notification permissions to all canonical portal roles'; }
    public function preflight(MigrationContext $context): void {
        foreach (['users', 'roles', 'permissions', 'role_permissions', 'notifications'] as $table) {
            $context->assertTableExists($table);
        }
    }
    public function up(MigrationContext $context): void {
        $pdo = $context->pdo();

        // IDs are always resolved from canonical codes so the migration works
        // in databases whose catalog rows were generated with different UUIDs.
        $roleIds = [];
        $findRole = $pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
        foreach (['student', 'teacher', 'school', 'enterprise', 'platform_admin'] as $roleCode) {
            $findRole->execute(['code' => $roleCode]);
            $roleId = $findRole->fetchColumn();
            if ($roleId !== false) {
                $roleIds[$roleCode] = (string) $roleId;
            }
        }

        $permCodes = ['notification.read_own', 'notification.mark_read_own'];
        foreach ($permCodes as $code) {
            $select = $pdo->prepare('SELECT id FROM permissions WHERE code=:code LIMIT 1');
            $select->execute(['code' => $code]);
            $permId = $select->fetchColumn();
            if ($permId === false) {
                $permId = self::stableId('permission:' . $code);
                $pdo->prepare('INSERT INTO permissions (id, code, description) VALUES (:id, :code, :description)')->execute(['id' => $permId, 'code' => $code, 'description' => 'TalentHub permission: ' . $code]);
            }
            $grant = $pdo->prepare('INSERT IGNORE INTO role_permissions (roleId, permissionId) VALUES (:roleId, :permissionId)');
            foreach ($roleIds as $roleId) {
                $grant->execute(['roleId' => $roleId, 'permissionId' => $permId]);
            }
        }

    }
    public function isReversible(): bool { return false; }
    public function down(MigrationContext $context): void { throw new RuntimeException('Irreversible migration.'); }
    private static function stableId(string $name): string {
        $namespace = hex2bin(str_replace('-', '', self::UUID_NAMESPACE));
        if ($namespace === false) { throw new RuntimeException('Invalid UUID namespace.'); }
        $hash = sha1($namespace . $name);
        return sprintf('%s-%s-5%s-%s%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 13, 3), dechex((hexdec($hash[16]) & 0x3) | 0x8), substr($hash, 17, 3), substr($hash, 20, 12));
    }
};