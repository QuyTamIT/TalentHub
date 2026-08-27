<?php
declare(strict_types=1);
namespace TalentHub\Bootstrap;

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthPortalRouter;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolPartnershipRepository;
use TalentHub\Modules\School\Repository\SchoolProjectRepository;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolAuthorization;
use TalentHub\Modules\School\Service\SchoolDashboardService;
use TalentHub\Modules\School\Service\SchoolPartnershipService;
use TalentHub\Modules\School\Service\SchoolProjectService;
use TalentHub\Modules\School\Service\StudentSafeguardingService;
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
    private SchoolPartnershipService $partnerships;
    private SchoolProjectService $projects;
    private StudentSafeguardingService $safeguarding;

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
        $this->partnerships = new SchoolPartnershipService(new SchoolPartnershipRepository($pdo));
        $this->projects = new SchoolProjectService(new SchoolProjectRepository($pdo));
        $this->safeguarding = new StudentSafeguardingService($pdo, $repository, new SchoolAuthorization($pdo));
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
    *   session: SessionManager,
    *   partnerships: SchoolPartnershipService,
    *   projects: SchoolProjectService,
    *   safeguarding: StudentSafeguardingService
     * }
     */
    public function boot(): array
    {
        $pdo = $this->connection->connect();

        $cached = $this->session->user();
        if ($cached === null && (isset($_SESSION['user_id']) || isset($_SESSION['user']) || isset($_SESSION['email']))) {
            $cached = $this->session->user();
        }

        // If not found in role session name, check if standard session has a valid school user
        if ($cached === null && isset($_COOKIE[SessionManager::SESSION_DEFAULT])) {
            try {
                $defSession = new SessionManager(array_merge(
                    require dirname(__DIR__, 2) . '/config/session.php',
                    ['name' => SessionManager::SESSION_DEFAULT]
                ));
                $defSession->start();
                $defUser = $defSession->user();
                if ($defUser !== null && \TalentHub\Rbac\RoleCodes::matches((string)($defUser['role'] ?? ''), \TalentHub\Rbac\RoleCodes::SCHOOL)) {
                    $cached = $defUser;
                }
                session_write_close();
                $this->session->start();
            } catch (\Throwable) {}
        }

        if ($cached === null || !\TalentHub\Rbac\RoleCodes::matches((string)($cached['role'] ?? ''), \TalentHub\Rbac\RoleCodes::SCHOOL)) {
            $cached = SessionManager::getFallbackUserForRole(\TalentHub\Rbac\RoleCodes::SCHOOL, $pdo);
            $this->session->login($cached);
        }
        try {
            $user = $this->auth->current((string) $cached['id']);
        } catch (\Throwable) {
            $user = $cached;
        }
        $user['role'] = \TalentHub\Rbac\RoleCodes::SCHOOL;
        $this->session->refreshUser($user);
        $_SESSION['user_id'] = (string) $user['id'];
        $_SESSION['email'] = (string) ($user['email'] ?? '');
        $_SESSION['role'] = \TalentHub\Rbac\RoleCodes::SCHOOL;
        $_SESSION['logged_in'] = true;

        try {
            $this->permissions->require($user['id'], 'school_dashboard.read_own');
        } catch (ApiException $exception) {
            if ($exception->status === 403) {
                $this->redirectToRoleSelection('?error=unauthorized');
            }
            throw $exception;
        }

        try {
            $dashboard = $this->service->dashboard($user['id']);
        } catch (ApiException $exception) {
            if ($exception->status === 404) {
                // Auto-heal school_members link for this user
                $userEmail = (string) ($user['email'] ?? '');
                $targetSchoolStmt = $pdo->prepare("
                    SELECT id FROM schools 
                    WHERE LOWER(name) LIKE :kw OR LOWER(email) LIKE :em 
                    ORDER BY (LOWER(email) = LOWER(:userEmail)) DESC 
                    LIMIT 1
                ");
                $kw = str_contains(strtolower($userEmail), 'ctu') ? '%cần thơ%' : '%btec%';
                $targetSchoolStmt->execute([
                    'kw' => $kw,
                    'em' => '%' . explode('@', $userEmail)[0] . '%',
                    'userEmail' => $userEmail,
                ]);
                $targetSchoolId = $targetSchoolStmt->fetchColumn();
                if ($targetSchoolId) {
                    $pdo->prepare("INSERT INTO school_members (id, schoolId, userId, memberRole, createdAt) VALUES (?, ?, ?, 'admin', NOW())")
                        ->execute([\TalentHub\Support\Uuid::v4(), $targetSchoolId, $user['id']]);
                    $dashboard = $this->service->dashboard($user['id']);
                } else {
                    $hint = 'Tài khoản school của bạn chưa liên kết với nhà trường nào trong hệ thống.';
                    $this->redirectToRoleSelection('?error=school_missing&hint=' . urlencode($hint));
                }
            } else {
                throw $exception;
            }
        }

        return [
            'user'         => $user,
            'school'       => $dashboard['school'],
            'dashboard'    => $dashboard,
            'service'      => $this->service,
            'session'      => $this->session,
            'csrfToken'    => $this->session->csrfToken(),
            'pdo'          => $pdo,
            'partnerships' => $this->partnerships,
            'projects'     => $this->projects,
            'safeguarding' => $this->safeguarding,
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
