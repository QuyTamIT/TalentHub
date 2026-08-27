<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dashboardSource = (string) file_get_contents($root . '/app/learner/index.php');
$sharedSource = (string) file_get_contents($root . '/app/learner/includes/student-data.php');

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(str_contains($dashboardSource, "require_once __DIR__ . '/includes/activity-data.php'"), 'Dashboard loads the learner activity adapter itself');
$assert(str_contains($dashboardSource, 'learner_activity_catalog()'), 'Dashboard calls the scoped discovery catalog');
$assert(str_contains($dashboardSource, 'array_slice(learner_activity_catalog(), 0, 3)'), 'Dashboard limits discovery cards to the first three');
$assert(!str_contains($sharedSource, 'learner_activity_catalog()'), 'Shared student-data does not run a global upcoming activity query');
$assert(str_contains($dashboardSource, 'Hoạt động đang mở cho bạn'), 'Dashboard labels discovery separately');
$assert(str_contains($dashboardSource, 'Hoạt động đã xác nhận'), 'Dashboard labels confirmed history separately');
$assert(str_contains($dashboardSource, 'activity-detail.php?id='), 'Dashboard activity cards link to scoped details');
$assert(str_contains($dashboardSource, 'activity-history.php'), 'Dashboard confirmed history links to activity history');
$assert(str_contains($dashboardSource, 'cover_image_url'), 'Dashboard uses the local activity cover field');
$assert(str_contains($dashboardSource, 'assets/activities/illustrations/hero-detail.svg'), 'Dashboard has the approved local cover fallback');
$assert(!preg_match('/(?:RecommendationService|RecommendationProvider|->recommend\s*\(|->generate\s*\()/i', $dashboardSource), 'Dashboard GET does not call an AI provider or model');

require_once $root . '/app/learner/data/bootstrap.php';
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, createdByTeacherId TEXT NOT NULL, title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT NULL, capacity INTEGER NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, audienceScope TEXT NOT NULL, displayCategory TEXT NOT NULL, filterCategory TEXT NOT NULL, locationName TEXT NOT NULL, coverImageUrl TEXT NULL, coverImageAlt TEXT NULL)');
$pdo->exec('CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT NOT NULL, registrationClosesAt TEXT NOT NULL, cancellationClosesAt TEXT NOT NULL, approvalMode TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL)');

$schoolA = '10000000-0000-4000-8000-000000000001';
$schoolB = '10000000-0000-4000-8000-000000000002';
$studentA = '20000000-0000-4000-8000-000000000001';
$teacher = '30000000-0000-4000-8000-000000000001';
$openIds = [
    '40000000-0000-4000-8000-000000000001',
    '40000000-0000-4000-8000-000000000002',
    '40000000-0000-4000-8000-000000000003',
];
$pdo->exec("INSERT INTO schools VALUES ('{$schoolA}', 'School A'), ('{$schoolB}', 'School B')");
$pdo->exec("INSERT INTO classes VALUES ('50000000-0000-4000-8000-000000000001', '{$schoolA}')");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$studentA}', '50000000-0000-4000-8000-000000000001')");
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$stamp = static fn (DateTimeImmutable $date): string => $date->format('Y-m-d H:i:s');
$insertActivity = $pdo->prepare('INSERT INTO activities VALUES (?,?,?,?,?,?,?,?,?,?)');
$insertDetail = $pdo->prepare('INSERT INTO activity_details VALUES (?,?,?,?,?,?,?)');
$insertPolicy = $pdo->prepare('INSERT INTO activity_registration_policies VALUES (?,?,?,?,?)');
$fixtures = [
    [$openIds[0], $schoolA, 'Open One', 10, 'published', 2],
    [$openIds[1], $schoolA, 'Open Two', 11, 'published', 2],
    [$openIds[2], $schoolA, 'Open Three', 12, 'published', 2],
    ['40000000-0000-4000-8000-000000000004', $schoolB, 'Foreign Secret', 9, 'published', 2],
    ['40000000-0000-4000-8000-000000000005', $schoolA, 'Completed', -48, 'completed', 2],
    ['40000000-0000-4000-8000-000000000006', $schoolA, 'Full', 13, 'published', 1],
];
foreach ($fixtures as [$id, $schoolId, $title, $hours, $status, $capacity]) {
    $start = $now->modify(sprintf('%+d hours', $hours));
    $end = $start->modify('+2 hours');
    $insertActivity->execute([$id, $schoolId, $teacher, $title, 'career_technical', $stamp($start), $stamp($end), $capacity, $status, $stamp($now->modify('-10 days'))]);
    $insertDetail->execute([$id, 'school_only', 'Kỹ thuật', 'Kỹ thuật', 'Phòng Lab', 'assets/activities/covers/talenthub-stem-robotics.webp', 'Hoạt động kỹ thuật']);
    $insertPolicy->execute([$id, $stamp($now->modify('-1 day')), $stamp($now->modify('+8 hours')), $stamp($now->modify('+9 hours')), 'automatic']);
}
$pdo->exec("INSERT INTO activity_registrations VALUES ('60000000-0000-4000-8000-000000000001', '40000000-0000-4000-8000-000000000006', '70000000-0000-4000-8000-000000000001', 'approved')");

learner_configure_data(['source' => 'database', 'pdo' => $pdo, 'student_id' => $studentA]);
require_once $root . '/app/learner/includes/activity-data.php';
$catalog = learner_activity_catalog();
$assert(array_column($catalog, 'id') === $openIds, 'Dashboard discovery fixture exposes exactly three ordered own-school open activities');
$assert(!str_contains(json_encode($catalog, JSON_UNESCAPED_UNICODE) ?: '', 'Foreign Secret'), 'Dashboard discovery never leaks cross-school metadata');
$assert(count($catalog) === 3, 'Dashboard discovery excludes completed and full activities before the three-card slice');

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_dashboard_integration_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_dashboard_integration_test: OK\n";
