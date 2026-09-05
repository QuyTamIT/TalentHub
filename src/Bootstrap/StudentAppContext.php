<?php
declare(strict_types=1);

namespace TalentHub\Bootstrap;

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthPortalRouter;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Repository\LearnerOnboardingRepository;
use TalentHub\Modules\Student\Repository\StudentRepository;
use TalentHub\Modules\Student\Service\LearnerOnboardingGate;
use TalentHub\Modules\Student\Service\LearnerOnboardingService;
use TalentHub\Modules\Student\Service\StudentProfileService;
use TalentHub\Rbac\Service\PermissionService;
use TalentHub\Support\Id\RequestId;

final class StudentAppContext
{
    private \PDO $pdo;
    private SessionManager $session;
    private AuthService $auth;
    private PermissionService $permissions;
    private StudentProfileService $students;

    public function __construct()
    {
        $root = dirname(__DIR__, 2);
        $this->pdo = (new Connection(require $root . '/config/database.php'))->connect();
        $sessionConfig = require $root . '/config/session.php';
        $sessionConfig['name'] = SessionManager::SESSION_STUDENT;
        $this->session = new SessionManager($sessionConfig);
        $this->session->start();
        $this->auth = new AuthService(new AuthRepository($this->pdo));
        $this->permissions = new PermissionService($this->pdo);
        $this->students = new StudentProfileService(new StudentRepository($this->pdo));
    }

    /** @return array{user:array<string,mixed>,student:array<string,mixed>,dashboard:array<string,mixed>,onboarding:array<string,mixed>,csrfToken:string,pdo:\PDO} */
    public function boot(): array
    {
        $cached = $this->session->user();
        if ($cached === null && (isset($_SESSION['user_id']) || isset($_SESSION['user']))) {
            $cached = $this->session->user();
        }
        $appEnv = strtolower((string) (\TalentHub\Config\Environment::optional('APP_ENV') ?: (getenv('APP_ENV') ?: 'production')));
        $isLocal = in_array($appEnv, ['local', 'dev', 'development', 'test'], true);

        if ($cached === null || !\TalentHub\Rbac\RoleCodes::matches((string)($cached['role'] ?? ''), \TalentHub\Rbac\RoleCodes::STUDENT) || ($isLocal && str_contains((string)($cached['email'] ?? ''), '@test.'))) {
            if ($isLocal) {
                $cached = $this->localFallbackStudent();
                $this->session->login($cached);
            } else {
                throw new ApiException(401, 'AUTHENTICATION_REQUIRED', 'Vui lòng đăng nhập tài khoản học viên.');
            }
        }

        try {
            $user = $this->auth->current((string) $cached['id']);
        } catch (\Throwable) {
            if ($isLocal) {
                $cached = $this->localFallbackStudent();
                $this->session->login($cached);
                $user = $this->auth->current((string) $cached['id']);
            } else {
                $user = $cached;
            }
        }
        $user['role'] = \TalentHub\Rbac\RoleCodes::STUDENT;
        $this->session->refreshUser($user);
        $_SESSION['user_id'] = (string) $user['id'];
        $_SESSION['role'] = \TalentHub\Rbac\RoleCodes::STUDENT;
        $_SESSION['logged_in'] = true;

        try {
            $this->permissions->require($user['id'], 'student_profile.read_own');
        } catch (ApiException $exception) {
            if ($isLocal && $exception->status === 403) {
                $cached = $this->localFallbackStudent();
                $this->session->login($cached);
                $user = $this->auth->current((string) $cached['id']);
                $user['role'] = \TalentHub\Rbac\RoleCodes::STUDENT;
                $this->session->refreshUser($user);
                $_SESSION['user_id'] = (string) $user['id'];
                $_SESSION['role'] = \TalentHub\Rbac\RoleCodes::STUDENT;
                $_SESSION['logged_in'] = true;
                $this->permissions->require($user['id'], 'student_profile.read_own');
            } else {
                throw $exception;
            }
        }

        try {
            $student = $this->students->get($user['id']);
            $dashboard = $this->students->dashboard($user['id']);
        } catch (ApiException $exception) {
            if ($exception->status === 404) {
                try {
                    $studentRepo = new StudentRepository($this->pdo);
                    $existing = $studentRepo->findByUserId($user['id']);
                    if ($existing === null) {
                        $now = date('Y-m-d H:i:s');
                        $stmt = $this->pdo->prepare('INSERT INTO student_profiles (id, userId, studyStatus, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?)');
                        $stmt->execute([\TalentHub\Support\Uuid::v4(), $user['id'], 'Đang học', $now, $now]);
                    }
                    $student = $this->students->get($user['id']);
                    $dashboard = $this->students->dashboard($user['id']);
                } catch (\Throwable) {
                    $this->redirectToIncompleteStudent($user['id']);
                }
            } else {
                throw $exception;
            }
        }

        $onboarding = ['required' => false, 'status' => 'completed'];
        try {
            $onboardingService = new LearnerOnboardingService(new LearnerOnboardingRepository($this->pdo));
            $onboarding = $onboardingService->reconcile(
                (string) $student['id'],
                (string) $user['id'],
                RequestId::make(null),
                isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
            );
            $path = (string) (parse_url(
                (string) ($_SERVER['REQUEST_URI'] ?? '/app/learner/index.php'),
                PHP_URL_PATH,
            ) ?: '/app/learner/index.php');
            $destination = (new LearnerOnboardingGate())->pageDestination($onboarding, $path);
            if ($destination !== null) {
                header('Location: ' . app_href($destination));
                exit;
            }
        } catch (\Throwable $e) {
            error_log('Learner onboarding check notice: ' . $e->getMessage());
        }

        return [
            'user' => $user,
            'student' => $student,
            'dashboard' => $dashboard,
            'onboarding' => $onboarding,
            'csrfToken' => $this->session->csrfToken(),
            'pdo' => $this->pdo,
        ];
    }

    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    private function localFallbackStudent(): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT u.id, u.email, u.fullName
FROM users u
INNER JOIN roles r ON r.id = u.roleId
WHERE u.status = 'active' AND r.code IN ('student', 'learner')
ORDER BY CASE WHEN u.email = 'vo-duc-anh@student.btec.talenthub.local' THEN 0 WHEN u.email = 'student@test.talenthub.local' THEN 1 ELSE 2 END, u.id ASC
LIMIT 1
SQL);
        $statement->execute();
        $student = $statement->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($student)) {
            throw new ApiException(401, 'AUTHENTICATION_REQUIRED', 'Không có tài khoản học viên thử nghiệm đang hoạt động.');
        }

        return [
            'id' => (string) $student['id'],
            'email' => (string) $student['email'],
            'fullName' => (string) ($student['fullName'] ?? 'Học viên TalentHub'),
            'role' => \TalentHub\Rbac\RoleCodes::STUDENT,
            'status' => 'active',
        ];
    }

    private function redirectToLogin(): never
    {
        $next = $_SERVER['REQUEST_URI'] ?? '/app/learner/index.php';
        header('Location: ' . app_href('/login.php') . '?next=' . urlencode($next));
        exit;
    }

    private function redirectToLoginWithRoleRequired(string $requiredRole): never
    {
        $base = app_href('/login.php');
        $target = app_href($_SERVER['REQUEST_URI'] ?? '/app/learner/');
        $loginUrl = $base . '?next=' . urlencode($target) . '&role_required=' . urlencode($requiredRole);
        header('Location: ' . $loginUrl);
        exit;
    }

    private function redirectToIncompleteStudent(string $userId): never
    {
        $next = $_SERVER['REQUEST_URI'] ?? '/app/learner/index.php';
        $url = app_href('/role-selection.php')
            . '?error=student_profile_missing'
            . '&hint=' . urlencode('Tài khoản student của bạn chưa có hồ sơ học viên (student_profiles). Vui lòng chạy seed testing: php bin/seed.php --testing');
        header('Location: ' . $url);
        exit;
    }
}
