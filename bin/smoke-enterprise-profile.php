<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/tests/Integration/EnterpriseProfileTest.php';

use TalentHub\Database\Connection;
use TalentHub\Tests\Integration\EnterpriseProfileTest;

try {
    $config = require dirname(__DIR__).'/config/database.php';
    $pdo = (new Connection($config))->connect();
    $runner = new EnterpriseProfileTest();
    $results = $runner->run($pdo);
    foreach ($results as $line) {
        fwrite(STDOUT, "[OK] {$line}" . PHP_EOL);
    }
    fwrite(STDOUT, "[ALL PASS] Enterprise Profile tests completed successfully." . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] " . $e->getMessage() . PHP_EOL);
    exit(1);
}
