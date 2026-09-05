<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationContext;

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
    echo "DATABASE={$database}\n";

    $migration = require dirname(__DIR__) . '/Database/migrations/20260906000100_reconcile_activity_review_permission_scope.php';
    $context = new MigrationContext($pdo);
    $migration->preflight($context);
    $migration->up($context);
    $migration->up($context);

    $mappingCount = static function (PDO $pdo, string $role): int {
        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM role_permissions rp
             INNER JOIN roles r ON r.id = rp.roleId
             INNER JOIN permissions p ON p.id = rp.permissionId
             WHERE r.code = :role AND p.code = :permission'
        );
        $statement->execute(['role' => $role, 'permission' => 'activity.review_school']);
        return (int) $statement->fetchColumn();
    };

    $permissionCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM permissions WHERE code = 'activity.review_school'"
    )->fetchColumn();
    $assert('Permission remains unique after two runs', $permissionCount === 1);
    $assert('School has exactly one activity review mapping', $mappingCount($pdo, 'school') === 1);
    $assert('Platform Admin has no activity review mapping', $mappingCount($pdo, 'platform_admin') === 0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

echo "Summary: {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
