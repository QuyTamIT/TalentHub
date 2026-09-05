<?php

declare(strict_types=1);

namespace TalentHub\Modules\Contact\Service;

use PDO;
use TalentHub\Http\ApiException;

final class ConsultationAdminAuthorization
{
    private const PERMISSIONS = ['admin.consultation.read', 'admin.consultation.update'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function require(string $userId, string $permission): void
    {
        if (!in_array($permission, self::PERMISSIONS, true)) {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Quyền tư vấn không hợp lệ.');
        }
        if ($this->usesLegacyUsers()) {
            $statement = $this->pdo->prepare("SELECT roles FROM users WHERE id = ? AND status = 'active' LIMIT 1");
            $statement->execute([$userId]);
            if (strtolower((string) $statement->fetchColumn()) === 'platform_admin') {
                return;
            }
        } else {
            $statement = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM users
                 INNER JOIN roles ON roles.id = users.roleId
                 INNER JOIN role_permissions ON role_permissions.roleId = roles.id
                 INNER JOIN permissions ON permissions.id = role_permissions.permissionId
                 WHERE users.id = ? AND users.status = 'active'
                   AND roles.code = 'platform_admin' AND permissions.code = ?"
            );
            $statement->execute([$userId, $permission]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }
        }
        throw new ApiException(403, 'PERMISSION_DENIED', 'Bạn không có quyền truy cập yêu cầu tư vấn.');
    }

    private function usesLegacyUsers(): bool
    {
        $driver = strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'sqlite') {
            $columns = $this->pdo->query("PRAGMA table_info('users')")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $column) {
                if (strtolower((string) ($column['name'] ?? '')) === 'roles') {
                    return true;
                }
            }
            return false;
        }
        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'roles'"
        );
        return (int) $statement->fetchColumn() === 1;
    }
}
