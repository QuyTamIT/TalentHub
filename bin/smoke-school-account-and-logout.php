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
echo "2. TEST BTEC FPT SCHOOL SESSION & ACCOUNT PAGE\n";
echo "=======================================================\n";
// Set up simulated BTEC session
$btecUser = $pdo->query("SELECT id, email, fullName, roleId FROM users WHERE email = 'btec@talenthub.local' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert(!empty($btecUser), 'btec@talenthub.local user must exist');

$_SESSION = [];
$_SESSION['user_id'] = $btecUser['id'];
$_SESSION['email'] = $btecUser['email'];
$_SESSION['role'] = 'school';
$_SESSION['logged_in'] = true;
$_SESSION['user'] = [
    'id' => $btecUser['id'],
    'email' => $btecUser['email'],
    'fullName' => $btecUser['fullName'],
    'role' => 'school',
    'status' => 'active',
];

ob_start();
require dirname(__DIR__) . '/app/school/account.php';
$btecHtml = ob_get_clean();

assert(str_contains($btecHtml, 'Cao đẳng Quốc tế BTEC FPT'), 'BTEC Account page must show school name');
assert(str_contains($btecHtml, 'BTEC-FPT'), 'BTEC Account page must show school code BTEC-FPT');
assert(str_contains($btecHtml, 'Thông tin Tổ chức / Trường học'), 'Must contain Part 1: Organization info');
assert(str_contains($btecHtml, 'Bảo mật & Đổi mật khẩu'), 'Must contain Part 2: Security & Password change');
assert(str_contains($btecHtml, 'school-sidebar__link--logout'), 'Sidebar must contain logout button class');
assert(str_contains($btecHtml, 'app/auth/logout.php'), 'Sidebar logout button must point to app/auth/logout.php');
echo "[PASS] BTEC FPT Account & Profile page rendered correctly with sidebar logout.\n";

echo "\n=======================================================\n";
echo "3. TEST CTU (ĐẠI HỌC CẦN THƠ) SESSION & ACCOUNT PAGE\n";
echo "=======================================================\n";
$ctuUser = $pdo->query("SELECT id, email, fullName, roleId FROM users WHERE email = 'ctu@talenthub.local' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert(!empty($ctuUser), 'ctu@talenthub.local user must exist');

$_SESSION = [];
$_SESSION['user_id'] = $ctuUser['id'];
$_SESSION['email'] = $ctuUser['email'];
$_SESSION['role'] = 'school';
$_SESSION['logged_in'] = true;
$_SESSION['user'] = [
    'id' => $ctuUser['id'],
    'email' => $ctuUser['email'],
    'fullName' => $ctuUser['fullName'],
    'role' => 'school',
    'status' => 'active',
];

ob_start();
require dirname(__DIR__) . '/app/school/account.php';
$ctuHtml = ob_get_clean();

assert(str_contains($ctuHtml, 'Đại học Cần Thơ'), 'CTU Account page must show school name Đại học Cần Thơ');
assert(str_contains($ctuHtml, 'CTU'), 'CTU Account page must show school code CTU');
assert(str_contains($ctuHtml, 'school-sidebar__link--logout'), 'Sidebar must contain logout button');
echo "[PASS] CTU Account & Profile page rendered correctly with distinct identity.\n";

echo "\n=======================================================\n";
echo "4. TEST PROFILE UPDATE LOGIC\n";
echo "=======================================================\n";
$schoolRepo = new TalentHub\Modules\School\Repository\SchoolRepository($pdo);
$schoolService = new TalentHub\Modules\School\Service\SchoolDashboardService($schoolRepo, $pdo, new TalentHub\Modules\School\Service\SchoolAuthorization($pdo));

$updatedBtec = $schoolService->update($btecUser['id'], [
    'name' => 'Cao đẳng Quốc tế BTEC FPT',
    'level' => 'Cao đẳng Quốc tế',
    'address' => 'Tòa nhà BTEC FPT, Trịnh Văn Bô, Nam Từ Liêm, Hà Nội',
    'phone' => '024 7300 9268',
    'email' => 'btec@talenthub.local',
    'website' => 'https://btec.fpt.edu.vn',
    'academicYear' => '2025 - 2026',
]);

assert($updatedBtec['phone'] === '024 7300 9268', 'Phone must be updated');
assert($updatedBtec['website'] === 'https://btec.fpt.edu.vn', 'Website must be updated');
echo "[PASS] School profile update verified via MySQL.\n";

echo "\n=======================================================\n";
echo "ALL TESTS PASSED SUCCESSFULLY!\n";
echo "=======================================================\n";
