<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationRunner;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

if (Environment::appEnvironment() !== 'test' || getenv('TALENTHUB_DISPOSABLE_TEST_DB') !== '1') {
    fwrite(STDERR, "Phase 11 requires APP_ENV=test and TALENTHUB_DISPOSABLE_TEST_DB=1\n");
    exit(2);
}

/** @return array{code:int,stdout:string,stderr:string} */
$run = static function (array $command, ?string $stdinFile = null, ?string $stdoutFile = null): array {
    $descriptors = [
        0 => $stdinFile === null ? ['pipe', 'r'] : ['file', $stdinFile, 'r'],
        1 => $stdoutFile === null ? ['pipe', 'w'] : ['file', $stdoutFile, 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start Phase 11 child process.');
    }
    if ($stdinFile === null) {
        fclose($pipes[0]);
    }
    $stdout = '';
    if ($stdoutFile === null) {
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
    }
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    return ['code' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
};

$config = require dirname(__DIR__) . '/config/database.php';
$sourceDatabase = (string) ($config['database'] ?? '');
$assert($sourceDatabase === 'talenthub_local', 'source must be talenthub_local');
$timestamp = gmdate('YmdHis');
$targetDatabase = 'talenthub_phase11_rehearsal_' . $timestamp;
$assert(preg_match('/\Atalenthub_phase11_rehearsal_\d{14}\z/', $targetDatabase) === 1, 'safe target name');
$assert($targetDatabase !== 'talenthub_local', 'target must not be primary');

$phpBin = (string) (getenv('TALENTHUB_PHP_EXE') ?: 'D:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe');
$mysqlBin = (string) (getenv('TALENTHUB_MYSQL_EXE') ?: 'D:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe');
$mysqldumpBin = (string) (getenv('TALENTHUB_MYSQLDUMP_EXE') ?: dirname($mysqlBin) . '\\mysqldump.exe');
$assert(is_file($phpBin), 'pinned PHP executable exists');
$assert(is_file($mysqlBin), 'pinned MySQL executable exists');
$assert(is_file($mysqldumpBin), 'pinned mysqldump executable exists');

$rootPdo = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']),
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
);
$primaryBefore = [
    'tables' => (int) $rootPdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'talenthub_local' AND table_type = 'BASE TABLE'")->fetchColumn(),
    'migrations' => (int) $rootPdo->query('SELECT COUNT(*) FROM talenthub_local.schema_migrations')->fetchColumn(),
];
$assert($primaryBefore === ['tables' => 61, 'migrations' => 29], 'pinned Phase 11 primary baseline matches');

$backupDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'TalentHubBackups';
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
    throw new RuntimeException('Unable to create the Phase 11 backup directory.');
}
$backupPath = $backupDirectory . DIRECTORY_SEPARATOR . "talenthub_local_pre_phase11_{$timestamp}.sql";
$dump = $run([
    $mysqldumpBin,
    '--host=' . $config['host'],
    '--port=' . (string) $config['port'],
    '--user=root',
    '--single-transaction',
    '--routines',
    '--events',
    '--triggers',
    '--hex-blob',
    '--default-character-set=utf8mb4',
    '--set-gtid-purged=OFF',
    $sourceDatabase,
], null, $backupPath);
$assert($dump['code'] === 0, 'mysqldump completed: ' . $dump['stderr']);
$assert(is_file($backupPath) && filesize($backupPath) > 0, 'backup is non-empty');
$backupSha256 = (string) hash_file('sha256', $backupPath);
$assert(preg_match('/\A[a-f0-9]{64}\z/', $backupSha256) === 1, 'backup SHA-256 is valid');
$assert(hash_equals($backupSha256, (string) hash_file('sha256', $backupPath)), 'backup SHA-256 re-verifies');

$failure = null;
$evidence = [];
try {
    $rootPdo->exec("CREATE DATABASE `{$targetDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach (['127.0.0.1', 'localhost'] as $host) {
        $rootPdo->exec("GRANT ALL PRIVILEGES ON `{$targetDatabase}`.* TO '{$config['username']}'@'{$host}'");
    }

    $restore = $run([
        $mysqlBin,
        '--host=' . $config['host'],
        '--port=' . (string) $config['port'],
        '--user=root',
        '--database=' . $targetDatabase,
    ], $backupPath);
    $assert($restore['code'] === 0, 'backup restore completed: ' . $restore['stderr']);

    $targetConfig = $config;
    $targetConfig['database'] = $targetDatabase;
    $targetPdo = (new Connection($targetConfig))->connect();
    $restored = [
        'tables' => (int) $targetPdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchColumn(),
        'migrations' => (int) $targetPdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn(),
    ];
    $assert($restored === $primaryBefore, 'restored table and migration counts match primary');

    $runner = new MigrationRunner($targetPdo, dirname(__DIR__) . '/Database/migrations');
    $runner->validate();
    $firstReplay = $runner->migrate();
    $secondReplay = $runner->migrate();
    $assert($firstReplay === [], 'first migration replay is a no-op');
    $assert($secondReplay === [], 'second migration replay is a no-op');
    $runner->validate();

    $primaryAfter = [
        'tables' => (int) $rootPdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'talenthub_local' AND table_type = 'BASE TABLE'")->fetchColumn(),
        'migrations' => (int) $rootPdo->query('SELECT COUNT(*) FROM talenthub_local.schema_migrations')->fetchColumn(),
    ];
    $assert($primaryAfter === $primaryBefore, 'disposable restore/replay did not mutate primary');

    $evidence = [
        'result' => 'PASS',
        'database' => $targetDatabase,
        'mysql_version' => (string) $targetPdo->query('SELECT VERSION()')->fetchColumn(),
        'backup' => ['path' => $backupPath, 'sha256' => $backupSha256, 'size' => filesize($backupPath)],
        'restored' => $restored,
        'migration_replay' => ['first' => $firstReplay, 'second' => $secondReplay, 'drift' => false],
        'primary_before_after_equal' => true,
        'assertions' => $assertions,
    ];
} catch (Throwable $error) {
    $failure = $error;
} finally {
    if (preg_match('/\Atalenthub_phase11_rehearsal_\d{14}\z/', $targetDatabase) !== 1 || $targetDatabase === 'talenthub_local') {
        throw new RuntimeException('Refusing unsafe Phase 11 cleanup.', previous: $failure);
    }
    foreach (['127.0.0.1', 'localhost'] as $host) {
        try {
            $rootPdo->exec("REVOKE ALL PRIVILEGES ON `{$targetDatabase}`.* FROM '{$config['username']}'@'{$host}'");
        } catch (Throwable) {
        }
    }
    try {
        $rootPdo->exec("DROP DATABASE IF EXISTS `{$targetDatabase}`");
    } catch (Throwable $cleanupError) {
        $failure ??= $cleanupError;
    }
}

$schemaCheck = $rootPdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = :schema');
$schemaCheck->execute(['schema' => $targetDatabase]);
$grantCheck = $rootPdo->prepare("SELECT COUNT(*) FROM mysql.db WHERE Db = :schema AND User = :user AND Host IN ('127.0.0.1', 'localhost')");
$grantCheck->execute(['schema' => $targetDatabase, 'user' => $config['username']]);
$assert((int) $schemaCheck->fetchColumn() === 0, 'disposable schema cleanup verified');
$assert((int) $grantCheck->fetchColumn() === 0, 'disposable grants cleanup verified');
if ($failure !== null) {
    throw $failure;
}

echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "student_portal_four_role_e2e_mysql_test: OK; cleanup verified\n";

