<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once __DIR__ . '/data/bootstrap.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/data/Database/DatabasePassportCvRepository.php';
require_once __DIR__ . '/data/ReadModel/PassportCvViewModel.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\Service\ProfileSharingService;

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'");

$token = trim((string) ($_GET['token'] ?? ''));
$code = trim((string) ($_GET['code'] ?? $_GET['passport'] ?? ''));
$resolved = null;
$pdo = null;

try {
    $pdo = isset($GLOBALS['__TALENTHUB_TEST_PDO__']) && $GLOBALS['__TALENTHUB_TEST_PDO__'] instanceof PDO
        ? $GLOBALS['__TALENTHUB_TEST_PDO__']
        : (new Connection(require dirname(__DIR__, 2) . '/config/database.php'))->connect();

    learner_configure_data(['source' => 'database', 'pdo' => $pdo]);

    $sharingService = new ProfileSharingService($pdo);
    if ($token !== '') {
        $resolved = $sharingService->resolveShare($token);
    } elseif ($code !== '') {
        $resolved = $sharingService->resolvePassportCode($code);
    }
} catch (\Throwable) {
    $resolved = null;
}

http_response_code($resolved === null ? 404 : 200);

if ($resolved !== null && !empty($resolved['studentId']) && $pdo instanceof PDO) {
    $studentId = (string) $resolved['studentId'];
    try {
        $data = (new \TalentHub\Learner\Data\Database\DatabasePassportCvRepository($pdo))->forStudent($studentId);
        $stamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('d/m/Y H:i:s');
        $cv = \TalentHub\Learner\Data\ReadModel\PassportCvViewModel::build($data, $stamp);

        // Filter fields if this was generated with specific sharedFields consent
        if (!empty($resolved['sharedFields']) && is_array($resolved['sharedFields'])) {
            $sharedLookup = array_flip($resolved['sharedFields']);
            if (!isset($sharedLookup['phone'])) {
                $cv['phone'] = null;
            }
            if (!isset($sharedLookup['email'])) {
                $cv['email'] = null;
            }
            if (!isset($sharedLookup['location'])) {
                $cv['location'] = null;
            }
            if (!isset($sharedLookup['headline'])) {
                $cv['headline'] = null;
            }
            if (!isset($sharedLookup['skills'])) {
                $cv['skills'] = [];
            }
            if (!isset($sharedLookup['projects'])) {
                $cv['projects'] = [];
            }
            if (!isset($sharedLookup['experience'])) {
                $cv['activities'] = [];
                $cv['activity_summary'] = ['total_hours' => 0.0, 'total_activities' => 0];
            }
            if (!isset($sharedLookup['certificates'])) {
                $cv['badges'] = [];
            }
        }

        $isGuestView = true;
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $verificationUrl = $scheme . '://' . $host . (function_exists('app_href') ? app_href('/app/learner/shared-profile.php') : '/app/learner/shared-profile.php') . '?code=' . urlencode($cv['passport_code']);

        require __DIR__ . '/includes/passport-cv-template.php';
        return;
    } catch (\Throwable) {
        http_response_code(500);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hồ sơ không khả dụng | Xác thực Năng lực FTalentHub</title>
  <link rel="stylesheet" href="../../assets/css/learner-passport-cv.css">
  <style>
    body { background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }
    .not-found-card { text-align: center; padding: 3rem 2rem; background: #ffffff; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06); max-width: 480px; width: 100%; }
    .not-found-icon { font-size: 3rem; margin-bottom: 0.75rem; }
    .not-found-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0 0 0.5rem; }
    .not-found-desc { color: #64748b; font-size: 0.9rem; line-height: 1.5; margin: 0 0 1.5rem; }
    .not-found-btn { display: inline-flex; align-items: center; gap: 0.5rem; background: #1e40af; color: #ffffff; padding: 0.65rem 1.25rem; border-radius: 6px; font-weight: 700; text-decoration: none; font-size: 0.9rem; }
  </style>
</head>
<body>
  <div class="not-found-card">
    <div class="not-found-icon">🔍</div>
    <h1 class="not-found-title">Không tìm thấy hồ sơ</h1>
    <p class="not-found-desc">Mã xác thực hoặc liên kết chia sẻ không tồn tại trong hệ thống FTalentHub hoặc đã hết hạn.</p>
    <a class="not-found-btn" href="/">← Về trang chủ FTalentHub</a>
  </div>
</body>
</html>
