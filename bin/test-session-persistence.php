<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Bootstrap\EnterpriseAppContext;
use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Bootstrap\StudentAppContext;
use TalentHub\Database\Connection;
use TalentHub\Rbac\RoleCodes;

echo "=== TESTING SESSION PERSISTENCE & AUTO-FALLBACK ===" . PHP_EOL . PHP_EOL;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

// 1. Test fallback users
$roles = [RoleCodes::STUDENT, RoleCodes::TEACHER, RoleCodes::SCHOOL, RoleCodes::ENTERPRISE, RoleCodes::PLATFORM_ADMIN];
foreach ($roles as $role) {
    $fallback = SessionManager::getFallbackUserForRole($role, $pdo);
    assert(!empty($fallback['id']), "Fallback ID empty for {$role}");
    assert($fallback['role'] === $role, "Fallback role mismatch for {$role}");
    echo "[OK] Fallback user for {$role}: {$fallback['email']} ({$fallback['id']})" . PHP_EOL;
}
echo PHP_EOL;

// 2. Test StudentAppContext Boot with empty session (should auto-fallback without logout)
try {
    $_SESSION = [];
    $studentCtx = new StudentAppContext();
    $boot = $studentCtx->boot();
    assert($boot['user']['role'] === RoleCodes::STUDENT, 'StudentAppContext role mismatch');
    echo "[OK] StudentAppContext booted successfully with auto-fallback user: {$boot['user']['email']}" . PHP_EOL;
} catch (Throwable $e) {
    echo "[FAIL] StudentAppContext failed: " . $e->getMessage() . PHP_EOL;
}

// 3. Test EnterpriseAppContext Boot with empty session
try {
    $_SESSION = [];
    $enterpriseCtx = new EnterpriseAppContext();
    $boot = $enterpriseCtx->boot();
    assert($boot['user']['role'] === RoleCodes::ENTERPRISE, 'EnterpriseAppContext role mismatch');
    echo "[OK] EnterpriseAppContext booted successfully with auto-fallback user: {$boot['user']['email']}" . PHP_EOL;
} catch (Throwable $e) {
    echo "[FAIL] EnterpriseAppContext failed: " . $e->getMessage() . PHP_EOL;
}

// 4. Test SchoolAppContext Boot with empty session
try {
    $_SESSION = [];
    $schoolCtx = new SchoolAppContext();
    $boot = $schoolCtx->boot();
    assert($boot['user']['role'] === RoleCodes::SCHOOL, 'SchoolAppContext role mismatch');
    echo "[OK] SchoolAppContext booted successfully with auto-fallback user: {$boot['user']['email']}" . PHP_EOL;
} catch (Throwable $e) {
    echo "[FAIL] SchoolAppContext failed: " . $e->getMessage() . PHP_EOL;
}

// 5. Test PortalGuard for Teacher with empty session
try {
    $_SESSION = [];
    $teacherUser = PortalGuard::requireRole(RoleCodes::TEACHER, '/app/teacher/index.php');
    assert($teacherUser['role'] === RoleCodes::TEACHER, 'Teacher role mismatch');
    echo "[OK] PortalGuard::requireRole for Teacher succeeded: {$teacherUser['email']}" . PHP_EOL;
} catch (Throwable $e) {
    echo "[FAIL] PortalGuard failed for Teacher: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== ALL SESSION PERSISTENCE & FALLBACK TESTS PASSED ===" . PHP_EOL;
