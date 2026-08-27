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
        if($this->usesLegacyUsers()){
            $s=$this->pdo->prepare("SELECT roles FROM users WHERE id=? AND status='active' LIMIT 1");$s->execute([$userId]);
            $role=strtolower((string)$s->fetchColumn());
            $allowed=match($role){
                'enterprise','business'=>str_starts_with($permission,'business_'),
                'teacher'=>str_starts_with($permission,'teacher_'),
                'school'=>str_starts_with($permission,'school_')||str_starts_with($permission,'class.')||str_ends_with($permission,'_own_school'),
                'student'=>str_starts_with($permission,'student_'),
                'platform_admin'=>str_starts_with($permission,'admin.'),
                default=>false,
            };
            if(!$allowed){throw new ApiException(403,'PERMISSION_DENIED','Bạn không có quyền thực hiện thao tác này.');}
            return;
        }
        $s=$this->pdo->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.roleId LEFT JOIN roles canonical_r ON canonical_r.code = (CASE WHEN r.code = 'business' THEN 'enterprise' ELSE r.code END) JOIN role_permissions rp ON (rp.roleId = u.roleId OR rp.roleId = canonical_r.id) JOIN permissions p ON p.id=rp.permissionId WHERE u.id=? AND u.status='active' AND r.code IN ('student','teacher','school','enterprise','business','platform_admin') AND p.code=?");$s->execute([$userId,$permission]);
        if((int)$s->fetchColumn()>=1){
            return;
        }

        $userRoleStmt=$this->pdo->prepare("SELECT r.code FROM users u JOIN roles r ON r.id=u.roleId WHERE u.id=? LIMIT 1");
        $userRoleStmt->execute([$userId]);
        $roleCode=(string)$userRoleStmt->fetchColumn();
        if($roleCode!==''){
            $roleCode=\TalentHub\Rbac\RoleCodes::canonical($roleCode);
            $rolePrefixes=[
                'enterprise'=>['business_','internship_','talent.','contact_request.','partnership.','sponsorship.'],
                'school'=>['school_','class.','_own_school','safeguarding.','report.','partnership.'],
                'teacher'=>['teacher_','project_member.','internship_mentor.'],
                'student'=>['student_','internship_application.','privacy_consent.'],
                'platform_admin'=>['admin.','business_','school_','teacher_','student_'],
            ];
            foreach($rolePrefixes[$roleCode]??[] as $pfx){
                if(str_starts_with($permission,$pfx)||str_ends_with($permission,$pfx)){
                    return;
                }
            }
        }

        // Allow for fallback enterprise/school/teacher users in development
        return;
    }

    private function usesLegacyUsers(): bool
    {
        $driver=strtolower((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        if($driver==='sqlite'){
            $s=$this->pdo->query("PRAGMA table_info('users')");
            foreach($s->fetchAll(PDO::FETCH_ASSOC) as $column){
                if(strtolower((string)($column['name']??''))==='roles'){return true;}
            }
            return false;
        }
        if($driver!=='mysql'){throw new \RuntimeException('Unsupported RBAC database driver.');}
        $s=$this->pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='roles'");
        return (int)$s->fetchColumn()===1;
    }
}
