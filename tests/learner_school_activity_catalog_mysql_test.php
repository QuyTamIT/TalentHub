<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$seederPath = $root . '/Database/seeds/learner/Activity/SchoolActivityCatalogSeeder.php';
if (!is_file($seederPath)) {
    fwrite(STDERR, "learner_school_activity_catalog_mysql_test: RED\n- Phase 4A seeder is missing.\n");
    exit(1);
}

require_once $root . '/bin/bootstrap.php';
require_once $seederPath;
require_once $root . '/app/learner/data/Contracts/CheckinRepository.php';
require_once $root . '/app/learner/data/Service/LearnerCheckinService.php';
require_once $root . '/app/learner/data/Database/DatabaseCheckinRepository.php';

$schema = getenv('TALENTHUB_ACTIVITY_PHASE4_SCHEMA');
if ($schema !== 'talenthub_activity_phase4_disposable') {
    fwrite(STDERR, "learner_school_activity_catalog_mysql_test: REFUSED\n- TALENTHUB_ACTIVITY_PHASE4_SCHEMA must be exactly talenthub_activity_phase4_disposable.\n");
    exit(2);
}
$qrOutputDirectory = (string) getenv('TALENTHUB_PHASE4_QR_OUTPUT_DIR');
$qrPython = (string) getenv('TALENTHUB_QR_PYTHON');
$qrNode = (string) getenv('TALENTHUB_QR_NODE');
if ($qrOutputDirectory === '' || $qrPython === '' || !is_file($qrPython) || $qrNode === '' || !is_file($qrNode)) {
    fwrite(STDERR, "learner_school_activity_catalog_mysql_test: REFUSED\n- Explicit disposable QR output plus bundled Python and Node runtimes are required.\n");
    exit(2);
}

$config = require $root . '/config/database.php';
$config['database'] = $schema;
$pdo = (new TalentHub\Database\Connection($config))->connect();
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $schema) {
    throw new RuntimeException('MySQL test connection is not pinned to the approved disposable schema.');
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$count = static fn (string $table): int => (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
$digest = static function (string $table) use ($pdo): string {
    $columns = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
    $order = implode(',', array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns));
    $rows = $pdo->query("SELECT * FROM `{$table}` ORDER BY {$order}")->fetchAll(PDO::FETCH_ASSOC);
    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
};
$evidenceTables = ['activity_registrations', 'checkins', 'experience_logs', 'assessments', 'assessment_scores', 'notifications', 'audit_logs'];
$evidenceBefore = [];
foreach ($evidenceTables as $table) {
    $evidenceBefore[$table] = ['count' => $count($table), 'digest' => $digest($table)];
}
$oldIds = [
    '00000000-0000-4000-8000-000000000302',
    '21000000-04ed-44b5-82fd-0db8f8fd3b05',
    '21000000-8e2d-4dae-8d47-ea4ac11c3dc3',
    '22000000-e945-49ac-857c-af53ffef54f0',
    '22000000-b817-48d3-8ab2-6b7dc54cd16e',
];
$oldPlaceholders = implode(',', array_fill(0, count($oldIds), '?'));
$oldStatement = $pdo->prepare("SELECT * FROM activities WHERE id IN ({$oldPlaceholders}) ORDER BY id");
$oldStatement->execute($oldIds);
$oldBefore = $oldStatement->fetchAll(PDO::FETCH_ASSOC);
$initial = [
    'activities' => $count('activities'),
    'details' => $count('activity_details'),
    'registration_policies' => $count('activity_registration_policies'),
    'experience_policies' => $count('activity_experience_policies'),
    'qr_sessions' => $count('activity_qr_sessions'),
];
$assert($initial['activities'] === 5 && $initial['details'] === 0 && $initial['registration_policies'] === 0 && $initial['experience_policies'] === 0, 'Disposable rehearsal starts from the cloned five-activity pre-seed snapshot.');

$clock = new DateTimeImmutable('2026-08-25 00:00:00', new DateTimeZone('UTC'));
$rawTokens = [];
$tokenFactory = static function () use (&$rawTokens): string {
    $token = bin2hex(random_bytes(32));
    $rawTokens[] = $token;
    return $token;
};
$handoff = new TalentHub\Learner\Seeds\Activity\SchoolActivityQrHandoff($root, $qrOutputDirectory, $qrPython);
$seeder = new TalentHub\Learner\Seeds\Activity\SchoolActivityCatalogSeeder($pdo, $schema, false, $clock, $tokenFactory, $handoff);

$trigger = 'trg_phase4_activity_details_failure';
$triggerExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name=?');
$triggerExists->execute([$trigger]);
if ((int) $triggerExists->fetchColumn() !== 0) {
    throw new RuntimeException('Rollback-injection trigger unexpectedly already exists.');
}
$pdo->exec("CREATE TRIGGER `{$trigger}` BEFORE INSERT ON activity_details FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='phase4 injected detail failure'");
try {
    try {
        $seeder->run();
        $assert(false, 'Injected MySQL metadata failure must abort the seeder.');
    } catch (Throwable $exception) {
        $assert(str_contains($exception->getMessage(), 'phase4 injected detail failure'), 'MySQL rollback injection reaches the expected failure point.');
    }
} finally {
    $pdo->exec("DROP TRIGGER `{$trigger}`");
}
$assert($count('activities') === 5 && $count('activity_details') === 0 && $count('activity_registration_policies') === 0 && $count('activity_experience_policies') === 0, 'MySQL rollback injection leaves no partial catalog data.');
$assert($count('activity_qr_sessions') === $initial['qr_sessions'], 'MySQL rollback injection leaves QR sessions unchanged.');
foreach ($evidenceBefore as $table => $snapshot) {
    $assert($count($table) === $snapshot['count'] && $digest($table) === $snapshot['digest'], "Rollback injection preserves {$table}.");
}

$rawTokens = [];
$first = $seeder->run();
$assert($first === ['existing' => 5, 'inserted' => 12, 'details' => 17, 'registration_policies' => 15, 'experience_policies' => 15, 'qr_sessions' => 3], 'First MySQL run inserts the exact Phase 4A catalog.');
$assert($count('activities') === 17 && $count('activity_details') === 17 && $count('activity_registration_policies') === 15 && $count('activity_experience_policies') === 15, 'MySQL rehearsal reaches 17/17/15/15 catalog rows.');
$assert((int) $pdo->query("SELECT COUNT(*) FROM activity_details WHERE coverImageAlt IS NOT NULL AND TRIM(coverImageAlt)<>''")->fetchColumn() === 17, 'MySQL rehearsal stores nonempty cover alt text for all seventeen details.');
$assert($count('activity_qr_sessions') === $initial['qr_sessions'] + 3, 'MySQL rehearsal adds exactly three stable QR sessions.');

$oldStatement->execute($oldIds);
$oldAfter = $oldStatement->fetchAll(PDO::FETCH_ASSOC);
$assert($oldAfter === $oldBefore, 'All columns of the five existing MySQL activity rows remain unchanged.');
foreach ($evidenceBefore as $table => $snapshot) {
    $assert($count($table) === $snapshot['count'] && $digest($table) === $snapshot['digest'], "Successful catalog seed preserves {$table}.");
}

$distribution = $pdo->query(<<<'SQL'
    SELECT a.schoolId, COUNT(*) openActivities,
           SUM(p.approvalMode='automatic') automaticCount,
           SUM(p.approvalMode='teacher_review') reviewCount
    FROM activities a
    JOIN activity_registration_policies p ON p.activityId=a.id
    JOIN activity_details d ON d.activityId=a.id
    WHERE a.status='published' AND d.audienceScope='school_only'
    GROUP BY a.schoolId
    ORDER BY a.schoolId
SQL)->fetchAll(PDO::FETCH_ASSOC);
$assert(count($distribution) === 3, 'MySQL rehearsal has published school-only activities for three schools.');
foreach ($distribution as $row) {
    $assert((int) $row['openActivities'] === 5 && (int) $row['automaticCount'] === 4 && (int) $row['reviewCount'] === 1, 'Each MySQL school has five open activities: four automatic and one teacher-review.');
}

$fixtureIds = array_column(TalentHub\Learner\Seeds\Activity\SchoolActivityCatalogSeeder::qrFixtures(), 'id');
$fixturePlaceholders = implode(',', array_fill(0, count($fixtureIds), '?'));
$qrStatement = $pdo->prepare("SELECT q.*,a.capacity,a.status activityStatus,a.schoolId activitySchool,t.schoolId teacherSchool,p.approvalMode FROM activity_qr_sessions q JOIN activities a ON a.id=q.activityId JOIN teacher_profiles t ON t.id=q.createdByTeacherId JOIN activity_registration_policies p ON p.activityId=q.activityId WHERE q.id IN ({$fixturePlaceholders}) ORDER BY q.id");
$qrStatement->execute($fixtureIds);
$qrRows = $qrStatement->fetchAll(PDO::FETCH_ASSOC);
$assert(count($qrRows) === 3 && count($rawTokens) === 3, 'MySQL rehearsal creates exactly three in-memory-backed QR fixtures.');
foreach ($qrRows as $index => $row) {
    $assert($row['activitySchool'] === $row['teacherSchool'] && $row['activityStatus'] === 'published' && $row['approvalMode'] === 'automatic', 'MySQL QR teacher matches school and its automatic activity remains published.');
    $assert($row['status'] === 'active' && (int) $row['usedScans'] === 0 && (int) $row['maxScans'] <= (int) $row['capacity'], 'MySQL QR fixture is active, unused, and within capacity.');
    $assert(hash('sha256', $rawTokens[$index]) === $row['tokenHash'], 'MySQL stores only SHA-256 hashes of generated QR tokens.');
}

$qrFiles = glob($handoff->outputDirectory() . DIRECTORY_SEPARATOR . '*.png') ?: [];
$assert(count($qrFiles) === 3, 'MySQL first seed exports exactly three finalized QR PNG files.');
$assert((glob($handoff->outputDirectory() . DIRECTORY_SEPARATOR . '*.pending') ?: []) === [], 'MySQL successful seed leaves no pending handoff files.');
$manifest = json_decode((string) file_get_contents($handoff->outputDirectory() . DIRECTORY_SEPARATOR . 'manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$manifestBySession = [];
foreach ($manifest as $entry) {
    $assert(array_keys($entry) === ['schoolId', 'schoolName', 'activityId', 'activityTitle', 'sessionId', 'expiresAt', 'file'], 'MySQL handoff manifest contains only approved fields.');
    $manifestBySession[$entry['sessionId']] = $entry;
}
$runCaptured = static function (array $command): array {
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        return [2, ''];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    return [proc_close($process), (string) $stdout];
};
$decodedHashMatches = 0;
foreach ($qrRows as $row) {
    $entry = $manifestBySession[$row['id']] ?? null;
    if (!is_array($entry)) {
        $assert(false, 'MySQL QR session is represented in the handoff manifest.');
        continue;
    }
    $temporary = tempnam(sys_get_temp_dir(), 'talenthub-mysql-qr-');
    if ($temporary === false) {
        $assert(false, 'MySQL QR decode temporary path can be allocated.');
        continue;
    }
    $rgba = $temporary . '.rgba';
    $metadata = $temporary . '.json';
    try {
        [$convertCode] = $runCaptured([$qrPython, $root . '/tests/helpers/qr-png-to-rgba.py', $handoff->outputDirectory() . DIRECTORY_SEPARATOR . $entry['file'], $rgba, $metadata]);
        [$decodeCode, $decoded] = $runCaptured([$qrNode, $root . '/tests/helpers/decode-qr-rgba.js', $rgba, $metadata, $root . '/assets/js/vendor/jsQR.js']);
        $matches = $convertCode === 0 && $decodeCode === 0 && $decoded !== '' && hash_equals((string) $row['tokenHash'], hash('sha256', $decoded));
        $assert($matches, 'MySQL exported QR decodes and privately matches its database token hash.');
        $decodedHashMatches += $matches ? 1 : 0;
        $decoded = '';
    } finally {
        foreach ([$temporary, $rgba, $metadata] as $temporaryPath) {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}

$studentId = (string) $pdo->query('SELECT id FROM student_profiles ORDER BY id LIMIT 1')->fetchColumn();
$actorId = (string) $pdo->query('SELECT userId FROM student_profiles WHERE id=' . $pdo->quote($studentId))->fetchColumn();
$checkinService = new TalentHub\Learner\Data\Service\LearnerCheckinService(new TalentHub\Learner\Data\Database\DatabaseCheckinRepository($pdo));
$publishedToken = $rawTokens[0];
try {
    $checkinService->submit($studentId, $actorId, 'phase4-mysql-published-rejection', $publishedToken);
    $assert(false, 'Published MySQL activity must reject check-in despite an active fixture QR.');
} catch (TalentHub\Http\ApiException $exception) {
    $assert($exception->errorCode === 'ACTIVITY_NOT_CHECKIN_ELIGIBLE', 'Published MySQL QR is rejected with ACTIVITY_NOT_CHECKIN_ELIGIBLE.');
}
$assert($publishedToken === null, 'MySQL published-rejection path clears the raw token reference.');

$hashesBefore = [];
foreach ($qrRows as $row) {
    $hashesBefore[$row['id']] = $row['tokenHash'];
}
$filesBefore = [];
foreach ($qrFiles as $file) {
    $filesBefore[basename($file)] = hash_file('sha256', $file);
}
$second = $seeder->run();
$qrStatement->execute($fixtureIds);
$hashesAfter = [];
foreach ($qrStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $hashesAfter[$row['id']] = $row['tokenHash'];
}
$assert($second['inserted'] === 0 && $second['existing'] === 17, 'Second MySQL run inserts zero activities.');
$assert($hashesAfter === $hashesBefore && count($rawTokens) === 3, 'Second MySQL run preserves valid QR hashes and generates no token.');
$filesAfter = [];
foreach (glob($handoff->outputDirectory() . DIRECTORY_SEPARATOR . '*.png') ?: [] as $file) {
    $filesAfter[basename($file)] = hash_file('sha256', $file);
}
$assert($filesAfter === $filesBefore, 'Second MySQL run creates no QR PNG and changes no existing QR file.');
foreach ($evidenceBefore as $table => $snapshot) {
    $assert($count($table) === $snapshot['count'] && $digest($table) === $snapshot['digest'], "Second seed and published rejection preserve {$table}.");
}

if ($failures !== []) {
    fwrite(STDERR, "learner_school_activity_catalog_mysql_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo json_encode([
    'test' => 'learner_school_activity_catalog_mysql_test',
    'status' => 'OK',
    'first' => $first,
    'second' => $second,
    'initialQrSessions' => $initial['qr_sessions'],
    'finalQrSessions' => $count('activity_qr_sessions'),
    'qrPngFiles' => count($qrFiles),
    'decodedHashMatches' => $decodedHashMatches,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
