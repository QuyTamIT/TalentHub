<?php

declare(strict_types=1);

use TalentHub\Learner\Seeds\Staging\LearnerCareerActivitySeeder;

$autoloadPath = dirname(__DIR__) . '/Database/seeds/learner/Staging/LearnerCareerActivitySeeder.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

function seeder_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function seeder_expect_exception(callable $fn, string $message): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        return;
    }
    fwrite(STDERR, "Expected exception not thrown: {$message}\n");
    exit(1);
}

function seeder_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, createdByTeacherId TEXT NOT NULL, title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT NULL, capacity INTEGER NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)');

    $pdo->exec("INSERT INTO schools (id, name, status) VALUES ('00000000-0000-4000-8000-000000000010', 'Synthetic AI Pilot School', 'active')");
    $pdo->exec("INSERT INTO teacher_profiles (id, schoolId) VALUES ('00000000-0000-4000-8000-000000000021', '00000000-0000-4000-8000-000000000010')");

    return $pdo;
}

seeder_assert(class_exists(LearnerCareerActivitySeeder::class), 'LearnerCareerActivitySeeder class exists');

$schoolId = '00000000-0000-4000-8000-000000000010';
$teacherId = '00000000-0000-4000-8000-000000000021';
$disposableSchema = 'talenthub_ai_career_group_verify_test';

// Test 1: Refuses talenthub_local
$pdo = seeder_fixture();
seeder_expect_exception(static function () use ($pdo, $schoolId, $teacherId): void {
    $seeder = new LearnerCareerActivitySeeder($pdo, 'talenthub_local', $schoolId, $teacherId);
    $seeder->seed();
}, 'seeder refuses talenthub_local schema');

// Test 1b: Explicit main-schema opt-in permits the reviewed local seed only
$mainPdo = seeder_fixture();
$mainSeeder = new LearnerCareerActivitySeeder(
    $mainPdo,
    'talenthub_local',
    $schoolId,
    $teacherId,
    allowMainSchema: true,
);
$mainRun = $mainSeeder->seed();
seeder_assert($mainRun['declared'] === 8, 'main-schema opt-in declares exactly 8 career activities');
seeder_assert($mainRun['inserted'] === 8, 'main-schema opt-in inserts all 8 career activities');
seeder_assert($mainRun['existing'] === 0, 'main-schema opt-in starts with 0 existing activities');

// Test 2: Refuses missing parent school / teacher
seeder_expect_exception(static function () use ($pdo, $disposableSchema): void {
    $seeder = new LearnerCareerActivitySeeder($pdo, $disposableSchema, 'non-existent-school', 'non-existent-teacher');
    $seeder->seed();
}, 'seeder refuses non-existent parent school/teacher');

// Test 3: First run inserts all 8 declared activities
$pdo = seeder_fixture();
$seeder = new LearnerCareerActivitySeeder($pdo, $disposableSchema, $schoolId, $teacherId);
$firstRun = $seeder->seed();
seeder_assert($firstRun['declared'] === 8, 'declares exactly 8 career activities');
seeder_assert($firstRun['inserted'] === 8, 'first run inserts all 8 activities');
seeder_assert($firstRun['existing'] === 0, 'first run has 0 existing');

// Verify distribution: 4 categories, each has 2 activities (1 club, 1 project/workshop)
$countsByCategory = [];
$rows = $pdo->query('SELECT category, title, status FROM activities ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
seeder_assert(count($rows) === 8, 'database contains 8 activities');
foreach ($rows as $row) {
    seeder_assert($row['status'] === 'published', 'every seeded activity is published');
    $countsByCategory[$row['category']] = ($countsByCategory[$row['category']] ?? 0) + 1;
}
seeder_assert(($countsByCategory['career_technical'] ?? 0) === 2, 'career_technical has 2 activities');
seeder_assert(($countsByCategory['career_business'] ?? 0) === 2, 'career_business has 2 activities');
seeder_assert(($countsByCategory['career_arts'] ?? 0) === 2, 'career_arts has 2 activities');
seeder_assert(($countsByCategory['career_sports_academic'] ?? 0) === 2, 'career_sports_academic has 2 activities');

// Test 4: Second run is idempotent no-op
$secondRun = $seeder->seed();
seeder_assert($secondRun['declared'] === 8, 'second run declared is 8');
seeder_assert($secondRun['inserted'] === 0, 'second run inserts 0');
seeder_assert($secondRun['existing'] === 8, 'second run finds 8 existing matching rows');

// Verify total rows unchanged
$totalCount = (int) $pdo->query('SELECT COUNT(*) FROM activities')->fetchColumn();
seeder_assert($totalCount === 8, 'total activities count is still 8 after second run');

// Test 5: Content conflict fails closed
// Modify one activity title in the DB to cause a conflict
$pdo->exec("UPDATE activities SET title = 'Conflicting Modified Title' WHERE id = '00000000-0000-4000-8000-000000000301'");
seeder_expect_exception(static function () use ($seeder): void {
    $seeder->seed();
}, 'seeder throws exception on content conflict');

echo "learner_career_activity_seeder_test: OK\n";
