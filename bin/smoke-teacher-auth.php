<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/Database/seeds/System/RolePermissionSeeder.php';
require dirname(__DIR__).'/Database/seeds/Testing/MinimalAuthRbacSeeder.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Tests\Integration\TeacherAuthIntegration;

try{
    if(Environment::appEnvironment()!=='test'){throw new RuntimeException('APP_ENV=test is required.');}
    $config=require dirname(__DIR__).'/config/database.php';$pdo=(new Connection($config))->connect();$runner=new MigrationRunner($pdo,dirname(__DIR__).'/Database/migrations');
    $results=(new TeacherAuthIntegration())->run($pdo,$config['database'],$runner,Environment::required('TALENTHUB_TEST_PASSWORD'));
    foreach($results as $line){fwrite(STDOUT,"[OK] {$line}".PHP_EOL);}exit(0);
}catch(Throwable $e){fwrite(STDERR,'[FAIL] '.$e->getMessage().PHP_EOL);exit(1);}
