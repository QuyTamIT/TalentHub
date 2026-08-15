<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Repository\StudentRepository;
use TalentHub\Modules\Student\Service\StudentProfileService;
use TalentHub\Rbac\Service\PermissionService;

if (Environment::appEnvironment() !== 'test') {
    fwrite(STDERR, "learner_foundation_mysql_test requires APP_ENV=test\n");
    exit(2);
}

function mysql_foundation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

/** @return array{status:int,payload:array<string,mixed>} */
function mysql_foundation_http_request(
    string $baseUrl,
    string $method,
    string $path,
    ?array $body,
    ?string &$cookie,
    array $extraHeaders = [],
): array {
    $headers = ['Accept: application/json', ...$extraHeaders];
    if ($cookie !== null) {
        $headers[] = 'Cookie: ' . $cookie;
    }
    $content = '';
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $content = json_encode($body, JSON_THROW_ON_ERROR);
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $content,
        'ignore_errors' => true,
        'timeout' => 5,
    ]]);
    $raw = @file_get_contents($baseUrl . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('/\AHTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
        if (stripos($header, 'Set-Cookie:') === 0) {
            $pair = trim(explode(';', trim(substr($header, strlen('Set-Cookie:'))), 2)[0]);
            if ($pair !== '') {
                $cookie = $pair;
            }
        }
    }
    $payload = is_string($raw) ? json_decode($raw, true) : null;

    return ['status' => $status, 'payload' => is_array($payload) ? $payload : []];
}

/** @param array{status:int,payload:array<string,mixed>} $response */
function mysql_foundation_assert_success(array $response, int $status, string $message): array
{
    if ($response['status'] !== $status
        || !array_key_exists('data', $response['payload'])
        || !is_array($response['payload']['data'])
        || !is_string($response['payload']['meta']['requestId'] ?? null)
    ) {
        throw new RuntimeException($message);
    }

    return $response['payload']['data'];
}

/** @param array{status:int,payload:array<string,mixed>} $response */
function mysql_foundation_assert_error(array $response, int $status, string $code, string $message): void
{
    if ($response['status'] !== $status
        || ($response['payload']['error']['code'] ?? null) !== $code
        || !is_string($response['payload']['error']['message'] ?? null)
        || !is_string($response['payload']['meta']['requestId'] ?? null)
    ) {
        throw new RuntimeException($message);
    }
}

function mysql_foundation_remove_test_student(PDO $pdo, string $email): void
{
    $statement = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $userId = $statement->fetchColumn();
    if (!is_string($userId) || $userId === '') {
        return;
    }
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare("DELETE FROM audit_logs WHERE userId = ? OR (entityType = 'user' AND entityId = ?)");
        $statement->execute([$userId, $userId]);
        $statement = $pdo->prepare('DELETE FROM student_profiles WHERE userId = ?');
        $statement->execute([$userId]);
        $statement = $pdo->prepare('DELETE FROM users WHERE id = ? AND email = ?');
        $statement->execute([$userId, $email]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function mysql_foundation_remove_runtime_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $entries = scandir($directory);
    if (!is_array($entries)) {
        throw new RuntimeException('Unable to inspect Task 7 runtime directory');
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($path) || !unlink($path)) {
            throw new RuntimeException('Unable to remove Task 7 runtime file');
        }
    }
    if (!rmdir($directory)) {
        throw new RuntimeException('Unable to remove Task 7 runtime directory');
    }
}

/**
 * @param list<array{id:string,lastLoginAt:?string,updatedAt:string}> $userState
 * @param array{id:string,dateOfBirth:string,phone:string,studyStatus:string,updatedAt:string} $profileState
 * @param list<array<string,mixed>> $rateLimitState
 * @param list<string> $requestIds
 */
function mysql_foundation_restore_auth_state(
    PDO $pdo,
    array $userState,
    array $profileState,
    array $rateLimitState,
    array $requestIds,
): void {
    $pdo->beginTransaction();
    try {
        if ($requestIds !== []) {
            $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
            $statement = $pdo->prepare("DELETE FROM audit_logs WHERE requestId IN ({$placeholders})");
            $statement->execute($requestIds);
        }
        $statement = $pdo->prepare('UPDATE users SET lastLoginAt = ?, updatedAt = ? WHERE id = ?');
        foreach ($userState as $row) {
            $statement->execute([$row['lastLoginAt'], $row['updatedAt'], $row['id']]);
        }
        $statement = $pdo->prepare(
            'UPDATE student_profiles SET dateOfBirth = ?, phone = ?, studyStatus = ?, updatedAt = ? WHERE id = ?',
        );
        $statement->execute([
            $profileState['dateOfBirth'],
            $profileState['phone'],
            $profileState['studyStatus'],
            $profileState['updatedAt'],
            $profileState['id'],
        ]);
        $pdo->exec('DELETE FROM auth_rate_limits');
        $statement = $pdo->prepare(
            'INSERT INTO auth_rate_limits(bucketKey,scope,failureCount,windowStartedAt,blockedUntil,updatedAt) '
            . 'VALUES(?,?,?,?,?,?)',
        );
        foreach ($rateLimitState as $row) {
            $statement->execute([
                $row['bucketKey'],
                $row['scope'],
                $row['failureCount'],
                $row['windowStartedAt'],
                $row['blockedUntil'],
                $row['updatedAt'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

$databaseConfig = require dirname(__DIR__) . '/config/database.php';
mysql_foundation_assert(
    preg_match('/test/i', (string) $databaseConfig['database']) === 1,
    'Student foundation integration requires a test database',
);
$pdo = (new Connection($databaseConfig))->connect();
mysql_foundation_assert(
    (string) $pdo->query('SELECT DATABASE()')->fetchColumn() === (string) $databaseConfig['database'],
    'Student foundation integration connected to configured test database',
);
$schoolSmokeSource = file_get_contents(dirname(__DIR__) . '/bin/smoke-school-api.php');
mysql_foundation_assert(is_string($schoolSmokeSource), 'School smoke harness is readable');
foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $databaseVariable) {
    mysql_foundation_assert(
        str_contains($schoolSmokeSource, "'{$databaseVariable}'"),
        "School smoke child receives {$databaseVariable}",
    );
}
mysql_foundation_assert(
    preg_match("/'APP_ENV'\\s*=>\\s*'test'/", $schoolSmokeSource) === 1,
    'School smoke child is forced to APP_ENV=test',
);
mysql_foundation_assert(
    !str_contains($schoolSmokeSource, "(\$data['ok'] ?? false) === true"),
    'School smoke checks canonical data/meta envelope instead of legacy ok flag',
);
mysql_foundation_assert(
    str_contains($schoolSmokeSource, 'session.save_path='),
    'School smoke child uses an isolated writable session path',
);
mysql_foundation_assert(
    !str_contains($schoolSmokeSource, 'shell_exec('),
    'School smoke sends JSON without shell-string quoting',
);
mysql_foundation_assert(
    !str_contains($schoolSmokeSource, "'password' => 'TestPassword_2026'"),
    'School smoke login uses the required test-only password variable',
);
mysql_foundation_assert(
    !str_contains($schoolSmokeSource, 'SchoolDemoSeeder')
        && str_contains($schoolSmokeSource, 'school@test.talenthub.local'),
    'School smoke uses the canonical testing fixture without demo seed dependency',
);
$studentEmail = 'student@test.talenthub.local';
$studentRow = (new AuthRepository($pdo))->findByEmail($studentEmail);
mysql_foundation_assert(is_array($studentRow), 'minimal Student fixture exists');
mysql_foundation_assert($studentRow['role'] === 'student', 'fixture resolves canonical student role');

$user = (new AuthService(new AuthRepository($pdo)))->current((string) $studentRow['id']);
(new PermissionService($pdo))->require($user['id'], 'student_profile.read_own');
$service = new StudentProfileService(new StudentRepository($pdo));
$profile = $service->get($user['id']);
$dashboard = $service->dashboard($user['id']);

mysql_foundation_assert($profile['userId'] === $user['id'], 'profile is scoped to current user');
mysql_foundation_assert($profile['email'] === $studentEmail, 'profile and auth identity agree');
mysql_foundation_assert($dashboard['student']['id'] === $profile['id'], 'dashboard uses same Student profile');

$business = (new AuthRepository($pdo))->findByEmail('business@test.talenthub.local');
mysql_foundation_assert(is_array($business), 'business fixture exists for negative authorization');
try {
    (new PermissionService($pdo))->require((string) $business['id'], 'student_profile.read_own');
    mysql_foundation_assert(false, 'business cannot read own Student profile permission');
} catch (ApiException $exception) {
    mysql_foundation_assert($exception->status === 403, 'wrong role receives 403');
}

$school = (new AuthRepository($pdo))->findByEmail('school@test.talenthub.local');
mysql_foundation_assert(is_array($school), 'school fixture exists for admin route denial');
$adminStatement = $pdo->prepare("SELECT COUNT(*) FROM school_members WHERE userId = ? AND memberRole = 'admin'");
$adminStatement->execute([(string) $school['id']]);
mysql_foundation_assert((int) $adminStatement->fetchColumn() === 1, 'school fixture is a school admin');

$authUserIds = [(string) $studentRow['id'], (string) $school['id'], (string) $business['id']];
$placeholders = implode(',', array_fill(0, count($authUserIds), '?'));
$authStateStatement = $pdo->prepare(
    "SELECT id,lastLoginAt,updatedAt FROM users WHERE id IN ({$placeholders}) ORDER BY id",
);
$authStateStatement->execute($authUserIds);
$authUserState = $authStateStatement->fetchAll();
mysql_foundation_assert(count($authUserState) === count($authUserIds), 'auth fixture state is snapshot for cleanup');
$profileStateStatement = $pdo->prepare(
    'SELECT id,dateOfBirth,phone,studyStatus,updatedAt FROM student_profiles WHERE id = ?',
);
$profileStateStatement->execute([(string) $profile['id']]);
$studentProfileState = $profileStateStatement->fetch();
mysql_foundation_assert(is_array($studentProfileState), 'Student profile state is snapshot for cleanup');
$rateLimitState = $pdo->query('SELECT * FROM auth_rate_limits ORDER BY bucketKey')->fetchAll();

$root = dirname(__DIR__);
$port = random_int(20000, 40000);
$baseUrl = "http://127.0.0.1:{$port}";
$sessionPath = $root . '/.superpowers/sdd/task7-sessions-' . bin2hex(random_bytes(8));
$serverCommand = [
    PHP_BINARY,
    '-d',
    'session.save_path=' . $sessionPath,
    '-S',
    "127.0.0.1:{$port}",
    '-t',
    $root,
    $root . '/api/v1/index.php',
];
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$testPassword = Environment::required('TALENTHUB_TEST_PASSWORD');
$otherStudentEmail = 'student2-task7-' . bin2hex(random_bytes(8)) . '@test.talenthub.local';
$studentCookie = null;
$adminCookie = null;
$businessCookie = null;
$otherStudentCookie = null;
$apiFailure = null;
$auditRequestIds = [];
$server = null;
$serverPipes = [];

try {
    if (!mkdir($sessionPath, 0770, true) && !is_dir($sessionPath)) {
        throw new RuntimeException('Task 7 session directory is not writable');
    }
    $server = proc_open($serverCommand, $descriptors, $serverPipes, $root, getenv());
    if (!is_resource($server)) {
        throw new RuntimeException('Student API test server did not start');
    }
    foreach ($serverPipes as $pipe) {
        stream_set_blocking($pipe, false);
    }
    mysql_foundation_remove_test_student($pdo, $otherStudentEmail);
    $ready = false;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $healthCookie = null;
        $health = mysql_foundation_http_request($baseUrl, 'GET', '/api/v1/health', null, $healthCookie);
        if ($health['status'] === 200) {
            mysql_foundation_assert_success($health, 200, 'health route uses success envelope');
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) {
        throw new RuntimeException('Student API test server did not become ready');
    }

    $anonymous = mysql_foundation_http_request($baseUrl, 'GET', '/api/v1/students/me', null, $studentCookie);
    mysql_foundation_assert_error($anonymous, 401, 'AUTHENTICATION_REQUIRED', 'anonymous Student route uses 401 envelope');

    $login = mysql_foundation_http_request($baseUrl, 'POST', '/api/v1/auth/login', [
        'email' => $studentEmail,
        'password' => $testPassword,
    ], $studentCookie);
    if (is_string($login['payload']['meta']['requestId'] ?? null)) {
        $auditRequestIds[] = $login['payload']['meta']['requestId'];
    }
    $loginData = mysql_foundation_assert_success($login, 200, 'Student login uses success envelope');
    if (($loginData['user']['id'] ?? null) !== $user['id'] || ($loginData['user']['role'] ?? null) !== 'student') {
        throw new RuntimeException('Student login resolves the owning Student');
    }
    $csrfToken = $loginData['csrfToken'] ?? null;
    if (!is_string($csrfToken) || $csrfToken === '') {
        throw new RuntimeException('Student login returns CSRF token');
    }

    $authMe = mysql_foundation_http_request($baseUrl, 'GET', '/api/v1/auth/me', null, $studentCookie);
    $authMeData = mysql_foundation_assert_success($authMe, 200, 'auth/me uses success envelope');
    if (($authMeData['user']['id'] ?? null) !== $user['id']) {
        throw new RuntimeException('auth/me returns current Student');
    }

    $profileResponse = mysql_foundation_http_request($baseUrl, 'GET', '/api/v1/students/me', null, $studentCookie);
    $profileData = mysql_foundation_assert_success($profileResponse, 200, 'students/me uses success envelope');
    if (($profileData['id'] ?? null) !== $profile['id'] || ($profileData['userId'] ?? null) !== $user['id']) {
        throw new RuntimeException('students/me returns only the owning profile');
    }

    $dashboardResponse = mysql_foundation_http_request($baseUrl, 'GET', '/api/v1/students/me/dashboard', null, $studentCookie);
    $dashboardData = mysql_foundation_assert_success($dashboardResponse, 200, 'Student dashboard uses success envelope');
    if (($dashboardData['student']['id'] ?? null) !== $profile['id']) {
        throw new RuntimeException('Student dashboard returns the owning profile summary');
    }

    $missingCsrf = mysql_foundation_http_request($baseUrl, 'PATCH', '/api/v1/students/me', [
        'fullName' => $profile['fullName'],
        'dateOfBirth' => $profile['dateOfBirth'],
        'phone' => $profile['phone'],
    ], $studentCookie);
    mysql_foundation_assert_error($missingCsrf, 403, 'CSRF_TOKEN_INVALID', 'Student PATCH rejects missing CSRF token');

    $validPatch = mysql_foundation_http_request($baseUrl, 'PATCH', '/api/v1/students/me', [
        'fullName' => $profile['fullName'],
        'dateOfBirth' => $profile['dateOfBirth'],
        'phone' => $profile['phone'],
    ], $studentCookie, ['X-CSRF-Token: ' . $csrfToken]);
    $patchedProfile = mysql_foundation_assert_success($validPatch, 200, 'Student PATCH accepts valid CSRF token');
    if (($patchedProfile['id'] ?? null) !== $profile['id']) {
        throw new RuntimeException('Student PATCH remains scoped to the owning profile');
    }

    $adminLogin = mysql_foundation_http_request($baseUrl, 'POST', '/api/v1/auth/login', [
        'email' => 'school@test.talenthub.local',
        'password' => $testPassword,
    ], $adminCookie);
    if (is_string($adminLogin['payload']['meta']['requestId'] ?? null)) {
        $auditRequestIds[] = $adminLogin['payload']['meta']['requestId'];
    }
    $adminLoginData = mysql_foundation_assert_success($adminLogin, 200, 'school admin login uses success envelope');
    if (($adminLoginData['user']['role'] ?? null) !== 'school') {
        throw new RuntimeException('school admin login resolves canonical school role');
    }
    $adminDenied = mysql_foundation_http_request($baseUrl, 'GET', '/api/v1/students/me', null, $adminCookie);
    mysql_foundation_assert_error($adminDenied, 403, 'PERMISSION_DENIED', 'school admin is denied Student-only route');

    $businessLogin = mysql_foundation_http_request($baseUrl, 'POST', '/api/v1/auth/login', [
        'email' => 'business@test.talenthub.local',
        'password' => $testPassword,
    ], $businessCookie);
    if (is_string($businessLogin['payload']['meta']['requestId'] ?? null)) {
        $auditRequestIds[] = $businessLogin['payload']['meta']['requestId'];
    }
    mysql_foundation_assert_success($businessLogin, 200, 'Business login uses success envelope');
    $businessDenied = mysql_foundation_http_request($baseUrl, 'GET', '/api/v1/students/me', null, $businessCookie);
    mysql_foundation_assert_error($businessDenied, 403, 'PERMISSION_DENIED', 'Business is denied Student-only route');

    $registration = mysql_foundation_http_request($baseUrl, 'POST', '/api/v1/auth/register', [
        'email' => $otherStudentEmail,
        'password' => $testPassword,
        'fullName' => 'Task 7 Other Student',
        'classId' => $profile['class']['id'],
        'dateOfBirth' => '2008-06-15',
        'phone' => '0900000002',
    ], $otherStudentCookie);
    if (is_string($registration['payload']['meta']['requestId'] ?? null)) {
        $auditRequestIds[] = $registration['payload']['meta']['requestId'];
    }
    $registrationData = mysql_foundation_assert_success($registration, 201, 'second Student fixture registers through API');
    $otherStudentId = $registrationData['user']['id'] ?? null;
    if (!is_string($otherStudentId) || $otherStudentId === $user['id']) {
        throw new RuntimeException('second Student fixture has a distinct identity');
    }

    $otherStudentLogin = mysql_foundation_http_request($baseUrl, 'POST', '/api/v1/auth/login', [
        'email' => $otherStudentEmail,
        'password' => $testPassword,
    ], $otherStudentCookie);
    if (is_string($otherStudentLogin['payload']['meta']['requestId'] ?? null)) {
        $auditRequestIds[] = $otherStudentLogin['payload']['meta']['requestId'];
    }
    mysql_foundation_assert_success($otherStudentLogin, 200, 'second Student login uses success envelope');
    $otherProfileResponse = mysql_foundation_http_request($baseUrl, 'GET', '/api/v1/students/me', null, $otherStudentCookie);
    $otherProfile = mysql_foundation_assert_success($otherProfileResponse, 200, 'second Student resolves own profile');
    if (($otherProfile['userId'] ?? null) !== $otherStudentId || ($otherProfile['id'] ?? null) === $profile['id']) {
        throw new RuntimeException('second Student cannot resolve the first Student through /me');
    }

    $ownerResourceAttempt = mysql_foundation_http_request(
        $baseUrl,
        'GET',
        '/api/v1/students/' . rawurlencode((string) $profile['id']),
        null,
        $otherStudentCookie,
    );
    mysql_foundation_assert_error(
        $ownerResourceAttempt,
        404,
        'RESOURCE_NOT_FOUND',
        'first Student resource is not addressable by another Student',
    );
} catch (Throwable $exception) {
    $apiFailure = $exception;
} finally {
    if (is_resource($server)) {
        proc_terminate($server, 9);
    }
    foreach ($serverPipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    if (is_resource($server)) {
        proc_close($server);
    }
    try {
        mysql_foundation_remove_test_student($pdo, $otherStudentEmail);
    } catch (Throwable $cleanupException) {
        $apiFailure ??= new RuntimeException('second Student fixture cleanup failed');
    }
    try {
        mysql_foundation_restore_auth_state(
            $pdo,
            $authUserState,
            $studentProfileState,
            $rateLimitState,
            $auditRequestIds,
        );
    } catch (Throwable $cleanupException) {
        $apiFailure ??= new RuntimeException('auth fixture state cleanup failed');
    }
    try {
        mysql_foundation_remove_runtime_directory($sessionPath);
    } catch (Throwable $cleanupException) {
        $apiFailure ??= new RuntimeException('Student API runtime cleanup failed');
    }
}

if ($apiFailure instanceof Throwable) {
    fwrite(STDERR, 'Assertion failed: ' . $apiFailure->getMessage() . "\n");
    exit(1);
}

echo "learner_foundation_mysql_test: OK\n";
