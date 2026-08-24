<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;

const IMPORT_PASSWORD_ENV = 'TALENTHUB_IMPORT_PASSWORD';

$environment = Environment::appEnvironment();
if (!in_array($environment, ['local', 'test'], true)) {
    throw new RuntimeException('Account import is allowed only in local or test environments.');
}

$password = getenv(IMPORT_PASSWORD_ENV);
if (!is_string($password) || strlen($password) < 12) {
    throw new RuntimeException(IMPORT_PASSWORD_ENV . ' must contain at least 12 characters.');
}
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    throw new RuntimeException('Unable to hash the import password.');
}

$accounts = [
    ['id' => '31000000-0000-4000-8000-000000000004', 'email' => 'student@talenthub.local', 'fullName' => 'TalentHub Demo Student', 'role' => 'student'],
    ['id' => '31000000-0000-4000-8000-000000000003', 'email' => 'teacher@talenthub.local', 'fullName' => 'TalentHub Demo Teacher', 'role' => 'teacher'],
    ['id' => '31000000-0000-4000-8000-000000000002', 'email' => 'school@talenthub.local', 'fullName' => 'TalentHub Demo School', 'role' => 'school'],
    ['id' => '31000000-0000-4000-8000-000000000001', 'email' => 'enterprise@talenthub.local', 'fullName' => 'TalentHub Demo Enterprise', 'role' => 'enterprise'],
];
$roleDescriptions = [
    'student' => 'Student role',
    'teacher' => 'Teacher role',
    'school' => 'School administrator role',
    'enterprise' => 'Enterprise member role',
];

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$pdo->beginTransaction();
try {
    $columns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users'")->fetchAll(PDO::FETCH_COLUMN);
    $legacy = in_array('roles', $columns, true);

    if ($legacy) {
        $roleStatement = $pdo->prepare(
            'INSERT INTO roles(name,description) VALUES(?,?) ON DUPLICATE KEY UPDATE description=VALUES(description)'
        );
        foreach ($roleDescriptions as $role => $description) {
            $roleStatement->execute([$role, $description]);
        }
        $userStatement = $pdo->prepare(
            "INSERT INTO users(id,email,passwordHash,fullName,roles,status) VALUES(?,?,?,?,?,'active')
             ON DUPLICATE KEY UPDATE id=id"
        );
        foreach ($accounts as $account) {
            $userStatement->execute([$account['id'], $account['email'], $passwordHash, $account['fullName'], $account['role']]);
        }
    } else {
        require_once dirname(__DIR__) . '/Database/seeds/System/RolePermissionSeeder.php';
        (new TalentHub\Database\Seeds\System\RolePermissionSeeder())->runWithinTransaction($pdo);
        $roles = $pdo->query("SELECT code,id FROM roles WHERE code IN ('student','teacher','school','enterprise')")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (count($roles) !== 4) {
            throw new RuntimeException('The four required canonical roles are unavailable after seeding.');
        }
        $userStatement = $pdo->prepare(
            "INSERT INTO users(id,roleId,email,passwordHash,fullName,status) VALUES(?,?,?,?,?,'active')
             ON DUPLICATE KEY UPDATE id=id"
        );
        foreach ($accounts as $account) {
            $userStatement->execute([$account['id'], $roles[$account['role']], $account['email'], $passwordHash, $account['fullName']]);
        }
    }

    $dryRun = in_array('--dry-run', $argv, true);
    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }

    echo $dryRun ? "Account and role import validation: OK (rolled back)\n" : "Accounts and roles imported successfully.\n";
    foreach ($accounts as $account) {
        echo sprintf("- %-10s %s\n", $account['role'], $account['email']);
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Import failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
