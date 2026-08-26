<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NULL)');
$pdo->exec('CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, displayCategory TEXT NULL, filterCategory TEXT NULL, locationName TEXT NULL, coverImageUrl TEXT NULL, coverImageAlt TEXT NULL)');
$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL)');
$pdo->exec('CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, activityId TEXT NOT NULL, checkinId TEXT NOT NULL, hours REAL NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL)');

$student = '11111111-1111-4111-8111-111111111111';
$other = '22222222-2222-4222-8222-222222222222';
$pdo->exec("INSERT INTO activities VALUES ('activity-ok', 'Confirmed activity', 'career_technical', '2026-08-25 09:00:00'), ('activity-other', 'Other activity', 'career_business', '2026-08-26 09:00:00')");
$pdo->exec("INSERT INTO activity_details VALUES ('activity-ok', 'Kỹ thuật', 'Kỹ thuật', 'Phòng Robotics B305', 'assets/activities/covers/talenthub-stem-robotics.webp', 'Ảnh robotics đã xác nhận')");
foreach ([
    ['reg-ok', 'activity-ok', $student, 'attended'],
    ['reg-approved', 'activity-ok', $student, 'approved'],
    ['reg-no-show', 'activity-ok', $student, 'no_show'],
    ['reg-other', 'activity-other', $other, 'attended'],
] as $row) {
    $statement = $pdo->prepare('INSERT INTO activity_registrations VALUES (?,?,?,?)');
    $statement->execute($row);
}
foreach ([
    ['checkin-ok', 'reg-ok', 'confirmed', '2026-08-25 10:00:00'],
    ['checkin-approved', 'reg-approved', 'confirmed', '2026-08-25 10:00:00'],
    ['checkin-no-show', 'reg-no-show', 'confirmed', '2026-08-25 10:00:00'],
    ['checkin-other', 'reg-other', 'confirmed', '2026-08-25 10:00:00'],
    ['checkin-unconfirmed', 'reg-ok', 'checked_in', null],
] as $row) {
    $statement = $pdo->prepare('INSERT INTO checkins VALUES (?,?,?,?)');
    $statement->execute($row);
}
foreach ([
    ['experience-ok', $student, 'activity-ok', 'checkin-ok', 3.0, 'confirmed', '2026-08-25 11:00:00'],
    ['experience-approved', $student, 'activity-ok', 'checkin-approved', 4.0, 'confirmed', '2026-08-25 11:00:00'],
    ['experience-no-show', $student, 'activity-ok', 'checkin-no-show', 5.0, 'confirmed', '2026-08-25 11:00:00'],
    ['experience-unconfirmed-checkin', $student, 'activity-ok', 'checkin-unconfirmed', 6.0, 'confirmed', '2026-08-25 11:00:00'],
    ['experience-other', $other, 'activity-other', 'checkin-other', 9.0, 'confirmed', '2026-08-25 11:00:00'],
] as $row) {
    $statement = $pdo->prepare('INSERT INTO experience_logs VALUES (?,?,?,?,?,?,?)');
    $statement->execute($row);
}

$repository = new DatabaseTalentPassportRepository($pdo);
$experienceMethod = new ReflectionMethod($repository, 'experience');
$experience = $experienceMethod->invoke($repository, $student);
$assert(($experience['confirmed_hours'] ?? null) === 3.0, 'Talent Passport sums only attended + confirmed check-in + confirmed experience evidence');
$assert(array_column($experience['confirmed_entries'] ?? [], 'id') === ['experience-ok'], 'Talent Passport excludes approved, no_show, unconfirmed check-in, and foreign evidence');
$entry = $experience['confirmed_entries'][0] ?? [];
$assert(($entry['activity_category'] ?? null) === 'career_technical', 'Confirmed evidence preserves canonical category truth');
$assert(($entry['display_category'] ?? null) === 'Kỹ thuật', 'Confirmed evidence includes presentation display category');
$assert(($entry['filter_category'] ?? null) === 'Kỹ thuật', 'Confirmed evidence includes filter category');
$assert(($entry['activity_start_at'] ?? null) === '2026-08-25 09:00:00', 'Confirmed evidence includes activity start');
$assert(($entry['location_name'] ?? null) === 'Phòng Robotics B305', 'Confirmed evidence includes real location');
$assert(($entry['cover_image_url'] ?? null) === 'assets/activities/covers/talenthub-stem-robotics.webp', 'Confirmed evidence includes local cover');
$assert(($entry['cover_image_alt'] ?? null) === 'Ảnh robotics đã xác nhận', 'Confirmed evidence includes cover alt');

$summaryMethod = new ReflectionMethod($repository, 'activitySummary');
$summary = $summaryMethod->invoke($repository, $student, (float) ($experience['confirmed_hours'] ?? 0));
$assert(($summary['registered_count'] ?? null) === 3, 'Legacy registered_count remains the total learner registration count');
$assert(($summary['active_registered_count'] ?? null) === 1, 'active_registered_count contains pending, approved, and waitlisted only');
$assert(($summary['attended_count'] ?? null) === 1, 'attended_count excludes no_show');

$legacy = new PDO('sqlite::memory:');
$legacy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$legacy->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, title TEXT NOT NULL, category TEXT NOT NULL)');
$legacy->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL)');
$legacy->exec('CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL)');
$legacy->exec('CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, activityId TEXT NOT NULL, checkinId TEXT NOT NULL, hours REAL NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL)');
$legacy->exec("INSERT INTO activities VALUES ('legacy-activity', 'Legacy activity', 'career_technical')");
$legacy->exec("INSERT INTO activity_registrations VALUES ('legacy-registration', 'legacy-activity', '{$student}', 'attended')");
$legacy->exec("INSERT INTO checkins VALUES ('legacy-checkin', 'legacy-registration', 'confirmed', '2026-08-25 10:00:00')");
$legacy->exec("INSERT INTO experience_logs VALUES ('legacy-experience', '{$student}', 'legacy-activity', 'legacy-checkin', 1.0, 'confirmed', '2026-08-25 11:00:00')");
$legacyRepository = new DatabaseTalentPassportRepository($legacy);
$legacyExperience = (new ReflectionMethod($legacyRepository, 'experience'))->invoke($legacyRepository, $student);
$assert(($legacyExperience['confirmed_hours'] ?? null) === 1.0, 'Legacy schema without optional activity metadata remains compatible');

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_passport_evidence_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_passport_evidence_test: OK\n";
