<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/Database/seeds/System/RolePermissionSeeder.php';
require dirname(__DIR__).'/Database/seeds/Testing/MinimalAuthRbacSeeder.php';
require dirname(__DIR__).'/tests/Support/SchoolApiFixture.php';
require dirname(__DIR__).'/tests/Integration/SchoolDashboardApiTest.php';
require dirname(__DIR__).'/tests/Integration/RoleProfileIntegration.php';
require dirname(__DIR__).'/tests/Integration/TeacherAuthIntegration.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Tests\Integration\RoleProfileIntegration;
use TalentHub\Tests\Integration\TeacherAuthIntegration;
use TalentHub\Tests\Integration\SchoolDashboardApiTest;

function runCase(callable $case, MigrationRunner $runner, PDO $pdo, string $database, string $password): array {
    // Empty DB so case-level pre-flight (which expects an empty DB) passes.
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
    if ($tables !== []) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    try {
        // Case itself runs $runner->migrate() inside its body.
        $results = $case($pdo, $database, $runner, $password);
        return $results;
    } finally {
        // Drop everything for the next iteration.
        $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
        if ($tables !== []) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}

try {
    $config = require dirname(__DIR__).'/config/database.php';
    $pdo = (new Connection($config))->connect();
    $database = $config['database'];
    if (preg_match('/test/i', $database) !== 1) {
        throw new RuntimeException('DB_DATABASE must contain "test".');
    }
    $runner = new MigrationRunner($pdo, dirname(__DIR__).'/Database/migrations');
    $password = $config['test_password'] ?? getenv('TALENTHUB_TEST_PASSWORD');

    $results = [];
    foreach ([
        'teacher auth/profile' => [TeacherAuthIntegration::class, 'run'],
        'school/student/business auth/profile' => [RoleProfileIntegration::class, 'run'],
        'school dashboard api' => [SchoolDashboardApiTest::class, 'run'],
    ] as $name => [$class, $method]) {
        $instance = new $class();
        $lines = runCase([$instance, $method], $runner, $pdo, $database, $password);
        foreach ($lines as $line) {
            $results[] = "[{$name}] {$line}";
        }
    }
    foreach ($results as $line) {
        fwrite(STDOUT, "[OK] {$line}".PHP_EOL);
    }
    fwrite(STDOUT, '[PASS] school module integration suite completed.'.PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] '.$e->getMessage().PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString().PHP_EOL);
    exit(1);
}