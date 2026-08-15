<?php
declare(strict_types=1);
namespace TalentHub\Tests\Integration;

use PDO;
use RuntimeException;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;
use TalentHub\Database\Seeds\Testing\MinimalAuthRbacSeeder;
use TalentHub\Modules\Teacher\Repository\TeacherRepository;
use TalentHub\Modules\Teacher\Service\TeacherProfileService;
use TalentHub\Rbac\Service\PermissionService;

final class TeacherAuthIntegration
{
    /** @return list<string> */
    public function run(PDO $pdo,string $database,MigrationRunner $runner,string $password): array
    {
        if(preg_match('/test/i',$database)!==1){throw new RuntimeException('Teacher integration requires DB_DATABASE containing test.');}
        if((string)$pdo->query('SELECT DATABASE()')->fetchColumn()!==$database){throw new RuntimeException('Connected database mismatch.');}
        if((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0){throw new RuntimeException('Teacher integration requires an empty database.');}
        $results=[];
        try{
            $runner->migrate();(new RolePermissionSeeder())->run($pdo);(new MinimalAuthRbacSeeder())->run($pdo,'test',$password);$results[]='baseline + fixture: OK';
            $auth=new AuthService(new AuthRepository($pdo));$user=$auth->login(['email'=>'teacher@test.talenthub.local','password'=>$password]);
            if($user['role']!=='teacher'){throw new RuntimeException('Teacher login returned wrong role.');}$results[]='teacher login: OK';
            $permission=new PermissionService($pdo);foreach(['teacher_profile.read_own','teacher_profile.update_own','teacher_dashboard.read_own'] as $code){$permission->require($user['id'],$code);}$results[]='teacher permissions: OK';
            $service=new TeacherProfileService(new TeacherRepository($pdo));$updated=$service->update($user['id'],['fullName'=>'Teacher Integration','phone'=>'0900000099','specialization'=>'STEM','bio'=>'Integration test profile']);
            if($updated['fullName']!=='Teacher Integration'||$updated['school']['name']!=='TalentHub Test School'){throw new RuntimeException('Teacher profile update/read mismatch.');}$results[]='teacher profile ownership + update: OK';
            $auth->changePassword($user['id'],['currentPassword'=>$password,'newPassword'=>$password.'-changed']);$auth->login(['email'=>'teacher@test.talenthub.local','password'=>$password.'-changed']);$results[]='password change + re-login: OK';
            return $results;
        }finally{try{$runner->rollback(null,1);}catch(\Throwable){}}
    }
}
