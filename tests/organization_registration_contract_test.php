<?php
declare(strict_types=1);
$root=dirname(__DIR__);$service=(string)file_get_contents($root.'/src/Auth/Service/OrganizationRegistrationService.php');$page=(string)file_get_contents($root.'/register-organization.php');$admin=(string)file_get_contents($root.'/src/Modules/Admin/Repository/AdminRepository.php');$login=(string)file_get_contents($root.'/login.php');
$assert=static function(bool $ok,string $message):void{if(!$ok){throw new RuntimeException($message);}};
foreach(["['school','enterprise']","status'=>'pending'",'password_hash','beginTransaction','auth.organization_registration_submitted','school_members','enterprise_members'] as $marker){$assert(str_contains($service,$marker),"Registration service missing marker: {$marker}");}
foreach(['?type=enterprise','?type=school','Gửi hồ sơ xác minh','passwordConfirmation','data-auth-form'] as $marker){$assert(str_contains($page,$marker),"Organization registration page missing marker: {$marker}");}
foreach(["'verified'=>'active'","'rejected'=>'disabled'",'admin.organization_verification_changed'] as $marker){$assert(str_contains($admin,$marker),"Organization verification flow missing marker: {$marker}");}
$assert(str_contains($login,'register-organization.php'),'Login page must link to organization registration.');
echo "organization_registration_contract_test: OK\n";
