<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$auth = new AuthService(new AuthRepository($pdo));

echo "========================================================\n";
echo "   RUNNING SCHOOL BOOT & CREDENTIAL VERIFICATION SUITE  \n";
echo "========================================================\n\n";

// TEST 1: Password Login verification for btec@school.edu.vn
echo "[TEST 1] Testing AuthService login with btec@school.edu.vn / 123456...\n";
try {
    $loginResult = $auth->login(['email' => 'btec@school.edu.vn', 'password' => '123456']);
    echo "  -> Login Success! User: {$loginResult['fullName']} ({$loginResult['id']}), Role: {$loginResult['role']}\n";
    if ($loginResult['role'] !== 'school') {
        throw new RuntimeException("Expected role 'school', got '{$loginResult['role']}'");
    }
    echo "  [PASS] Test 1\n\n";
} catch (Throwable $e) {
    echo "  [FAIL] Test 1: " . $e->getMessage() . "\n\n";
    exit(1);
}

// TEST 2: Boot without session (Incognito / Fresh visitor)
echo "[TEST 2] Testing SchoolAppContext::boot() in clean / incognito state...\n";
try {
    $_SESSION = [];
    $_COOKIE = [];
    $context = (new SchoolAppContext())->boot();
    echo "  -> Booted school: {$context['school']['name']} (ID: {$context['school']['id']})\n";
    echo "  -> Resolved user: {$context['user']['email']} (ID: {$context['user']['id']})\n";
    echo "  -> KPIs count: " . count($context['dashboard']['kpis']) . "\n";
    echo "  [PASS] Test 2\n\n";
} catch (Throwable $e) {
    echo "  [FAIL] Test 2: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

// TEST 3: Boot with corrupt/stale userId in session
echo "[TEST 3] Testing SchoolAppContext::boot() with fake / non-existent userId in session...\n";
try {
    $_SESSION = [
        'user_id' => '00000000-dead-beef-0000-000000000000',
        'user' => [
            'id' => '00000000-dead-beef-0000-000000000000',
            'email' => 'ghost@school.edu.vn',
            'role' => 'school',
        ],
    ];
    $context = (new SchoolAppContext())->boot();
    echo "  -> Handled stale session safely!\n";
    echo "  -> Rescued user: {$context['user']['email']} (ID: {$context['user']['id']})\n";
    echo "  -> School: {$context['school']['name']}\n";
    echo "  [PASS] Test 3\n\n";
} catch (Throwable $e) {
    echo "  [FAIL] Test 3: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

// TEST 4: Boot with new valid school user unlinked in school_members (Auto-heal test)
echo "[TEST 4] Testing SchoolAppContext::boot() auto-heal for unlinked school user...\n";
$tempUserId = Uuid::v4();
$tempEmail = 'temp.btec.admin.' . time() . '@btec.edu.vn';
$schoolRoleId = (string) $pdo->query("SELECT id FROM roles WHERE code = 'school' LIMIT 1")->fetchColumn();

try {
    $pdo->prepare("INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt) VALUES (?, ?, ?, ?, 'Temp BTEC Admin', 'active', NOW(), NOW())")
        ->execute([$tempUserId, $schoolRoleId, $tempEmail, password_hash('123456', PASSWORD_DEFAULT)]);

    $_SESSION = [
        'user_id' => $tempUserId,
        'user' => [
            'id' => $tempUserId,
            'email' => $tempEmail,
            'fullName' => 'Temp BTEC Admin',
            'role' => 'school',
            'status' => 'active',
        ],
    ];
    
    $context = (new SchoolAppContext())->boot();
    echo "  -> Auto-heal successfully linked user $tempEmail to school: {$context['school']['name']}!\n";
    
    // Verify row was inserted into school_members
    $chkStmt = $pdo->prepare("SELECT id, schoolId, memberRole FROM school_members WHERE userId = ? LIMIT 1");
    $chkStmt->execute([$tempUserId]);
    $memberRow = $chkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$memberRow) {
        throw new RuntimeException("Auto-heal did not create school_members record!");
    }
    echo "  -> Confirmed school_members row: {$memberRow['id']} (Role: {$memberRow['memberRole']})\n";
    echo "  [PASS] Test 4\n\n";
} catch (Throwable $e) {
    echo "  [FAIL] Test 4: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
    // Clean up temp
    $pdo->prepare("DELETE FROM school_members WHERE userId = ?")->execute([$tempUserId]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$tempUserId]);
    exit(1);
} finally {
    // Cleanup temp
    $pdo->prepare("DELETE FROM school_members WHERE userId = ?")->execute([$tempUserId]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$tempUserId]);
}

// TEST 5: Render app/school/index.php output buffer
echo "[TEST 5] Testing app/school/index.php template execution...\n";
try {
    $_SESSION = [];
    $_COOKIE = [];
    ob_start();
    include dirname(__DIR__) . '/app/school/index.php';
    $renderedHtml = ob_get_clean();
    if (!str_contains($renderedHtml, 'Cao đẳng Quốc tế BTEC FPT') && !str_contains($renderedHtml, 'Khu vực Nhà trường')) {
        throw new RuntimeException("Rendered HTML missing expected school portal content!");
    }
    echo "  -> Output rendered successfully (" . strlen($renderedHtml) . " bytes)\n";
    echo "  [PASS] Test 5\n\n";
} catch (Throwable $e) {
    echo "  [FAIL] Test 5: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

echo "========================================================\n";
echo "   ALL TESTS PASSED SUCCESSFULLY! NO ERRORS FOUND.       \n";
echo "========================================================\n";
