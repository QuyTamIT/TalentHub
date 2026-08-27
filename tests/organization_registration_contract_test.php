<?php
declare(strict_types=1);
$root=dirname(__DIR__);$service=(string)file_get_contents($root.'/src/Auth/Service/OrganizationRegistrationService.php');$page=(string)file_get_contents($root.'/register-organization.php');$admin=(string)file_get_contents($root.'/src/Modules/Admin/Repository/AdminRepository.php');$login=(string)file_get_contents($root.'/login.php');
$assert=static function(bool $ok,string $message):void{if(!$ok){throw new RuntimeException($message);}};
foreach(["['school','enterprise']",'organization_registration_requests','password_hash','EXPIRY_DAYS=3','purgeExpired','expiresAt'] as $marker){$assert(str_contains($service,$marker),"Registration request service missing marker: {$marker}");}
foreach(['?type=enterprise','?type=school','Gửi yêu cầu đăng ký','passwordConfirmation','data-auth-form'] as $marker){$assert(str_contains($page,$marker),"Organization registration page missing marker: {$marker}");}
foreach(['reviewOrganizationRegistration','admin.organization_registration_approved','admin.organization_registration_rejected','accountCreated','INSERT INTO users'] as $marker){$assert(str_contains($admin,$marker),"Organization approval flow missing marker: {$marker}");}
$migration=(string)file_get_contents($root.'/Database/migrations/20260822000100_create_organization_registration_requests.php');
foreach(['organization_registration_requests','status,expiresAt',"status IN('pending','approved','rejected')"] as $marker){$assert(str_contains($migration,$marker),"Organization request migration missing marker: {$marker}");}
$assert(!str_contains($service,'INSERT INTO users'),'Public organization submission must not create a user.');
$assert(!str_contains($service,'INSERT INTO schools'),'Public organization submission must not create a school.');
$assert(!str_contains($service,'INSERT INTO enterprises'),'Public organization submission must not create an enterprise.');
$roleSelection=(string)file_get_contents($root.'/role-selection.php');
$assert(str_contains($login,'role-selection.php'),'Login page must route new users through role selection.');
$assert(str_contains($roleSelection,'register-school.php'),'Role selection must link to school registration.');
$assert(str_contains($roleSelection,'register-enterprise.php'),'Role selection must link to enterprise registration.');
$assert(str_contains((string)file_get_contents($root.'/register-school.php'),'$forcedOrganizationType=\'school\''),'School registration must lock the school role.');
$assert(str_contains((string)file_get_contents($root.'/register-enterprise.php'),'$forcedOrganizationType=\'enterprise\''),'Enterprise registration must lock the enterprise role.');
echo "organization_registration_contract_test: OK\n";
