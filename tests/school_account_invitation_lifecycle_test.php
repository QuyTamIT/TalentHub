<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolRepository;
use TalentHub\Modules\School\Service\SchoolDashboardService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
$pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, roleId TEXT NOT NULL, email TEXT NOT NULL, passwordHash TEXT NOT NULL, fullName TEXT NOT NULL, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE account_invitations (id TEXT PRIMARY KEY, userId TEXT NOT NULL, invitedByUserId TEXT NOT NULL, schoolId TEXT NOT NULL, tokenHash TEXT NOT NULL UNIQUE, expiresAt TEXT NOT NULL, acceptedAt TEXT NULL, revokedAt TEXT NULL, createdAt TEXT DEFAULT CURRENT_TIMESTAMP, updatedAt TEXT DEFAULT CURRENT_TIMESTAMP)');
$pdo->exec('CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT NULL, action TEXT NOT NULL, entityType TEXT NULL, entityId TEXT NULL, requestId TEXT NULL, ipAddress TEXT NULL, metadata TEXT NULL, createdAt TEXT DEFAULT CURRENT_TIMESTAMP)');
$pdo->exec("INSERT INTO roles VALUES ('role-teacher', 'teacher')");
$pdo->exec("INSERT INTO users VALUES ('user-valid', 'role-teacher', 'valid@example.test', 'unusable', 'Valid User', 'pending')");
$pdo->exec("INSERT INTO users VALUES ('user-expired', 'role-teacher', 'expired@example.test', 'unusable', 'Expired User', 'pending')");
$pdo->exec("INSERT INTO users VALUES ('user-revoked', 'role-teacher', 'revoked@example.test', 'unusable', 'Revoked User', 'pending')");

$repository = new SchoolRepository($pdo);
$service = new SchoolDashboardService($repository, $pdo);
$inviter = '00000000-0000-4000-8000-000000000001';
$schoolA = '10000000-0000-4000-8000-000000000001';

$validToken = str_repeat('a', 64);
$validId = $repository->createAccountInvitation('user-valid', $inviter, $schoolA, hash('sha256', $validToken), gmdate('Y-m-d H:i:s', time() + 3600));
$accepted = $service->acceptInvitation($validToken, 'StrongPassword!123');
$assert($accepted['invitationStatus'] === 'accepted', 'A valid invitation must be accepted.');
$assert($accepted['schoolId'] === $schoolA, 'Acceptance must preserve the invited school; no caller-selected school is allowed.');
$assert($pdo->query("SELECT status FROM users WHERE id='user-valid'")->fetchColumn() === 'active', 'Accepting must activate the pending account.');
$acceptedAt = $pdo->query("SELECT acceptedAt FROM account_invitations WHERE id='{$validId}'")->fetchColumn();
$assert(is_string($acceptedAt) && $acceptedAt !== '', 'Accepting must consume the invitation.');

try {
    $service->acceptInvitation($validToken, 'StrongPassword!123');
    throw new RuntimeException('An invitation must be one-time.');
} catch (ApiException $exception) {
    $assert($exception->errorCode === 'INVITATION_ALREADY_ACCEPTED', 'A reused token must have the correct lifecycle error.');
}

$expiredToken = str_repeat('b', 64);
$repository->createAccountInvitation('user-expired', $inviter, $schoolA, hash('sha256', $expiredToken), gmdate('Y-m-d H:i:s', time() - 60));
try {
    $service->acceptInvitation($expiredToken, 'StrongPassword!123');
    throw new RuntimeException('An expired invitation must be rejected.');
} catch (ApiException $exception) {
    $assert($exception->errorCode === 'INVITATION_EXPIRED', 'Expired invitation error mismatch.');
}

$revokedToken = str_repeat('c', 64);
$revokedId = $repository->createAccountInvitation('user-revoked', $inviter, $schoolA, hash('sha256', $revokedToken), gmdate('Y-m-d H:i:s', time() + 3600));
$pdo->prepare('UPDATE account_invitations SET revokedAt=CURRENT_TIMESTAMP WHERE id=?')->execute([$revokedId]);
try {
    $service->acceptInvitation($revokedToken, 'StrongPassword!123');
    throw new RuntimeException('A revoked invitation must be rejected.');
} catch (ApiException $exception) {
    $assert($exception->errorCode === 'INVITATION_REVOKED', 'Revoked invitation error mismatch.');
}

$storedHash = $pdo->query("SELECT tokenHash FROM account_invitations WHERE id='{$validId}'")->fetchColumn();
$assert($storedHash === hash('sha256', $validToken), 'Only the token hash may be stored.');
$assert($storedHash !== $validToken, 'Raw invitation token must not be stored.');

$serviceSource = file_get_contents(dirname(__DIR__) . '/src/Modules/School/Service/SchoolDashboardService.php');
$teacherPage = file_get_contents(dirname(__DIR__) . '/app/school/teachers.php');
$assert($serviceSource !== false && !str_contains($serviceSource, 'generatedPassword'), 'Invitation response must not return a temporary password.');
$assert($teacherPage !== false && !str_contains($teacherPage, 'Mật khẩu tạm'), 'School UI must not display a temporary password.');

echo "school_account_invitation_lifecycle_test: OK\n";
