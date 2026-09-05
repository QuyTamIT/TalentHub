<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Modules\Teacher\Repository\TeacherQrSessionRepository;
use TalentHub\Modules\Teacher\Service\TeacherQrSessionService;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, "[FAIL] {$message}\n");
        return;
    }

    echo "[PASS] {$message}\n";
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL);
CREATE TABLE activity_qr_sessions (
    id TEXT PRIMARY KEY,
    createdByTeacherId TEXT NOT NULL,
    tokenHash TEXT NOT NULL,
    status TEXT NOT NULL,
    expiresAt TEXT NOT NULL,
    maxScans INTEGER NOT NULL,
    usedScans INTEGER NOT NULL
);
SQL);

$teacherId = '11111111-1111-4111-8111-111111111111';
$otherTeacherId = '22222222-2222-4222-8222-222222222222';
$teacherUserId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$otherTeacherUserId = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$activeSessionId = '33333333-3333-4333-8333-333333333333';
$expiredSessionId = '44444444-4444-4444-8444-444444444444';
$revokedSessionId = '55555555-5555-4555-8555-555555555555';
$activeToken = 'opaque-qr-token-stable';
$expiresAt = '2099-01-01 00:00:00.000000';

$insertTeacher = $pdo->prepare('INSERT INTO teacher_profiles (id, userId) VALUES (?, ?)');
$insertTeacher->execute([$teacherId, $teacherUserId]);
$insertTeacher->execute([$otherTeacherId, $otherTeacherUserId]);

$insertSession = $pdo->prepare(
    'INSERT INTO activity_qr_sessions (id, createdByTeacherId, tokenHash, status, expiresAt, maxScans, usedScans)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$insertSession->execute([$activeSessionId, $teacherId, hash('sha256', $activeToken), 'active', $expiresAt, 10, 2]);
$insertSession->execute([$expiredSessionId, $teacherId, hash('sha256', 'expired-token'), 'active', '2000-01-01 00:00:00.000000', 10, 2]);
$insertSession->execute([$revokedSessionId, $teacherId, hash('sha256', 'revoked-token'), 'revoked', $expiresAt, 10, 2]);

$service = new TeacherQrSessionService(new TeacherQrSessionRepository($pdo));
$display = [
    'sessionId' => $activeSessionId,
    'rawToken' => $activeToken,
    'expiresAt' => $expiresAt,
];

$first = $service->resolveDisplayToken($teacherUserId, $display);
$second = $service->resolveDisplayToken($teacherUserId, $display);
$assert(is_array($first) && $first['rawToken'] === $activeToken, 'Active QR resolves with the original raw token.');
$assert(is_array($second) && $second['rawToken'] === $activeToken, 'Repeated GET-style resolution keeps the same raw token.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM activity_qr_sessions')->fetchColumn() === 3, 'Repeated resolution does not create another QR session.');
$assert((int) $pdo->query('SELECT usedScans FROM activity_qr_sessions WHERE id = "' . $activeSessionId . '"')->fetchColumn() === 2, 'Repeated resolution does not change the scan counter.');

$assert($service->resolveDisplayToken($otherTeacherUserId, $display) === null, 'Another teacher cannot resolve the QR token.');
$assert($service->resolveDisplayToken($teacherUserId, [
    'sessionId' => $expiredSessionId,
    'rawToken' => 'expired-token',
    'expiresAt' => '2000-01-01 00:00:00.000000',
]) === null, 'Expired QR is not displayed after a refresh.');
$assert($service->resolveDisplayToken($teacherUserId, [
    'sessionId' => $revokedSessionId,
    'rawToken' => 'revoked-token',
    'expiresAt' => $expiresAt,
]) === null, 'Revoked QR is not displayed after a refresh.');
$assert($service->resolveDisplayToken($teacherUserId, [
    'sessionId' => $activeSessionId,
    'rawToken' => 'wrong-token',
    'expiresAt' => $expiresAt,
]) === null, 'A token hash mismatch is not displayed.');

$indexSource = (string) file_get_contents(dirname(__DIR__) . '/app/teacher/checkins/index.php');
$jsSource = (string) file_get_contents(dirname(__DIR__) . '/assets/js/teacher-qr.js');
$repoSource = (string) file_get_contents(dirname(__DIR__) . '/src/Modules/Teacher/Repository/TeacherQrSessionRepository.php');
$assert(str_contains($indexSource, "\$_SESSION['teacherQrDisplays']"), 'Teacher QR display data is stored in the PHP session.');
$assert(str_contains($indexSource, 'resolveDisplayToken'), 'GET validates the QR display data against the database.');
$assert(str_contains($indexSource, 'unset($_SESSION[\'teacherQrDisplays\'][$rawSessionId])'), 'Revoke removes the matching QR token from the PHP session.');
$assert(str_contains($indexSource, 'Cache-Control'), 'QR page keeps no-store cache headers.');
$assert(str_contains($indexSource, "if (\$_SERVER['REQUEST_METHOD'] === 'POST')"), 'QR creation and revocation are handled only by POST.');
$assert(str_contains($indexSource, "header('Location: ' . app_href('/app/teacher/checkins/index.php'))"), 'QR mutations keep POST/Redirect/GET.');
$assert(!str_contains($indexSource, 'HIỂN THỊ MỘT LẦN'), 'One-time display label is removed.');
$assert(!str_contains($indexSource, 'Token thô sẽ không được hiển thị lại'), 'One-time token warning is removed.');
$assert(!preg_match('/teacherQrFlash[^;]*rawToken|rawToken[^;]*teacherQrFlash/is', $indexSource), 'Raw token is not stored in the flash message.');
$assert(!preg_match('/(?:localStorage|sessionStorage)/i', $indexSource . $jsSource), 'Raw token is not stored in browser storage.');
$assert(!preg_match('/console\.(?:log|info|warn|error)\s*\([^)]*token/i', $indexSource . $jsSource), 'Raw token is not written to console logs.');
$assert(!preg_match('/[?&](?:token|qr)=/i', $indexSource . $jsSource), 'Raw token is not placed in a URL.');
$assert(str_contains($repoSource, 'tokenHash'), 'Repository persists and verifies only tokenHash.');

if ($failures !== []) {
    fwrite(STDERR, "Summary: " . (count($failures)) . " failed\n");
    exit(1);
}

echo "Summary: QR display session tests passed\n";
