<?php
declare(strict_types=1);

namespace TalentHub\Bootstrap;

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Rbac\RoleCodes;

final class PortalGuard
{
    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    public static function requireRole(string $role, string $fallbackPath): array
    {
        $root = dirname(__DIR__, 2);
        $sessionConfig = require $root . '/config/session.php';
        $sessionConfig['name'] = SessionManager::sessionNameForRole($role);
        $session = new SessionManager($sessionConfig);
        $session->start();
        $cached = $session->user();
        if ($cached === null) {
            self::redirect('/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? $fallbackPath) . '&role_required=' . urlencode($role));
        }

        if (!RoleCodes::matches((string) ($cached['role'] ?? ''), $role)) {
            self::renderRoleMismatch((string) ($cached['role'] ?? ''), $role);
        }

        try {
            $pdo = (new Connection(require $root . '/config/database.php'))->connect();
            $user = (new AuthService(new AuthRepository($pdo)))->current((string) $cached['id']);
            $session->refreshUser($user);
        } catch (ApiException $exception) {
            if ($exception->status === 401) {
                $session->destroy();
                self::redirect('/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? $fallbackPath));
            }
            throw $exception;
        }

        if (!RoleCodes::matches((string) ($user['role'] ?? ''), $role)) {
            self::renderRoleMismatch((string) ($user['role'] ?? ''), $role);
        }
        return $user;
    }

    public static function renderRoleMismatch(string $currentRole, string $requiredRole): never
    {
        http_response_code(403);

        $roleLabels = [
            'student'        => 'Học sinh / Sinh viên',
            'enterprise'     => 'Doanh nghiệp / Nhà tuyển dụng',
            'teacher'        => 'Giáo viên / Cố vấn',
            'school'         => 'Nhà trường / Ban giám hiệu',
            'platform_admin' => 'Quản trị viên hệ thống',
        ];

        $canonicalCurrent = RoleCodes::canonical($currentRole);
        $canonicalRequired = RoleCodes::canonical($requiredRole);

        $currentLabel = $roleLabels[$canonicalCurrent] ?? $currentRole;
        $requiredLabel = $roleLabels[$canonicalRequired] ?? $requiredRole;

        $nextParam = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        $loginUrl = app_href('/login.php?next=' . $nextParam . '&role_required=' . urlencode($canonicalRequired));
        $logoutUrl = app_href('/logout.php');
        $dashboardUrl = app_href(AuthPortalRouter::getDashboardUrl($canonicalCurrent));

        // If API / JSON request, return JSON 403 response
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (str_contains($accept, 'application/json') || str_starts_with($uri, '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => [
                    'code' => 'FORBIDDEN_ROLE_MISMATCH',
                    'message' => "Tài khoản hiện tại ({$currentLabel}) không có quyền truy cập vào phân hệ này ({$requiredLabel}).",
                    'currentRole' => $canonicalCurrent,
                    'requiredRole' => $canonicalRequired,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Quyền truy cập bị từ chối | TalentHub</title>
    <link rel="stylesheet" href="<?= app_href('/assets/css/home.css'); ?>">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 1.5rem;
            color: #1e293b;
        }
        .error-card {
            background: #ffffff;
            max-width: 500px;
            width: 100%;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .error-icon {
            width: 64px;
            height: 64px;
            background: #fee2e2;
            color: #ef4444;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }
        .error-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.5rem 0;
        }
        .error-desc {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.6;
            margin: 0 0 1.5rem 0;
        }
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 0.375rem;
            font-weight: 600;
            font-size: 0.8125rem;
        }
        .role-badge--current {
            background: #e0f2fe;
            color: #0284c7;
        }
        .role-badge--required {
            background: #fef3c7;
            color: #d97706;
        }
        .error-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.875rem 1rem;
            margin-bottom: 1.75rem;
            text-align: left;
            font-size: 0.875rem;
        }
        .error-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.25rem 0;
        }
        .error-actions {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 0.5rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-primary {
            background: #2563eb;
            color: #ffffff;
            border: 1px solid transparent;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .btn-danger {
            background: transparent;
            color: #ef4444;
            border: 1px solid transparent;
        }
        .btn-danger:hover {
            background: #fee2e2;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>
        <h1 class="error-title">Quyền truy cập bị từ chối (403)</h1>
        <p class="error-desc">Tài khoản hiện tại của bạn không có quyền truy cập vào phân hệ này.</p>
        
        <div class="error-info">
            <div class="error-info-row">
                <span style="color: #64748b;">Tài khoản đăng nhập:</span>
                <span class="role-badge role-badge--current"><?= htmlspecialchars($currentLabel); ?></span>
            </div>
            <div class="error-info-row" style="margin-top: 0.375rem;">
                <span style="color: #64748b;">Phân hệ yêu cầu:</span>
                <span class="role-badge role-badge--required"><?= htmlspecialchars($requiredLabel); ?></span>
            </div>
        </div>

        <div class="error-actions">
            <a href="<?= htmlspecialchars($loginUrl); ?>" class="btn btn-primary">
                Đăng nhập tài khoản phù hợp
            </a>
            <a href="<?= htmlspecialchars($dashboardUrl); ?>" class="btn btn-secondary">
                Về trang chủ của tôi (<?= htmlspecialchars($currentLabel); ?>)
            </a>
            <a href="<?= htmlspecialchars($logoutUrl); ?>" class="btn btn-danger">
                Đăng xuất
            </a>
        </div>
    </div>
</body>
</html>
        <?php
        exit;
    }

    private static function redirect(string $path): never
    {
        header('Location: ' . app_href($path));
        exit;
    }
}
