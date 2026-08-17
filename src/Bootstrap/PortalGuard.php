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
        $session = new SessionManager(require $root . '/config/session.php');
        $session->start();
        $cached = $session->user();
        if ($cached === null) {
            self::redirect('/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? $fallbackPath));
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

        if (!RoleCodes::matches($user['role'], $role)) {
            self::redirect('/role-selection.php?error=forbidden');
        }
        return $user;
    }

    private static function redirect(string $path): never
    {
        header('Location: ' . app_href($path));
        exit;
    }
}
