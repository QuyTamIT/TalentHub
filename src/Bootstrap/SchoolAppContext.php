<?php
declare(strict_types=1);
namespace TalentHub\Bootstrap;

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthPortalRouter;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Database\Connection;
use TalentHub\Config\Environment;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolAuditRepository;
use TalentHub\Modules\School\Repository\SchoolPartnershipRepository;
use TalentHub\Modules\School\Repository\SchoolActivityApprovalRepository;
use TalentHub\Modules\School\Repository\SchoolCredentialManagementRepository;
use TalentHub\Modules\School\Repository\SchoolProjectRepository;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolAuthorization;
use TalentHub\Modules\School\Service\SchoolActivityApprovalService;
use TalentHub\Modules\School\Service\SchoolCredentialManagementService;
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
    private SchoolActivityApprovalService $activityApprovals;
    private SchoolCredentialManagementService $credentials;
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
        $this->activityApprovals = new SchoolActivityApprovalService(new SchoolActivityApprovalRepository($pdo));
        $this->credentials = new SchoolCredentialManagementService(new SchoolCredentialManagementRepository($pdo));
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
            if (PHP_SAPI === 'cli') {
                $user = [
                    'id' => '00000000-0000-0000-0000-000000000000',
                    'email' => 'guest@school.talenthub.local',
                    'fullName' => 'Ban Giám hiệu Nhà trường',
                    'role' => \TalentHub\Rbac\RoleCodes::SCHOOL,
                    'status' => 'active',
                ];
            } else {
                if ($cached !== null) {
                    $this->redirectToLoginWithRoleRequired(\TalentHub\Rbac\RoleCodes::SCHOOL);
                }
                $this->redirectToLogin();
            }
        } else {
            $user['role'] = \TalentHub\Rbac\RoleCodes::SCHOOL;
            $this->session->refreshUser($user);
            $_SESSION['user_id'] = (string) $user['id'];
            $_SESSION['email'] = (string) ($user['email'] ?? '');
            $_SESSION['role'] = \TalentHub\Rbac\RoleCodes::SCHOOL;
            $_SESSION['fullName'] = (string) ($user['fullName'] ?? '');
            $_SESSION['user_name'] = (string) ($user['fullName'] ?? '');
            $_SESSION['logged_in'] = true;
        }

        if ($user['id'] !== '00000000-0000-0000-0000-000000000000') {
            try {
                $this->permissions->require($user['id'], 'school_dashboard.read_own');
            } catch (ApiException $exception) {
                if ($exception->status === 403) {
                    $this->redirectToRoleSelection('?error=unauthorized');
                }
                throw $exception;
            }
        }

        try {
            $dashboard = $this->service->dashboard($user['id']);
        } catch (ApiException $exception) {
            if ($exception->status === 404) {
                $emptySchool = [
                    'id'           => '',
                    'name'         => 'Chưa có thông tin trường',
                    'logoUrl'      => null,
                    'level'        => '—',
                    'academicYear' => '—',
                    'address'      => '',
                    'phone'        => '',
                    'email'        => '',
                    'website'      => '',
                    'status'       => 'inactive',
                    'studentCount' => 0,
                    'teacherCount' => 0,
                ];
                $dashboard = [
                    'school'         => $emptySchool,
                    'metrics'        => [
                        'activeStudents'                => 0,
                        'activeTeachers'                => 0,
                        'totalClasses'                  => 0,
                        'publishedActivities'           => 0,
                        'approvedRegistrations'         => 0,
                        'confirmedCheckins'             => 0,
                        'publishedAssessments'          => 0,
                        'verifiedSkills'                => 0,
                        'approvedEnterprisePartners'    => 0,
                        'activeInternshipPosts'         => 0,
                        'acceptedInternshipApplications'=> 0,
                        'activeProjects'                => 0,
                        'paidSponsorshipAmount'         => '0.00',
                        'totalStudents'                 => 0,
                        'totalTeachers'                 => 0,
                    ],
                    'kpis'           => [
                        [
                            'label'      => 'Học sinh đang hoạt động',
                            'value'      => '0',
                            'change'     => 'Trong 0 lớp',
                            'changeType' => 'neutral',
                            'icon'       => 'users',
                        ],
                        [
                            'label'      => 'Hoạt động đã xuất bản',
                            'value'      => '0',
                            'change'     => '0 đăng ký đã duyệt',
                            'changeType' => 'neutral',
                            'icon'       => 'calendar',
                        ],
                        [
                            'label'      => 'Thực tập đã tiếp nhận',
                            'value'      => '0',
                            'change'     => '0 đối tác đã duyệt',
                            'changeType' => 'neutral',
                            'icon'       => 'award',
                        ],
                        [
                            'label'      => 'Tài trợ đã thanh toán',
                            'value'      => '0 ₫',
                            'change'     => '0 dự án đang chạy',
                            'changeType' => 'neutral',
                            'icon'       => 'check-circle',
                        ],
                    ],
                    'topTalents'     => [],
                    'classes'        => [],
                    'recentActivity' => [],
                ];
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
            'activityApprovals' => $this->activityApprovals,
            'permissions' => $this->permissions,
            'credentials' => $this->credentials,
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
