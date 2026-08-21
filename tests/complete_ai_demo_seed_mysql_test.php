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

function demo_outside_namespace_counts(PDO $pdo, array $tables): array
{
    $result = [];
    foreach ($tables as $table) {
        // For tables with id column, count outside demo namespaces; otherwise global count
        try {
            $hasId = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=" . $pdo->quote($table) . " AND column_name='id'")->fetchColumn();
            if ($hasId === 1) {
                $result[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `" . str_replace('`', '``', $table) . "` WHERE id NOT LIKE '21000000-%' AND id NOT LIKE '22000000-%'")->fetchColumn();
            } else {
                $result[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`')->fetchColumn();
            }
        } catch (Throwable) {
            $result[$table] = -1;
        }
    }
    return $result;
}

function demo_owned_count(PDO $pdo, string $table): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM `" . str_replace('`', '``', $table) . "` WHERE id LIKE '21000000-%' OR id LIKE '22000000-%'")->fetchColumn();
}

$schema = (string) getenv('COMPLETE_AI_DEMO_TEST_SCHEMA');
demo_mysql_assert($schema !== '' && preg_match('/^talenthub_complete_demo_test_[a-z0-9_]+$/', $schema) === 1, 'COMPLETE_AI_DEMO_TEST_SCHEMA must match ^talenthub_complete_demo_test_[a-z0-9_]+$');
demo_mysql_assert($schema !== 'talenthub_local', 'Refusing to run disposable test on talenthub_local');

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

    // Baseline outside namespace
    $baselineOutside = demo_outside_namespace_counts($pdo, $seeder->touchedTables());

    $first = $seeder->run($pdo, 'test', $password, $clock);
    $firstCounts = demo_table_counts($pdo, $seeder->touchedTables());
    $second = $seeder->run($pdo, 'test', $password, $clock);
    $secondCounts = demo_table_counts($pdo, $seeder->touchedTables());

    demo_mysql_assert($firstCounts === $secondCounts, 'second seed has zero count drift');
    demo_mysql_assert(demo_outside_namespace_counts($pdo, $seeder->touchedTables()) === $baselineOutside, 'unrelated rows unchanged');
    demo_mysql_assert((int) $pdo->query("SELECT COUNT(*) FROM schools WHERE id LIKE '22000000-%' AND name='Đại học FPT'")->fetchColumn() === 1, 'FPT school exists');
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

    foreach (CompleteAiDemoDataset::heroStudentIds() as $studentId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_attempts WHERE studentId=? AND status='submitted'");
        $stmt->execute([$studentId]);
        demo_mysql_assert((int) $stmt->fetchColumn() === 4, 'hero has four submitted attempts: ' . $studentId);
    }

    $submittedPerLearner = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM (
    SELECT studentId
    FROM test_attempts
    WHERE status = 'submitted'
    GROUP BY studentId
    HAVING COUNT(*) < 2
) AS incomplete_learners
SQL)->fetchColumn();
    demo_mysql_assert($submittedPerLearner === 0, 'every learner has at least two submitted attempts');

    $answerCountMismatch = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM (
    SELECT attempts.id
    FROM test_attempts AS attempts
    JOIN learner_assessment_attempt_metadata AS metadata ON metadata.attemptId = attempts.id
    JOIN learner_assessment_question_versions AS questions ON questions.versionId = metadata.versionId
    LEFT JOIN learner_assessment_answers AS answers
      ON answers.attemptId = attempts.id AND answers.questionId = questions.questionId
    WHERE attempts.status = 'submitted'
    GROUP BY attempts.id
    HAVING COUNT(answers.id) <> SUM(questions.required)
) AS invalid_answers
SQL)->fetchColumn();
    demo_mysql_assert($answerCountMismatch === 0, 'submitted attempts answer every required published question');

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
} finally {
    if ($adminPdo instanceof PDO) {
        try {
            $adminPdo->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '``', $schema) . '`');
        } catch (Throwable) {
        }
    }
    putenv('COMPLETE_AI_DEMO_TEST_SCHEMA');
    unset($_ENV['COMPLETE_AI_DEMO_TEST_SCHEMA']);
}
