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
        $this->session = new SessionManager(require $root . '/config/session.php');
        $this->session->start();
        $this->auth = new AuthService(new AuthRepository($this->pdo));
        $this->permissions = new PermissionService($this->pdo);
        $this->students = new StudentProfileService(new StudentRepository($this->pdo));
    }

    /** @return array{user:array<string,mixed>,student:array<string,mixed>,dashboard:array<string,mixed>,onboarding:array<string,mixed>,csrfToken:string,pdo:\PDO} */
    public function boot(): array
    {
        $cached = $this->session->user();
        if ($cached === null) {
            $this->redirectToLogin();
        }
        if (($cached['role'] ?? null) !== 'student') {
            header('Location: ' . AuthPortalRouter::destination((string) ($cached['role'] ?? '')));
            exit;
        }

        try {
            $user = $this->auth->current((string) $cached['id']);
        } catch (ApiException $exception) {
            if ($exception->status === 401) {
                $this->session->destroy();
                $this->redirectToLogin();
            }
            throw $exception;
        }
        if (($user['role'] ?? null) !== 'student') {
            header('Location: ' . AuthPortalRouter::destination((string) ($user['role'] ?? '')));
            exit;
        }
        $this->session->refreshUser($user);
        $this->permissions->require($user['id'], 'student_profile.read_own');

        try {
            $student = $this->students->get($user['id']);
            $dashboard = $this->students->dashboard($user['id']);
        } catch (ApiException $exception) {
            if ($exception->status === 404) {
                header('Location: /role-selection.php?error=student_profile_missing');
                exit;
            }
            throw $exception;
        }

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

        return [
            'user' => $user,
            'student' => $student,
            'dashboard' => $dashboard,
            'onboarding' => $onboarding,
            'csrfToken' => $this->session->csrfToken(),
            'pdo' => $this->pdo,
        ];
    }

    private function redirectToLogin(): never
    {
        $next = $_SERVER['REQUEST_URI'] ?? '/app/learner/index.php';
        header('Location: /login.php?next=' . urlencode($next));
        exit;
    }
}
