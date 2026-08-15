<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/Database/seeds/System/RolePermissionSeeder.php';
require dirname(__DIR__).'/Database/seeds/Testing/MinimalAuthRbacSeeder.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Tests\Integration\AuthAutomatedSuite;

try{
    if(Environment::appEnvironment()!=='test'){throw new RuntimeException('APP_ENV=test is required.');}
    $config=require dirname(__DIR__).'/config/database.php';$pdo=(new Connection($config))->connect();
    $results=(new AuthAutomatedSuite())->run($pdo,$config['database'],dirname(__DIR__).'/Database/migrations',Environment::required('TALENTHUB_TEST_PASSWORD'));
    foreach($results as $line){fwrite(STDOUT,"[OK] {$line}".PHP_EOL);}fwrite(STDOUT,'[PASS] Automated auth suite completed.'.PHP_EOL);exit(0);
}catch(Throwable $exception){fwrite(STDERR,'[FAIL] '.$exception->getMessage().PHP_EOL);exit(1);}
