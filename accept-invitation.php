<?php

declare(strict_types=1);

require __DIR__ . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolDashboardService;

$session = new SessionManager(require __DIR__ . '/config/session.php');
$session->start();
$pdo = (new Connection(require __DIR__ . '/config/database.php'))->connect();
$service = new SchoolDashboardService(new SchoolRepository($pdo), $pdo);

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$accepted = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $newPassword = (string) ($_POST['newPassword'] ?? '');
        if (!hash_equals($newPassword, (string) ($_POST['confirmPassword'] ?? ''))) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mật khẩu nhập lại chưa khớp.');
        }
        $service->acceptInvitation($token, $newPassword);
        $accepted = true;
        $token = '';
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable) {
        $error = 'Không thể xử lý lời mời lúc này. Vui lòng thử lại sau.';
    }
}

$tokenLooksValid = preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
if (!$accepted && !$tokenLooksValid && $error === null) {
    $error = 'Liên kết lời mời không hợp lệ.';
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nhận lời mời | TalentHub</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_href('/assets/css/home.css')); ?>">
    <style>
        body { min-height:100vh;display:grid;place-items:center;background:#f4f7fb;padding:1rem; }
        .invitation-card { width:min(100%,480px);background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:2rem;box-shadow:0 16px 40px rgba(15,23,42,.08); }
        .invitation-card h1 { margin:0 0 .5rem;font-size:1.5rem; }
        .invitation-card p { color:#64748b;line-height:1.6; }
        .invitation-form { display:grid;gap:1rem;margin-top:1.5rem; }
        .invitation-form label { display:grid;gap:.4rem;font-weight:600; }
        .invitation-form input { padding:.75rem;border:1px solid #cbd5e1;border-radius:8px;font:inherit; }
        .invitation-message { padding:.8rem 1rem;border-radius:8px;margin-top:1rem; }
        .invitation-message--error { background:#fef2f2;color:#b91c1c; }
        .invitation-message--success { background:#f0fdf4;color:#166534; }
    </style>
</head>
<body>
<main class="invitation-card">
    <h1>Thiết lập tài khoản TalentHub</h1>
    <?php if ($accepted): ?>
        <div class="invitation-message invitation-message--success" role="status">
            Đã đặt mật khẩu thành công. Lời mời này không thể sử dụng lại.
        </div>
        <p><a class="btn btn-primary" href="<?= htmlspecialchars(app_href('/login.php')); ?>">Đến trang đăng nhập</a></p>
    <?php else: ?>
        <p>Đặt mật khẩu riêng để hoàn tất lời mời. Mật khẩu cần có từ 12 đến 255 ký tự.</p>
        <?php if ($error !== null): ?>
            <div class="invitation-message invitation-message--error" role="alert"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($tokenLooksValid): ?>
            <form method="post" class="invitation-form">
                <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                <label>
                    Mật khẩu mới
                    <input type="password" name="newPassword" minlength="12" maxlength="255" required autocomplete="new-password">
                </label>
                <label>
                    Nhập lại mật khẩu
                    <input type="password" name="confirmPassword" minlength="12" maxlength="255" required autocomplete="new-password">
                </label>
                <button type="submit" class="btn btn-primary">Hoàn tất lời mời</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</main>
<script>
document.querySelector('.invitation-form')?.addEventListener('submit', function (event) {
    const password = this.elements.newPassword.value;
    const confirmation = this.elements.confirmPassword.value;
    if (password !== confirmation) {
        event.preventDefault();
        window.alert('Mật khẩu nhập lại chưa khớp.');
    }
});
</script>
</body>
</html>
