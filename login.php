<?php
/**
 * TalentHub - Login Page (School demo entry point).
 *
 * The legacy UI lives outside the /api/v1 router, so this page handles
 * the form POST directly via the same AuthService used by the API. The
 * service writes the session, regenerates the session id and stores the
 * authenticated user payload for the school dashboard.
 */
declare(strict_types=1);

require __DIR__ . '/bin/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Support\Id\RequestId;

$errorMessage = null;
$emailValue   = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $config    = require __DIR__ . '/config/database.php';
    $pdo       = (new Connection($config))->connect();
    $session   = new SessionManager(require __DIR__ . '/config/session.php');
    $session->start();
    $auth      = new AuthService(new AuthRepository($pdo));

    $emailValue = trim((string) ($_POST['email'] ?? ''));
    $password   = (string) ($_POST['password'] ?? '');
    $next       = (string) ($_POST['next'] ?? ($_GET['next'] ?? '/app/school/'));
    if (!str_starts_with($next, '/') || str_starts_with($next, '//')) {
        $next = '/app/school/';
    }

    try {
        $session->assertLoginAllowed();
        $requestId = RequestId::make(null);
        $user = $auth->login(
            ['email' => $emailValue, 'password' => $password],
            $requestId,
            $_SERVER['REMOTE_ADDR'] ?? null
        );
        $session->clearLoginFailures();
        $session->login($user);
        header('Location: ' . $next);
        exit;
    } catch (ApiException $e) {
        if ($e->errorCode === 'INVALID_CREDENTIALS') {
            $session->recordLoginFailure();
        }
        $errorMessage = $e->getMessage();
    } catch (Throwable $e) {
        $errorMessage = 'Đã xảy ra lỗi hệ thống, vui lòng thử lại.';
    }
}

$next = (string) ($_GET['next'] ?? '/app/school/');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - TalentHub Nhà trường</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <style>
        .login-shell {
            max-width: 420px;
            margin: 80px auto;
            padding: 36px 32px;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.08);
        }
        .login-shell h1 {
            font-size: 1.6rem;
            margin: 0 0 6px;
        }
        .login-shell p.lead {
            margin: 0 0 24px;
            color: #475569;
        }
        .login-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }
        .login-field label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e293b;
        }
        .login-field input {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 1rem;
        }
        .login-field input:focus {
            outline: 2px solid #2563eb;
            border-color: #2563eb;
        }
        .login-shell .btn-primary {
            width: 100%;
            padding: 12px 0;
            border-radius: 10px;
        }
        .login-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
        .login-hint {
            margin-top: 18px;
            font-size: 0.85rem;
            color: #475569;
            background: #f1f5f9;
            padding: 12px;
            border-radius: 10px;
        }
        .login-hint code { background: #e2e8f0; padding: 1px 6px; border-radius: 6px; }
    </style>
</head>
<body>
    <main class="login-shell">
        <h1>Đăng nhập Nhà trường</h1>
        <p class="lead">Sử dụng tài khoản school admin đã được seed để truy cập dashboard.</p>

        <?php if ($errorMessage !== null): ?>
            <div class="login-error"><?= htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <input type="hidden" name="next" value="<?= htmlspecialchars($next); ?>">
            <div class="login-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($emailValue); ?>" required autocomplete="email">
            </div>
            <div class="login-field">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary">Đăng nhập</button>
        </form>

        <div class="login-hint">
            <strong>Tài khoản demo:</strong><br>
            Email: <code>school.admin@talenthub.vn</code><br>
            Mật khẩu: <code>TestPassword_2026</code>
        </div>
    </main>
</body>
</html>