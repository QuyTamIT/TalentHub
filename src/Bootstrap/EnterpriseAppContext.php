<?php
declare(strict_types=1);
namespace TalentHub\Bootstrap;

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Business\Repository\BusinessRepository;
use TalentHub\Modules\Business\Service\BusinessProfileService;

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

    public function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $this->connection = new Connection($config);
        $this->session = new SessionManager(require dirname(__DIR__, 2) . '/config/session.php');
        $this->session->start();
        $pdo = $this->connection->connect();
        $repository = new BusinessRepository($pdo);
        $this->service = new BusinessProfileService($repository);
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
     * }
     */
    public function boot(): array
    {
        $user = $this->session->user();
        if ($user === null) {
            $this->redirectToLogin();
        }
        if (($user['role'] ?? null) !== 'business') {
            $this->redirectToRoleSelection();
        }

        try {
            $enterprise = $this->service->get($user['id']);
            $dashboard  = $this->service->dashboard($user['id']);
        } catch (ApiException $exception) {
            if ($exception->status === 404) {
                $this->redirectToRoleSelection('?error=enterprise_missing');
            }
            throw $exception;
        }

        return [
            'user'       => $user,
            'enterprise' => $enterprise,
            'dashboard'  => $dashboard,
            'service'    => $this->service,
            'session'    => $this->session,
            'csrfToken'  => $this->session->csrfToken(),
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
