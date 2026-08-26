<?php
declare(strict_types=1);
namespace TalentHub\Bootstrap;

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthPortalRouter;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolAuthorization;
use TalentHub\Modules\School\Service\SchoolDashboardService;
use TalentHub\Rbac\Service\PermissionService;

/**
 * Lightweight service container for the legacy PHP UI under /app/school.
 *
 * Boots a database connection and a session, then exposes the
 * SchoolDashboardService plus the resolved school payload for the
 * currently logged-in school admin user. Renders an HTTP redirect to
 * the login page when no active session exists.
 */
final class SchoolAppContext
{
    private Connection $connection;
    private SessionManager $session;
    private SchoolDashboardService $service;
    private AuthService $auth;
    private PermissionService $permissions;

    public function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $this->connection = new Connection($config);
        $sessionConfig = require dirname(__DIR__, 2) . '/config/session.php';
        $sessionConfig['name'] = SessionManager::SESSION_SCHOOL;
        $this->session = new SessionManager($sessionConfig);
        $this->session->start();
        $pdo = $this->connection->connect();
        $repository = new SchoolRepository($pdo);
        $this->service = new SchoolDashboardService(
            $repository,
            $pdo,
            new SchoolAuthorization($pdo)
        );
        $this->auth = new AuthService(new AuthRepository($pdo));
        $this->permissions = new PermissionService($pdo);
    }

    /**
     * Boot the context, ensure the visitor is an authenticated school admin,
     * and return the resolved dashboard payload.
     *
     * @return array{
     *   user: array{id:string,email:string,fullName:string,role:string,status:string},
     *   school: array<string,mixed>,
     *   dashboard: array<string,mixed>,
     *   service: SchoolDashboardService,
     *   session: SessionManager
     * }
     */
    public function boot(): array
    {
        $cached = $this->session->user();
        if ($cached === null && (isset($_SESSION['user_id']) || isset($_SESSION['user']))) {
            $cached = $this->session->user();
        }
        if ($cached === null) {
            $this->redirectToLogin();
        }
        $currentRole = (string) ($cached['role'] ?? $_SESSION['role'] ?? $_SESSION['user']['role'] ?? '');
        if (!\TalentHub\Rbac\RoleCodes::matches($currentRole, \TalentHub\Rbac\RoleCodes::SCHOOL)) {
            PortalGuard::renderRoleMismatch($currentRole, \TalentHub\Rbac\RoleCodes::SCHOOL);
        }
        try {
            $user = $this->auth->current((string) $cached['id']);
            $this->session->refreshUser($user);
        } catch (ApiException $exception) {
            if ($exception->status === 401) {
                $this->session->destroy();
                $this->redirectToLogin();
            }
            throw $exception;
        }
        if (!\TalentHub\Rbac\RoleCodes::matches((string) ($user['role'] ?? ''), \TalentHub\Rbac\RoleCodes::SCHOOL)) {
            PortalGuard::renderRoleMismatch((string) ($user['role'] ?? ''), \TalentHub\Rbac\RoleCodes::SCHOOL);
        }
        $this->permissions->require($user['id'], 'school_dashboard.read_own');

        try {
            $dashboard = $this->service->dashboard($user['id']);
        } catch (ApiException $exception) {
            if ($exception->status === 404) {
                $hint = 'Tài khoản school của bạn chưa liên kết với nhà trường nào trong hệ thống. Vui lòng chạy seed testing: php bin/seed.php --testing';
                $this->redirectToRoleSelection('?error=school_missing&hint=' . urlencode($hint));
            }
            throw $exception;
        }

        return [
            'user'      => $user,
            'school'    => $dashboard['school'],
            'dashboard' => $dashboard,
            'service'   => $this->service,
            'session'   => $this->session,
        ];
    }

    public function redirectToLogin(): never
    {
        $login = $this->resolveLoginUrl();
        header('Location: ' . $login);
        exit;
    }

    public function redirectToRoleSelection(string $query = ''): never
    {
        $target = app_href('/role-selection.php') . $query;
        header('Location: ' . $target);
        exit;
    }

    public function redirectToLoginWithRoleRequired(string $requiredRole): never
    {
        $base = app_href('/login.php');
        $target = app_href($_SERVER['REQUEST_URI'] ?? '/app/school/index.php');
        $loginUrl = $base . '?next=' . urlencode($target) . '&role_required=' . urlencode($requiredRole);
        header('Location: ' . $loginUrl);
        exit;
    }

    public function session(): SessionManager
    {
        return $this->session;
    }

    public function service(): SchoolDashboardService
    {
        return $this->service;
    }

    private function resolveLoginUrl(): string
    {
        $base = app_href('/login.php');
        return $base . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/app/school/index.php') . '&role_required=school';
    }
}
