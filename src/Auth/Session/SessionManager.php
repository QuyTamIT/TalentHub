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

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    }

    public static function writeUserToRoleSession(array $user, array $baseConfig): void
    {
        $currentSid = session_status() === PHP_SESSION_ACTIVE ? session_id() : '';
        $roleSessionName = self::sessionNameForRole($user['role'] ?? null);

        if ($currentSid !== '' && !headers_sent()) {
            $path = '/';
            $secure = self::isHttps() && (isset($baseConfig['secure']) ? (bool)$baseConfig['secure'] : true);
            $sameSite = $baseConfig['sameSite'] ?? 'Lax';
            $lifetime = (int) ($baseConfig['lifetime'] ?? (86400 * 7));
            $cookieOptions = [
                'expires' => time() + $lifetime,
                'path' => $path,
                'domain' => $baseConfig['domain'] ?? '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => $sameSite,
            ];

            $allNames = [
                self::SESSION_DEFAULT,
                $roleSessionName,
                self::SESSION_STUDENT,
                self::SESSION_ENTERPRISE,
                self::SESSION_SCHOOL,
                self::SESSION_TEACHER,
                self::SESSION_ADMIN,
            ];

            foreach (array_unique($allNames) as $sName) {
                setcookie($sName, $currentSid, $cookieOptions);
            }
        }
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $this->configureStorage();
        $sessionName = $this->config['name'] ?? self::SESSION_DEFAULT;

        if (!isset($_COOKIE[$sessionName])) {
            $allKnownCookies = [
                self::SESSION_DEFAULT,
                self::SESSION_ENTERPRISE,
                self::SESSION_STUDENT,
                self::SESSION_SCHOOL,
                self::SESSION_TEACHER,
                self::SESSION_ADMIN,
                'TALENTHUB_STUDENT_SESSION',
                'TALENTHUB_ENTERPRISE_SESSION',
                'TALENTHUB_SCHOOL_SESSION',
                'TALENTHUB_TEACHER_SESSION',
                'TALENTHUB_ADMIN_SESSION',
                'PHPSESSID',
            ];
            foreach ($allKnownCookies as $alias) {
                if (isset($_COOKIE[$alias]) && is_string($_COOKIE[$alias]) && $_COOKIE[$alias] !== '') {
                    $_COOKIE[$sessionName] = $_COOKIE[$alias];
                    if (!headers_sent()) {
                        @session_id($_COOKIE[$alias]);
                    }
                    break;
                }
            }
        }

        $path = '/';
        $secure = self::isHttps() && (isset($this->config['secure']) ? (bool)$this->config['secure'] : true);
        $sameSite = $this->config['sameSite'] ?? 'Lax';
        $domain = $this->config['domain'] ?? '';
        $lifetime = (int) ($this->config['lifetime'] ?? (86400 * 7));

        if (!headers_sent()) {
            session_name($sessionName);
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => $sameSite,
            ]);
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_only_cookies', '1');
            @ini_set('session.cookie_path', $path);
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.cookie_samesite', $sameSite);
        }

        $this->open();
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

    public static function getFallbackUserForRole(string $role, ?\PDO $pdo = null): array
    {
        $role = \TalentHub\Rbac\RoleCodes::canonical($role);
        $activeEmail = (string) ($_SESSION['user']['email'] ?? ($_SESSION['email'] ?? ''));
        $activeUserId = (string) ($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? ''));
        $activeName = (string) ($_SESSION['user']['fullName'] ?? ($_SESSION['user']['full_name'] ?? ($_SESSION['user_name'] ?? '')));

        if ($pdo instanceof \PDO) {
            try {
                // 1. If active user id is present in session, query exact DB record
                if ($activeUserId !== '') {
                    $s = $pdo->prepare('SELECT u.id, u.email, u.fullName, r.code AS role
                                        FROM users u
                                        LEFT JOIN roles r ON r.id = u.roleId
                                        WHERE u.id = :userId LIMIT 1');
                    $s->execute(['userId' => $activeUserId]);
                    $row = $s->fetch(\PDO::FETCH_ASSOC);
                    if ($row) {
                        return [
                            'id' => (string) $row['id'],
                            'email' => (string) $row['email'],
                            'fullName' => (string) ($row['fullName'] ?? ucfirst($role) . ' User'),
                            'role' => (string) ($row['role'] ?? $role),
                            'status' => 'active',
                        ];
                    }
                }

                // 2. If active email is present in session, query exact DB record
                if ($activeEmail !== '' && !str_contains($activeEmail, '@test.')) {
                    $s = $pdo->prepare('SELECT u.id, u.email, u.fullName, r.code AS role
                                        FROM users u
                                        LEFT JOIN roles r ON r.id = u.roleId
                                        WHERE u.email = :email LIMIT 1');
                    $s->execute(['email' => $activeEmail]);
                    $row = $s->fetch(\PDO::FETCH_ASSOC);
                    if ($row) {
                        return [
                            'id' => (string) $row['id'],
                            'email' => (string) $row['email'],
                            'fullName' => (string) ($row['fullName'] ?? ucfirst($role) . ' User'),
                            'role' => (string) ($row['role'] ?? $role),
                            'status' => 'active',
                        ];
                    }
                }

                // 3. Fallback when no session exists: use deterministic canonical account for the role
                $targetEmails = match ($role) {
                    \TalentHub\Rbac\RoleCodes::ENTERPRISE => ['fpt@talenthub.local', 'enterprise@talenthub.local'],
                    \TalentHub\Rbac\RoleCodes::STUDENT => ['student@talenthub.local', 'vuducanh@student.btec.edu.vn'],
                    \TalentHub\Rbac\RoleCodes::TEACHER => ['teacher@talenthub.local'],
                    \TalentHub\Rbac\RoleCodes::SCHOOL => ['btec@school.edu.vn', 'btec@talenthub.local', 'school@talenthub.local'],
                    \TalentHub\Rbac\RoleCodes::PLATFORM_ADMIN => ['admin@talenthub.local'],
                    default => [],
                };

                foreach ($targetEmails as $targetEmail) {
                    $s = $pdo->prepare('SELECT u.id, u.email, u.fullName, r.code AS role
                                        FROM users u
                                        LEFT JOIN roles r ON r.id = u.roleId
                                        WHERE u.email = :email LIMIT 1');
                    $s->execute(['email' => $targetEmail]);
                    $row = $s->fetch(\PDO::FETCH_ASSOC);
                    if ($row) {
                        return [
                            'id' => (string) $row['id'],
                            'email' => (string) $row['email'],
                            'fullName' => (string) ($row['fullName'] ?? ucfirst($role) . ' User'),
                            'role' => $role,
                            'status' => 'active',
                        ];
                    }
                }

                // 4. If no target email matched, find any active user with matching role from DB
                $sRole = $pdo->prepare('SELECT u.id, u.email, u.fullName, r.code AS role
                                        FROM users u
                                        JOIN roles r ON r.id = u.roleId
                                        WHERE (r.code = :role OR (r.code = "school_admin" AND :role = "school") OR (r.code = "business" AND :role = "enterprise")) AND u.status = "active"
                                        ORDER BY (u.email LIKE "%btec%") DESC, (u.email LIKE "%school%") DESC, u.id ASC
                                        LIMIT 1');
                $sRole->execute(['role' => $role]);
                $row = $sRole->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    return [
                        'id' => (string) $row['id'],
                        'email' => (string) $row['email'],
                        'fullName' => (string) ($row['fullName'] ?? ucfirst($role) . ' User'),
                        'role' => $role,
                        'status' => 'active',
                    ];
                }
            } catch (\Throwable) {}
        }

        $defaultEmails = [
            \TalentHub\Rbac\RoleCodes::STUDENT => 'student@talenthub.local',
            \TalentHub\Rbac\RoleCodes::TEACHER => 'teacher@talenthub.local',
            \TalentHub\Rbac\RoleCodes::SCHOOL => 'school@talenthub.local',
            \TalentHub\Rbac\RoleCodes::ENTERPRISE => 'fpt@talenthub.local',
            \TalentHub\Rbac\RoleCodes::PLATFORM_ADMIN => 'admin@talenthub.local',
        ];
        $defaultNames = [
            \TalentHub\Rbac\RoleCodes::STUDENT => 'Học viên TalentHub',
            \TalentHub\Rbac\RoleCodes::TEACHER => 'Giáo viên TalentHub',
            \TalentHub\Rbac\RoleCodes::SCHOOL => 'Ban Giám hiệu TalentHub',
            \TalentHub\Rbac\RoleCodes::ENTERPRISE => 'FPT Software',
            \TalentHub\Rbac\RoleCodes::PLATFORM_ADMIN => 'Admin TalentHub',
        ];
        $defaultIds = [
            \TalentHub\Rbac\RoleCodes::ENTERPRISE => '31000000-0000-4000-8000-000000000015',
        ];
        return [
            'id' => $defaultIds[$role] ?? \TalentHub\Support\Uuid::v4(),
            'email' => $defaultEmails[$role] ?? 'demo@talenthub.local',
            'fullName' => $defaultNames[$role] ?? 'TalentHub User',
            'role' => $role,
            'status' => 'active',
        ];
    }

    private function open(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (!@session_start()) {
            @session_save_path('');
            @session_start();
        }
    }

    private function configureStorage(): void
    {
        $savePath = trim((string)($this->config['savePath'] ?? ''));
        if ($savePath !== '') {
            if (!is_dir($savePath)) {
                @mkdir($savePath, 0777, true);
            }
            if (is_dir($savePath) && is_writable($savePath)) {
                @session_save_path($savePath);
            }
        }
    }

    /** @param array{id:string,email:string,fullName:string,role:string,status:string,enterpriseId?:string,studentId?:string,schoolId?:string,teacherId?:string} $user */
    public function login(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }
        $fullName = (string) ($user['fullName'] ?? ($user['full_name'] ?? ($user['name'] ?? ($user['email'] ?? ''))));
        $_SESSION['user_id'] = (string) $user['id'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['email'] = (string) ($user['email'] ?? '');
        $_SESSION['user_name'] = $fullName;
        $_SESSION['fullName'] = $fullName;
        $_SESSION['full_name'] = $fullName;
        $_SESSION['name'] = $fullName;
        $_SESSION['logged_in'] = true;
        $_SESSION['user'] = [
            'id' => (string) $user['id'],
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) $user['role'],
            'name' => $fullName,
            'fullName' => $fullName,
            'full_name' => $fullName,
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
        if ($token === null || $token === '') {
            throw new ApiException(403, 'CSRF_TOKEN_INVALID', 'CSRF token is required.');
        }
        $sessionToken = $this->csrfToken();
        $altToken = (string) ($_SESSION['csrf_token'] ?? $_SESSION['csrfToken'] ?? $sessionToken);
        if (!hash_equals($sessionToken, $token) && !hash_equals($altToken, $token)) {
            throw new ApiException(403, 'CSRF_TOKEN_INVALID', 'CSRF token mismatch.');
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
        $fullName = (string) ($user['fullName'] ?? ($user['full_name'] ?? ($user['name'] ?? ($user['email'] ?? ''))));
        $_SESSION['user_id'] = (string) ($user['id'] ?? ($_SESSION['user_id'] ?? ''));
        $_SESSION['role'] = (string) ($user['role'] ?? ($_SESSION['role'] ?? ''));
        if ($fullName !== '') {
            $_SESSION['user_name'] = $fullName;
            $_SESSION['fullName'] = $fullName;
            $_SESSION['full_name'] = $fullName;
            $_SESSION['name'] = $fullName;
        }
        if (!empty($user['email'])) {
            $_SESSION['email'] = (string) $user['email'];
        }
        $_SESSION['user'] = array_merge(is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [], [
            'id' => (string) ($user['id'] ?? ($_SESSION['user_id'] ?? '')),
            'email' => (string) ($user['email'] ?? ($_SESSION['email'] ?? '')),
            'role' => (string) ($user['role'] ?? ($_SESSION['role'] ?? '')),
            'name' => $fullName !== '' ? $fullName : ($_SESSION['user']['name'] ?? ''),
            'fullName' => $fullName !== '' ? $fullName : ($_SESSION['user']['fullName'] ?? ''),
            'full_name' => $fullName !== '' ? $fullName : ($_SESSION['user']['full_name'] ?? ''),
            'status' => (string) ($user['status'] ?? ($_SESSION['user']['status'] ?? 'active')),
        ]);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
