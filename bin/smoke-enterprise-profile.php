<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\Business\Repository\BusinessRepository;
use TalentHub\Modules\Business\Service\BusinessProfileService;

try {
    $config = require dirname(__DIR__).'/config/database.php';
    $pdo = (new Connection($config))->connect();
    $repo = new BusinessRepository($pdo);
    $service = new BusinessProfileService($repo);

    // 1. Test FPT Software User
    $fptUser = $pdo->query("SELECT id, email FROM users WHERE email = 'fpt@talenthub.local'")->fetch(PDO::FETCH_ASSOC);
    if (!$fptUser) {
        throw new RuntimeException("FPT user not found");
    }
    $fptProfile = $service->get((string)$fptUser['id']);
    if (($fptProfile['name'] ?? '') !== 'Công ty TNHH Phần mềm FPT') {
        throw new RuntimeException("Expected FPT Software, got: " . ($fptProfile['name'] ?? 'NULL'));
    }
    fwrite(STDOUT, "[OK] FPT Software profile fetched correctly: {$fptProfile['name']}" . PHP_EOL);

    // 2. Test Vinamilk User
    $vnmUser = $pdo->query("SELECT id, email FROM users WHERE email = 'vinamilk@talenthub.local'")->fetch(PDO::FETCH_ASSOC);
    if (!$vnmUser) {
        throw new RuntimeException("Vinamilk user not found");
    }
    $vnmProfile = $service->get((string)$vnmUser['id']);
    if (($vnmProfile['name'] ?? '') !== 'Công ty Cổ phần Sữa Việt Nam (Vinamilk)') {
        throw new RuntimeException("Expected Vinamilk, got: " . ($vnmProfile['name'] ?? 'NULL'));
    }
    fwrite(STDOUT, "[OK] Vinamilk profile fetched correctly: {$vnmProfile['name']}" . PHP_EOL);

    fwrite(STDOUT, "[ALL PASS] Enterprise Profile tests completed successfully." . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] " . $e->getMessage() . PHP_EOL);
    exit(1);
}
