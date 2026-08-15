<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/Database/seeds/System/RolePermissionSeeder.php';
use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Exception\DatabaseConnectionException;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Tests\Smoke\DatabaseMigrationSmoke;
try{
    if(Environment::appEnvironment()!=='test'){throw new RuntimeException('Database smoke test requires APP_ENV=test.');}
    $config=require dirname(__DIR__).'/config/database.php';$connection=new Connection($config);$pdo=$connection->connect();$runner=new MigrationRunner($pdo,dirname(__DIR__).'/Database/migrations');
    foreach((new DatabaseMigrationSmoke())->run($pdo,$config['database'],$runner) as $result){fwrite(STDOUT,"[PASS] {$result}".PHP_EOL);}$connection->disconnect();exit(0);
}catch(DatabaseConnectionException $e){fwrite(STDERR,'[FAIL] '.$e->errorCode().' SQLSTATE='.($e->sqlState()??'unknown').PHP_EOL);exit(1);}catch(Throwable $e){fwrite(STDERR,'[FAIL] '.$e->getMessage().PHP_EOL);exit(1);}
