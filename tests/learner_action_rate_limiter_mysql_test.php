<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/Security/PersistentActionRateLimiter.php';

use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Security\PersistentActionRateLimiter;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};
$config = require dirname(__DIR__) . '/config/database.php';
$root = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']),
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
);
$database = 'talenthub_phase10_rate_limit_' . gmdate('YmdHis');
$assert(preg_match('/\Atalenthub_phase10_rate_limit_[0-9]{14}\z/', $database) === 1, 'Disposable database name is allow-listed.');
$primaryBefore = [
    'tables' => (int) $root->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'talenthub_local' AND table_type = 'BASE TABLE'")->fetchColumn(),
    'migrations' => (int) $root->query('SELECT COUNT(*) FROM talenthub_local.schema_migrations')->fetchColumn(),
];
$failure = null;

try {
    $root->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach (['127.0.0.1', 'localhost'] as $host) {
        $root->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$config['username']}'@'{$host}'");
    }
    $disposableConfig = $config;
    $disposableConfig['database'] = $database;
    $pdo = (new Connection($disposableConfig))->connect();
    $pdo->exec(<<<'SQL'
        CREATE TABLE auth_rate_limits (
            bucketKey CHAR(64) NOT NULL,
            scope VARCHAR(20) NOT NULL,
            failureCount SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            windowStartedAt DATETIME(6) NOT NULL,
            blockedUntil DATETIME(6) NULL,
            updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            PRIMARY KEY(bucketKey),
            CONSTRAINT chk_phase10_rate_scope CHECK(scope IN('identity','ip'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);
    $now = 1_800_000_000;
    $limiter = new PersistentActionRateLimiter($pdo, static fn (): int => $now, [
        'phase10.mysql' => ['identity' => 2, 'ip' => 4, 'window' => 60, 'block' => 60],
    ]);
    $limiter->consume('phase10.mysql', 'student-test', '127.0.0.1');
    $limiter->consume('phase10.mysql', 'student-test', '127.0.0.1');
    try {
        $limiter->consume('phase10.mysql', 'student-test', '127.0.0.1');
        $assert(false, 'MySQL limiter rejects the first request above the identity limit.');
    } catch (ApiException $exception) {
        $assert($exception->status === 429, 'MySQL limiter returns 429.');
        $assert(($exception->headers['Retry-After'] ?? '') === '60', 'MySQL limiter returns Retry-After.');
    }
    $assert((int) $pdo->query('SELECT COUNT(*) FROM auth_rate_limits')->fetchColumn() === 2, 'Only hashed identity and IP buckets are persisted.');
} catch (Throwable $exception) {
    $failure = $exception;
} finally {
    foreach (['127.0.0.1', 'localhost'] as $host) {
        try {
            $root->exec("REVOKE ALL PRIVILEGES ON `{$database}`.* FROM '{$config['username']}'@'{$host}'");
        } catch (Throwable) {
        }
    }
    try {
        $root->exec("DROP DATABASE IF EXISTS `{$database}`");
    } catch (Throwable $cleanupError) {
        $failure ??= $cleanupError;
    }
}

$schemaCheck = $root->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
$schemaCheck->execute([$database]);
$primaryAfter = [
    'tables' => (int) $root->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'talenthub_local' AND table_type = 'BASE TABLE'")->fetchColumn(),
    'migrations' => (int) $root->query('SELECT COUNT(*) FROM talenthub_local.schema_migrations')->fetchColumn(),
];
$assert((int) $schemaCheck->fetchColumn() === 0, 'Disposable database is removed.');
$assert($primaryAfter === $primaryBefore, 'Primary database remains unchanged.');
if ($failure !== null) {
    throw $failure;
}

echo "learner_action_rate_limiter_mysql_test: OK; cleanup verified\n";
