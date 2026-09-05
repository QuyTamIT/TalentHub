<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Modules\Contact\Exception\ConsultationValidationException;
use TalentHub\Modules\Contact\Repository\ConsultationRequestRepository;
use TalentHub\Modules\Contact\Service\ConsultationAdminAuthorization;
use TalentHub\Modules\Contact\Service\ConsultationFormGuard;
use TalentHub\Modules\Contact\Service\ConsultationRateLimiter;
use TalentHub\Modules\Contact\Service\ConsultationRequestService;

function consultation_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function consultation_test_schema(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE auth_rate_limits (
        bucketKey TEXT PRIMARY KEY, scope TEXT NOT NULL, failureCount INTEGER NOT NULL DEFAULT 0,
        windowStartedAt TEXT NOT NULL, blockedUntil TEXT NULL, updatedAt TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT NOT NULL UNIQUE)');
    $pdo->exec('CREATE TABLE permissions (id TEXT PRIMARY KEY, code TEXT NOT NULL UNIQUE)');
    $pdo->exec('CREATE TABLE role_permissions (roleId TEXT NOT NULL, permissionId TEXT NOT NULL, PRIMARY KEY(roleId, permissionId))');
    $pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, roleId TEXT NOT NULL, email TEXT NOT NULL, fullName TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE consultation_requests (
        id TEXT PRIMARY KEY, fullName TEXT NOT NULL, audience TEXT NOT NULL, email TEXT NOT NULL,
        phone TEXT NULL, message TEXT NOT NULL, contactConsent INTEGER NOT NULL, status TEXT NOT NULL,
        idempotencyKey TEXT NOT NULL UNIQUE, handledByUserId TEXT NULL,
        createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completedAt TEXT NULL
    )');
    return $pdo;
}

$pdo = consultation_test_schema();
$repository = new ConsultationRequestRepository($pdo);
$limiter = new ConsultationRateLimiter($pdo);
$service = new ConsultationRequestService($repository, $limiter);
$guard = new ConsultationFormGuard();

$valid = [
    'fullName' => 'Nguyễn Minh An',
    'audience' => 'student',
    'email' => 'minhan@example.test',
    'phone' => '',
    'message' => 'Tôi cần tư vấn về cách sử dụng nền tảng.',
    'contactConsent' => '1',
];
$token = hash('sha256', 'valid-form-token');
$created = $service->submit($valid, $token, '127.0.0.10');
consultation_test_assert($created['created'] === true, 'Valid submission must create a request.');
consultation_test_assert((int) $pdo->query('SELECT COUNT(*) FROM consultation_requests')->fetchColumn() === 1, 'Valid submission was not stored.');
consultation_test_assert((string) $pdo->query('SELECT status FROM consultation_requests')->fetchColumn() === 'new', 'New request must start in new status.');
consultation_test_assert($pdo->query('SELECT phone FROM consultation_requests')->fetchColumn() === null, 'Optional empty phone must be stored as NULL.');

$duplicate = $service->submit($valid, $token, '127.0.0.10');
consultation_test_assert($duplicate['created'] === false, 'Repeated form token must resolve as an idempotent duplicate.');
consultation_test_assert($duplicate['id'] === $created['id'], 'Duplicate token must reference the original request.');
consultation_test_assert((int) $pdo->query('SELECT COUNT(*) FROM consultation_requests')->fetchColumn() === 1, 'Duplicate submission created another row.');

try {
    $service->submit([], hash('sha256', 'invalid-empty'), '127.0.0.11');
    throw new RuntimeException('Empty payload should fail validation.');
} catch (ConsultationValidationException $exception) {
    foreach (['fullName', 'audience', 'email', 'message', 'contactConsent'] as $field) {
        consultation_test_assert(isset($exception->errors[$field]), "Missing validation error for {$field}.");
    }
}

$invalid = $valid;
$invalid['email'] = 'not-an-email';
$invalid['phone'] = 'abc';
$invalid['message'] = 'short';
try {
    $service->submit($invalid, hash('sha256', 'invalid-fields'), '127.0.0.11');
    throw new RuntimeException('Invalid fields should fail validation.');
} catch (ConsultationValidationException $exception) {
    consultation_test_assert(isset($exception->errors['email'], $exception->errors['phone'], $exception->errors['message']), 'Invalid field errors are incomplete.');
}
consultation_test_assert((int) $pdo->query('SELECT COUNT(*) FROM consultation_requests')->fetchColumn() === 1, 'Invalid payload must not be stored.');

$invalidCases = [
    'fullName' => 'A',
    'audience' => 'unknown',
    'email' => 'not-an-email',
    'phone' => 'abc',
    'message' => 'short',
    'contactConsent' => '0',
];
foreach ($invalidCases as $field => $value) {
    $case = $valid;
    $case[$field] = $value;
    try {
        $service->validate($case);
        throw new RuntimeException("Invalid {$field} should fail validation.");
    } catch (ConsultationValidationException $exception) {
        consultation_test_assert(isset($exception->errors[$field]), "Missing isolated validation error for {$field}.");
    }
}

$xss = $valid;
$xss['fullName'] = '<img src=x onerror=alert(1)> Nguyễn An';
$xss['email'] = 'escape@example.test';
$xss['message'] = '<script>alert(1)</script> Nội dung tư vấn hợp lệ.';
$xssResult = $service->submit($xss, hash('sha256', 'xss-storage'), '127.0.0.12');
$storedXss = $repository->find($xssResult['id']);
$escapedName = htmlspecialchars((string) $storedXss['fullName'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$escapedMessage = htmlspecialchars((string) $storedXss['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
consultation_test_assert(!str_contains($escapedName, '<img') && str_contains($escapedName, '&lt;img'), 'Displayed consultation name must be escaped.');
consultation_test_assert(!str_contains($escapedMessage, '<script>') && str_contains($escapedMessage, '&lt;script&gt;'), 'Displayed consultation message must be escaped.');

$csrf = hash('sha256', 'csrf');
$guard->assertCsrf($csrf, $csrf);
try {
    $guard->assertCsrf($csrf, 'wrong-token');
    throw new RuntimeException('Invalid CSRF token should fail.');
} catch (RuntimeException $exception) {
    consultation_test_assert(str_contains($exception->getMessage(), 'bảo mật'), 'CSRF failure should be understandable.');
}
$guard->assertFormToken([$token => time()], $token);
try {
    $guard->assertFormToken([], $token);
    throw new RuntimeException('Consumed form token should fail.');
} catch (RuntimeException $exception) {
    consultation_test_assert(str_contains($exception->getMessage(), 'đã được gửi'), 'Consumed token failure should be understandable.');
}

$spam = $valid;
$spam['email'] = 'rate-limit@example.test';
for ($attempt = 1; $attempt <= 5; $attempt++) {
    $service->submit($spam, hash('sha256', 'rate-limit-' . $attempt), '127.0.0.13');
}
try {
    $service->submit($spam, hash('sha256', 'rate-limit-6'), '127.0.0.13');
    throw new RuntimeException('Sixth request in the window should be rate limited.');
} catch (ApiException $exception) {
    consultation_test_assert($exception->status === 429 && $exception->errorCode === 'RATE_LIMIT_EXCEEDED', 'Rate limit must return 429 semantics.');
}

$pdo->exec("INSERT INTO roles(id, code) VALUES ('role-admin', 'platform_admin'), ('role-student', 'student')");
$pdo->exec("INSERT INTO permissions(id, code) VALUES ('perm-read', 'admin.consultation.read'), ('perm-update', 'admin.consultation.update')");
$pdo->exec("INSERT INTO users(id, roleId, email, fullName, status) VALUES
    ('admin-user', 'role-admin', 'admin@example.test', 'Test Admin', 'active'),
    ('student-user', 'role-student', 'student@example.test', 'Test Student', 'active')");
$pdo->exec("INSERT INTO role_permissions(roleId, permissionId) VALUES ('role-admin', 'perm-read')");
$authorization = new ConsultationAdminAuthorization($pdo);
$authorization->require('admin-user', 'admin.consultation.read');
try {
    $authorization->require('admin-user', 'admin.consultation.update');
    throw new RuntimeException('Admin without update permission should be denied.');
} catch (ApiException $exception) {
    consultation_test_assert($exception->status === 403, 'Missing Admin permission must return 403 semantics.');
}
try {
    $authorization->require('student-user', 'admin.consultation.read');
    throw new RuntimeException('Non-admin should be denied.');
} catch (ApiException $exception) {
    consultation_test_assert($exception->status === 403, 'Non-admin must return 403 semantics.');
}
$pdo->exec("INSERT INTO role_permissions(roleId, permissionId) VALUES ('role-admin', 'perm-update')");
$authorization->require('admin-user', 'admin.consultation.update');

$updated = $repository->updateStatus($created['id'], 'in_progress', 'admin-user');
consultation_test_assert($updated['status'] === 'in_progress', 'Admin could not move request to in-progress.');
$completed = $repository->updateStatus($created['id'], 'completed', 'admin-user');
consultation_test_assert($completed['status'] === 'completed' && $completed['completedAt'] !== null, 'Admin could not complete request.');
try {
    $repository->updateStatus($created['id'], 'in_progress', 'admin-user');
    throw new RuntimeException('Completed request should not move backwards.');
} catch (RuntimeException $exception) {
    consultation_test_assert(str_contains($exception->getMessage(), 'đúng thứ tự'), 'Invalid transition should explain the sequence.');
}

$contactSource = (string) file_get_contents(dirname(__DIR__) . '/contact.php');
$homeSource = (string) file_get_contents(dirname(__DIR__) . '/index.php');
$contactCss = (string) file_get_contents(dirname(__DIR__) . '/assets/css/contact.css');
$adminSource = (string) file_get_contents(dirname(__DIR__) . '/app/admin/consultations.php');
consultation_test_assert(str_contains($contactSource, "header('Location: ' . app_href('/contact.php'), true, 303)"), 'Contact form must use POST/Redirect/GET with 303.');
consultation_test_assert(str_contains($contactSource, "unset(\$tokens[\$formToken])"), 'Successful submission must consume its form token.');
consultation_test_assert(str_contains($homeSource, 'href="./contact.php"'), 'Home consultation button must point to contact.php.');
consultation_test_assert(!str_contains($contactSource, 'FTalentHub'), 'Contact page must not contain the incorrect FTalentHub brand.');
consultation_test_assert(substr_count($contactSource, 'aria-label="TalentHub"') === 2, 'Contact header and footer must expose the TalentHub accessible name.');
consultation_test_assert(substr_count($contactSource, 'Talent<span>Hub</span>') === 2, 'Contact header and footer must render TalentHub consistently.');
consultation_test_assert(str_contains($contactCss, '@media (max-width: 600px)') && str_contains($contactCss, '.form-row { grid-template-columns: 1fr; }'), 'Mobile one-column rules are missing.');
consultation_test_assert(str_contains($contactCss, 'color: #64748b;'), 'Accessible placeholder color is missing.');
consultation_test_assert(str_contains($contactSource, 'id="contactConsent"') && str_contains($contactSource, 'for="contactConsent"'), 'Consent checkbox id/label link is missing.');
consultation_test_assert(str_contains($contactSource, 'aria-invalid="true" aria-describedby="contactConsent-error"'), 'Consent checkbox error state is not described.');
consultation_test_assert(str_contains($contactSource, 'id="contactConsent-error" role="alert"'), 'Consent checkbox alert is missing.');
consultation_test_assert(!str_contains($adminSource, "['error' => \$exception->getMessage()]"), 'Admin must not flash an unexpected exception message.');
consultation_test_assert(str_contains($adminSource, 'Không thể cập nhật yêu cầu lúc này. Vui lòng thử lại.'), 'Admin generic error message is missing.');

echo "PASS consultation request tests (isolated SQLite in-memory database).\n";
