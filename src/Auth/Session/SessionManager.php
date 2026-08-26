<?php
declare(strict_types=1);
namespace TalentHub\Auth\Session;

use RuntimeException;
use TalentHub\Http\ApiException;

final class SessionManager
{
    public const SESSION_STUDENT = 'TALENTHUB_STUDENT_SESS';
    public const SESSION_ENTERPRISE = 'TALENTHUB_ENTERPRISE_SESS';
    public const SESSION_SCHOOL = 'TALENTHUB_SCHOOL_SESS';
    public const SESSION_TEACHER = 'TALENTHUB_TEACHER_SESS';
    public const SESSION_ADMIN = 'TALENTHUB_ADMIN_SESS';
    public const SESSION_DEFAULT = 'TALENTHUBSESSID';

    /** @param array{name:string,lifetime:int,secure:bool,sameSite:string,savePath:string,path?:string,domain?:string} $config */
    public function __construct(private readonly array $config) {}

    public static function sessionNameForRole(?string $role): string
    {
        if ($role === null || trim($role) === '') {
            return self::SESSION_DEFAULT;
        }
        $role = \TalentHub\Rbac\RoleCodes::canonical($role);
        return match ($role) {
            \TalentHub\Rbac\RoleCodes::STUDENT => self::SESSION_STUDENT,
            \TalentHub\Rbac\RoleCodes::ENTERPRISE => self::SESSION_ENTERPRISE,
            \TalentHub\Rbac\RoleCodes::SCHOOL => self::SESSION_SCHOOL,
            \TalentHub\Rbac\RoleCodes::TEACHER => self::SESSION_TEACHER,
            \TalentHub\Rbac\RoleCodes::PLATFORM_ADMIN => self::SESSION_ADMIN,
            default => self::SESSION_DEFAULT,
        };
    }

    public function name(): string
    {
        return (string) ($this->config['name'] ?? self::SESSION_DEFAULT);
    }

    public static function writeUserToRoleSession(array $user, array $baseConfig): void
    {
        $roleSessionName = self::sessionNameForRole($user['role'] ?? null);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $mgr = new self(array_merge($baseConfig, ['name' => $roleSessionName]));
        $mgr->start();
        $mgr->login($user);
        session_write_close();
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $this->configureStorage();
        $sessionName = $this->config['name'] ?? self::SESSION_DEFAULT;

        if (!isset($_COOKIE[$sessionName])) {
            $legacyAliases = [
                self::SESSION_STUDENT => ['TALENTHUB_STUDENT_SESSION', 'TALENTHUBSESSID', 'PHPSESSID'],
                self::SESSION_ENTERPRISE => ['TALENTHUB_ENTERPRISE_SESSION', 'TALENTHUBSESSID', 'PHPSESSID'],
                self::SESSION_SCHOOL => ['TALENTHUB_SCHOOL_SESSION', 'TALENTHUBSESSID', 'PHPSESSID'],
                self::SESSION_TEACHER => ['TALENTHUB_TEACHER_SESSION', 'TALENTHUBSESSID', 'PHPSESSID'],
                self::SESSION_ADMIN => ['TALENTHUB_ADMIN_SESSION', 'TALENTHUBSESSID', 'PHPSESSID'],
            ];
            foreach ($legacyAliases[$sessionName] ?? [] as $alias) {
                if (isset($_COOKIE[$alias]) && is_string($_COOKIE[$alias]) && $_COOKIE[$alias] !== '') {
                    $_COOKIE[$sessionName] = $_COOKIE[$alias];
                    break;
                }
            }
        }
        session_name($sessionName);

        $path = '/';
        $secure = (bool) ($this->config['secure'] ?? false);
        $sameSite = $this->config['sameSite'] ?? 'Lax';
        $domain = $this->config['domain'] ?? '';

        $lifetime = (int) ($this->config['lifetime'] ?? (86400 * 7));

        session_set_cookie_params([
            'lifetime' => $lifetime,
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
        if (isset($_SESSION['lastSeenAt']) && time() - (int)$_SESSION['lastSeenAt'] > $lifetime) {
            $this->destroy();
            $this->open();
        }
        $_SESSION['lastSeenAt'] = time();
        if (!isset($_SESSION['csrfToken']) && !isset($_SESSION['csrf_token'])) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['csrfToken'] = $token;
            $_SESSION['csrf_token'] = $token;
        } else {
            $token = (string) ($_SESSION['csrfToken'] ?? $_SESSION['csrf_token'] ?? '');
            if ($token !== '') {
                $_SESSION['csrfToken'] = $token;
                $_SESSION['csrf_token'] = $token;
            }
        }
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
        $_SESSION['user_id'] = (string) $user['id'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['user'] = [
            'id' => (string) $user['id'],
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) $user['role'],
            'name' => (string) ($user['fullName'] ?? ($user['name'] ?? '')),
            'fullName' => (string) ($user['fullName'] ?? ($user['name'] ?? '')),
            'status' => (string) ($user['status'] ?? 'active'),
        ];
        if (isset($user['enterpriseId'])) {
            $_SESSION['enterprise_id'] = $user['enterpriseId'];
            $_SESSION['enterpriseId'] = $user['enterpriseId'];
            $_SESSION['user']['enterpriseId'] = $user['enterpriseId'];
        }
        if (isset($user['studentId'])) {
            $_SESSION['student_id'] = $user['studentId'];
            $_SESSION['studentId'] = $user['studentId'];
            $_SESSION['user']['studentId'] = $user['studentId'];
        }
        if (isset($user['schoolId'])) {
            $_SESSION['school_id'] = $user['schoolId'];
            $_SESSION['schoolId'] = $user['schoolId'];
            $_SESSION['user']['schoolId'] = $user['schoolId'];
        }
        if (isset($user['teacherId'])) {
            $_SESSION['teacher_id'] = $user['teacherId'];
            $_SESSION['teacherId'] = $user['teacherId'];
            $_SESSION['user']['teacherId'] = $user['teacherId'];
        }
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrfToken'] = $token;
        $_SESSION['csrf_token'] = $token;
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
                'name' => (string) ($_SESSION['fullName'] ?? $_SESSION['name'] ?? ''),
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
        if (!isset($_SESSION['csrfToken']) && !isset($_SESSION['csrf_token'])) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['csrfToken'] = $token;
            $_SESSION['csrf_token'] = $token;
        }
        $token = (string) ($_SESSION['csrfToken'] ?? $_SESSION['csrf_token'] ?? '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION['csrfToken'] = $token;
            $_SESSION['csrf_token'] = $token;
        }
        $_SESSION['csrfToken'] = $token;
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public function assertCsrf(?string $token): void
    {
        $sessionToken = $this->csrfToken();
        $altToken = (string) ($_SESSION['csrf_token'] ?? $_SESSION['csrfToken'] ?? $sessionToken);
        if ($token === null || $token === '' || (!hash_equals($sessionToken, $token) && !hash_equals($altToken, $token))) {
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
