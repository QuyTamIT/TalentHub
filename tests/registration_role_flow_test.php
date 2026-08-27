<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$home=(string)file_get_contents($root.'/index.php');
$roles=(string)file_get_contents($root.'/role-selection.php');
$login=(string)file_get_contents($root.'/login.php');
$teacher=(string)file_get_contents($root.'/register-teacher.php');
$service=(string)file_get_contents($root.'/src/Auth/Service/TeacherRegistrationService.php');
$assert=static function(bool $condition,string $message):void{if(!$condition){throw new RuntimeException($message);}};

$assert(str_contains($home,'href="./login.php" class="btn btn-primary" data-cta="app"'),'Primary experience CTA must open login.');
$assert(str_contains($home,'href="./role-selection.php" class="btn btn-primary site-header__app-btn"'),'Header registration CTA must open role selection.');
foreach(['register.php','register-teacher.php','register-school.php','register-enterprise.php'] as $route){$assert(str_contains($roles,"'route' => '{$route}'"),"Role selection missing {$route}.");}
$assert(str_contains($teacher,"['type'=>'registered-pending'"),'Teacher registration must set the pending success flash.');
$assert(str_contains($service,"code='teacher'"),'Teacher registration must create the teacher role.');
$assert(str_contains($service,"INSERT INTO teacher_profiles"),'Teacher registration must create a teacher profile.');
$assert(str_contains($login,'<strong>Đăng ký thành công.</strong>'),'Login must show the requested registration success message.');

echo "registration_role_flow_test: OK\n";
