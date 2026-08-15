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
            $permission=new PermissionService($pdo);foreach(['teacher_profile.read_own','teacher_profile.update_own','teacher_dashboard.read_own','activity.read_managed','activity_registration.read_managed','assessment.read_managed','assessment.update_managed'] as $code){$permission->require($user['id'],$code);}$results[]='teacher permissions: OK';
            $this->assertAssessmentSchema($pdo);$results[]='teacher activity assessment schema: OK';
            $service=new TeacherProfileService(new TeacherRepository($pdo));$updated=$service->update($user['id'],['fullName'=>'Teacher Integration','phone'=>'0900000099','specialization'=>'STEM','bio'=>'Integration test profile']);
            if($updated['fullName']!=='Teacher Integration'||$updated['school']['name']!=='TalentHub Test School'){throw new RuntimeException('Teacher profile update/read mismatch.');}$results[]='teacher profile ownership + update: OK';
            $auth->changePassword($user['id'],['currentPassword'=>$password,'newPassword'=>$password.'-changed']);$auth->login(['email'=>'teacher@test.talenthub.local','password'=>$password.'-changed']);$results[]='password change + re-login: OK';
            return $results;
        }finally{try{$runner->rollback(null,1);}catch(\Throwable){}}
    }

    private function assertAssessmentSchema(PDO $pdo): void
    {
        $ids=[
            'activity'=>'20000000-0000-4000-8000-000000000001',
            'registration'=>'20000000-0000-4000-8000-000000000002',
            'criteria'=>'20000000-0000-4000-8000-000000000003',
            'assessment'=>'20000000-0000-4000-8000-000000000004',
            'score'=>'20000000-0000-4000-8000-000000000005',
        ];
        $teacherId=(string)$pdo->query("SELECT id FROM teacher_profiles WHERE userId='10000000-0000-4000-8000-000000000012'")->fetchColumn();
        $pdo->prepare("INSERT INTO activities(id,schoolId,createdByTeacherId,title,category,startAt,endAt,capacity,status) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6),DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 2 HOUR),?,'published')")
            ->execute([$ids['activity'],'10000000-0000-4000-8000-000000000001',$teacherId,'Assessment integration activity','academic',30]);
        $pdo->prepare("INSERT INTO activity_registrations(id,activityId,studentId,status) VALUES(?,?,?,'approved')")
            ->execute([$ids['registration'],$ids['activity'],'10000000-0000-4000-8000-000000000021']);
        $pdo->prepare("INSERT INTO assessment_criteria(id,code,name,minScore,maxScore,displayOrder) VALUES(?,?,'Teamwork',0,10,1)")
            ->execute([$ids['criteria'],'teamwork']);
        $pdo->prepare("INSERT INTO assessments(id,teacherId,studentId,activityId,overallScore,comment,status,publishedAt) VALUES(?,?,?,?,?,?, 'published',UTC_TIMESTAMP(6))")
            ->execute([$ids['assessment'],$teacherId,'10000000-0000-4000-8000-000000000021',$ids['activity'],85.5,'Good progress']);
        $pdo->prepare('INSERT INTO assessment_scores(id,assessmentId,criteriaId,score) VALUES(?,?,?,?)')
            ->execute([$ids['score'],$ids['assessment'],$ids['criteria'],8.5]);
        $duplicateRejected=false;
        try{
            $pdo->prepare('INSERT INTO assessment_scores(id,assessmentId,criteriaId,score) VALUES(?,?,?,?)')
                ->execute(['20000000-0000-4000-8000-000000000006',$ids['assessment'],$ids['criteria'],9]);
        }catch(\PDOException){$duplicateRejected=true;}
        if(!$duplicateRejected){throw new RuntimeException('Assessment criteria score uniqueness was not enforced.');}
    }
}
