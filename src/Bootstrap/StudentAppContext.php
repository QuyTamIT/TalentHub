<?php
declare(strict_types=1);

namespace TalentHub\Bootstrap;

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthPortalRouter;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Repository\StudentRepository;
use TalentHub\Modules\Student\Service\StudentProfileService;
use TalentHub\Rbac\Service\PermissionService;

final class StudentAppContext
{
    private SessionManager $session;
    private AuthService $auth;
    private PermissionService $permissions;
    private StudentProfileService $students;

    public function __construct()
    {
        $root = dirname(__DIR__, 2);
        $pdo = (new Connection(require $root . '/config/database.php'))->connect();
        $this->session = new SessionManager(require $root . '/config/session.php');
        $this->session->start();
        $this->auth = new AuthService(new AuthRepository($pdo));
        $this->permissions = new PermissionService($pdo);
        $this->students = new StudentProfileService(new StudentRepository($pdo));
    }

    /** @return array{user:array<string,mixed>,student:array<string,mixed>,dashboard:array<string,mixed>,csrfToken:string} */
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

        return [
            'user' => $user,
            'student' => $student,
            'dashboard' => $dashboard,
            'csrfToken' => $this->session->csrfToken(),
        ];
    }

    private function redirectToLogin(): never
    {
        $next = $_SERVER['REQUEST_URI'] ?? '/app/learner/index.php';
        header('Location: /login.php?next=' . urlencode($next));
        exit;
    }
}
