<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoDataset.php';
require_once dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoSeeder.php';
require_once dirname(__DIR__) . '/app/learner/data/Database/SchemaInspector.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/LearnerForwardMigration.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/LearnerMigrationPreflight.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/ForwardMigrationDefinition.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/LearnerMigrationChecksum.php';
require_once dirname(__DIR__) . '/app/learner/data/Readiness/AiScopePolicy.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/LearnerForwardMigrationRunner.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Seeds\Demo\CompleteAiDemoDataset;
use TalentHub\Database\Seeds\Demo\CompleteAiDemoSeeder;

function demo_mysql_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$completeSeederSource = file_get_contents(dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoSeeder.php');
demo_mysql_assert(
    is_string($completeSeederSource) && str_contains($completeSeederSource, 'ON DUPLICATE KEY UPDATE'),
    'owned demo rows use guarded MySQL duplicate-key upserts',
);

function demo_table_counts(PDO $pdo, array $tables): array
{
    $result = [];
    foreach ($tables as $table) {
        $result[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`')->fetchColumn();
    }
    return $result;
}

function demo_outside_namespace_snapshots(PDO $pdo, array $tables): array
{
    $result = [];
    foreach ($tables as $table) {
        $escapedTable = str_replace('`', '``', $table);
        $columns = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table ORDER BY ordinal_position');
        $columns->execute(['table' => $table]);
        $columnNames = array_map('strval', $columns->fetchAll(PDO::FETCH_COLUMN));
        demo_mysql_assert($columnNames !== [], 'snapshot table exists: ' . $table);
        $quotedColumns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columnNames);
        $hasId = in_array('id', $columnNames, true);
        $where = $hasId ? " WHERE `id` NOT LIKE '21000000-%' AND `id` NOT LIKE '22000000-%'" : '';
        $orderBy = $hasId ? ' ORDER BY `id`' : ' ORDER BY ' . implode(', ', $quotedColumns);
        $rows = $pdo->query('SELECT ' . implode(', ', $quotedColumns) . ' FROM `' . $escapedTable . '`' . $where . $orderBy)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            ksort($row, SORT_STRING);
        }
        unset($row);
        $result[$table] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    return $result;
}

function demo_row_snapshot(PDO $pdo, string $table, string $id): string
{
    $statement = $pdo->prepare('SELECT * FROM `' . str_replace('`', '``', $table) . '` WHERE id=:id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    demo_mysql_assert(is_array($row), 'foreign collision row exists: ' . $table);
    ksort($row, SORT_STRING);
    return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function demo_expect_foreign_collision(PDO $pdo, CompleteAiDemoSeeder $seeder, string $password, DateTimeImmutable $clock, string $table, string $foreignId, string $expectedMessage = 'Foreign natural-key collision'): void
{
    $before = demo_row_snapshot($pdo, $table, $foreignId);
    try {
        $seeder->run($pdo, 'test', $password, $clock);
        throw new RuntimeException('Expected foreign natural-key collision for ' . $table . '.');
    } catch (RuntimeException $exception) {
        demo_mysql_assert(
            str_contains($exception->getMessage(), $expectedMessage),
            'expected failure is raised for ' . $table,
        );
    }
    demo_mysql_assert(demo_row_snapshot($pdo, $table, $foreignId) === $before, 'foreign row remains unchanged: ' . $table);
}

function demo_assert_schema_dropped(PDO $adminPdo, string $schema): void
{
    $statement = $adminPdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=:schema');
    $statement->execute(['schema' => $schema]);
    demo_mysql_assert((int) $statement->fetchColumn() === 0, 'disposable schema was dropped');
}

function demo_owned_count(PDO $pdo, string $table): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM `" . str_replace('`', '``', $table) . "` WHERE id LIKE '21000000-%' OR id LIKE '22000000-%'")->fetchColumn();
}

/** @return list<string> */
function demo_expected_thpt_student_ids(): array
{
    $ids = array_column(
        array_filter(CompleteAiDemoDataset::learners(), static fn (array $learner): bool => $learner['band'] === 'high'),
        'student_id',
    );
    sort($ids, SORT_STRING);
    return $ids;
}

/** @return list<string> */
function demo_expected_thpt_teacher_ids(): array
{
    return [
        '20000000-0000-4000-8000-000000000050',
        '20000000-0000-4000-8000-000000000051',
        '20000000-0000-4000-8000-000000000052',
        '20000000-0000-4000-8000-000000000053',
        '20000000-0000-4000-8000-000000000054',
        '20000000-0000-4000-8000-000000000055',
    ];
}

/** @param list<string> $expectedIds */
function demo_assert_exact_thpt_ids(PDO $pdo, string $table, array $expectedIds, string $label): void
{
    $statement = $pdo->query("SELECT id FROM `" . str_replace('`', '``', $table) . "` WHERE id LIKE '20000000-%' ORDER BY id");
    $actualIds = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    demo_mysql_assert($actualIds === $expectedIds, $label . ' IDs exactly match their source fixture');
}

function demo_expect_fixture_preflight_failure(PDO $pdo, CompleteAiDemoSeeder $seeder, string $password, DateTimeImmutable $clock, string $expectedMessage): void
{
    try {
        $seeder->run($pdo, 'test', $password, $clock);
        throw new RuntimeException('Expected exact THPT fixture preflight failure.');
    } catch (RuntimeException $exception) {
        demo_mysql_assert(str_contains($exception->getMessage(), $expectedMessage), 'exact THPT fixture preflight failure is raised');
    }
    demo_mysql_assert(!$pdo->inTransaction(), 'THPT fixture preflight fails before the seed transaction remains open');
}

function demo_assert_assessment_plan(PDO $pdo): void
{
    foreach (CompleteAiDemoDataset::assessmentPlan() as $studentId => $expectedCodes) {
        sort($expectedCodes, SORT_STRING);
        $attempts = $pdo->prepare(<<<'SQL'
SELECT attempts.id, tests.code
FROM test_attempts attempts
JOIN talent_tests tests ON tests.id = attempts.testId
WHERE attempts.studentId = :studentId AND attempts.status = 'submitted'
ORDER BY tests.code
SQL);
        $attempts->execute(['studentId' => $studentId]);
        $rows = $attempts->fetchAll(PDO::FETCH_ASSOC);
        $actualCodes = array_map(static fn (array $row): string => (string) $row['code'], $rows);
        demo_mysql_assert($actualCodes === $expectedCodes, 'learner submitted attempts exactly match assessment plan: ' . $studentId);

        foreach ($rows as $row) {
            $attemptId = (string) $row['id'];
            $metadata = $pdo->prepare('SELECT versionId, status FROM learner_assessment_attempt_metadata WHERE attemptId=:attemptId');
            $metadata->execute(['attemptId' => $attemptId]);
            $metadataRows = $metadata->fetchAll(PDO::FETCH_ASSOC);
            demo_mysql_assert(count($metadataRows) === 1, 'submitted attempt has exactly one metadata row: ' . $attemptId);
            demo_mysql_assert((string) $metadataRows[0]['status'] === 'submitted', 'attempt metadata is submitted: ' . $attemptId);

            $results = $pdo->prepare('SELECT COUNT(*) FROM test_results WHERE attemptId=:attemptId');
            $results->execute(['attemptId' => $attemptId]);
            demo_mysql_assert((int) $results->fetchColumn() === 1, 'submitted attempt has exactly one result: ' . $attemptId);

            $publishedRequired = $pdo->prepare(<<<'SQL'
SELECT COUNT(*)
FROM learner_assessment_question_versions questions
JOIN learner_assessment_versions versions ON versions.id = questions.versionId
WHERE questions.versionId = :versionId AND questions.required = 1 AND versions.status = 'published'
SQL);
            $publishedRequired->execute(['versionId' => $metadataRows[0]['versionId']]);
            $answers = $pdo->prepare('SELECT COUNT(*) FROM learner_assessment_answers WHERE attemptId=:attemptId');
            $answers->execute(['attemptId' => $attemptId]);
            demo_mysql_assert(
                (int) $answers->fetchColumn() === (int) $publishedRequired->fetchColumn(),
                'submitted attempt answer count matches published required questions: ' . $attemptId,
            );
        }
    }
}

$schema = (string) getenv('COMPLETE_AI_DEMO_TEST_SCHEMA');
demo_mysql_assert($schema !== '' && preg_match('/^talenthub_complete_demo_test_[a-z0-9_]+$/', $schema) === 1, 'COMPLETE_AI_DEMO_TEST_SCHEMA must match ^talenthub_complete_demo_test_[a-z0-9_]+$');
demo_mysql_assert($schema !== 'talenthub_local', 'Refusing to run disposable test on talenthub_local');
$collisionCase = (string) (getenv('COMPLETE_AI_DEMO_COLLISION_CASE') ?: '');
demo_mysql_assert(
    in_array($collisionCase, ['', 'shared_skill', 'user_email', 'student_skills', 'assessment_answers', 'assessment_criteria', 'duplicate_role', 'fixture_student', 'fixture_teacher', 'fixture_school'], true),
    'COMPLETE_AI_DEMO_COLLISION_CASE is unsupported',
);

$projectRoot = dirname(__DIR__);
$adminHost = (string) (getenv('COMPLETE_AI_DEMO_TEST_ADMIN_HOST') ?: '127.0.0.1');
$adminPort = (string) (getenv('COMPLETE_AI_DEMO_TEST_ADMIN_PORT') ?: '3306');
$adminUsername = (string) (getenv('COMPLETE_AI_DEMO_TEST_ADMIN_USERNAME') ?: 'root');
$adminPassword = (string) (getenv('COMPLETE_AI_DEMO_TEST_ADMIN_PASSWORD') ?: '');
demo_mysql_assert(filter_var($adminPort, FILTER_VALIDATE_INT) !== false && (int) $adminPort > 0, 'COMPLETE_AI_DEMO_TEST_ADMIN_PORT must be a positive integer');

putenv('APP_ENV=test');
$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_ENV'] = 'test';
putenv('DB_DATABASE=' . $schema);
$_ENV['DB_DATABASE'] = $schema;
$_SERVER['DB_DATABASE'] = $schema;
putenv('DB_HOST=' . $adminHost);
$_ENV['DB_HOST'] = $adminHost;
$_SERVER['DB_HOST'] = $adminHost;
putenv('DB_PORT=' . $adminPort);
$_ENV['DB_PORT'] = $adminPort;
$_SERVER['DB_PORT'] = $adminPort;
putenv('DB_USERNAME=' . $adminUsername);
$_ENV['DB_USERNAME'] = $adminUsername;
$_SERVER['DB_USERNAME'] = $adminUsername;
putenv('DB_PASSWORD=' . $adminPassword);
$_ENV['DB_PASSWORD'] = $adminPassword;
$_SERVER['DB_PASSWORD'] = $adminPassword;

$config = [
    'driver' => 'mysql',
    'host' => $adminHost,
    'port' => (int) $adminPort,
    'database' => $schema,
    'username' => $adminUsername,
    'password' => $adminPassword,
    'charset' => 'utf8mb4',
    'connectTimeout' => 5,
    'persistent' => false,
];
$pdo = null;
$adminPdo = null;
$primaryFailure = null;
$cleanupFailure = null;
try {
    $adminPdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $adminHost, (int) $adminPort),
        $adminUsername,
        $adminPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $adminPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $schema) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    $pdo = (new Connection($config))->connect();
    $pdo->exec("SET time_zone = '+00:00'");
    demo_mysql_assert((string) $pdo->query('SELECT DATABASE()')->fetchColumn() === $schema, 'PDO is pinned to disposable schema');

    // Run migrations
    $phpBin = 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
    $migrateCmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($projectRoot . '/bin/migrate.php') . ' migrate --step=12 2>&1';
    $output = [];
    $code = 0;
    exec($migrateCmd, $output, $code);
    if ($code !== 0) {
        throw new RuntimeException('Migration failed: ' . implode("\n", $output));
    }

    $learnerMigrations = new TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner(
        $pdo,
        $projectRoot . '/Database/migrations/learner',
        new TalentHub\Learner\Data\Database\SchemaInspector($pdo, $schema),
    );
    foreach ([
        '002_create_ai_input_foundation',
        '003_create_ai_input_extensions',
        '004_create_recommendation_store',
    ] as $version) {
        demo_mysql_assert(
            $learnerMigrations->migrateApproved([$version]) === [$version],
            'learner migration applies: ' . $version,
        );
    }

    $migrateCmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($projectRoot . '/bin/migrate.php') . ' migrate 2>&1';
    $output = [];
    $code = 0;
    exec($migrateCmd, $output, $code);
    if ($code !== 0) {
        throw new RuntimeException('Reconciliation migration failed: ' . implode("\n", $output));
    }

    // Seed base SchoolDemoSeeder
    require_once $projectRoot . '/Database/seeds/Demo/SchoolDemoSeeder.php';
    require_once $projectRoot . '/Database/seeds/System/RolePermissionSeeder.php';
    $roleSeeder = new TalentHub\Database\Seeds\System\RolePermissionSeeder();
    $roleSeeder->run($pdo);
    $password = 'CompleteDemoPass!2026';
    putenv('TALENTHUB_TEST_PASSWORD=' . $password);
    $_ENV['TALENTHUB_TEST_PASSWORD'] = $password;
    putenv('APP_ENV=test');
    $_ENV['APP_ENV'] = 'test';
    $env = 'test';
    (new TalentHub\Database\Seeds\Demo\SchoolDemoSeeder())->run($pdo, $env, $password);

    // Seed catalogs into disposable schema
    $catalogSeederFile = $projectRoot . '/Database/seeds/learner/AssessmentCatalogMasterSeeder.php';
    if (is_file($catalogSeederFile)) {
        require_once $catalogSeederFile;
        $seeder = new TalentHub\Learner\Seeds\AssessmentCatalogMasterSeeder($pdo, $schema);
        $seeder->seedAll();
    }

    $seeder = new CompleteAiDemoSeeder();
    $clock = new DateTimeImmutable('2026-08-20 00:00:00.000000', new DateTimeZone('UTC'));

    if (in_array($collisionCase, ['fixture_student', 'fixture_teacher', 'fixture_school'], true)) {
        $fixture = match ($collisionCase) {
            'fixture_student' => ['student_profiles', '20000000-0000-4000-8000-000000000060', '30000000-0000-4000-8000-000000000201', 'THPT student profile fixture IDs'],
            'fixture_teacher' => ['teacher_profiles', '20000000-0000-4000-8000-000000000050', '30000000-0000-4000-8000-000000000202', 'THPT teacher profile fixture IDs'],
            'fixture_school' => ['schools', '20000000-0000-4000-8000-000000000001', '30000000-0000-4000-8000-000000000203', 'THPT school fixture ID'],
        };
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            $statement = $pdo->prepare('UPDATE `' . $fixture[0] . '` SET id=:replacementId WHERE id=:expectedId');
            $statement->execute(['replacementId' => $fixture[2], 'expectedId' => $fixture[1]]);
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        demo_expect_fixture_preflight_failure($pdo, $seeder, $password, $clock, $fixture[3]);
        echo "complete_ai_demo_seed_mysql_test: {$collisionCase} OK\n";
    } elseif ($collisionCase === 'shared_skill') {
        $foreignId = '30000000-0000-4000-8000-000000000100';
        $statement = $pdo->prepare('INSERT INTO skills (id, code, name, category, status, createdAt, updatedAt) VALUES (:id, :code, :name, :category, :status, :createdAt, :updatedAt)');
        $statement->execute([
            'id' => $foreignId,
            'code' => 'communication',
            'name' => 'Shared communication skill',
            'category' => 'shared',
            'status' => 'active',
            'createdAt' => '2020-01-01 00:00:00.000000',
            'updatedAt' => '2020-01-01 00:00:00.000000',
        ]);
        $before = demo_row_snapshot($pdo, 'skills', $foreignId);
        $seeder->run($pdo, 'test', $password, $clock);
        demo_mysql_assert(demo_row_snapshot($pdo, 'skills', $foreignId) === $before, 'active shared skill remains unchanged');
        demo_mysql_assert((int) $pdo->query("SELECT COUNT(*) FROM skills WHERE code='communication'")->fetchColumn() === 1, 'active shared skill is reused by code');
        echo "complete_ai_demo_seed_mysql_test: shared_skill OK\n";
    } elseif ($collisionCase === 'user_email') {
        $foreignId = '30000000-0000-4000-8000-000000000101';
        $roleId = (string) $pdo->query("SELECT id FROM roles WHERE code='school'")->fetchColumn();
        $statement = $pdo->prepare('INSERT INTO users (id, roleId, email, passwordHash, fullName, status) VALUES (:id, :roleId, :email, :passwordHash, :fullName, :status)');
        $statement->execute([
            'id' => $foreignId,
            'roleId' => $roleId,
            'email' => 'fpt.admin@talenthub.vn',
            'passwordHash' => password_hash('foreign-user-only', PASSWORD_DEFAULT),
            'fullName' => 'Foreign fixture user',
            'status' => 'active',
        ]);
        demo_expect_foreign_collision($pdo, $seeder, $password, $clock, 'users', $foreignId);
        echo "complete_ai_demo_seed_mysql_test: collision user_email OK\n";
    } elseif ($collisionCase === 'duplicate_role') {
        $pdo->exec('ALTER TABLE roles DROP INDEX uq_roles_code');
        foreach (['school', 'teacher', 'student'] as $offset => $code) {
            $role = $pdo->query("SELECT name, description, isSystem FROM roles WHERE code=" . $pdo->quote($code) . ' LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            demo_mysql_assert(is_array($role), $code . ' role exists for duplicate-role preflight');
            $foreignId = sprintf('30000000-0000-4000-8000-%012d', 102 + $offset);
            $statement = $pdo->prepare('INSERT INTO roles (id, code, name, description, isSystem) VALUES (:id, :code, :name, :description, :isSystem)');
            $statement->execute(['id' => $foreignId, 'code' => $code, ...$role]);
            demo_expect_foreign_collision($pdo, $seeder, $password, $clock, 'roles', $foreignId, 'Role must exist exactly once');
            $pdo->prepare('DELETE FROM roles WHERE id=:id')->execute(['id' => $foreignId]);
        }
        echo "complete_ai_demo_seed_mysql_test: collision duplicate_role OK\n";
    } else {
        $probeBaseline = demo_outside_namespace_snapshots($pdo, $seeder->touchedTables());
        $thptSchoolId = '20000000-0000-4000-8000-000000000001';
        $originalSchoolName = $pdo->prepare('SELECT name FROM schools WHERE id=:id');
        $originalSchoolName->execute(['id' => $thptSchoolId]);
        $schoolName = $originalSchoolName->fetchColumn();
        demo_mysql_assert(is_string($schoolName), 'THPT school exists for snapshot probe');
        $pdo->prepare('UPDATE schools SET name=:name WHERE id=:id')->execute(['name' => 'Snapshot mutation probe', 'id' => $thptSchoolId]);
        demo_mysql_assert(
            demo_outside_namespace_snapshots($pdo, $seeder->touchedTables()) !== $probeBaseline,
            'outside-namespace snapshots detect row-content changes without count drift',
        );
        $pdo->prepare('UPDATE schools SET name=:name WHERE id=:id')->execute(['name' => $schoolName, 'id' => $thptSchoolId]);
        $baselineOutside = demo_outside_namespace_snapshots($pdo, $seeder->touchedTables());
        $first = $seeder->run($pdo, 'test', $password, $clock);
        $firstCounts = demo_table_counts($pdo, $seeder->touchedTables());

        if ($collisionCase === 'student_skills') {
            $ownedId = (string) $pdo->query("SELECT id FROM student_skills WHERE id LIKE '22000000-%' ORDER BY id LIMIT 1")->fetchColumn();
            $foreignId = '30000000-0000-4000-8000-000000000103';
            demo_mysql_assert($ownedId !== '', 'owned student skill exists for collision test');
            $statement = $pdo->prepare('UPDATE student_skills SET id=:foreignId WHERE id=:ownedId');
            $statement->execute(['foreignId' => $foreignId, 'ownedId' => $ownedId]);
            demo_expect_foreign_collision($pdo, $seeder, $password, $clock, 'student_skills', $foreignId);
            echo "complete_ai_demo_seed_mysql_test: collision student_skills OK\n";
        } elseif ($collisionCase === 'assessment_answers') {
            $ownedId = (string) $pdo->query("SELECT id FROM learner_assessment_answers WHERE id LIKE '22000000-%' ORDER BY id LIMIT 1")->fetchColumn();
            $foreignId = '30000000-0000-4000-8000-000000000104';
            demo_mysql_assert($ownedId !== '', 'owned assessment answer exists for collision test');
            $statement = $pdo->prepare('UPDATE learner_assessment_answers SET id=:foreignId WHERE id=:ownedId');
            $statement->execute(['foreignId' => $foreignId, 'ownedId' => $ownedId]);
            demo_expect_foreign_collision($pdo, $seeder, $password, $clock, 'learner_assessment_answers', $foreignId);
            echo "complete_ai_demo_seed_mysql_test: collision assessment_answers OK\n";
        } elseif ($collisionCase === 'assessment_criteria') {
            $ownedId = (string) $pdo->query("SELECT id FROM assessment_criteria WHERE id LIKE '22000000-%' ORDER BY id LIMIT 1")->fetchColumn();
            $foreignId = '30000000-0000-4000-8000-000000000105';
            demo_mysql_assert($ownedId !== '', 'owned assessment criterion exists for collision test');
            $statement = $pdo->prepare('UPDATE assessment_criteria SET id=:foreignId WHERE id=:ownedId');
            $statement->execute(['foreignId' => $foreignId, 'ownedId' => $ownedId]);
            demo_expect_foreign_collision($pdo, $seeder, $password, $clock, 'assessment_criteria', $foreignId);
            echo "complete_ai_demo_seed_mysql_test: collision assessment_criteria OK\n";
        } else {
            $second = $seeder->run($pdo, 'test', $password, $clock);
            $secondCounts = demo_table_counts($pdo, $seeder->touchedTables());

            demo_mysql_assert($firstCounts === $secondCounts, 'second seed has zero count drift');
            demo_mysql_assert(demo_outside_namespace_snapshots($pdo, $seeder->touchedTables()) === $baselineOutside, 'unrelated row content is unchanged');
    demo_mysql_assert((int) $pdo->query("SELECT COUNT(*) FROM schools WHERE id LIKE '22000000-%' AND name='Đại học FPT'")->fetchColumn() === 1, 'FPT school exists');
    demo_assert_exact_thpt_ids($pdo, 'schools', ['20000000-0000-4000-8000-000000000001'], 'THPT school fixture');
    demo_assert_exact_thpt_ids($pdo, 'teacher_profiles', demo_expected_thpt_teacher_ids(), 'THPT teacher profile fixture');
    demo_assert_exact_thpt_ids($pdo, 'student_profiles', demo_expected_thpt_student_ids(), 'THPT student profile fixture');
    demo_mysql_assert(
        $pdo->query("SELECT website FROM schools WHERE id LIKE '22000000-%' AND name='Đại học FPT'")->fetchColumn() === 'https://fpt.demo.talenthub.local',
        'FPT website is explicitly synthetic',
    );
    demo_mysql_assert((int) $pdo->query("SELECT COUNT(*) FROM student_profiles WHERE id LIKE '22000000-%'")->fetchColumn() === 8, '8 FPT students exist');
    demo_mysql_assert((int) $pdo->query("SELECT COUNT(*) FROM teacher_profiles WHERE id LIKE '22000000-%'")->fetchColumn() === 4, '4 FPT lecturers exist');
    demo_mysql_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_ai_consent_events WHERE id LIKE '21000000-%' OR id LIKE '22000000-%'")->fetchColumn() === 76, 'four consent scopes for 19 learners');
    demo_mysql_assert((int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM student_skills ss
JOIN student_profiles sp ON sp.id = ss.studentId
JOIN classes c ON c.id = sp.classId
WHERE c.schoolId = '20000000-0000-4000-8000-000000000001'
  AND ss.id NOT LIKE '21000000-%'
SQL)->fetchColumn() === 0, 'THPT student skills use the 21000000 namespace');
    demo_mysql_assert((int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM learner_skill_evidence evidence
JOIN student_skills ss ON ss.id = evidence.studentSkillId
JOIN student_profiles sp ON sp.id = ss.studentId
JOIN classes c ON c.id = sp.classId
WHERE c.schoolId = '20000000-0000-4000-8000-000000000001'
  AND evidence.id NOT LIKE '21000000-%'
SQL)->fetchColumn() === 0, 'THPT skill evidence uses the 21000000 namespace');
    $invalidEvidence = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM (
    SELECT student_skill.id
    FROM student_skills student_skill
    LEFT JOIN learner_skill_evidence evidence ON evidence.studentSkillId = student_skill.id
    WHERE student_skill.id LIKE '21000000-%' OR student_skill.id LIKE '22000000-%'
    GROUP BY student_skill.id
    HAVING COUNT(evidence.id) <> 1
        OR SUM(
            evidence.evidenceType = 'teacher_observation'
            AND evidence.evidenceRef = CONCAT('demo://verified/', student_skill.id)
            AND evidence.verificationStatus = 'verified'
            AND evidence.observedAt IS NOT NULL
        ) <> 1
) AS invalid_evidence
SQL)->fetchColumn();
    demo_mysql_assert($invalidEvidence === 0, 'every seeded student skill has exactly one verified teacher-observation evidence row');

    // Assessment band checks
    $wrongBands = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM test_attempts a
JOIN student_profiles sp ON sp.id=a.studentId
JOIN classes c ON c.id=sp.classId
JOIN talent_tests t ON t.id=a.testId
WHERE (c.schoolId='20000000-0000-4000-8000-000000000001' AND t.code NOT LIKE '%\_high')
   OR (c.schoolId LIKE '22000000-%' AND t.code NOT LIKE '%\_college')
SQL)->fetchColumn();
    demo_mysql_assert($wrongBands === 0, 'assessment bands never cross');

    demo_assert_assessment_plan($pdo);

    $inputHashes = $pdo->query('SELECT inputHash FROM learner_assessment_attempt_metadata WHERE status = \'submitted\'')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($inputHashes as $inputHash) {
        demo_mysql_assert(is_string($inputHash) && preg_match('/^[a-f0-9]{64}$/', $inputHash) === 1, 'submitted input hash is canonical lowercase SHA-256');
    }
    $scoreRows = $pdo->query("SELECT dimensionScoresJson, scoringVersion FROM test_results")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($scoreRows as $scoreRow) {
        $scores = json_decode((string) $scoreRow['dimensionScoresJson'], true, 512, JSON_THROW_ON_ERROR);
        demo_mysql_assert(is_array($scores) && $scores !== [] && $scoreRow['scoringVersion'] !== '', 'all results have scores');
        foreach ($scores as $score) {
            demo_mysql_assert(is_int($score) && $score >= 0 && $score <= 100, 'result scores are integers in [0,100]');
        }
    }

    // Journey counts
    demo_mysql_assert(demo_owned_count($pdo, 'activities') === 18, '18 owned activities');
    demo_mysql_assert(demo_owned_count($pdo, 'activity_registrations') === 40, '40 owned registrations');
    demo_mysql_assert(demo_owned_count($pdo, 'checkins') === 20, '20 confirmed check-ins');
    demo_mysql_assert(demo_owned_count($pdo, 'experience_logs') === 20, '20 confirmed experiences');
    demo_mysql_assert(demo_owned_count($pdo, 'assessments') === 20, '20 published evaluations');
    demo_mysql_assert(demo_owned_count($pdo, 'assessment_scores') === 60, 'three scores per evaluation');
    demo_mysql_assert(demo_owned_count($pdo, 'activity_qr_sessions') === 8, 'four QR sessions per organization');
    demo_mysql_assert((int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM activity_qr_sessions session
JOIN activities activity ON activity.id = session.activityId
WHERE session.id LIKE '21000000-%'
  AND session.status = 'revoked'
  AND activity.title = 'Triển lãm Thiết kế sáng tạo'
SQL)->fetchColumn() === 1, 'THPT revoked QR session targets the second ongoing activity');

    $invalid = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM checkins c
JOIN activity_registrations r ON r.id=c.registrationId
JOIN activity_qr_sessions q ON q.id=c.qrSessionId
WHERE c.status<>'confirmed'
   OR r.status<>'attended'
   OR r.activityId<>q.activityId
   OR c.checkedInAt IS NULL
   OR c.confirmedAt IS NULL
SQL)->fetchColumn();
    demo_mysql_assert($invalid === 0, 'check-in lifecycle is coherent');

    $badCancelled = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM activity_registrations registrations
LEFT JOIN checkins checkins ON checkins.registrationId = registrations.id
LEFT JOIN experience_logs experiences ON experiences.checkinId = checkins.id
LEFT JOIN assessments assessments
  ON assessments.studentId = registrations.studentId AND assessments.activityId = registrations.activityId
WHERE registrations.status = 'cancelled'
  AND (checkins.id IS NOT NULL OR experiences.id IS NOT NULL OR assessments.id IS NOT NULL)
SQL)->fetchColumn();
    demo_mysql_assert($badCancelled === 0, 'cancelled registrations have no check-in, experience, or evaluation');

    $orphanedAssessments = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM assessments assessment
LEFT JOIN activity_registrations registration
  ON registration.studentId = assessment.studentId AND registration.activityId = assessment.activityId
WHERE assessment.status = 'published' AND registration.id IS NULL
SQL)->fetchColumn();
    demo_mysql_assert($orphanedAssessments === 0, 'published evaluations match a registration');

    $orphanedExperiences = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM experience_logs experience
JOIN checkins checkin_row ON checkin_row.id = experience.checkinId
JOIN activity_registrations registration ON registration.id = checkin_row.registrationId
WHERE experience.status = 'confirmed'
  AND (experience.studentId <> registration.studentId OR experience.activityId <> registration.activityId)
SQL)->fetchColumn();
    demo_mysql_assert($orphanedExperiences === 0, 'confirmed experiences match their check-in registration');

    $crossOrganizationActivities = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM activities activity
JOIN teacher_profiles teacher ON teacher.id = activity.createdByTeacherId
WHERE activity.schoolId <> teacher.schoolId
SQL)->fetchColumn();
    demo_mysql_assert($crossOrganizationActivities === 0, 'activities never use a teacher from another organization');

    echo "complete_ai_demo_seed_mysql_test: OK\n";
        }
    }
} catch (Throwable $exception) {
    $primaryFailure = $exception;
} finally {
    try {
        $cleanupPdo = $adminPdo instanceof PDO
            ? $adminPdo
            : new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $adminHost, (int) $adminPort),
                $adminUsername,
                $adminPassword,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        $cleanupPdo->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '``', $schema) . '`');
        demo_assert_schema_dropped($cleanupPdo, $schema);
        echo "complete_ai_demo_seed_mysql_test: schema dropped {$schema}\n";
    } catch (Throwable $exception) {
        $cleanupFailure = $exception;
    }
    putenv('COMPLETE_AI_DEMO_TEST_SCHEMA');
    unset($_ENV['COMPLETE_AI_DEMO_TEST_SCHEMA']);
}

if ($primaryFailure instanceof Throwable) {
    if ($cleanupFailure instanceof Throwable) {
        throw new RuntimeException(
            'Primary test failure: ' . $primaryFailure->getMessage() . '; cleanup failure: ' . $cleanupFailure->getMessage(),
            0,
            $primaryFailure,
        );
    }
    throw $primaryFailure;
}
if ($cleanupFailure instanceof Throwable) {
    throw $cleanupFailure;
}
