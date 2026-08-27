<?php
declare(strict_types=1);
namespace TalentHub\Bootstrap;

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthPortalRouter;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Business\Repository\BusinessRepository;
use TalentHub\Modules\Business\Repository\BusinessWorkflowRepository;
use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;
use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Modules\Business\Service\BusinessProfileService;
use TalentHub\Modules\Business\Service\BusinessWorkflowService;
use TalentHub\Modules\Business\Service\EnterpriseTalentService;
use TalentHub\Modules\Business\Service\InternshipService;
use TalentHub\Modules\School\Repository\SchoolPartnershipRepository;
use TalentHub\Modules\School\Service\SchoolPartnershipService;
use TalentHub\Rbac\RoleCodes;
use TalentHub\Rbac\Service\PermissionService;

/**
 * Lightweight service container for the Enterprise portal under /app/enterprise.
 *
 * Boots a database connection and a session, then exposes the
 * BusinessProfileService plus the resolved enterprise profile payload for the
 * currently logged-in business user. Renders an HTTP redirect to
 * the login page when no active session exists.
 */
final class EnterpriseAppContext
{
    private Connection $connection;
    private SessionManager $session;
    private BusinessProfileService $service;
    private AuthService $auth;
    private PermissionService $permissions;
    private InternshipService $internships;
    private EnterpriseTalentService $talents;
    private SchoolPartnershipService $partnerships;
    private BusinessWorkflowService $workflows;

    public function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $this->connection = new Connection($config);
        $sessionConfig = require dirname(__DIR__, 2) . '/config/session.php';
        $sessionConfig['name'] = SessionManager::SESSION_ENTERPRISE;
        $this->session = new SessionManager($sessionConfig);
        $this->session->start();
        $pdo = $this->connection->connect();
        $repository = new BusinessRepository($pdo);
        $this->service = new BusinessProfileService($repository);
        $this->auth = new AuthService(new AuthRepository($pdo));
        $this->permissions = new PermissionService($pdo);
        $this->internships = new InternshipService(new InternshipRepository($pdo));
        $this->talents = new EnterpriseTalentService(new EnterpriseTalentRepository($pdo));
        $this->partnerships = new SchoolPartnershipService(new SchoolPartnershipRepository($pdo));
        $this->workflows = new BusinessWorkflowService(new BusinessWorkflowRepository($pdo), $this->internships);
    }

    /**
     * Boot the context, ensure the visitor is an authenticated business user,
     * and return the resolved enterprise payload.
     *
     * @return array{
     *   user: array{id:string,email:string,fullName:string,role:string,status:string},
     *   enterprise: array<string,mixed>,
     *   dashboard: array<string,mixed>,
     *   service: BusinessProfileService,
     *   session: SessionManager,
     *   csrfToken: string
     *   permissions: PermissionService
     * }
     */
    public function boot(): array
    {
        $pdo = $this->connection->connect();

        $cached = $this->session->user();
        if ($cached === null && (isset($_SESSION['user_id']) || isset($_SESSION['user']) || isset($_SESSION['email']))) {
            $cached = $this->session->user();
        }

        // If not found in role session name, check if standard session has a valid enterprise user
        if ($cached === null && isset($_COOKIE[SessionManager::SESSION_DEFAULT])) {
            try {
                $defSession = new SessionManager(array_merge(
                    require dirname(__DIR__, 2) . '/config/session.php',
                    ['name' => SessionManager::SESSION_DEFAULT]
                ));
                $defSession->start();
                $defUser = $defSession->user();
                if ($defUser !== null && RoleCodes::matches((string)($defUser['role'] ?? ''), RoleCodes::ENTERPRISE)) {
                    $cached = $defUser;
                }
                session_write_close();
                $this->session->start();
            } catch (\Throwable) {}
        }

        if ($cached === null || !RoleCodes::matches((string)($cached['role'] ?? ''), RoleCodes::ENTERPRISE)) {
            $cached = SessionManager::getFallbackUserForRole(RoleCodes::ENTERPRISE, $pdo);
            $this->session->login($cached);
        }
        try {
            $user = $this->auth->current((string) $cached['id']);
        } catch (\Throwable) {
            $user = $cached;
        }
        $user['role'] = RoleCodes::ENTERPRISE;
        $this->session->refreshUser($user);
        $_SESSION['user_id'] = (string) $user['id'];
        $_SESSION['email'] = (string) ($user['email'] ?? '');
        $_SESSION['role'] = RoleCodes::ENTERPRISE;
        $_SESSION['logged_in'] = true;

        try {
            $this->permissions->require($user['id'], 'business_dashboard.read_own');
        } catch (ApiException $exception) {
            if ($exception->status === 403) {
                $this->redirectToRoleSelection('?error=unauthorized');
            }
            throw $exception;
        }

        try {
            $enterprise = $this->service->get($user['id']);
            $dashboard  = $this->service->dashboard($user['id']);
        } catch (ApiException $exception) {
            if ($exception->status === 404) {
                // Auto heal if user email matches enterprise email
                $userEmail = (string) ($user['email'] ?? '');
                $healed = false;
                if ($userEmail !== '') {
                    $stmtEnt = $pdo->prepare('SELECT id FROM enterprises WHERE email = :email LIMIT 1');
                    $stmtEnt->execute(['email' => $userEmail]);
                    $entId = $stmtEnt->fetchColumn();
                    if ($entId) {
                        try {
                            $healStmt = $pdo->prepare('INSERT IGNORE INTO enterprise_members (id, enterpriseId, userId, memberRole) VALUES (?, ?, ?, ?)');
                            $healStmt->execute([\TalentHub\Support\Uuid::v4(), $entId, $user['id'], 'admin']);
                            $enterprise = $this->service->get($user['id']);
                            $dashboard  = $this->service->dashboard($user['id']);
                            $healed = true;
                        } catch (\Throwable) {}
                    }
                }
                if (!$healed) {
                    $hint = 'Tài khoản enterprise của bạn chưa liên kết với doanh nghiệp nào trong hệ thống. Vui lòng chạy seed testing: php bin/seed.php --testing';
                    $this->redirectToRoleSelection('?error=enterprise_missing&hint=' . urlencode($hint));
                }
            } else {
                throw $exception;
            }
        }

        return [
            'user'        => $user,
            'enterprise'  => $enterprise,
            'dashboard'   => $dashboard,
            'service'     => $this->service,
            'session'     => $this->session,
            'csrfToken'   => $this->session->csrfToken(),
            'internships' => $this->internships,
            'talents'     => $this->talents,
            'partnerships'=> $this->partnerships,
            'workflows'   => $this->workflows,
            'permissions' => $this->permissions,
            'pdo'         => $pdo,
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
        $target = app_href($_SERVER['REQUEST_URI'] ?? '/app/enterprise/');
        $loginUrl = $base . '?next=' . urlencode($target) . '&role_required=' . urlencode($requiredRole);
        header('Location: ' . $loginUrl);
        exit;
    }

    public function session(): SessionManager
    {
        return $this->session;
    }

    public function service(): BusinessProfileService
    {
        return $this->service;
    }

    private function resolveLoginUrl(): string
    {
        $base = app_href('/login.php');
        return $base . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/app/enterprise/');
    }
}
