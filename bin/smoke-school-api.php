<?php
/**
 * Smoke test the JSON API endpoints for the school module.
 *
 * Spins up PHP's built-in dev server bound to api/v1/index.php and
 * exercises the endpoints with curl from the local host.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/bin/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Config\Environment;

function school_smoke_remove_runtime_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $entries = scandir($directory);
    if (!is_array($entries)) {
        throw new RuntimeException('Unable to inspect School smoke runtime directory');
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($path) || !unlink($path)) {
            throw new RuntimeException('Unable to remove School smoke runtime file');
        }
    }
    if (!rmdir($directory)) {
        throw new RuntimeException('Unable to remove School smoke runtime directory');
    }
}

/**
 * @param array{id:string,lastLoginAt:?string,updatedAt:string} $userState
 * @param list<array<string,mixed>> $rateLimitState
 */
function school_smoke_restore_auth_state(
    PDO $pdo,
    array $userState,
    array $rateLimitState,
    ?string $requestId,
): void {
    $pdo->beginTransaction();
    try {
        if ($requestId !== null) {
            $statement = $pdo->prepare('DELETE FROM audit_logs WHERE requestId = ?');
            $statement->execute([$requestId]);
        }
        $statement = $pdo->prepare('UPDATE users SET lastLoginAt = ?, updatedAt = ? WHERE id = ?');
        $statement->execute([$userState['lastLoginAt'], $userState['updatedAt'], $userState['id']]);
        $pdo->exec('DELETE FROM auth_rate_limits');
        $statement = $pdo->prepare(
            'INSERT INTO auth_rate_limits(bucketKey,scope,failureCount,windowStartedAt,blockedUntil,updatedAt) '
            . 'VALUES(?,?,?,?,?,?)',
        );
        foreach ($rateLimitState as $row) {
            $statement->execute([
                $row['bucketKey'],
                $row['scope'],
                $row['failureCount'],
                $row['windowStartedAt'],
                $row['blockedUntil'],
                $row['updatedAt'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

$environment = Environment::appEnvironment();
$config = require $root . '/config/database.php';
$disposableDatabaseProven = $environment === 'test'
    && Environment::boolean('TALENTHUB_DISPOSABLE_TEST_DB', false)
    && strtolower((string) $config['host']) === '127.0.0.1'
    && preg_match('/\Atalenthub_test_[a-z0-9_]+\z/', (string) $config['database']) === 1;
if (!$disposableDatabaseProven) {
    fwrite(STDERR, '[FAIL] School API smoke requires explicit disposable loopback database proof' . PHP_EOL);
    exit(1);
}
$pdo    = (new Connection($config))->connect();
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== (string) $config['database']) {
    fwrite(STDERR, '[FAIL] School API smoke database mismatch' . PHP_EOL);
    exit(1);
}

$schoolEmail = 'school@test.talenthub.local';
$adminStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$adminStmt->execute(['email' => $schoolEmail]);
$adminId = $adminStmt->fetchColumn();

if (!$adminId) {
    fwrite(STDERR, '[FAIL] Testing School admin not found' . PHP_EOL);
    exit(1);
}
$adminStateStatement = $pdo->prepare('SELECT id,lastLoginAt,updatedAt FROM users WHERE id = ?');
$adminStateStatement->execute([(string) $adminId]);
$adminState = $adminStateStatement->fetch();
if (!is_array($adminState)) {
    fwrite(STDERR, '[FAIL] Unable to snapshot testing School admin' . PHP_EOL);
    exit(1);
}
$rateLimitState = $pdo->query('SELECT * FROM auth_rate_limits ORDER BY bucketKey')->fetchAll();
$testPassword = Environment::required('TALENTHUB_TEST_PASSWORD');

$docroot = $root;
$router  = $docroot . '/api/v1/index.php';
$portSocket = @stream_socket_server('tcp://127.0.0.1:0', $socketErrorNumber, $socketErrorMessage);
if (!is_resource($portSocket)) {
    fwrite(STDERR, '[FAIL] Unable to reserve a School smoke port' . PHP_EOL);
    exit(1);
}
$socketAddress = stream_socket_get_name($portSocket, false);
fclose($portSocket);
$portSeparator = is_string($socketAddress) ? strrpos($socketAddress, ':') : false;
$port = is_int($portSeparator) ? (int) substr($socketAddress, $portSeparator + 1) : 0;
if ($port < 1) {
    fwrite(STDERR, '[FAIL] Unable to select a School smoke port' . PHP_EOL);
    exit(1);
}
$base    = "http://127.0.0.1:$port";
$runtimeDir = $root . '/.superpowers/sdd/smoke-school-api-' . bin2hex(random_bytes(8));

$env = getenv();
$env = is_array($env) ? $env : [];
$env = array_replace($env, [
    'APP_ENV'                 => 'test',
    'TALENTHUB_DISPOSABLE_TEST_DB' => 'true',
    'DB_HOST'                 => (string) $config['host'],
    'DB_PORT'                 => (string) $config['port'],
    'DB_DATABASE'             => (string) $config['database'],
    'DB_USERNAME'             => (string) $config['username'],
    'DB_PASSWORD'             => (string) $config['password'],
    'DB_CONNECT_TIMEOUT'      => (string) $config['connectTimeout'],
    'DB_PERSISTENT'           => $config['persistent'] ? 'true' : 'false',
    'TALENTHUB_TEST_PASSWORD' => $testPassword,
]);

$cmd = [
    PHP_BINARY,
    '-d',
    'session.save_path=' . $runtimeDir,
    '-S',
    "127.0.0.1:{$port}",
    '-t',
    $docroot,
    $router,
];
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$endpoints = [
    ['GET', '/api/v1/health',  null],
    ['POST','/api/v1/auth/login', json_encode(['email' => $schoolEmail, 'password' => $testPassword])],
    ['GET', '/api/v1/schools/me', null],
    ['GET', '/api/v1/schools/me/dashboard', null],
    ['GET', '/api/v1/schools/me/classes', null],
    ['GET', '/api/v1/schools/me/teachers', null],
    ['GET', '/api/v1/schools/me/students', null],
];

$failures = 0;
$proc = null;
$pipes = [];
$cookieJar = null;
$loginRequestId = null;
try {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required for School API smoke');
    }
    if (!mkdir($runtimeDir, 0770, true) && !is_dir($runtimeDir)) {
        throw new RuntimeException('Unable to create isolated School smoke runtime directory');
    }
    $proc = proc_open($cmd, $descriptors, $pipes, $root, $env);
    if (!is_resource($proc)) {
        throw new RuntimeException('Unable to start School API test server');
    }
    if (isset($pipes[0]) && is_resource($pipes[0])) {
        fclose($pipes[0]);
        unset($pipes[0]);
    }
    foreach ($pipes as $pipe) {
        stream_set_blocking($pipe, false);
    }

    $ready = false;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $processStatus = proc_get_status($proc);
        if (($processStatus['running'] ?? false) !== true) {
            break;
        }
        $health = curl_init($base . '/api/v1/health');
        curl_setopt_array($health, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 500,
        ]);
        curl_exec($health);
        $healthStatus = (int) curl_getinfo($health, CURLINFO_RESPONSE_CODE);
        curl_close($health);
        if ($healthStatus === 200) {
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) {
        throw new RuntimeException('School API test server did not become ready');
    }

    $cookieJar = tempnam($runtimeDir, 'th_ck_');
    if ($cookieJar === false) {
        throw new RuntimeException('Unable to create School smoke cookie jar');
    }
    foreach ($endpoints as [$method, $path, $body]) {
        $headers = ['Accept: application/json'];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $curl = curl_init($base . $path);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_COOKIEFILE => $cookieJar,
            CURLOPT_COOKIEJAR => $cookieJar,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 5,
        ]);
        $output = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $data = json_decode((string) $output, true);
        if ($path === '/api/v1/auth/login'
            && is_array($data)
            && is_string($data['meta']['requestId'] ?? null)
        ) {
            $loginRequestId = $data['meta']['requestId'];
        }
        if ($status === 200
            && is_array($data)
            && is_array($data['data'] ?? null)
            && is_string($data['meta']['requestId'] ?? null)
        ) {
            $keys = array_keys($data['data']);
            echo '[OK]   ' . sprintf('%-45s', "$method $path") . ' status=200 keys=' . implode(',', array_slice($keys, 0, 6)) . PHP_EOL;
        } else {
            $errorCode = is_array($data) && is_string($data['error']['code'] ?? null)
                && preg_match('/\A[A-Z][A-Z0-9_]{1,63}\z/', $data['error']['code']) === 1
                    ? $data['error']['code']
                    : 'INVALID_RESPONSE';
            echo '[WARN] ' . sprintf('%-45s', "$method $path") . " status={$status} error={$errorCode}" . PHP_EOL;
            $failures++;
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    $failures++;
} finally {
    if (is_resource($proc)) {
        proc_terminate($proc, 9);
    }
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    if (is_resource($proc)) {
        proc_close($proc);
    }
    if (is_string($cookieJar) && is_file($cookieJar) && !unlink($cookieJar)) {
        fwrite(STDERR, '[FAIL] Unable to remove School smoke cookie jar' . PHP_EOL);
        $failures++;
    }
    try {
        school_smoke_restore_auth_state(
            $pdo,
            $adminState,
            $rateLimitState,
            $loginRequestId,
        );
    } catch (Throwable $exception) {
        fwrite(STDERR, '[FAIL] Unable to restore School smoke auth state' . PHP_EOL);
        $failures++;
    }
    try {
        school_smoke_remove_runtime_directory($runtimeDir);
    } catch (Throwable $exception) {
        fwrite(STDERR, '[FAIL] Unable to remove School smoke runtime state' . PHP_EOL);
        $failures++;
    }
}

exit($failures === 0 ? 0 : 1);
