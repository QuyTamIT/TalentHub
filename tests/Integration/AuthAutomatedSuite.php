<?php
declare(strict_types=1);
namespace TalentHub\Tests\Integration;

use PDO;
use RuntimeException;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Auth\Service\AuthPortalRouter;

final class AuthAutomatedSuite
{
    /** @return list<string> */
    public function run(PDO $pdo,string $database,string $migrationDirectory,string $password): array
    {
        $this->assertSafeTarget($pdo,$database);$this->assertPortalRedirects();$results=['role dashboard redirects: OK'];
        $cases=[
            'student registration'=>(new StudentRegistrationIntegration())->run(...),
            'login audit'=>(new LoginAuditIntegration())->run(...),
            'login rate limit'=>(new LoginRateLimitIntegration())->run(...),
            'teacher auth/profile'=>(new TeacherAuthIntegration())->run(...),
            'school/student/business auth/profile'=>(new RoleProfileIntegration())->run(...),
        ];
        foreach($cases as $name=>$case){
            $runner=new MigrationRunner($pdo,$migrationDirectory);
            try{
                $lines=$name==='login rate limit'?$case($pdo,$database,$runner):$case($pdo,$database,$runner,$password);
                foreach($lines as $line){$results[]="{$name}: {$line}";}
            }finally{$this->removeMigrationMetadata($pdo,$database);}
        }
        return $results;
    }

    private function assertSafeTarget(PDO $pdo,string $database): void
    {
        if(preg_match('/test/i',$database)!==1){throw new RuntimeException('Automated auth suite requires DB_DATABASE containing test.');}
        if((string)$pdo->query('SELECT DATABASE()')->fetchColumn()!==$database){throw new RuntimeException('Connected database mismatch.');}
        $version=(string)$pdo->query('SELECT VERSION()')->fetchColumn();if(stripos($version,'mariadb')!==false||preg_match('/^8\.4\./',$version)!==1){throw new RuntimeException('Automated auth suite requires MySQL 8.4.x.');}
        if((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0){throw new RuntimeException('Automated auth suite requires an empty test database.');}
    }

    private function assertPortalRedirects(): void
    {
        $expected=['student'=>'/app/learner/index.php','teacher'=>'/app/teacher/index.php','school'=>'/app/school/index.php','business'=>'/app/enterprise/index.php'];
        foreach($expected as $role=>$path){if(AuthPortalRouter::destination($role)!==$path){throw new RuntimeException("Dashboard redirect mismatch for {$role}.");}}
        if(AuthPortalRouter::destination('business','/app/school/index.php')!=='/app/enterprise/index.php'||AuthPortalRouter::destination('student','//external.test')!=='/app/learner/index.php'){throw new RuntimeException('Cross-role or external redirect was accepted.');}
    }

    private function removeMigrationMetadata(PDO $pdo,string $database): void
    {
        if((string)$pdo->query('SELECT DATABASE()')->fetchColumn()!==$database||preg_match('/test/i',$database)!==1){throw new RuntimeException('Refusing test metadata cleanup on an unsafe database.');}
        $tables=$pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name')->fetchAll(PDO::FETCH_COLUMN);
        if($tables===[]){return;}
        if($tables!==['schema_migrations']){throw new RuntimeException('Test case cleanup left unexpected tables: '.implode(',',array_map('strval',$tables)));}
        $pdo->exec('DROP TABLE schema_migrations');
    }
}
