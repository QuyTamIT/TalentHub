<?php
declare(strict_types=1);
namespace TalentHub\Modules\Student\Service;

use PDO;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Http\ApiException;
use TalentHub\Rbac\RoleCodes;

/** Strict portfolio access; never demo-login or trust an identity supplied by the browser. */
final class PortfolioAccess
{
    public static function identity(PDO $pdo, SessionManager $session, string $role): array
    {
        if (!in_array($role,['student','teacher'],true)) throw new \LogicException('Unsupported portfolio role');
        $user=$session->requireUser();
        if (!RoleCodes::matches((string)($user['role']??''),$role)) throw new ApiException(403,'PERMISSION_DENIED','Không có quyền truy cập báo cáo.');
        $query=$pdo->prepare('SELECT u.id,r.code FROM users u JOIN roles r ON r.id=u.roleId WHERE u.id=? AND u.status=\'active\'');
        $query->execute([(string)$user['id']]);
        $owner=$query->fetch(PDO::FETCH_ASSOC);
        if (!$owner || !RoleCodes::matches((string)$owner['code'],$role)) throw new ApiException(403,'PERMISSION_DENIED','Tài khoản không hợp lệ.');
        $table=$role==='student'?'student_profiles':'teacher_profiles';
        $query=$pdo->prepare("SELECT id FROM {$table} WHERE userId=?");
        $query->execute([(string)$owner['id']]);
        $profile=$query->fetchColumn();
        if (!$profile) throw new ApiException(403,'PERMISSION_DENIED','Chưa có hồ sơ vai trò phù hợp.');
        return ['userId'=>(string)$owner['id'],'studentId'=>$role==='student'?(string)$profile:null,'teacherId'=>$role==='teacher'?(string)$profile:null];
    }
}
