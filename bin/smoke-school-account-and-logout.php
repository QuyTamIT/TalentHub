<?php
/**
 * Smoke Test: School Account, Profile & Sidebar Logout Verification
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "=======================================================\n";
echo "1. VERIFY LOGOUT HANDLER (app/auth/logout.php)\n";
echo "=======================================================\n";
assert(file_exists(dirname(__DIR__) . '/app/auth/logout.php'), 'app/auth/logout.php must exist');
echo "[PASS] app/auth/logout.php exists.\n";

echo "\n=======================================================\n";
echo "2. TEST SCHOOL ACCOUNT PAGE & SIDEBAR LOGOUT (CLEAN STATE)\n";
echo "=======================================================\n";

ob_start();
require dirname(__DIR__) . '/app/school/account.php';
$accountHtml = ob_get_clean();

assert(str_contains($accountHtml, 'Thông tin Tổ chức / Trường học'), 'Must contain Part 1: Organization info');
assert(str_contains($accountHtml, 'Bảo mật & Đổi mật khẩu'), 'Must contain Part 2: Security & Password change');
assert(str_contains($accountHtml, 'school-sidebar__link--logout'), 'Sidebar must contain logout button class');
assert(str_contains($accountHtml, 'app/auth/logout.php'), 'Sidebar logout button must point to app/auth/logout.php');
echo "[PASS] School Account & Profile page rendered correctly with clean empty state.\n";

echo "\n=======================================================\n";
echo "ALL TESTS PASSED SUCCESSFULLY!\n";
echo "=======================================================\n";
