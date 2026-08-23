<?php

declare(strict_types=1);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

require dirname(__DIR__) . '/src/Modules/School/Service/SchoolCheckinAggregateService.php';

use TalentHub\Modules\School\Service\SchoolCheckinAggregateService;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT)');
$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT)');
$pdo->exec('CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT, status TEXT)');
$pdo->exec('CREATE TABLE experience_logs (id TEXT PRIMARY KEY, activityId TEXT, checkinId TEXT, hours REAL, status TEXT)');
$pdo->exec("INSERT INTO activities VALUES ('act-a','school-a'),('act-b','school-b')");
$pdo->exec("INSERT INTO activity_registrations VALUES ('reg-a','act-a','student-a'),('reg-b','act-b','student-b')");
$pdo->exec("INSERT INTO checkins VALUES ('chk-a','reg-a','confirmed'),('chk-b','reg-b','confirmed'),('chk-pending','reg-a','pending')");
$pdo->exec("INSERT INTO experience_logs VALUES ('exp-a','act-a','chk-a',2.5,'confirmed'),('exp-a-pending','act-a','chk-pending',9,'confirmed'),('exp-b','act-b','chk-b',5,'confirmed'),('exp-unconfirmed','act-a','chk-a',3,'pending')");

$aggregate = (new SchoolCheckinAggregateService($pdo))->confirmedForSchool('school-a');
$assert($aggregate['confirmedCheckins'] === 1, 'School aggregate counts only confirmed check-ins in scope.');
$assert($aggregate['confirmedHours'] === '2.50', 'School aggregate sums only confirmed hours in scope.');

$schoolDashboardSource = file_get_contents(dirname(__DIR__) . '/src/Modules/School/Service/SchoolDashboardService.php') ?: '';
$assert(str_contains($schoolDashboardSource, "'checkinExperience'"), 'School analytics endpoint consumes the scoped Phase 5 aggregate.');
$assert(str_contains($schoolDashboardSource, 'confirmedForSchool($school[\'id\'])'), 'School scope is derived from the authenticated School profile.');

echo "phase5_school_aggregate_test: OK\n";
