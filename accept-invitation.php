<?php

declare(strict_types=1);

require __DIR__ . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolAccountInvitationService;

$session = new SessionManager(require __DIR__ . '/config/session.php');
$session->start();
$pdo = (new Connection(require __DIR__ . '/config/database.php'))->connect();
$service = new SchoolAccountInvitationService(new SchoolRepository($pdo));
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = null;
$success = false;
$invitation = null;

try {
    if ($token === '') {
        throw new ApiException(422, 'INVALID_INVITATION_TOKEN', 'Liên kết lời mời không hợp lệ.');
    }
    $invitation = $service->inspect($token);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $service->accept($token, [
            'password' => $_POST['password'] ?? '',
            'passwordConfirmation' => $_POST['passwordConfirmation'] ?? '',
        ]);
        $success = true;
        $invitation['status'] = 'accepted';
    }
} catch (ApiException $exception) {
    $error = $exception->getMessage();
} catch (Throwable) {
    $error = 'Không thể xử lý lời mời lúc này. Vui lòng thử lại sau.';
}

$roleLabel = (($invitation['role'] ?? '') === 'teacher') ? 'Giáo viên' : 'Học sinh/Sinh viên';
?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kích hoạt tài khoản · TalentHub</title>
    <style>
        body{font-family:system-ui,sans-serif;background:#f5f7fb;color:#172033;margin:0;display:grid;min-height:100vh;place-items:center}.card{background:#fff;border:1px solid #dfe5ef;border-radius:16px;box-shadow:0 16px 50px #17203318;padding:32px;width:min(92vw,520px)}h1{font-size:1.5rem;margin:0 0 8px}.muted{color:#667085}.field{display:grid;gap:6px;margin:18px 0}.field input{padding:12px;border:1px solid #b9c3d3;border-radius:8px;font:inherit}.button{display:inline-block;background:#3157d5;color:#fff;border:0;border-radius:8px;padding:12px 18px;font-weight:700;text-decoration:none;cursor:pointer}.alert{padding:12px;border-radius:8px;margin:16px 0}.error{background:#fff1f1;color:#a11}.success{background:#ecfdf3;color:#067647}
    </style>
</head>
<body>
<main class="card">
    <h1>Kích hoạt tài khoản TalentHub</h1>
    <?php if ($success): ?>
        <div class="alert success">Tài khoản đã được kích hoạt. Bạn có thể đăng nhập bằng mật khẩu vừa đặt.</div>
        <a class="button" href="<?= htmlspecialchars(app_href('/login.php'), ENT_QUOTES, 'UTF-8'); ?>">Đến trang đăng nhập</a>
    <?php elseif ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif (($invitation['status'] ?? '') !== 'pending'): ?>
        <div class="alert error">Lời mời này không còn hiệu lực (<?= htmlspecialchars((string) $invitation['status'], ENT_QUOTES, 'UTF-8'); ?>).</div>
    <?php else: ?>
        <p class="muted">
            <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?> ·
            <?= htmlspecialchars((string) $invitation['schoolName'], ENT_QUOTES, 'UTF-8'); ?><br>
            <?= htmlspecialchars((string) $invitation['email'], ENT_QUOTES, 'UTF-8'); ?> · hết hạn <?= htmlspecialchars((string) $invitation['expiresAt'], ENT_QUOTES, 'UTF-8'); ?> UTC
        </p>
        <form method="post">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <label class="field"><span>Mật khẩu mới (ít nhất 12 ký tự)</span><input type="password" name="password" minlength="12" maxlength="255" autocomplete="new-password" required></label>
            <label class="field"><span>Nhập lại mật khẩu</span><input type="password" name="passwordConfirmation" minlength="12" maxlength="255" autocomplete="new-password" required></label>
            <button class="button" type="submit">Kích hoạt tài khoản</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
