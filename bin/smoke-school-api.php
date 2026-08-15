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
require $root . '/Database/seeds/Demo/SchoolDemoSeeder.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Seeds\Demo\SchoolDemoSeeder;

$config = require $root . '/config/database.php';
$pdo    = (new Connection($config))->connect();

$adminStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$adminStmt->execute(['email' => (new SchoolDemoSeeder())->demoAdminEmail()]);
$adminId = $adminStmt->fetchColumn();

if (!$adminId) {
    fwrite(STDERR, '[FAIL] Demo admin not found' . PHP_EOL);
    exit(1);
}

$docroot = $root;
$router  = $docroot . '/api/v1/index.php';
$port    = 8765;
$base    = "http://127.0.0.1:$port";

$env = [
    'PATH'                   => getenv('PATH'),
    'TALENTHUB_TEST_PASSWORD'=> getenv('TALENTHUB_TEST_PASSWORD') ?: 'TestPassword_2026',
    'APP_ENV'                => 'local',
];

$envString = '';
foreach ($env as $k => $v) {
    if ($v === '') continue;
    $envString .= escapeshellarg("$k=$v") . ' ';
}

$cmd = $envString . 'php -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($docroot) . ' ' . escapeshellarg($router);
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proc = proc_open($cmd, $descriptors, $pipes);
if (!is_resource($proc)) {
    fwrite(STDERR, '[FAIL] Unable to start dev server' . PHP_EOL);
    exit(1);
}

stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

usleep(700000);

$cookieJar = tempnam(sys_get_temp_dir(), 'th_ck_');
$endpoints = [
    ['GET', '/api/v1/health',  null],
    ['POST','/api/v1/auth/login', json_encode(['email' => (new SchoolDemoSeeder())->demoAdminEmail(), 'password' => 'TestPassword_2026'])],
    ['GET', '/api/v1/schools/me', null],
    ['GET', '/api/v1/schools/me/dashboard', null],
    ['GET', '/api/v1/schools/me/classes', null],
    ['GET', '/api/v1/schools/me/teachers', null],
    ['GET', '/api/v1/schools/me/students', null],
];

$failures = 0;
foreach ($endpoints as [$method, $path, $body]) {
    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    $args = [
        '-s',
        '-X', $method,
        '-b', $cookieJar,
        '-c', $cookieJar,
        '-H', $headers[0],
    ];
    if ($body !== null) {
        $args[] = '-H'; $args[] = $headers[1];
        $args[] = '-d'; $args[] = $body;
    }
    $args[] = $base . $path;

    $cmdLine = 'curl ' . implode(' ', array_map('escapeshellarg', $args));
    $output  = shell_exec($cmdLine);

    $data = json_decode((string) $output, true);
    if (is_array($data) && ($data['ok'] ?? false) === true) {
        $keys = array_keys($data['data'] ?? []);
        echo '[OK]   ' . sprintf('%-45s', "$method $path") . ' keys=' . implode(',', array_slice($keys, 0, 6)) . PHP_EOL;
    } else {
        echo '[WARN] ' . sprintf('%-45s', "$method $path") . ' => ' . substr((string) $output, 0, 200) . PHP_EOL;
        $failures++;
    }
}

proc_terminate($proc, 9);
proc_close($proc);
@unlink($cookieJar);

exit($failures === 0 ? 0 : 1);