<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/Database/seeds/System/RolePermissionSeeder.php';
require dirname(__DIR__) . '/Database/seeds/Local/AdminAccountSeeder.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Seeds\Local\AdminAccountSeeder;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;

try {
    $environment = Environment::appEnvironment();
    $pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
    $legacy = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='roles'")->fetchColumn() === 1;
    if (!$legacy) {
        (new RolePermissionSeeder())->run($pdo);
    }
    (new AdminAccountSeeder())->run($pdo, $environment, Environment::required(AdminAccountSeeder::PASSWORD_ENV));
    fwrite(STDOUT, '[OK] local admin account ready: ' . AdminAccountSeeder::EMAIL . PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
