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

final class StudentRegistrationIntegration
{
    /** @return list<string> */
    public function run(PDO $pdo,string $database,MigrationRunner $runner,string $password): array
    {
        if(preg_match('/test/i',$database)!==1){throw new RuntimeException('Registration integration requires DB_DATABASE containing test.');}
        if((string)$pdo->query('SELECT DATABASE()')->fetchColumn()!==$database){throw new RuntimeException('Connected database mismatch.');}
        if((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0){throw new RuntimeException('Registration integration requires an empty database.');}
        try{
            $runner->migrate();(new RolePermissionSeeder())->run($pdo);(new MinimalAuthRbacSeeder())->run($pdo,'test',$password);
            $repository=new AuthRepository($pdo);$classes=$repository->registrationClasses();if(count($classes)!==1||$classes[0]['id']!=='10000000-0000-4000-8000-000000000002'||$classes[0]['schoolName']!=='TalentHub Test School'){throw new RuntimeException('Registration class catalog mismatch.');}
            $auth=new AuthService($repository);$input=['email'=>'New.Student@Example.com','password'=>$password,'fullName'=>'New Student','classId'=>'10000000-0000-4000-8000-000000000002','dateOfBirth'=>'2009-06-15','phone'=>'0912345678'];
            $user=$auth->registerStudent($input,'register-test','127.0.0.1');
            if($user['role']!=='student'||$user['email']!=='new.student@example.com'){throw new RuntimeException('Registration returned incorrect public user.');}
            $profileCount=(int)$pdo->query("SELECT COUNT(*) FROM student_profiles sp JOIN users u ON u.id=sp.userId WHERE u.email='new.student@example.com' AND sp.studyStatus='active'")->fetchColumn();
            if($profileCount!==1){throw new RuntimeException('Registration did not atomically create the student profile.');}
            $auth->login(['email'=>'new.student@example.com','password'=>$password]);
            $this->expect($auth,$input,409,'DUPLICATE_RESOURCE');
            $this->expect($auth,[...$input,'email'=>'role-injection@example.com','role'=>'school'],422,'VALIDATION_FAILED');
            $this->expect($auth,[...$input,'email'=>'bad-class@example.com','classId'=>'20000000-0000-4000-8000-000000000099'],422,'VALIDATION_FAILED');
            $this->expect($auth,[...$input,'email'=>'bad-password@example.com','password'=>'short'],422,'VALIDATION_FAILED');
            if((int)$pdo->query("SELECT COUNT(*) FROM users WHERE email IN ('role-injection@example.com','bad-class@example.com','bad-password@example.com')")->fetchColumn()!==0){throw new RuntimeException('Failed registration left partial users.');}
            return ['baseline + fixture: OK','active school/class registration catalog: OK','registration transaction + normalized identity: OK','duplicate email + validation failures: OK','login after registration: OK'];
        }finally{try{$runner->rollback(null,1);}catch(\Throwable){}}
    }

    private function expect(AuthService $auth,array $input,int $status,string $code): void
    {
        try{$auth->registerStudent($input,'register-test','127.0.0.1');}catch(ApiException $exception){if($exception->status===$status&&$exception->errorCode===$code){return;}throw $exception;}throw new RuntimeException("Expected {$status} {$code}.");
    }
}
