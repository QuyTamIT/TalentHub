<?php
declare(strict_types=1);
require __DIR__ . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;

$baseConfig = require __DIR__ . '/config/session.php';
$sessionNames = [
    SessionManager::SESSION_STUDENT,
    SessionManager::SESSION_ENTERPRISE,
    SessionManager::SESSION_SCHOOL,
    SessionManager::SESSION_ADMIN,
    SessionManager::SESSION_DEFAULT,
];

$roleLogout = is_string($_GET['role'] ?? null) ? trim($_GET['role']) : null;
$targetNames = $roleLogout !== null
    ? [SessionManager::sessionNameForRole($roleLogout)]
    : $sessionNames;

foreach ($targetNames as $sName) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (isset($_COOKIE[$sName])) {
        $mgr = new SessionManager(array_merge($baseConfig, ['name' => $sName]));
        $mgr->start();
        $mgr->destroy();
        setcookie($sName, '', [
            'expires' => time() - 86400,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

header('Location: ' . app_href('/login.php'));
exit;