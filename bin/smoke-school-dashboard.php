<?php
/**
 * Smoke test for the school dashboard rendering and JSON API.
 * Runs entirely from CLI - no Apache needed.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/bin/bootstrap.php';
require $root . '/Database/seeds/Demo/SchoolDemoSeeder.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Database\Seeds\Demo\SchoolDemoSeeder;
use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\Request;
use TalentHub\Bootstrap\Application;

$config  = require $root . '/config/database.php';
$pdo     = (new Connection($config))->connect();
$session = new SessionManager(require $root . '/config/session.php');

$stmt = $pdo->prepare('SELECT id, email, fullName FROM users WHERE email = :email');
$stmt->execute(['email' => (new SchoolDemoSeeder())->demoAdminEmail()]);
$admin = $stmt->fetch();

if (!$admin) {
    fwrite(STDERR, '[FAIL] Demo admin not found - run bin/seed.php --demo first' . PHP_EOL);
    exit(1);
}

echo '[INFO] Demo admin: ' . $admin['email'] . ' (' . $admin['fullName'] . ')' . PHP_EOL;

$roleStmt = $pdo->prepare('SELECT r.code FROM users u JOIN roles r ON r.id = u.roleId WHERE u.id = :id');
$roleStmt->execute(['id' => $admin['id']]);
$roleCode = $roleStmt->fetchColumn();
echo '[INFO] Role: ' . $roleCode . PHP_EOL;

session_start();
$_SESSION['user'] = [
    'id'       => $admin['id'],
    'email'    => $admin['email'],
    'fullName' => $admin['fullName'],
    'role'     => 'school',
    'status'   => 'active',
];

$context = (new SchoolAppContext())->boot();

echo '[OK] Session resolved: ' . $context['user']['email'] . PHP_EOL;
echo '[OK] School resolved: ' . $context['school']['name'] . ' (' . $context['school']['id'] . ')' . PHP_EOL;
echo '[OK] Dashboard metrics: ' . json_encode($context['dashboard']['metrics']) . PHP_EOL;
echo '[OK] KPI count: ' . count($context['dashboard']['kpis']) . PHP_EOL;
echo '[OK] Classes count: ' . count($context['dashboard']['classes']) . PHP_EOL;
echo '[OK] Top talents count: ' . count($context['dashboard']['topTalents']) . PHP_EOL;
echo '[OK] Recent activities count: ' . count($context['dashboard']['recentActivity']) . PHP_EOL;

session_destroy();

$request = Request::fromGlobals();
echo PHP_EOL . '[SKIP] Live HTTP request test needs Apache. Run curl after login.' . PHP_EOL;