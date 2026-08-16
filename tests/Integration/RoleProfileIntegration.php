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
use TalentHub\Http\ApiException;
use TalentHub\Modules\Business\Repository\BusinessRepository;
use TalentHub\Modules\Business\Service\BusinessProfileService;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolAuthorization;
use TalentHub\Modules\School\Service\SchoolDashboardService;
use TalentHub\Modules\Student\Repository\StudentRepository;
use TalentHub\Modules\Student\Service\StudentProfileService;
use TalentHub\Rbac\Service\PermissionService;

final class RoleProfileIntegration
{
    /** @return list<string> */
    public function run(PDO $pdo,string $database,MigrationRunner $runner,string $password): array
    {
        if(preg_match('/test/i',$database)!==1){throw new RuntimeException('Role integration requires DB_DATABASE containing test.');}
        if((string)$pdo->query('SELECT DATABASE()')->fetchColumn()!==$database){throw new RuntimeException('Connected database mismatch.');}
        if((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0){throw new RuntimeException('Role integration requires an empty database.');}
        $results=[];
        try{
            $runner->migrate();(new RolePermissionSeeder())->run($pdo);(new MinimalAuthRbacSeeder())->run($pdo,'test',$password);$results[]='baseline + fixture: OK';
            $auth=new AuthService(new AuthRepository($pdo));$permission=new PermissionService($pdo);
            $this->school($pdo,$auth,$permission,$password);$results[]='school login + permission + profile + dashboard: OK';
            $this->student($pdo,$auth,$permission,$password);$results[]='student login + permission + profile + dashboard: OK';
            $this->business($pdo,$auth,$permission,$password);$results[]='business login + permission + profile + dashboard: OK';
            return $results;
        }finally{try{$runner->rollback(null,1);}catch(\Throwable){}}
    }

    private function school(PDO $pdo,AuthService $auth,PermissionService $permission,string $password): void
    {
        $user=$this->login($auth,'school@test.talenthub.local',$password,'school');
        foreach(['school_profile.read_own','school_profile.update_own','school_dashboard.read_own'] as $code){$permission->require($user['id'],$code);}
        $service=new SchoolDashboardService(new SchoolRepository($pdo),$pdo,new SchoolAuthorization($pdo));$profile=$service->update($user['id'],['name'=>'Integration School','academicYear'=>'2026-2027']);
        if($profile['name']!=='Integration School'||$service->dashboard($user['id'])['school']['id']!==$profile['id']){throw new RuntimeException('School profile/dashboard mismatch.');}
        $this->assertFieldRejected(fn()=>$service->update($user['id'],['status'=>'suspended']));
        $this->changeAndRelogin($auth,$user['id'],'school@test.talenthub.local',$password);
    }

    private function student(PDO $pdo,AuthService $auth,PermissionService $permission,string $password): void
    {
        $user=$this->login($auth,'student@test.talenthub.local',$password,'student');
        foreach(['student_profile.read_own','student_profile.update_own','student_dashboard.read_own'] as $code){$permission->require($user['id'],$code);}
        $service=new StudentProfileService(new StudentRepository($pdo));$profile=$service->update($user['id'],['fullName'=>'Integration Student','phone'=>'0900000088']);
        if($profile['fullName']!=='Integration Student'||$service->dashboard($user['id'])['student']['id']!==$profile['id']){throw new RuntimeException('Student profile/dashboard mismatch.');}
        $this->assertFieldRejected(fn()=>$service->update($user['id'],['classId'=>'10000000-0000-4000-8000-000000000002']));
        $this->assertPermissionDenied(fn()=>$permission->require($user['id'],'business_profile.read_own'));
        $this->changeAndRelogin($auth,$user['id'],'student@test.talenthub.local',$password);
    }

    private function business(PDO $pdo,AuthService $auth,PermissionService $permission,string $password): void
    {
        $user=$this->login($auth,'business@test.talenthub.local',$password,'business');
        foreach(['business_profile.read_own','business_profile.update_own','business_dashboard.read_own'] as $code){$permission->require($user['id'],$code);}
        $service=new BusinessProfileService(new BusinessRepository($pdo));
        $profile=$service->update($user['id'],[
            'name'=>'Integration Business',
            'industry'=>'Education Technology',
            'companySize'=>'50 - 200 nhân viên',
            'foundedYear'=>2019,
            'taxCode'=>'0109988776',
            'phone'=>'0900000077',
            'website'=>'https://business.integration.local',
            'address'=>'123 Innovation Blvd',
            'description'=>'Enterprise integration testing description.'
        ]);
        if($profile['name']!=='Integration Business'||$profile['companySize']!=='50 - 200 nhân viên'||$profile['foundedYear']!==2019||$profile['taxCode']!=='0109988776'||$service->dashboard($user['id'])['business']['id']!==$profile['id']){
            throw new RuntimeException('Business profile/dashboard mismatch.');
        }
        $this->assertFieldRejected(fn()=>$service->update($user['id'],['verificationStatus'=>'verified']));
        $this->assertFieldRejected(fn()=>$service->update($user['id'],['foundedYear'=>1750]));
        $this->changeAndRelogin($auth,$user['id'],'business@test.talenthub.local',$password);
    }

    private function login(AuthService $auth,string $email,string $password,string $role): array
    {
        $user=$auth->login(['email'=>$email,'password'=>$password]);if($user['role']!==$role){throw new RuntimeException("{$role} login returned wrong role.");}return $user;
    }

    private function changeAndRelogin(AuthService $auth,string $userId,string $email,string $password): void
    {
        $changed=$password.'-changed';$auth->changePassword($userId,['currentPassword'=>$password,'newPassword'=>$changed]);$auth->login(['email'=>$email,'password'=>$changed]);
    }

    private function assertFieldRejected(callable $operation): void
    {
        try{$operation();}catch(ApiException $exception){if($exception->errorCode==='VALIDATION_FAILED'){return;}throw $exception;}throw new RuntimeException('Forbidden profile field was accepted.');
    }

    private function assertPermissionDenied(callable $operation): void
    {
        try{$operation();}catch(ApiException $exception){if($exception->status===403){return;}throw $exception;}throw new RuntimeException('Cross-role permission was accepted.');
    }
}
