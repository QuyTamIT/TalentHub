<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$seederPath = $root . '/Database/seeds/learner/Activity/SchoolActivityCatalogSeeder.php';
$runnerPath = $root . '/Database/seeds/learner/Activity/run-school-activity-catalog.php';
$datasetPath = $root . '/Database/seeds/learner/Activity/SchoolActivityCatalogDataset.php';

$missing = array_values(array_filter([$seederPath, $runnerPath], static fn (string $path): bool => !is_file($path)));
if ($missing !== []) {
    fwrite(STDERR, "learner_school_activity_catalog_seeder_test: RED\n- Missing Phase 4A file: " . implode("\n- Missing Phase 4A file: ", $missing) . "\n");
    exit(1);
}

require_once $datasetPath;
require_once $seederPath;

use TalentHub\Learner\Seeds\Activity\SchoolActivityCatalogDataset;
use TalentHub\Learner\Seeds\Activity\SchoolActivityCatalogSeeder;
use TalentHub\Learner\Seeds\Activity\SchoolActivityQrHandoff;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$records = SchoolActivityCatalogDataset::records();
$existing = array_values(array_filter($records, static fn (array $row): bool => ($row['source'] ?? null) === 'existing'));
$new = array_values(array_filter($records, static fn (array $row): bool => ($row['source'] ?? null) === 'new'));
$assert(count($existing) === 5, 'Dataset marks exactly five immutable existing activities.');
$assert(count($new) === 12, 'Dataset marks exactly twelve new activities.');
foreach ($existing as $record) {
    $assert(($record['preserveExistingFields'] ?? null) === true, 'Every existing activity is explicitly preserve-only.');
    $assert(is_array($record['existingActivitySnapshot'] ?? null) && count($record['existingActivitySnapshot']) === 11, 'Every existing activity declares its exact 11-column snapshot.');
}

$runner = (string) file_get_contents($runnerPath);
$assert(str_contains($runner, '--dry-run') && str_contains($runner, '--apply') && str_contains($runner, '--allow-primary'), 'Runner exposes explicit dry-run, apply, and primary approval gates.');
$assert(str_contains($runner, "'talenthub_activity_phase4_disposable'") || str_contains($runner, 'talenthub_activity_phase4_disposable'), 'Runner recognizes only the approved disposable Phase 4 schema.');

if (!method_exists(SchoolActivityCatalogSeeder::class, 'preflight') || !method_exists(SchoolActivityCatalogSeeder::class, 'run')) {
    $failures[] = 'Seeder exposes preflight() and transactional run().';
}

$makeFixture = static function () use ($records): PDO {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ([
        'CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL)',
        'CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL)',
        'CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, createdByTeacherId TEXT NOT NULL, title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT NOT NULL, capacity INTEGER NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)',
        'CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, responsibleTeacherId TEXT, audienceScope TEXT NOT NULL, displayCategory TEXT NOT NULL, filterCategory TEXT NOT NULL, summary TEXT NOT NULL, description TEXT NOT NULL, experienceHighlights TEXT NOT NULL, skillTags TEXT NOT NULL, eligibilityRules TEXT NOT NULL, benefitItems TEXT NOT NULL, locationName TEXT NOT NULL, locationAddress TEXT, deliveryMode TEXT NOT NULL, onlineMeetingUrl TEXT, organizerName TEXT NOT NULL, organizerContact TEXT, organizerEmail TEXT, organizerPhone TEXT, coverImageUrl TEXT, coverImageAlt TEXT, feeAmount NUMERIC NOT NULL, currency TEXT NOT NULL, targetAudience TEXT NOT NULL, certificateLabel TEXT, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)',
        'CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT NOT NULL, registrationClosesAt TEXT NOT NULL, cancellationClosesAt TEXT NOT NULL, approvalMode TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)',
        'CREATE TABLE activity_experience_policies (activityId TEXT PRIMARY KEY, confirmedHours NUMERIC NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)',
        'CREATE TABLE activity_qr_sessions (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, createdByTeacherId TEXT NOT NULL, tokenHash TEXT NOT NULL UNIQUE, status TEXT NOT NULL, expiresAt TEXT NOT NULL, maxScans INTEGER NOT NULL, usedScans INTEGER NOT NULL, revokedAt TEXT, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)',
        'CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT, registeredAt TEXT, updatedAt TEXT)',
        'CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT, qrSessionId TEXT, status TEXT, checkedInAt TEXT, confirmedAt TEXT, createdAt TEXT)',
        'CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, checkinId TEXT, hours NUMERIC, status TEXT, auditReason TEXT, confirmedAt TEXT, createdAt TEXT)',
        'CREATE TABLE assessments (id TEXT PRIMARY KEY)',
        'CREATE TABLE assessment_scores (id TEXT PRIMARY KEY)',
        'CREATE TABLE notifications (id TEXT PRIMARY KEY)',
    ] as $sql) {
        $pdo->exec($sql);
    }
    $schoolInsert = $pdo->prepare('INSERT OR IGNORE INTO schools (id,name,status) VALUES (?,?,?)');
    foreach ($records as $record) {
        $schoolInsert->execute([$record['school_id'], $record['school_name'], 'active']);
    }
    $teacherInsert = $pdo->prepare('INSERT OR IGNORE INTO teacher_profiles (id,schoolId) VALUES (?,?)');
    foreach ($records as $record) {
        $teacherInsert->execute([$record['details']['responsibleTeacherId'], $record['school_id']]);
    }
    $activityInsert = $pdo->prepare('INSERT INTO activities (id,schoolId,createdByTeacherId,title,category,startAt,endAt,capacity,status,createdAt,updatedAt) VALUES (:id,:schoolId,:createdByTeacherId,:title,:category,:startAt,:endAt,:capacity,:status,:createdAt,:updatedAt)');
    foreach ($records as $record) {
        if (($record['source'] ?? null) === 'existing') {
            $activityInsert->execute($record['existingActivitySnapshot']);
        }
    }
    return $pdo;
};

$clock = new DateTimeImmutable('2026-08-25 00:00:00', new DateTimeZone('UTC'));
$qrPython = (string) getenv('TALENTHUB_QR_PYTHON');
if ($qrPython === '' || !is_file($qrPython)) {
    fwrite(STDERR, "learner_school_activity_catalog_seeder_test: RED\n- TALENTHUB_QR_PYTHON must point to the approved bundled Python runtime.\n");
    exit(1);
}
$qrOutputDirectories = [];
$makeHandoff = static function (string $label) use ($root, $qrPython, &$qrOutputDirectories): SchoolActivityQrHandoff {
    $relative = '.codex_tmp/activity-qr-fixtures/' . $label . '-' . bin2hex(random_bytes(6));
    $handoff = new SchoolActivityQrHandoff($root, $relative, $qrPython);
    $qrOutputDirectories[] = $handoff->outputDirectory();
    return $handoff;
};
$removeTree = static function (string $directory) use ($root): void {
    $approved = realpath($root) . DIRECTORY_SEPARATOR . '.codex_tmp' . DIRECTORY_SEPARATOR . 'activity-qr-fixtures' . DIRECTORY_SEPARATOR;
    if (!str_starts_with(strtolower(str_replace('/', DIRECTORY_SEPARATOR, $directory)), strtolower($approved)) || !is_dir($directory)) {
        return;
    }
    foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $name) {
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_file($path) || is_link($path)) {
            unlink($path);
        }
    }
    rmdir($directory);
};
register_shutdown_function(static function () use (&$qrOutputDirectories, $removeTree): void {
    foreach ($qrOutputDirectories as $directory) {
        $removeTree($directory);
    }
});
$assertThrows = static function (callable $operation, string $needle, string $message) use ($assert): void {
    try {
        $operation();
        $assert(false, $message);
    } catch (Throwable $exception) {
        $assert(str_contains($exception->getMessage(), $needle), $message . ' Actual: ' . $exception->getMessage());
    }
};

$protectionPdo = $makeFixture();
$assertThrows(
    static fn () => (new SchoolActivityCatalogSeeder($protectionPdo, 'talenthub_local', true, $clock))->run(),
    'talenthub_local',
    'Seeder always refuses talenthub_local.',
);
$assertThrows(
    static fn () => (new SchoolActivityCatalogSeeder($protectionPdo, 'talenthub', false, $clock))->run(),
    '--allow-primary',
    'Seeder refuses a talenthub write without explicit primary approval.',
);
$assertThrows(
    static fn () => (new SchoolActivityCatalogSeeder($protectionPdo, 'unexpected_schema', false, $clock))->run(),
    'not approved',
    'Seeder refuses every unapproved schema name.',
);

$pdo = $makeFixture();
$oldBefore = $pdo->query("SELECT * FROM activities WHERE id NOT LIKE '31000000-%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$rawTokens = [];
$tokenFactory = static function () use (&$rawTokens): string {
    $token = bin2hex(random_bytes(32));
    $rawTokens[] = $token;
    return $token;
};
$handoff = $makeHandoff('sqlite-success');
$seeder = new SchoolActivityCatalogSeeder($pdo, SchoolActivityCatalogSeeder::SQLITE_TEST_SCHEMA, false, $clock, $tokenFactory, $handoff);
$first = $seeder->run();
$assert($first === ['existing' => 5, 'inserted' => 12, 'details' => 17, 'registration_policies' => 15, 'experience_policies' => 15, 'qr_sessions' => 3], 'First SQLite run inserts exactly the Phase 4A catalog.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM activities')->fetchColumn() === 17, 'SQLite rehearsal has exactly 17 activities.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM activity_details')->fetchColumn() === 17, 'SQLite rehearsal has exactly 17 details.');
$assert((int) $pdo->query("SELECT COUNT(*) FROM activity_details WHERE coverImageAlt IS NOT NULL AND TRIM(coverImageAlt)<>''")->fetchColumn() === 17, 'All seventeen seeded activity details have nonempty cover alt text.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM activity_registration_policies')->fetchColumn() === 15, 'SQLite rehearsal has exactly 15 registration policies.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM activity_experience_policies')->fetchColumn() === 15, 'SQLite rehearsal has exactly 15 experience policies.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM activity_qr_sessions')->fetchColumn() === 3, 'SQLite rehearsal creates exactly three QR sessions.');
$assert(count($rawTokens) === 3, 'Exactly three opaque QR tokens are generated in memory.');
foreach ($rawTokens as $token) {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM activity_qr_sessions WHERE tokenHash=?');
    $statement->execute([hash('sha256', $token)]);
    $assert((int) $statement->fetchColumn() === 1, 'Database stores only the SHA-256 hash of each generated QR token.');
}
$oldAfter = $pdo->query("SELECT * FROM activities WHERE id NOT LIKE '31000000-%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$assert($oldAfter === $oldBefore, 'All eleven columns of the five existing activities remain byte-for-byte unchanged.');
$assert((int) $pdo->query("SELECT COUNT(*) FROM activity_registration_policies p JOIN activities a ON a.id=p.activityId WHERE a.status='completed'")->fetchColumn() === 0, 'Completed activities receive no registration policy.');
$assert((int) $pdo->query("SELECT COUNT(*) FROM activity_experience_policies p JOIN activities a ON a.id=p.activityId WHERE a.status='completed'")->fetchColumn() === 0, 'Completed activities receive no experience policy.');
$hashesBefore = $pdo->query("SELECT id,tokenHash FROM activity_qr_sessions ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
$qrFilesBefore = [];
foreach (glob($handoff->outputDirectory() . DIRECTORY_SEPARATOR . '*.png') ?: [] as $path) {
    $qrFilesBefore[basename($path)] = hash_file('sha256', $path);
}
$second = $seeder->run();
$hashesAfter = $pdo->query("SELECT id,tokenHash FROM activity_qr_sessions ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
$qrFilesAfter = [];
foreach (glob($handoff->outputDirectory() . DIRECTORY_SEPARATOR . '*.png') ?: [] as $path) {
    $qrFilesAfter[basename($path)] = hash_file('sha256', $path);
}
$assert($second['inserted'] === 0 && $second['existing'] === 17, 'Second SQLite run is idempotent and inserts no activity.');
$assert($hashesAfter === $hashesBefore && count($rawTokens) === 3, 'Second run neither regenerates nor changes a valid QR token hash.');
$assert(count($qrFilesBefore) === 3 && $qrFilesAfter === $qrFilesBefore, 'Second run creates no QR PNG and changes no existing QR file.');
$assertThrows(
    static fn () => (new SchoolActivityCatalogSeeder($pdo, SchoolActivityCatalogSeeder::SQLITE_TEST_SCHEMA, false, $clock))->run(),
    'one-time handoff is unavailable',
    'A fresh runner seeing existing fixture sessions without their handoff fails closed and never rotates tokens.',
);

$schoolRows = $pdo->query("SELECT a.schoolId,COUNT(*) total,SUM(p.approvalMode='automatic') automaticCount,SUM(p.approvalMode='teacher_review') reviewCount FROM activities a JOIN activity_registration_policies p ON p.activityId=a.id JOIN activity_details d ON d.activityId=a.id WHERE a.status='published' AND d.audienceScope='school_only' GROUP BY a.schoolId ORDER BY a.schoolId")->fetchAll(PDO::FETCH_ASSOC);
$assert(count($schoolRows) === 3, 'SQLite policy distribution covers exactly three schools.');
foreach ($schoolRows as $row) {
    $assert((int) $row['total'] === 5 && (int) $row['automaticCount'] === 4 && (int) $row['reviewCount'] === 1, 'Each school has five published activities: four automatic and one teacher-review.');
}

$rollbackPdo = $makeFixture();
$rollbackPdo->exec("CREATE TRIGGER phase4_fail_details BEFORE INSERT ON activity_details WHEN NEW.activityId='31000000-0000-4000-8000-000000000006' BEGIN SELECT RAISE(ABORT, 'injected detail failure'); END");
$rollbackHandoff = $makeHandoff('sqlite-rollback');
$assertThrows(
    static fn () => (new SchoolActivityCatalogSeeder($rollbackPdo, SchoolActivityCatalogSeeder::SQLITE_TEST_SCHEMA, false, $clock, null, $rollbackHandoff))->run(),
    'injected detail failure',
    'Injected detail failure aborts the seeder.',
);
$assert((int) $rollbackPdo->query('SELECT COUNT(*) FROM activities')->fetchColumn() === 5, 'Injected failure rolls back all twelve new activities.');
$assert((int) $rollbackPdo->query('SELECT COUNT(*) FROM activity_details')->fetchColumn() === 0, 'Injected failure leaves no partial details.');
$assert((int) $rollbackPdo->query('SELECT COUNT(*) FROM activity_registration_policies')->fetchColumn() === 0, 'Injected failure leaves no partial policies.');
$assert((int) $rollbackPdo->query('SELECT COUNT(*) FROM activity_qr_sessions')->fetchColumn() === 0, 'Injected failure leaves no QR fixture.');
$assert(!is_dir($rollbackHandoff->outputDirectory()), 'Injected database rollback removes all pending QR handoff files and its empty output directory.');

$finalizePdo = $makeFixture();
$finalizeRelative = '.codex_tmp/activity-qr-fixtures/sqlite-finalize-' . bin2hex(random_bytes(6));
$finalizeHandoff = new SchoolActivityQrHandoff(
    $root,
    $finalizeRelative,
    $qrPython,
    null,
    static fn (string $from, string $to): bool => false,
);
$qrOutputDirectories[] = $finalizeHandoff->outputDirectory();
$assertThrows(
    static fn () => (new SchoolActivityCatalogSeeder($finalizePdo, SchoolActivityCatalogSeeder::SQLITE_TEST_SCHEMA, false, $clock, null, $finalizeHandoff))->run(),
    'Database committed, but QR handoff finalization failed',
    'A post-commit rename failure is reported explicitly without token rotation.',
);
$assert((int) $finalizePdo->query('SELECT COUNT(*) FROM activities')->fetchColumn() === 17 && (int) $finalizePdo->query('SELECT COUNT(*) FROM activity_qr_sessions')->fetchColumn() === 3, 'Post-commit rename failure does not pretend the committed database transaction rolled back.');
$assert(count(glob($finalizeHandoff->outputDirectory() . DIRECTORY_SEPARATOR . '*.pending') ?: []) === 4, 'Post-commit rename failure retains all three QR PNG and manifest pending files for recovery.');

$parentPdo = $makeFixture();
$parentPdo->exec("UPDATE teacher_profiles SET schoolId='wrong-school' WHERE id='10000000-0000-4000-8000-000000000022'");
$assertThrows(
    static fn () => (new SchoolActivityCatalogSeeder($parentPdo, SchoolActivityCatalogSeeder::SQLITE_TEST_SCHEMA, false, $clock))->run(),
    'does not belong',
    'Teacher/activity cross-school mismatch fails closed before writes.',
);
$assert((int) $parentPdo->query('SELECT COUNT(*) FROM activities')->fetchColumn() === 5, 'Cross-school failure leaves activity rows untouched.');

$snapshotPdo = $makeFixture();
$snapshotPdo->exec("UPDATE activities SET title='tampered' WHERE id='21000000-8e2d-4dae-8d47-ea4ac11c3dc3'");
$assertThrows(
    static fn () => (new SchoolActivityCatalogSeeder($snapshotPdo, SchoolActivityCatalogSeeder::SQLITE_TEST_SCHEMA, false, $clock))->run(),
    'snapshot mismatch',
    'A changed existing-activity snapshot fails closed.',
);

$collisionPdo = $makeFixture();
$collisionPdo->prepare('INSERT INTO activities (id,schoolId,createdByTeacherId,title,category,startAt,endAt,capacity,status,createdAt,updatedAt) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([
    '31000000-0000-4000-8000-000000000001',
    '10000000-0000-4000-8000-000000000001',
    '10000000-0000-4000-8000-000000000022',
    'incompatible collision',
    'career_technical',
    '2026-09-08 00:00:00.000000',
    '2026-09-08 04:00:00.000000',
    30,
    'published',
    '2026-08-25 00:00:00.000000',
    '2026-08-25 00:00:00.000000',
]);
$assertThrows(
    static fn () => (new SchoolActivityCatalogSeeder($collisionPdo, SchoolActivityCatalogSeeder::SQLITE_TEST_SCHEMA, false, $clock))->run(),
    'UUID collision',
    'An existing new UUID with incompatible content fails closed.',
);
$assert((int) $collisionPdo->query('SELECT COUNT(*) FROM activities')->fetchColumn() === 6, 'UUID collision failure performs no additional writes.');

$expiredClock = new DateTimeImmutable('2026-09-04 00:00:00', new DateTimeZone('UTC'));
$assertThrows(
    static fn () => (new SchoolActivityCatalogSeeder($makeFixture(), SchoolActivityCatalogSeeder::SQLITE_TEST_SCHEMA, false, $expiredClock))->run(),
    'Registration window has expired',
    'Seeder never moves an expired existing schedule automatically.',
);

if ($failures !== []) {
    fwrite(STDERR, "learner_school_activity_catalog_seeder_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_school_activity_catalog_seeder_test: OK\n";
