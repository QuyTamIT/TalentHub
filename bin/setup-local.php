<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/Database/seeds/System/RolePermissionSeeder.php';
require dirname(__DIR__) . '/Database/seeds/System/CareerRoleBenchmarkSeeder.php';
require dirname(__DIR__) . '/Database/seeds/Testing/MinimalAuthRbacSeeder.php';
require dirname(__DIR__) . '/Database/seeds/Demo/SchoolDemoSeeder.php';
require dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoDataset.php';
require dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoSeeder.php';
require dirname(__DIR__) . '/Database/seeds/Demo/BtecStructuralSeeder.php';
require dirname(__DIR__) . '/Database/seeds/Demo/SchoolAiProjectCatalogDataset.php';
require dirname(__DIR__) . '/Database/seeds/Demo/SchoolAiProjectCatalogSeeder.php';
require dirname(__DIR__) . '/Database/seeds/Demo/SchoolCredentialDemoDataset.php';
require dirname(__DIR__) . '/Database/seeds/Demo/SchoolCredentialDemoSeeder.php';
require dirname(__DIR__) . '/Database/seeds/Demo/EnterpriseDemoSeeder.php';
require dirname(__DIR__) . '/Database/seeds/Demo/EnrichmentDemoSeeder.php';
require dirname(__DIR__) . '/Database/seeds/Local/AdminAccountSeeder.php';
require dirname(__DIR__) . '/Database/seeds/learner/AssessmentCatalogMasterSeeder.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Database\Seeds\Demo\BtecStructuralSeeder;
use TalentHub\Database\Seeds\Demo\CompleteAiDemoSeeder;
use TalentHub\Database\Seeds\Demo\EnrichmentDemoSeeder;
use TalentHub\Database\Seeds\Demo\EnterpriseDemoSeeder;
use TalentHub\Database\Seeds\Demo\SchoolAiProjectCatalogSeeder;
use TalentHub\Database\Seeds\Demo\SchoolCredentialDemoSeeder;
use TalentHub\Database\Seeds\Demo\SchoolDemoSeeder;
use TalentHub\Database\Seeds\Local\AdminAccountSeeder;
use TalentHub\Database\Seeds\System\CareerRoleBenchmarkSeeder;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;
use TalentHub\Database\Seeds\Testing\MinimalAuthRbacSeeder;
use TalentHub\Learner\Seeds\AssessmentCatalogMasterSeeder;

const LOCAL_SETUP_LOCK = 'talenthub:local_setup';

try {
    $environment = Environment::appEnvironment();
    if (!in_array($environment, ['local', 'test'], true)) {
        throw new RuntimeException('Local setup is allowed only when APP_ENV is local or test.');
    }
    if (PHP_VERSION_ID < 80300) {
        throw new RuntimeException('TalentHub local setup requires PHP 8.3 or newer.');
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('PHP extension pdo_mysql is required.');
    }

    $databaseConfig = require dirname(__DIR__) . '/config/database.php';
    $pdo = (new Connection($databaseConfig))->connect();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '' || $database !== $databaseConfig['database']) {
        throw new RuntimeException('Connected database does not match DB_DATABASE.');
    }

    $password = Environment::required(MinimalAuthRbacSeeder::PASSWORD_ENV);
    $adminPassword = Environment::required(AdminAccountSeeder::PASSWORD_ENV);
    if (strlen($password) < 12 || strlen($adminPassword) < 12) {
        throw new RuntimeException('TALENTHUB_TEST_PASSWORD and TALENTHUB_ADMIN_PASSWORD must contain at least 12 characters.');
    }

    setupLine('environment', $environment);
    setupLine('database', $database . '@' . $databaseConfig['host'] . ':' . $databaseConfig['port']);

    $migrations = new MigrationRunner($pdo, dirname(__DIR__) . '/Database/migrations');
    $applied = $migrations->migrate();
    setupLine('migrations', $applied === [] ? 'up-to-date' : 'applied=' . count($applied));

    $lock = $pdo->prepare('SELECT GET_LOCK(?, 30)');
    $lock->execute([LOCAL_SETUP_LOCK]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Unable to acquire local setup lock.');
    }

    try {
        (new RolePermissionSeeder())->run($pdo);
        (new CareerRoleBenchmarkSeeder())->run($pdo);
        (new MinimalAuthRbacSeeder())->run($pdo, $environment, $password);
        (new AdminAccountSeeder())->run($pdo, $environment, $adminPassword);

        $catalogResult = (new AssessmentCatalogMasterSeeder(
            $pdo,
            $database,
            $database === AssessmentCatalogMasterSeeder::PROTECTED_DATABASE,
            static function (string $line): void {
                if (str_starts_with($line, 'SUMMARY') || str_starts_with($line, 'PREFLIGHT')) {
                    fwrite(STDOUT, '[SETUP] assessment_catalogs=' . str_replace(' ', '_', strtolower($line)) . PHP_EOL);
                }
            },
        ))->seedAll();
        if ($catalogResult['failed'] !== 0) {
            throw new RuntimeException('Assessment catalog seed failed.');
        }

        (new SchoolDemoSeeder())->run($pdo, $environment, $password);
        (new CompleteAiDemoSeeder())->run(
            $pdo,
            $environment,
            $password,
            new DateTimeImmutable('today', new DateTimeZone('UTC')),
        );
        (new EnterpriseDemoSeeder())->run($pdo, $environment, $password);
        (new BtecStructuralSeeder())->run($pdo);
        (new SchoolAiProjectCatalogSeeder())->run(
            $pdo,
            $environment,
            new DateTimeImmutable('today', new DateTimeZone('UTC')),
        );
        (new SchoolCredentialDemoSeeder())->run($pdo, $environment);
        (new EnrichmentDemoSeeder())->run($pdo);
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([LOCAL_SETUP_LOCK]);
    }

    $accounts = [
        'student' => 'hs.minh@talenthub.vn',
        'teacher' => 'gv.mai@talenthub.vn',
        'school' => 'school.admin@talenthub.vn',
        'enterprise' => 'business@test.talenthub.local',
        'admin' => AdminAccountSeeder::EMAIL,
    ];
    $passwords = [
        'student' => $password,
        'teacher' => $password,
        'school' => $password,
        'enterprise' => $password,
        'admin' => $adminPassword,
    ];
    verifyLocalAccounts($pdo, $accounts, $passwords);

    foreach (tableCounts($pdo) as $table => $count) {
        setupLine('count.' . $table, (string) $count);
    }
    fwrite(STDOUT, PHP_EOL . '[READY] Dữ liệu mẫu đã sẵn sàng.' . PHP_EOL);
    fwrite(STDOUT, 'Chạy web: php -S 127.0.0.1:8080 -t ' . escapeshellarg(dirname(__DIR__)) . PHP_EOL);
    fwrite(STDOUT, 'Đăng nhập: http://127.0.0.1:8080/login.php' . PHP_EOL);
    foreach ($accounts as $role => $email) {
        fwrite(STDOUT, sprintf("  %-10s %s (%s)%s", $role . ':', $email, $role === 'admin' ? 'TALENTHUB_ADMIN_PASSWORD' : 'TALENTHUB_TEST_PASSWORD', PHP_EOL));
    }
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

function setupLine(string $key, string $value): void
{
    fwrite(STDOUT, '[SETUP] ' . $key . '=' . $value . PHP_EOL);
}

/** @param array<string,string> $accounts @param array<string,string> $passwords */
function verifyLocalAccounts(PDO $pdo, array $accounts, array $passwords): void
{
    $statement = $pdo->prepare(
        'SELECT u.passwordHash, u.status, r.code AS role FROM users u INNER JOIN roles r ON r.id = u.roleId WHERE u.email = ? LIMIT 1'
    );
    foreach ($accounts as $role => $email) {
        $statement->execute([$email]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);
        $expectedRole = $role === 'admin' ? 'platform_admin' : $role;
        if (!is_array($account)
            || $account['status'] !== 'active'
            || $account['role'] !== $expectedRole
            || !password_verify($passwords[$role], (string) $account['passwordHash'])
        ) {
            throw new RuntimeException('Demo account verification failed for ' . $email . '.');
        }
    }
    setupLine('accounts', 'verified=' . count($accounts));
}

/** @return array<string,int> */
function tableCounts(PDO $pdo): array
{
    $tables = [
        'users', 'schools', 'classes', 'student_profiles', 'teacher_profiles',
        'enterprises', 'activities', 'projects', 'internship_posts',
        'learner_recommendation_input_snapshots', 'learner_recommendation_runs',
        'learner_ai_roadmaps', 'learner_ai_roadmap_phases', 'learner_ai_roadmap_tasks',
    ];
    $counts = [];
    foreach ($tables as $table) {
        try {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        } catch (Throwable) {
            $counts[$table] = -1;
        }
    }
    return $counts;
}