<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Exception\DatabaseConnectionException;
use TalentHub\Database\Migration\MigrationRunner;

try{
    $command=$argv[1]??'status'; if(!in_array($command,['status','validate','migrate','rollback'],true)){throw new InvalidArgumentException('Usage: php bin/migrate.php status|validate|migrate [--step=N]|rollback [--steps=N|--batch=N]');}
    $env=Environment::appEnvironment(); if($env==='production'&&!Environment::boolean('ALLOW_PRODUCTION_MIGRATIONS')){throw new RuntimeException('Production migrations require ALLOW_PRODUCTION_MIGRATIONS=true.');}
    if($command==='rollback'&&$env==='production'&&!Environment::boolean('ALLOW_PRODUCTION_ROLLBACK')){throw new RuntimeException('Production rollback requires ALLOW_PRODUCTION_ROLLBACK=true.');}
    $option=$argv[2]??null;$step=$steps=$batch=null;
    if($option!==null){if($command==='migrate'&&preg_match('/\A--step=([1-9]\d*)\z/',$option,$m)===1){$step=(int)$m[1];}elseif($command==='rollback'&&preg_match('/\A--steps=([1-9]\d*)\z/',$option,$m)===1){$steps=(int)$m[1];}elseif($command==='rollback'&&preg_match('/\A--batch=([1-9]\d*)\z/',$option,$m)===1){$batch=(int)$m[1];}else{throw new InvalidArgumentException('Invalid command option.');}}
    $config=require dirname(__DIR__).'/config/database.php';$connection=new Connection($config);$runner=new MigrationRunner($connection->connect(),dirname(__DIR__).'/Database/migrations');
    $results=match($command){'status'=>$runner->status(),'validate'=>($runner->validate()===null?['validation: OK']:[]),'migrate'=>$runner->migrate($step),'rollback'=>$runner->rollback($steps,$batch)};
    if($results===[]){$results=['no changes'];}foreach($results as $line){fwrite(STDOUT,"[OK] {$line}".PHP_EOL);}exit(0);
}catch(DatabaseConnectionException $e){fwrite(STDERR,'[FAIL] '.$e->errorCode().' SQLSTATE='.($e->sqlState()??'unknown').PHP_EOL);exit(1);}catch(Throwable $e){fwrite(STDERR,'[FAIL] '.$e->getMessage().PHP_EOL);exit(2);}
