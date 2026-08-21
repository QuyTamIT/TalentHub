<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';require dirname(__DIR__).'/tests/Integration/OpportunityWorkflowIntegration.php';
use TalentHub\Config\Environment;use TalentHub\Database\Connection;use TalentHub\Tests\Integration\OpportunityWorkflowIntegration;
try{if(Environment::appEnvironment()!=='test'){throw new RuntimeException('APP_ENV=test is required.');}$config=require dirname(__DIR__).'/config/database.php';$pdo=(new Connection($config))->connect();foreach((new OpportunityWorkflowIntegration())->run($pdo) as $line){fwrite(STDOUT,"[OK] {$line}".PHP_EOL);}fwrite(STDOUT,"[PASS] Opportunity workflows completed.".PHP_EOL);}catch(Throwable $e){fwrite(STDERR,"[FAIL] {$e->getMessage()}".PHP_EOL);exit(1);}
