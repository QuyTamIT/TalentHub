<?php
declare(strict_types=1);

/**
 * bin/connect-check.php
 *
 * Quick DB health probe for TalentHub.
 *
 * Trả lời 4 câu hỏi:
 *   1. Database đã kết nối chưa?
 *   2. Database tên gì?
 *   3. Database ở đâu (host:port)?
 *   4. Có những bảng nào? (để biết đường thêm dữ liệu tay)
 *
 * Cách dùng:
 *   php bin/connect-check.php
 *   php bin/connect-check.php --quick     (chỉ check connection, không list bảng)
 *   php bin/connect-check.php --json      (output JSON để dễ pipe)
 *
 * Sync với:
 *   - src/Database/Connection.php
 *   - src/Config/Environment.php
 *   - config/database.php
 */

require __DIR__ . '/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Exception\DatabaseConnectionException;

$asJson = in_array('--json', $argv, true);
$quick  = in_array('--quick', $argv, true);

function out(string $line, bool $asJson): void
{
    if ($asJson) {
        return; // JSON mode is handled at the end
    }
    fwrite(STDOUT, $line . PHP_EOL);
}

$report = [
    'php'             => PHP_VERSION,
    'pdo_mysql'       => extension_loaded('pdo_mysql'),
    'app_env'         => null,
    'db'              => null,
    'connection'      => null,
    'server_version'  => null,
    'tables'          => [],
    'migrations'      => ['applied' => 0, 'pending' => 0, 'drift' => false],
    'warnings'        => [],
    'errors'          => [],
];

try {
    $report['app_env'] = Environment::appEnvironment();
} catch (Throwable $e) {
    $report['errors'][] = '[FAIL] APP_ENV: ' . $e->getMessage();
    $asJson ? fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL) : out('[FAIL] APP_ENV: ' . $e->getMessage(), $asJson);
    exit(2);
}

try {
    $config = require dirname(__DIR__) . '/config/database.php';
    $report['db'] = [
        'driver'   => $config['driver'],
        'host'     => $config['host'],
        'port'     => $config['port'],
        'database' => $config['database'],
        'username' => $config['username'],
        'password' => $config['password'] === '' ? '(empty)' : '(set)',
    ];

    $connection = new Connection($config);
    $pdo = $connection->connect();
    $report['connection'] = 'OK';
    $report['server_version'] = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

    if (!$quick) {
        $report['tables'] = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        $migrationTable = $pdo->query("SHOW TABLES LIKE 'schema_migrations'")->fetchColumn();
        if ($migrationTable !== false) {
            $applied = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
            $report['migrations']['applied'] = $applied;

            $files = glob(dirname(__DIR__) . '/Database/migrations/*.php') ?: [];
            $report['migrations']['pending'] = max(0, count($files) - $applied);

            try {
                $runner = new TalentHub\Database\Migration\MigrationRunner($pdo, dirname(__DIR__) . '/Database/migrations');
                $runner->validate();
            } catch (Throwable $e) {
                $report['migrations']['drift'] = true;
                $report['warnings'][] = '[DRIFT] ' . $e->getMessage();
            }
        } else {
            $report['warnings'][] = '[WARN] schema_migrations table not found — database chưa được migrate.';
        }
    }
} catch (DatabaseConnectionException $e) {
    $report['connection'] = 'FAIL';
    $report['errors'][] = '[FAIL] Connection: ' . $e->errorCode() . ' SQLSTATE=' . ($e->sqlState() ?? 'unknown');
    if ($asJson) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    } else {
        fwrite(STDOUT, '[FAIL] Connection: ' . $e->errorCode() . ' SQLSTATE=' . ($e->sqlState() ?? 'unknown') . PHP_EOL);
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
    }
    exit(1);
} catch (Throwable $e) {
    $report['connection'] = 'FAIL';
    $report['errors'][] = '[FAIL] ' . $e->getMessage();
    if ($asJson) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    } else {
        fwrite(STDERR, '[FAIL] ' . $e->getMessage() . PHP_EOL);
    }
    exit(1);
}

if ($asJson) {
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(0);
}

out('=== TalentHub DB Connect Check ===', $asJson);
out('PHP version       : ' . $report['php'], $asJson);
out('pdo_mysql ext     : ' . ($report['pdo_mysql'] ? 'OK' : 'MISSING — chạy Laragon → PHP → Extensions → bật pdo_mysql'), $asJson);
out('APP_ENV           : ' . $report['app_env'], $asJson);
out('DB driver         : ' . $report['db']['driver'], $asJson);
out('DB Host:Port      : ' . $report['db']['host'] . ':' . $report['db']['port'], $asJson);
out('DB name           : ' . $report['db']['database'], $asJson);
out('DB user           : ' . $report['db']['username'] . ' (password ' . $report['db']['password'] . ')', $asJson);
out('Server version    : ' . $report['server_version'], $asJson);
out('Connection        : ' . ($report['connection'] === 'OK' ? 'OK (database: available)' : 'FAIL'), $asJson);

if (!$quick) {
    out('', $asJson);
    out('Migrations        : ' . $report['migrations']['applied'] . ' applied, ' . $report['migrations']['pending'] . ' pending' . ($report['migrations']['drift'] ? ' [DRIFT]' : ''), $asJson);

    out('', $asJson);
    out('Tables (' . count($report['tables']) . '):', $asJson);
    foreach ($report['tables'] as $table) {
        out('  - ' . $table, $asJson);
    }

    out('', $asJson);
    out('Cách thêm dữ liệu tay: chọn 1 trong 3 cách', $asJson);
    out('  1. Adminer GUI  : http://localhost/adminer/  (Server 127.0.0.1, user root, password rỗng, DB: ' . $report['db']['database'] . ')', $asJson);
    out('  2. HeidiSQL/DBeaver: connect 127.0.0.1:3306 / root / (rỗng) / ' . $report['db']['database'], $asJson);
    out('  3. CLI mysql    : & "C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe" -u root ' . $report['db']['database'], $asJson);
}

if ($report['warnings'] !== []) {
    out('', $asJson);
    out('Warnings:', $asJson);
    foreach ($report['warnings'] as $w) {
        out('  ' . $w, $asJson);
    }
}

if ($report['errors'] !== []) {
    out('', $asJson);
    out('Errors:', $asJson);
    foreach ($report['errors'] as $e) {
        out('  ' . $e, $asJson);
    }
    exit(1);
}

out('', $asJson);
out('[OK] All checks passed.', $asJson);
exit(0);
