<?php

declare(strict_types=1);

use TalentHub\Http\ApiException;
use TalentHub\Rbac\Service\PermissionService;

require dirname(__DIR__) . '/bin/bootstrap.php';

function permission_driver_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function permission_driver_expect_denied(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (ApiException $exception) {
        permission_driver_assert($exception->status === 403 && $exception->errorCode === 'PERMISSION_DENIED', $message);
        return;
    }
    permission_driver_assert(false, $message);
}

function canonical_permission_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, roleId TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE permissions (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE role_permissions (roleId TEXT NOT NULL, permissionId TEXT NOT NULL)');
    $pdo->exec("INSERT INTO roles VALUES ('role-student', 'student')");
    $pdo->exec("INSERT INTO users VALUES ('user-student', 'role-student', 'active')");
    $pdo->exec("INSERT INTO permissions VALUES ('permission-read', 'student_profile.read_own')");
    $pdo->exec("INSERT INTO role_permissions VALUES ('role-student', 'permission-read')");
    return $pdo;
}

function legacy_permission_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, roles TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec("INSERT INTO users VALUES ('user-business', 'business', 'active')");
    return $pdo;
}

$canonical = new PermissionService(canonical_permission_fixture());
$canonical->require('user-student', 'student_profile.read_own');
permission_driver_expect_denied(
    static fn (): null => $canonical->require('user-student', 'student_profile.update_own'),
    'canonical SQLite permission check denies an ungranted permission'
);

$legacy = new PermissionService(legacy_permission_fixture());
$legacy->require('user-business', 'business_dashboard.read');
permission_driver_expect_denied(
    static fn (): null => $legacy->require('user-business', 'student_profile.read_own'),
    'legacy SQLite permission check retains role-prefix restrictions'
);

echo "permission_service_driver_compatibility_test: OK\n";
