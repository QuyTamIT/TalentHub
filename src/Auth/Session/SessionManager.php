<?php
declare(strict_types=1);
namespace TalentHub\Auth\Session;

use RuntimeException;
use TalentHub\Http\ApiException;

final class SessionManager
{
    /** @param array{name:string,lifetime:int,secure:bool,sameSite:string,savePath:string,path?:string,domain?:string} $config */
    public function __construct(private readonly array $config) {}

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $this->configureStorage();
        session_name($this->config['name']);
        
        $path = $this->config['path'] ?? '/';
        $secure = (bool) ($this->config['secure'] ?? false);
        $sameSite = $this->config['sameSite'] ?? 'Lax';
        $domain = $this->config['domain'] ?? '';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_path', $path);
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', $sameSite);

        $this->open();
        if (isset($_SESSION['lastSeenAt']) && time() - (int)$_SESSION['lastSeenAt'] > $this->config['lifetime']) {
            $this->destroy();
            $this->open();
        }
        $_SESSION['lastSeenAt'] = time();
    }

    private function open(): void
    {
        if (!@session_start()) {
            throw new RuntimeException('Unable to start the session.');
        }
    }

    private function configureStorage(): void
    {
        $savePath = trim($this->config['savePath']);
        if ($savePath === '') {
            throw new RuntimeException('Session save path must not be empty.');
        }
        if (headers_sent()) {
            throw new RuntimeException('Session must be started before response output.');
        }
        if (!is_dir($savePath) && !@mkdir($savePath, 0700, true) && !is_dir($savePath)) {
            throw new RuntimeException('Unable to create the session storage directory.');
        }
        if (!is_writable($savePath)) {
            throw new RuntimeException('Session storage directory is not writable.');
        }
        if (session_save_path($savePath) === false) {
            throw new RuntimeException('Unable to configure session storage.');
        }
    }

    /** @param array{id:string,email:string,fullName:string,role:string,status:string,enterpriseId?:string,studentId?:string,schoolId?:string,teacherId?:string} $user */
    public function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['user_id'] = $user['id'] ?? null;
        $_SESSION['role'] = $user['role'] ?? null;
        if (isset($user['enterpriseId'])) {
            $_SESSION['enterprise_id'] = $user['enterpriseId'];
            $_SESSION['enterpriseId'] = $user['enterpriseId'];
        }
        if (isset($user['studentId'])) {
            $_SESSION['student_id'] = $user['studentId'];
            $_SESSION['studentId'] = $user['studentId'];
        }
        if (isset($user['schoolId'])) {
            $_SESSION['school_id'] = $user['schoolId'];
            $_SESSION['schoolId'] = $user['schoolId'];
        }
        if (isset($user['teacherId'])) {
            $_SESSION['teacher_id'] = $user['teacherId'];
            $_SESSION['teacherId'] = $user['teacherId'];
        }
        $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        $_SESSION['lastSeenAt'] = time();
    }

    /** @return array{id:string,email:string,fullName:string,role:string,status:string,enterpriseId?:string,studentId?:string,schoolId?:string,teacherId?:string}|null */
    public function user(): ?array
    {
        $user = $_SESSION['user'] ?? null;
        if (is_array($user) && isset($user['id'])) {
            return $user;
        }
        if (isset($_SESSION['user_id'])) {
            return [
                'id' => (string) $_SESSION['user_id'],
                'email' => (string) ($_SESSION['email'] ?? ''),
                'fullName' => (string) ($_SESSION['fullName'] ?? $_SESSION['name'] ?? ''),
                'role' => (string) ($_SESSION['role'] ?? ''),
                'status' => (string) ($_SESSION['status'] ?? 'active'),
                'enterpriseId' => isset($_SESSION['enterprise_id']) ? (string) $_SESSION['enterprise_id'] : (isset($_SESSION['enterpriseId']) ? (string) $_SESSION['enterpriseId'] : null),
                'studentId' => isset($_SESSION['student_id']) ? (string) $_SESSION['student_id'] : (isset($_SESSION['studentId']) ? (string) $_SESSION['studentId'] : null),
                'schoolId' => isset($_SESSION['school_id']) ? (string) $_SESSION['school_id'] : (isset($_SESSION['schoolId']) ? (string) $_SESSION['schoolId'] : null),
                'teacherId' => isset($_SESSION['teacher_id']) ? (string) $_SESSION['teacher_id'] : (isset($_SESSION['teacherId']) ? (string) $_SESSION['teacherId'] : null),
            ];
        }
        return null;
    }

    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    public function requireUser(): array
    {
        return $this->user() ?? throw new ApiException(401, 'AUTHENTICATION_REQUIRED', 'Bạn cần đăng nhập.');
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION['csrfToken'])) {
            $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrfToken'];
    }

    public function assertCsrf(?string $token): void
    {
        if ($token === null || !hash_equals($this->csrfToken(), $token)) {
            throw new ApiException(403, 'CSRF_TOKEN_INVALID', 'CSRF token không hợp lệ.');
        }
    }

    public function assertLoginAllowed(): void
    {
        $window = (int) ($_SESSION['loginWindowAt'] ?? 0);
        if ($window === 0 || time() - $window >= 300) {
            $_SESSION['loginWindowAt'] = time();
            $_SESSION['loginAttempts'] = 0;
        }
        if ((int) ($_SESSION['loginAttempts'] ?? 0) >= 5) {
            throw new ApiException(429, 'RATE_LIMIT_EXCEEDED', 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau.');
        }
    }

    public function recordLoginFailure(): void
    {
        $_SESSION['loginAttempts'] = (int) ($_SESSION['loginAttempts'] ?? 0) + 1;
    }

    public function clearLoginFailures(): void
    {
        unset($_SESSION['loginAttempts'], $_SESSION['loginWindowAt']);
    }

    /** @param array{id:string,email:string,fullName:string,role:string,status:string} $user */
    public function refreshUser(array $user): void
    {
        $_SESSION['user'] = $user;
        $_SESSION['user_id'] = $user['id'] ?? null;
        $_SESSION['role'] = $user['role'] ?? null;
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
