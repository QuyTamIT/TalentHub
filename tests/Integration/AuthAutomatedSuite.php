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
            'school dashboard api'=>(new SchoolDashboardApiTest())->run(...),
        ];
        foreach($cases as $name=>$case){
            $runner=new MigrationRunner($pdo,$migrationDirectory);
            try{
                $lines=$name==='login rate limit'?$case($pdo,$database,$runner):$case($pdo,$database,$runner,$password);
                foreach($lines as $line){$results[]="{$name}: {$line}";}
            }finally{$this->resetTestDatabase($pdo,$database);}
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
        $expected=['student'=>'/app/learner/index.php','teacher'=>'/app/teacher/index.php','school'=>'/app/school/index.php','enterprise'=>'/app/enterprise/index.php','platform_admin'=>'/app/admin/index.php'];
        foreach($expected as $role=>$path){if(AuthPortalRouter::destination($role)!==$path){throw new RuntimeException("Dashboard redirect mismatch for {$role}.");}}
        if(AuthPortalRouter::destination('enterprise','/app/school/index.php')!=='/app/enterprise/index.php'||AuthPortalRouter::destination('business')!=='/app/enterprise/index.php'||AuthPortalRouter::destination('student','//external.test')!=='/app/learner/index.php'){throw new RuntimeException('Cross-role, legacy alias, or external redirect handling is invalid.');}
    }

    private function resetTestDatabase(PDO $pdo,string $database): void
    {
        if((string)$pdo->query('SELECT DATABASE()')->fetchColumn()!==$database||preg_match('/test/i',$database)!==1||preg_match('/^[A-Za-z0-9_]+$/',$database)!==1){throw new RuntimeException('Refusing test database reset on an unsafe target.');}
        $version=(string)$pdo->query('SELECT VERSION()')->fetchColumn();if(stripos($version,'mariadb')!==false||preg_match('/^8\.4\./',$version)!==1){throw new RuntimeException('Test reset requires MySQL 8.4.x.');}
        $quoted='`'.str_replace('`','``',$database).'`';
        $pdo->exec("DROP DATABASE {$quoted}");$pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo->exec("USE {$quoted}");
    }
}
