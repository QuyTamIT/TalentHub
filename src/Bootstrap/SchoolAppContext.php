<?php
declare(strict_types=1);
namespace TalentHub\Bootstrap;

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolDashboardService;

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

    public function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $this->connection = new Connection($config);
        $this->session = new SessionManager(require dirname(__DIR__, 2) . '/config/session.php');
        $this->session->start();
        $pdo = $this->connection->connect();
        $this->service = new SchoolDashboardService(new SchoolRepository($pdo), $pdo);
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
        $user = $this->session->user();
        if ($user === null) {
            $this->redirectToLogin();
        }
        if (($user['role'] ?? null) !== 'school') {
            $this->redirectToRoleSelection();
        }

        try {
            $dashboard = $this->service->dashboard($user['id']);
        } catch (ApiException $exception) {
            if ($exception->statusCode === 404) {
                $this->redirectToRoleSelection('?error=school_missing');
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
        $target = '/role-selection.php' . $query;
        header('Location: ' . $target);
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
        $base = '/login.php';
        return $base . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/app/school/');
    }
}