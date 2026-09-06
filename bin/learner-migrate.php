<?php

declare(strict_types=1);

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner;

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

$command = $argv[1] ?? 'status';
if (!in_array($command, ['status', 'validate', 'apply', 'rollback'], true)) {
    fwrite(STDERR, "Usage: php bin/learner-migrate.php status|validate|apply [--versions=001_name,002_name]|rollback\n");
    exit(2);
}
if ($command === 'rollback') {
    fwrite(STDERR, "Learner migrations are forward-only; rollback requires the approved database backup/restore procedure.\n");
    exit(2);
}

try {
    $config = require dirname(__DIR__) . '/config/database.php';
    $pdo = (new Connection($config))->connect();
    $runner = new LearnerForwardMigrationRunner($pdo, dirname(__DIR__) . '/Database/migrations/learner', new SchemaInspector($pdo, (string) $config['database']));
    if ($command === 'status' || $command === 'validate') {
        $status = $runner->status();
        foreach ($status as $version => $entry) fwrite(STDOUT, sprintf("[%s] %s %s\n", $entry['applied'] ? 'APPLIED' : 'PENDING', $version, $entry['description']));
        if ($command === 'validate') fwrite(STDOUT, "[OK] learner migration validation\n");
        exit(0);
    }
    $raw = null;
    foreach (array_slice($argv, 2) as $arg) if (str_starts_with($arg, '--versions=')) $raw = substr($arg, 11);
    if ($raw === null || trim($raw) === '') throw new InvalidArgumentException('apply requires --versions=version[,version].');
    $versions = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn(string $v): bool => preg_match('/\A\d{3}_[a-z][a-z0-9]*(?:_[a-z0-9]+)*\z/', $v) === 1));
    if ($versions === []) throw new InvalidArgumentException('No valid learner migration versions supplied.');
    foreach ($runner->migrateApproved($versions) as $version) fwrite(STDOUT, "[APPLIED] {$version}\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
