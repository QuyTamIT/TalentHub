<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;

$config = require __DIR__ . '/../../config/database.php';
$pdo = (new Connection($config))->connect();

$stmt = $pdo->prepare("SELECT u.id, u.email, u.fullName FROM users u JOIN roles r ON r.id = u.roleId WHERE r.code = 'business' LIMIT 1");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "[FAIL] No business user found in DB.\n";
    exit(1);
}

// Start session as logged in business user
$session = new SessionManager(require __DIR__ . '/../../config/session.php');
$session->start();
$session->login([
    'id'       => $user['id'],
    'email'    => $user['email'],
    'fullName' => $user['fullName'],
    'role'     => 'business',
    'status'   => 'active',
]);

$pages = [
    'app/enterprise/profile.php'              => 'Hồ sơ doanh nghiệp',
    'app/enterprise/index.php'                => 'Dashboard',
    'app/enterprise/talents.php'              => 'Tìm nhân tài',
    'app/enterprise/talents/detail.php'       => 'Hồ sơ nhân tài (Talent Passport)',
    'app/enterprise/internships/index.php'    => 'Tuyển thực tập',
    'app/enterprise/internships/create.php'   => 'Đăng tin mới',
    'app/enterprise/internships/applicants.php'=> 'Quản lý ứng viên',
    'app/enterprise/sponsorships/index.php'   => 'Tài trợ dự án',
    'app/enterprise/analytics.php'            => 'Phân tích tuyển dụng',
];

$allPassed = true;

foreach ($pages as $relativePath => $pageTitle) {
    $fullPath = dirname(__DIR__, 2) . '/' . $relativePath;
    if (!file_exists($fullPath)) {
        echo "[FAIL] File missing: {$relativePath}\n";
        $allPassed = false;
        continue;
    }

    ob_start();
    try {
        include $fullPath;
        $output = ob_get_clean();
        if (strlen($output) < 500 || !str_contains($output, '<!DOCTYPE html>')) {
            echo "[FAIL] {$relativePath} did not render full HTML (len=" . strlen($output) . ")\n";
            $allPassed = false;
        } else {
            echo "[OK] {$pageTitle} ({$relativePath}) rendered successfully (" . strlen($output) . " bytes)\n";
        }
    } catch (\Throwable $e) {
        ob_end_clean();
        echo "[FAIL] {$relativePath} threw exception: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\n[ALL PASS] All Enterprise pages rendered cleanly with active session and dynamic DB data!\n";
    exit(0);
} else {
    echo "\n[FAIL] Some enterprise pages failed to render.\n";
    exit(1);
}
