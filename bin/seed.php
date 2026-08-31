<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/Database/seeds/System/RolePermissionSeeder.php';
require dirname(__DIR__).'/Database/seeds/System/CareerRoleBenchmarkSeeder.php';
require dirname(__DIR__).'/Database/seeds/Testing/MinimalAuthRbacSeeder.php';
require dirname(__DIR__).'/Database/seeds/Demo/SchoolDemoSeeder.php';
require dirname(__DIR__).'/Database/seeds/Demo/CompleteAiDemoDataset.php';
require dirname(__DIR__).'/Database/seeds/Demo/CompleteAiDemoSeeder.php';
require dirname(__DIR__).'/Database/seeds/Demo/SchoolAiProjectCatalogDataset.php';
require dirname(__DIR__).'/Database/seeds/Demo/SchoolAiProjectCatalogSeeder.php';
require dirname(__DIR__).'/Database/seeds/Demo/SchoolCredentialDemoDataset.php';
require dirname(__DIR__).'/Database/seeds/Demo/SchoolCredentialDemoSeeder.php';
require dirname(__DIR__).'/Database/seeds/Demo/EnterpriseDemoSeeder.php';
use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;
use TalentHub\Database\Seeds\System\CareerRoleBenchmarkSeeder;
use TalentHub\Database\Seeds\Testing\MinimalAuthRbacSeeder;
use TalentHub\Database\Seeds\Demo\SchoolDemoSeeder;
use TalentHub\Database\Seeds\Demo\CompleteAiDemoSeeder;
use TalentHub\Database\Seeds\Demo\SchoolAiProjectCatalogSeeder;
use TalentHub\Database\Seeds\Demo\SchoolCredentialDemoSeeder;
use TalentHub\Database\Seeds\Demo\EnterpriseDemoSeeder;
try {
    $testing=in_array('--testing',$argv,true);$demo=in_array('--demo',$argv,true);$demoAi=in_array('--demo-ai',$argv,true);$schoolAiProjects=in_array('--school-ai-projects',$argv,true);$schoolCredentials=in_array('--school-credentials',$argv,true);$enterprise=in_array('--enterprise',$argv,true);$env=Environment::appEnvironment();
    if($testing&&!in_array($env,['local','test'],true)){throw new RuntimeException('Testing seed is allowed only in local/test.');}
    if($demo&&!in_array($env,['local','test'],true)){throw new RuntimeException('Demo seed is allowed only in local/test.');}
    if($demoAi&&!in_array($env,['local','test'],true)){throw new RuntimeException('Complete AI demo seed is allowed only in local/test.');}
    if($schoolAiProjects&&!in_array($env,['local','test'],true)){throw new RuntimeException('School AI project catalog seed is allowed only in local/test.');}
    if($schoolCredentials&&!in_array($env,['local','test'],true)){throw new RuntimeException('School credential demo seed is allowed only in local/test.');}
    if($enterprise&&!in_array($env,['local','test'],true)){throw new RuntimeException('Enterprise demo seed is allowed only in local/test.');}
    $config=require dirname(__DIR__).'/config/database.php';$connection=new Connection($config);$pdo=$connection->connect();
    $lock=$pdo->prepare('SELECT GET_LOCK(?,30)');$lock->execute(['talenthub:system_seeds']);if((int)$lock->fetchColumn()!==1){throw new RuntimeException('Unable to acquire seed lock.');}
    try{
        (new RolePermissionSeeder())->run($pdo);
        (new CareerRoleBenchmarkSeeder())->run($pdo);
        if($testing){(new MinimalAuthRbacSeeder())->run($pdo,$env,Environment::required(MinimalAuthRbacSeeder::PASSWORD_ENV));}
        if($demo){
            (new SchoolDemoSeeder())->run($pdo,$env,Environment::required(SchoolDemoSeeder::PASSWORD_ENV));
            (new EnterpriseDemoSeeder())->run($pdo,$env,getenv(SchoolDemoSeeder::PASSWORD_ENV)?:'Talenthub@123');
        }
        if($demoAi){
            $password=Environment::required(SchoolDemoSeeder::PASSWORD_ENV);
            (new SchoolDemoSeeder())->run($pdo,$env,$password);
            (new CompleteAiDemoSeeder())->run($pdo,$env,$password,new \DateTimeImmutable('today',new \DateTimeZone('UTC')));
            (new EnterpriseDemoSeeder())->run($pdo,$env,$password);
        }
        if($schoolAiProjects || $demoAi){(new SchoolAiProjectCatalogSeeder())->run($pdo,$env,new \DateTimeImmutable('today',new \DateTimeZone('UTC')));}
        if($schoolCredentials){(new SchoolCredentialDemoSeeder())->run($pdo,$env);}
        if($enterprise){(new EnterpriseDemoSeeder())->run($pdo,$env,getenv(SchoolDemoSeeder::PASSWORD_ENV)?:'Talenthub@123');}
    }
    finally{$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute(['talenthub:system_seeds']);}
    fwrite(STDOUT,'[OK] system seed'.PHP_EOL);
    fwrite(STDOUT,'[OK] career role benchmark seed'.PHP_EOL);
    if($testing){fwrite(STDOUT,'[OK] testing seed'.PHP_EOL);}
    if($demo){fwrite(STDOUT,'[OK] demo seed'.PHP_EOL);}
    if($demoAi){fwrite(STDOUT,'[OK] complete AI demo seed'.PHP_EOL);}
    if($schoolAiProjects){fwrite(STDOUT,'[OK] school AI project catalog seed'.PHP_EOL);}
    if($schoolCredentials){fwrite(STDOUT,'[OK] school credential demo seed'.PHP_EOL);}
    if($enterprise){fwrite(STDOUT,'[OK] enterprise demo seed'.PHP_EOL);}
    exit(0);
}catch(Throwable $e){fwrite(STDERR,'[FAIL] '.$e->getMessage().PHP_EOL);exit(1);}
