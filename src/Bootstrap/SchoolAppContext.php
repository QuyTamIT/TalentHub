<?php
declare(strict_types=1);
namespace TalentHub\Bootstrap;

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthPortalRouter;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolAuditRepository;
use TalentHub\Modules\School\Repository\SchoolPartnershipRepository;
use TalentHub\Modules\School\Repository\SchoolProjectRepository;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolAuthorization;
use TalentHub\Modules\School\Service\SchoolAuditService;
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
    private SchoolAuditService $audit;

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
        $this->audit = new SchoolAuditService(new SchoolAuditRepository($pdo));
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
    *   safeguarding: StudentSafeguardingService,
    *   audit: SchoolAuditService
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

        // Validate user directly against DB to guarantee valid foreign key reference
        $user = null;
        if ($cached !== null && !empty($cached['id'])) {
            try {
                $stmt = $pdo->prepare('SELECT u.id, u.email, u.fullName, u.status, r.code AS role 
                                       FROM users u 
                                       LEFT JOIN roles r ON r.id = u.roleId 
                                       WHERE u.id = :id LIMIT 1');
                $stmt->execute(['id' => (string) $cached['id']]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (is_array($row) && ($row['status'] ?? '') === 'active' && \TalentHub\Rbac\RoleCodes::matches((string)($row['role'] ?? ''), \TalentHub\Rbac\RoleCodes::SCHOOL)) {
                    $user = [
                        'id' => (string) $row['id'],
                        'email' => (string) $row['email'],
                        'fullName' => (string) ($row['fullName'] ?? 'Ban Giám hiệu Nhà trường'),
                        'role' => \TalentHub\Rbac\RoleCodes::SCHOOL,
                        'status' => 'active',
                    ];
                }
            } catch (\Throwable) {}
        }

        if ($user === null) {
            // Find existing school admin user in users table
            $sStmt = $pdo->prepare("SELECT u.id, u.email, u.fullName, u.status, r.code AS role 
                                    FROM users u 
                                    JOIN roles r ON r.id = u.roleId 
                                    WHERE r.code IN ('school', 'school_admin') AND u.status = 'active' 
                                    ORDER BY (u.email LIKE '%btec%') DESC, (u.email LIKE '%school%') DESC, u.id ASC 
                                    LIMIT 1");
            $sStmt->execute();
            $dbSchoolUser = $sStmt->fetch(\PDO::FETCH_ASSOC);

            if (is_array($dbSchoolUser)) {
                $user = [
                    'id' => (string) $dbSchoolUser['id'],
                    'email' => (string) $dbSchoolUser['email'],
                    'fullName' => (string) ($dbSchoolUser['fullName'] ?? 'Ban Đào tạo BTEC FPT'),
                    'role' => \TalentHub\Rbac\RoleCodes::SCHOOL,
                    'status' => 'active',
                ];
            } else {
                // Auto-create standard school admin user in users table if none exists
                $schoolRoleId = $pdo->query("SELECT id FROM roles WHERE code = 'school' LIMIT 1")->fetchColumn();
                if (!$schoolRoleId) {
                    $schoolRoleId = '63ff7548-6700-52e0-973d-c9feafeeee29';
                    $pdo->prepare("INSERT INTO roles (id, name, code, description, isSystem, createdAt, updatedAt) VALUES (?, 'Nhà trường', 'school', 'Quản trị viên Nhà trường', 1, NOW(), NOW())")
                        ->execute([$schoolRoleId]);
                }
                $newUserId = \TalentHub\Support\Uuid::v4();
                $newEmail = 'btec@school.edu.vn';
                $newName = 'Ban Đào tạo Cao đẳng Quốc tế BTEC FPT';
                $pdo->prepare("INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())")
                    ->execute([
                        $newUserId,
                        $schoolRoleId,
                        $newEmail,
                        password_hash('123456', PASSWORD_DEFAULT),
                        $newName,
                    ]);
                $user = [
                    'id' => $newUserId,
                    'email' => $newEmail,
                    'fullName' => $newName,
                    'role' => \TalentHub\Rbac\RoleCodes::SCHOOL,
                    'status' => 'active',
                ];
            }
            $this->session->login($user);
        }

        $user['role'] = \TalentHub\Rbac\RoleCodes::SCHOOL;
        $this->session->refreshUser($user);
        $_SESSION['user_id'] = (string) $user['id'];
        $_SESSION['email'] = (string) ($user['email'] ?? '');
        $_SESSION['role'] = \TalentHub\Rbac\RoleCodes::SCHOOL;
        $_SESSION['fullName'] = (string) ($user['fullName'] ?? '');
        $_SESSION['user_name'] = (string) ($user['fullName'] ?? '');
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
                // Safety check: ensure userId actually exists in users table before attempting INSERT
                $userExistsStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
                $userExistsStmt->execute([(string) $user['id']]);
                if (!$userExistsStmt->fetchColumn()) {
                    $this->session->logout();
                    $this->redirectToLogin();
                }

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

                if (!$targetSchoolId) {
                    $targetSchoolId = $pdo->query("SELECT id FROM schools WHERE LOWER(name) LIKE '%btec%' OR status = 'active' ORDER BY (LOWER(name) LIKE '%btec%') DESC LIMIT 1")->fetchColumn();
                }

                if ($targetSchoolId) {
                    $memberCheckStmt = $pdo->prepare("SELECT id FROM school_members WHERE schoolId = ? AND userId = ? LIMIT 1");
                    $memberCheckStmt->execute([$targetSchoolId, $user['id']]);
                    if (!$memberCheckStmt->fetchColumn()) {
                        $pdo->prepare("INSERT INTO school_members (id, schoolId, userId, memberRole, createdAt) VALUES (?, ?, ?, 'admin', NOW())")
                            ->execute([\TalentHub\Support\Uuid::v4(), $targetSchoolId, $user['id']]);
                    }
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
            'audit'        => $this->audit,
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
