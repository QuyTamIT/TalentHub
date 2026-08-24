<?php
declare(strict_types=1);
require __DIR__ . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;

$session = new SessionManager(require __DIR__ . '/config/session.php');
$session->start();
$session->destroy();

header('Location: ./login.php');
exit;