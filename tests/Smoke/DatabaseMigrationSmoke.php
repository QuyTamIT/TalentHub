<?php
declare(strict_types=1);
namespace TalentHub\Tests\Smoke;
use PDO;
use RuntimeException;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;
final class DatabaseMigrationSmoke
{
    private const TABLES=['roles','permissions','schools','users','role_permissions','enterprises','classes','student_profiles','teacher_profiles','school_members','enterprise_members','audit_logs','reports','auth_rate_limits','activities','activity_registrations','assessment_criteria','assessments','assessment_scores','activity_qr_sessions','checkins'];
    /** @return list<string> */
    public function run(PDO $pdo,string $database,MigrationRunner $runner): array
    {
        $this->assertSafeTarget($pdo,$database);$results=['connection: OK'];
        foreach(self::TABLES as $table){if($this->tableExists($pdo,$table)){throw new RuntimeException("Fresh test database required; found {$table}.");}}
        $runner->validate();$results[]='validate: OK';
        if(count($runner->migrate())!==15){throw new RuntimeException('First migrate must apply fifteen migrations.');}$results[]='migrate: OK';
        $seeder=new RolePermissionSeeder();$seeder->run($pdo);$seeder->run($pdo);$this->assertRolePermissionMatrix($pdo,$seeder);$results[]='system seed idempotency + exact role matrix: OK';
        if($runner->migrate()!==[]){throw new RuntimeException('Second migrate must be a no-op.');}$results[]='migrate no-op: OK';
        try{$runner->rollbackLastBatch();throw new RuntimeException('QR session migration rollback must be rejected.');}
        catch(RuntimeException $exception){if(!str_contains($exception->getMessage(),'Migration is irreversible: 20260820000100')){throw $exception;}}$results[]='irreversible check-in migration guard: OK';
        $this->assertFingerprint($pdo);$this->assertRolePermissionMatrix($pdo,$seeder);$results[]='fingerprint + exact role matrix: OK';return $results;
    }
    private function assertSafeTarget(PDO $pdo,string $database): void
    {
        if(preg_match('/test/i',$database)!==1){throw new RuntimeException('Smoke migrations require DB_DATABASE containing "test".');}
        $selected=$pdo->query('SELECT DATABASE()')->fetchColumn();if(!is_string($selected)||!hash_equals($database,$selected)){throw new RuntimeException('Connected database does not match DB_DATABASE.');}
        $version=(string)$pdo->query('SELECT VERSION()')->fetchColumn();if(stripos($version,'mariadb')!==false||preg_match('/\A8\.4\./',$version)!==1){throw new RuntimeException('Smoke migrations require MySQL 8.4.x.');}
        if((string)$pdo->query('SELECT @@SESSION.time_zone')->fetchColumn()!=='+00:00'){throw new RuntimeException('Database session timezone must be +00:00.');}
    }
    private function tableExists(PDO $pdo,string $table): bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');$s->execute(['table'=>$table]);return(int)$s->fetchColumn()===1;}
    private function assertFingerprint(PDO $pdo): void
    {foreach(self::TABLES as $table){if(!$this->tableExists($pdo,$table)){throw new RuntimeException("Missing baseline table {$table}.");}}foreach(['roles'=>4,'permissions'=>100,'role_permissions'=>118] as $table=>$expected){$actual=(int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();if($actual!==$expected){throw new RuntimeException("Unexpected {$table} count: {$actual}.");}}}

    private function assertRolePermissionMatrix(PDO $pdo,RolePermissionSeeder $seeder): void
    {
        $statement=$pdo->query('SELECT r.code AS roleCode,p.code AS permissionCode FROM role_permissions rp JOIN roles r ON r.id=rp.roleId JOIN permissions p ON p.id=rp.permissionId ORDER BY r.code,p.code');
        $actual=[];foreach($statement->fetchAll() as $row){$actual[(string)$row['roleCode']][]=(string)$row['permissionCode'];}
        ksort($actual);
        foreach($actual as &$permissions){sort($permissions);}
        unset($permissions);
        $expected=$seeder->expectedPermissionsByRole();
        if(array_keys($actual)!==array_keys($expected)){throw new RuntimeException('Role codes in permission mappings do not match the canonical matrix.');}
        foreach($expected as $role=>$permissions){if(($actual[$role]??[])!==$permissions){$missing=array_values(array_diff($permissions,$actual[$role]??[]));$extra=array_values(array_diff($actual[$role]??[],$permissions));throw new RuntimeException("Permission mapping mismatch for {$role}; missing=".implode(',',$missing).'; extra='.implode(',',$extra));}}
    }
}
