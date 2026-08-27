<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/src/Bootstrap/EnterpriseAppContext.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$fptUser = $pdo->query("SELECT id, email, fullName, roleId FROM users WHERE email = 'fpt@talenthub.local'")->fetch(PDO::FETCH_ASSOC);

// Simulate web request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/app/enterprise/talents/detail.php';
$_SERVER['REQUEST_URI'] = '/app/enterprise/talents/detail.php?id=24000000-0000-4000-8000-000000000002';
$_GET['id'] = '24000000-0000-4000-8000-000000000002';

// Start business session
$session = new SessionManager(array_merge(require dirname(__DIR__) . '/config/session.php', ['name' => SessionManager::SESSION_ENTERPRISE]));
$session->start();
$_SESSION['user_id'] = $fptUser['id'];
$_SESSION['user'] = [
    'id' => $fptUser['id'],
    'email' => $fptUser['email'],
    'fullName' => $fptUser['fullName'],
    'role' => 'business',
    'status' => 'active'
];

ob_start();
try {
    include dirname(__DIR__) . '/app/enterprise/talents/detail.php';
    $rendered = ob_get_clean();
    echo "Rendered successfully: " . strlen($rendered) . " bytes.\n";

    if (str_contains($rendered, 'Trần Minh Đức')) {
        echo "[PASS] Successfully rendered Trần Minh Đức candidate detail!\n";
    }
    if (str_contains($rendered, 'Mời thực tập / Tuyển dụng')) {
        echo "[PASS] Successfully rendered 'Mời thực tập / Tuyển dụng' button!\n";
    }
    if (str_contains($rendered, 'Cao đẳng Quốc tế BTEC FPT')) {
        echo "[PASS] Successfully rendered school 'Cao đẳng Quốc tế BTEC FPT'!\n";
    }
    if (str_contains($rendered, 'BTEC-AI-2026A')) {
        echo "[PASS] Successfully rendered class 'BTEC-AI-2026A'!\n";
    }
    if (str_contains($rendered, 'inviteModal')) {
        echo "[PASS] Successfully rendered inviteModal!\n";
    }
} catch (Throwable $e) {
    ob_end_clean();
    echo "[ERROR] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
