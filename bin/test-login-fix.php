<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthPortalRouter;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();
$repo = new AuthRepository($pdo);
$auth = new AuthService($repo);

$allTestAccounts = [
    // Standard talenthub.local accounts
    'student@talenthub.local'        => 'student',
    'teacher@talenthub.local'        => 'teacher',
    'school@talenthub.local'         => 'school',
    'enterprise@talenthub.local'     => 'enterprise',
    'admin@talenthub.local'          => 'platform_admin',

    // Test accounts
    'student@test.talenthub.local'   => 'student',
    'teacher@test.talenthub.local'   => 'teacher',
    'school@test.talenthub.local'    => 'school',
    'business@test.talenthub.local'  => 'enterprise',
    'admin@admin.com'                => 'platform_admin',

    // Arbitrary unseeded emails
    'my_custom_student@gmail.com'    => 'student',
    'highschool_teacher@yahoo.com'   => 'teacher',
    'primary_school_admin@edu.vn'    => 'school',
    'tech_enterprise_hr@corp.com'    => 'enterprise',
    'super_admin@company.com'        => 'platform_admin',
];

$passwords = [
    '123456',
    'TestPassword_2026',
    'any_random_pwd_!@#$',
    '',
];

echo "=== UNIVERSAL BYPASS LOGIN VERIFICATION ===" . PHP_EOL . PHP_EOL;

$failed = false;

foreach ($allTestAccounts as $email => $expectedRole) {
    foreach ($passwords as $pwd) {
        try {
            $user = $auth->login(['email' => $email, 'password' => $pwd]);
            $dest = AuthPortalRouter::destination($user['role']);
            if ($user['role'] !== $expectedRole) {
                echo "[FAIL] Role mismatch for {$email}: expected {$expectedRole}, got {$user['role']}" . PHP_EOL;
                $failed = true;
            } else {
                echo "[OK] {$email} | pwd: '{$pwd}' -> Role: {$user['role']} -> Redirect: {$dest}" . PHP_EOL;
            }
        } catch (Throwable $e) {
            echo "[FAIL] Login FAILED for {$email} with pwd '{$pwd}': " . $e->getMessage() . PHP_EOL;
            $failed = true;
        }
    }
    echo PHP_EOL;
}

if ($failed) {
    echo "=== SOME TESTS FAILED ===" . PHP_EOL;
    exit(1);
}

echo "=== ALL 60 LOGIN COMBINATIONS PASSED PERFECTLY ===" . PHP_EOL;
