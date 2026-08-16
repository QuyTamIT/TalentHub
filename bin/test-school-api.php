<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/Database/seeds/System/RolePermissionSeeder.php';
require dirname(__DIR__).'/Database/seeds/Testing/MinimalAuthRbacSeeder.php';
require dirname(__DIR__).'/tests/Support/SchoolApiFixture.php';
require dirname(__DIR__).'/tests/Integration/SchoolDashboardApiTest.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Tests\Integration\SchoolDashboardApiTest;

try {
    $config = require dirname(__DIR__).'/config/database.php';
    $pdo = (new Connection($config))->connect();
    $database = $config['database'];
    if (preg_match('/test/i', $database) !== 1) {
        throw new RuntimeException('DB_DATABASE must contain "test" (got: '.$database.').');
    }
    $runner = new MigrationRunner($pdo, dirname(__DIR__).'/Database/migrations');
    $results = (new SchoolDashboardApiTest())->run($pdo, $database, $runner, $config['test_password'] ?? getenv('TALENTHUB_TEST_PASSWORD'));
    foreach ($results as $line) {
        fwrite(STDOUT, "[OK] {$line}".PHP_EOL);
    }
    fwrite(STDOUT, '[PASS] School dashboard API test completed.'.PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] '.$e->getMessage().PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString().PHP_EOL);
    exit(1);
}
