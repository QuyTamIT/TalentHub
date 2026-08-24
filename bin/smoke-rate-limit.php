<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Tests\Integration\LoginRateLimitIntegration;

try{
    if(Environment::appEnvironment()!=='test'){throw new RuntimeException('APP_ENV=test is required.');}
    $config=require dirname(__DIR__).'/config/database.php';$pdo=(new Connection($config))->connect();$runner=new MigrationRunner($pdo,dirname(__DIR__).'/Database/migrations');
    $results=(new LoginRateLimitIntegration())->run($pdo,$config['database'],$runner);
    foreach($results as $line){fwrite(STDOUT,"[OK] {$line}".PHP_EOL);}exit(0);
}catch(Throwable $exception){fwrite(STDERR,'[FAIL] '.$exception->getMessage().PHP_EOL);exit(1);}
