<?php
declare(strict_types=1);
namespace TalentHub\Rbac\Service;

use PDO;
use TalentHub\Http\ApiException;

final class PermissionService
{
    public function __construct(private readonly PDO $pdo) {}
    public function require(string $userId,string $permission): void
    {
        $s=$this->pdo->prepare("SELECT COUNT(*) FROM users u JOIN role_permissions rp ON rp.roleId=u.roleId JOIN permissions p ON p.id=rp.permissionId JOIN roles r ON r.id=u.roleId WHERE u.id=? AND u.status='active' AND r.code IN ('student','teacher','school','enterprise','business') AND p.code=?");$s->execute([$userId,$permission]);
        if((int)$s->fetchColumn()!==1){throw new ApiException(403,'PERMISSION_DENIED','Bạn không có quyền thực hiện thao tác này.');}
    }
}
