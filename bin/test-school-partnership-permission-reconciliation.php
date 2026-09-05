<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Database/seeds/System/RolePermissionSeeder.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationContext;
use TalentHub\Database\Migration\MigrationDefinition;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;

$failures = 0;
$assert = static function (string $name, bool $condition) use (&$failures): void {
    if ($condition) {
        echo "[PASS] {$name}\n";
        return;
    }

    ++$failures;
    fwrite(STDERR, "[FAIL] {$name}\n");
};

try {
    $config = require dirname(__DIR__) . '/config/database.php';
    $pdo = (new Connection($config))->connect();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!str_starts_with($database, 'talenthub_e2e_')) {
        throw new RuntimeException('This regression test is restricted to a disposable talenthub_e2e_* database.');
    }

    $migrationPath = dirname(__DIR__) . '/Database/migrations/20260825000210_grant_school_partnership_permissions.php';
    $migration = require $migrationPath;
    $contents = file_get_contents($migrationPath);
    if (!is_string($contents)) {
        throw new RuntimeException('Unable to read migration under test.');
    }
    $canonical = hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents));
    $runner = new MigrationRunner($pdo, dirname(__DIR__) . '/Database/migrations');
    $definition = new MigrationDefinition(
        '20260825000210',
        'grant_school_partnership_permissions',
        $migrationPath,
        $canonical,
        $migration,
    );
    $checksumMatches = new ReflectionMethod(MigrationRunner::class, 'checksumMatches');
    $checksumMatches->setAccessible(true);
    $matches = static fn (string $checksum): bool => (bool) $checksumMatches->invoke($runner, $definition, $checksum);

    $assert('Repaired checksum is accepted', $matches('f4e1dec1d5f849cb29284bd169dc2150247d728ee5f19dedcbe3d01f16631360'));
    $assert('Origin checksum is accepted through compatibility', $matches('5e06f6811336e87339ec73a3bc82eaf49b3a526fb5534e43a04d02df3bb7fd95'));
    $assert('Unknown checksum is rejected as drift', !$matches(str_repeat('0', 64)));

    $preflightSource = substr($contents, 0, (int) strpos($contents, '    public function up'));
    $assert(
        'Partnership grant preflight does not require the later partnership table',
        !str_contains($preflightSource, 'school_enterprise_partnerships'),
    );

    $reconciliationPath = dirname(__DIR__) . '/Database/migrations/20260831000110_reconcile_school_partnership_permission_mappings.php';
    $reconciliation = require $reconciliationPath;
    $context = new MigrationContext($pdo);
    $reconciliation->preflight($context);
    $reconciliation->up($context);
    $firstPermissionCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM permissions WHERE code IN ('partnership.read_own_school','partnership.review_own_school')"
    )->fetchColumn();
    $firstMappingCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM role_permissions rp
         INNER JOIN roles r ON r.id = rp.roleId
         INNER JOIN permissions p ON p.id = rp.permissionId
         WHERE r.code = 'school' AND p.code IN ('partnership.read_own_school','partnership.review_own_school')"
    )->fetchColumn();
    $reconciliation->up($context);
    $secondPermissionCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM permissions WHERE code IN ('partnership.read_own_school','partnership.review_own_school')"
    )->fetchColumn();
    $secondMappingCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM role_permissions rp
         INNER JOIN roles r ON r.id = rp.roleId
         INNER JOIN permissions p ON p.id = rp.permissionId
         WHERE r.code = 'school' AND p.code IN ('partnership.read_own_school','partnership.review_own_school')"
    )->fetchColumn();
    $assert('Reconciliation creates both permissions and mappings', $firstPermissionCount === 2 && $firstMappingCount === 2);
    $assert('Reconciliation is idempotent on a second run', $secondPermissionCount === 2 && $secondMappingCount === 2);

    $seeder = new RolePermissionSeeder();
    $seeder->run($pdo);
    $seeder->run($pdo);
    $schoolMappingCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM role_permissions rp
         INNER JOIN roles r ON r.id = rp.roleId
         INNER JOIN permissions p ON p.id = rp.permissionId
         WHERE r.code = 'school' AND p.code IN ('partnership.read_own_school','partnership.review_own_school')"
    )->fetchColumn();
    $otherRoleMappingCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM role_permissions rp
         INNER JOIN roles r ON r.id = rp.roleId
         INNER JOIN permissions p ON p.id = rp.permissionId
         WHERE r.code <> 'school' AND p.code IN ('partnership.read_own_school','partnership.review_own_school')"
    )->fetchColumn();
    $assert('RolePermissionSeeder keeps both mappings after two runs', $schoolMappingCount === 2);
    $assert('Other roles do not receive school partnership permissions', $otherRoleMappingCount === 0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

echo "Summary: {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
