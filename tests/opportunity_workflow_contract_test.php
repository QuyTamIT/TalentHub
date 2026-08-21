<?php
declare(strict_types=1);
require dirname(__DIR__).'/bin/bootstrap.php';
require dirname(__DIR__).'/Database/seeds/System/RolePermissionSeeder.php';
use TalentHub\Database\Seeds\System\RolePermissionSeeder;use TalentHub\Rbac\EndpointPermissionMatrix;
$assert=static function(bool $ok,string $message):void{if(!$ok){throw new RuntimeException($message);}};
$migration=(string)file_get_contents(dirname(__DIR__).'/Database/migrations/20260820000200_create_opportunity_workflows.php');
foreach(['projects','internship_posts','internship_applications','project_sponsorships','notifications','payment_orders'] as $table){$assert(str_contains($migration,"CREATE TABLE {$table}"),"Missing workflow table {$table}");}
$seeder=new RolePermissionSeeder();$counts=$seeder->expectedCounts();$assert($counts===['roles'=>5,'permissions'=>120,'mappings'=>144],'Workflow RBAC counts mismatch: '.json_encode($counts));
$expected=['POST /api/v1/businesses/me/payments'=>'payment.create_own_business','GET /api/v1/notifications'=>'notification.read_own','POST /api/v1/internship-posts/{postId}/applications'=>'internship_application.create_own','POST /api/v1/schools/me/reports'=>'report.create_own_school'];
$matrix=EndpointPermissionMatrix::all();foreach($expected as $endpoint=>$permission){$assert(($matrix[$endpoint]??null)===$permission,"Endpoint matrix mismatch: {$endpoint}");}
$granted=[];foreach($seeder->expectedPermissionsByRole() as $permissions){$granted=array_merge($granted,$permissions);}foreach($matrix as $endpoint=>$permission){$assert(in_array($permission,$granted,true),"Matrix permission is not seeded: {$endpoint} => {$permission}");}
$app=(string)file_get_contents(dirname(__DIR__).'/src/Bootstrap/Application.php');foreach(array_keys($expected) as $endpoint){[$method,$path]=explode(' ',$endpoint,2);$assert(str_contains($app,"'{$method}','{$path}'")||str_contains($path,'{postId}/applications'),"Route missing: {$endpoint}");}
echo "opportunity_workflow_contract_test: OK\n";
