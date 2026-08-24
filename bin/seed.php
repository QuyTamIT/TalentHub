<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/Database/seeds/System/RolePermissionSeeder.php';
require dirname(__DIR__).'/Database/seeds/Testing/MinimalAuthRbacSeeder.php';
require dirname(__DIR__).'/Database/seeds/Demo/SchoolDemoSeeder.php';
require dirname(__DIR__).'/Database/seeds/Demo/CompleteAiDemoDataset.php';
require dirname(__DIR__).'/Database/seeds/Demo/CompleteAiDemoSeeder.php';
use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;
use TalentHub\Database\Seeds\Testing\MinimalAuthRbacSeeder;
use TalentHub\Database\Seeds\Demo\SchoolDemoSeeder;
use TalentHub\Database\Seeds\Demo\CompleteAiDemoSeeder;
try {
    $testing=in_array('--testing',$argv,true);$demo=in_array('--demo',$argv,true);$demoAi=in_array('--demo-ai',$argv,true);$env=Environment::appEnvironment();
    if($testing&&!in_array($env,['local','test'],true)){throw new RuntimeException('Testing seed is allowed only in local/test.');}
    if($demo&&!in_array($env,['local','test'],true)){throw new RuntimeException('Demo seed is allowed only in local/test.');}
    if($demoAi&&!in_array($env,['local','test'],true)){throw new RuntimeException('Complete AI demo seed is allowed only in local/test.');}
    $config=require dirname(__DIR__).'/config/database.php';$connection=new Connection($config);$pdo=$connection->connect();
    $lock=$pdo->prepare('SELECT GET_LOCK(?,30)');$lock->execute(['talenthub:system_seeds']);if((int)$lock->fetchColumn()!==1){throw new RuntimeException('Unable to acquire seed lock.');}
    try{
        (new RolePermissionSeeder())->run($pdo);
        if($testing){(new MinimalAuthRbacSeeder())->run($pdo,$env,Environment::required(MinimalAuthRbacSeeder::PASSWORD_ENV));}
        if($demo){(new SchoolDemoSeeder())->run($pdo,$env,Environment::required(SchoolDemoSeeder::PASSWORD_ENV));}
        if($demoAi){
            $password=Environment::required(SchoolDemoSeeder::PASSWORD_ENV);
            (new SchoolDemoSeeder())->run($pdo,$env,$password);
            (new CompleteAiDemoSeeder())->run($pdo,$env,$password,new \DateTimeImmutable('today',new \DateTimeZone('UTC')));
        }
    }
    finally{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute(['talenthub:system_seeds']);}
    fwrite(STDOUT,'[OK] system seed'.PHP_EOL);
    if($testing){fwrite(STDOUT,'[OK] testing seed'.PHP_EOL);}
    if($demo){fwrite(STDOUT,'[OK] demo seed'.PHP_EOL);}
    if($demoAi){fwrite(STDOUT,'[OK] complete AI demo seed'.PHP_EOL);}
    exit(0);
}catch(Throwable $e){fwrite(STDERR,'[FAIL] '.$e->getMessage().PHP_EOL);exit(1);}
