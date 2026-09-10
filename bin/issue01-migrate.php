<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Migration\Issue01Migration;

try {
    $command = $argv[1] ?? 'preflight';
    if (count($argv) > 2 || !in_array($command, ['preflight','apply'], true)) throw new InvalidArgumentException('Usage: php bin/issue01-migrate.php preflight|apply');
    if ($command === 'apply' && Environment::appEnvironment() === 'production' && !Environment::boolean('ALLOW_PRODUCTION_MIGRATIONS')) throw new RuntimeException('Production migrations require ALLOW_PRODUCTION_MIGRATIONS=true.');
    $config = require dirname(__DIR__) . '/config/database.php';
    $pdo = (new Connection($config))->connect();
    echo '[OK] ' . (new Issue01Migration($pdo, dirname(__DIR__)))->run($command === 'apply') . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, '[FAIL] ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
