<?php
declare(strict_types=1);
namespace TalentHub\Tests\Smoke;
use PDO;
use RuntimeException;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;
final class DatabaseMigrationSmoke
{
    private const TABLES=['roles','permissions','schools','users','role_permissions','enterprises','classes','student_profiles','teacher_profiles','school_members','enterprise_members','audit_logs'];
    /** @return list<string> */
    public function run(PDO $pdo,string $database,MigrationRunner $runner): array
    {
        $this->assertSafeTarget($pdo,$database);$results=['connection: OK'];
        foreach(self::TABLES as $table){if($this->tableExists($pdo,$table)){throw new RuntimeException("Fresh test database required; found {$table}.");}}
        $runner->validate();$results[]='validate: OK';
        if(count($runner->migrate())!==5){throw new RuntimeException('First migrate must apply five migrations.');}$results[]='migrate: OK';
        (new RolePermissionSeeder())->run($pdo);$results[]='system seed: OK';
        if($runner->migrate()!==[]){throw new RuntimeException('Second migrate must be a no-op.');}$results[]='migrate no-op: OK';
        if(count($runner->rollbackLastBatch())!==5){throw new RuntimeException('Rollback must revert five migrations.');}
        foreach(self::TABLES as $table){if($this->tableExists($pdo,$table)){throw new RuntimeException("Rollback left table {$table}.");}}$results[]='rollback: OK';
        if(count($runner->migrate())!==5){throw new RuntimeException('Migrate after rollback must apply five migrations.');}
        (new RolePermissionSeeder())->run($pdo);$this->assertFingerprint($pdo);$results[]='migrate again + fingerprint: OK';return $results;
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
    {foreach(self::TABLES as $table){if(!$this->tableExists($pdo,$table)){throw new RuntimeException("Missing baseline table {$table}.");}}foreach(['roles'=>4,'permissions'=>81,'role_permissions'=>99] as $table=>$expected){$actual=(int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();if($actual!==$expected){throw new RuntimeException("Unexpected {$table} count: {$actual}.");}}}
}
