<?php
declare(strict_types=1);

require dirname(__DIR__).'/bin/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\Admin\Repository\AdminRepository;

$root=dirname(__DIR__);$assert=static function(bool $ok,string $message):void{if(!$ok){throw new RuntimeException($message);}};
$repository=new AdminRepository((new Connection(require $root.'/config/database.php'))->connect());
$dashboard=$repository->dashboard();
$assert(isset($dashboard['users'],$dashboard['auditEvents'],$dashboard['generatedAt'],$dashboard['queue'],$dashboard['usersByRole'],$dashboard['recentOrganizations'],$dashboard['recentAudits']),'Dashboard aggregation is incomplete.');
$assert(is_array($dashboard['queue']),'Operational queue must be derived from database data.');
$assert(array_sum($dashboard['usersByRole'])===$dashboard['users'],'Role distribution must equal the total user count.');
$users=$repository->users();$assert(is_array($users),'Users explorer must return a list.');
$admin=array_values(array_filter($users,static fn(array $row):bool=>($row['email']??null)==='admin@admin.com'));
$assert(count($admin)===1&&($admin[0]['role']??null)==='platform_admin','Seeded platform admin must be visible in explorer.');
$assert(is_array($repository->organizations()),'Organization explorer must return a list.');
$assert(is_array($repository->audits()),'Audit explorer must return a list.');
$rbac=$repository->rbac();$assert(isset($rbac['roles'],$rbac['mappings']),'RBAC explorer is incomplete.');
$system=$repository->system();$assert(isset($system['databaseVersion'],$system['tableCount'],$system['phpVersion']),'System health payload is incomplete.');
foreach(['activities','applications','payments','notifications'] as $resource){$assert(is_array($repository->resource($resource)),"Resource {$resource} must return a list.");}
try{$repository->setUserStatus((string)$admin[0]['id'],(string)$admin[0]['id'],'suspended','valid reason','test-request');throw new RuntimeException('Self-suspension guard did not run.');}catch(RuntimeException $e){$assert(str_contains($e->getMessage(),'không thể tự'),'Unexpected self-suspension error.');}
$source=(string)file_get_contents($root.'/src/Bootstrap/Application.php');
foreach(['/api/v1/admin/dashboard','/api/v1/admin/users','/api/v1/admin/organizations','/api/v1/admin/audit','/api/v1/admin/rbac','/api/v1/admin/system','/api/v1/admin/resources/{resource}'] as $endpoint){$assert(str_contains($source,$endpoint),"Missing Admin endpoint {$endpoint}.");}
foreach(["add('POST','/api/v1/admin/users'","add('PATCH','/api/v1/admin/users/{userId}'","add('DELETE','/api/v1/admin/users/{userId}'"] as $route){$assert(str_contains($source,$route),"Missing Admin account mutation route {$route}.");}
$adminId=(string)$admin[0]['id'];
try{$repository->deleteUser($adminId,$adminId,'Kiểm tra chặn tự xóa','test-request');throw new RuntimeException('Self-delete guard did not run.');}catch(RuntimeException $e){$assert(str_contains($e->getMessage(),'Admin')&&!str_contains($e->getMessage(),'guard did not run'),'Unexpected self-delete error.');}
echo "admin_portal_functional_test: OK\n";
