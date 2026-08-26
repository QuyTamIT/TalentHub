<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$seederPath = $root . '/Database/seeds/learner/Activity/SchoolActivityCatalogSeeder.php';
if (!is_file($seederPath)) {
    fwrite(STDERR, "learner_school_activity_qr_fixture_test: RED\n- Phase 4A QR fixture implementation is missing.\n");
    exit(1);
}

$source = (string) file_get_contents($seederPath);
$failures = [];
foreach (['random_bytes', "hash('sha256'", 'usedScans', 'maxScans', "'active'"] as $contract) {
    if (!str_contains($source, $contract)) {
        $failures[] = "Seeder is missing QR contract token: {$contract}.";
    }
}
if (preg_match('/(?:rawToken|raw_token)\s*=>/', $source) === 1) {
    $failures[] = 'Seeder must not return or serialize raw QR tokens.';
}
if ($failures !== []) {
    fwrite(STDERR, "learner_school_activity_qr_fixture_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

require_once $root . '/bin/bootstrap.php';
require_once $root . '/app/learner/data/Contracts/CheckinRepository.php';
require_once $root . '/app/learner/data/Contracts/NotificationRepository.php';
require_once $root . '/app/learner/data/Service/NotificationService.php';
require_once $root . '/app/learner/data/Service/LearnerCheckinService.php';
require_once $root . '/app/learner/data/Database/DatabaseCheckinRepository.php';
ob_start();
require $root . '/tests/learner_school_activity_catalog_seeder_test.php';
ob_end_clean();

// The required SQLite contract leaves $pdo with a successfully seeded catalog and
// keeps the three generated tokens only in this process memory.
$qrFailures = [];
$qrAssert = static function (bool $condition, string $message) use (&$qrFailures): void {
    if (!$condition) {
        $qrFailures[] = $message;
    }
};

$fixtures = TalentHub\Learner\Seeds\Activity\SchoolActivityCatalogSeeder::qrFixtures();
$qrAssert(count($fixtures) === 3 && count(array_unique(array_column($fixtures, 'id'))) === 3, 'Exactly three stable QR session IDs are declared.');
$sessions = $pdo->query(<<<'SQL'
    SELECT q.*, a.schoolId activitySchoolId, a.capacity, a.status activityStatus,
           p.approvalMode,
           t.schoolId teacherSchoolId
    FROM activity_qr_sessions q
    JOIN activities a ON a.id=q.activityId
    JOIN activity_registration_policies p ON p.activityId=q.activityId
    JOIN teacher_profiles t ON t.id=q.createdByTeacherId
    ORDER BY q.id
SQL)->fetchAll(PDO::FETCH_ASSOC);
$qrAssert(count($sessions) === 3, 'Exactly three QR fixture rows exist.');
foreach ($sessions as $index => $session) {
    $qrAssert($session['activitySchoolId'] === $session['teacherSchoolId'], 'Every QR teacher belongs to the activity school.');
    $qrAssert($session['status'] === 'active' && (int) $session['usedScans'] === 0, 'Every new QR fixture is active and unused.');
    $qrAssert((int) $session['maxScans'] > 0 && (int) $session['maxScans'] <= (int) $session['capacity'], 'QR maxScans never exceeds activity capacity.');
    $qrAssert($session['activityStatus'] === 'published', 'Seeding QR fixtures does not make a published activity ongoing.');
    $qrAssert($session['approvalMode'] === 'automatic', 'Every QR fixture belongs to an automatic activity.');
    $qrAssert(new DateTimeImmutable($session['expiresAt'], new DateTimeZone('UTC')) > $clock, 'QR fixture expiry is after the injected rehearsal clock.');
    $qrAssert(isset($rawTokens[$index]) && hash('sha256', $rawTokens[$index]) === $session['tokenHash'], 'Only SHA-256 tokenHash is stored for the in-memory token.');
}

$qrFiles = glob($handoff->outputDirectory() . DIRECTORY_SEPARATOR . '*.png') ?: [];
$qrAssert(count($qrFiles) === 3, 'Successful first seed exports exactly three finalized QR PNG files.');
$qrAssert((glob($handoff->outputDirectory() . DIRECTORY_SEPARATOR . '*.pending') ?: []) === [], 'Successful seed leaves no pending handoff files.');
$manifestPath = $handoff->outputDirectory() . DIRECTORY_SEPARATOR . 'manifest.json';
$manifestRaw = is_file($manifestPath) ? (string) file_get_contents($manifestPath) : '';
$manifest = json_decode($manifestRaw, true);
$qrAssert(is_array($manifest) && count($manifest) === 3, 'QR handoff manifest contains exactly three records.');
$allowedManifestKeys = ['schoolId', 'schoolName', 'activityId', 'activityTitle', 'sessionId', 'expiresAt', 'file'];
$manifestBySession = [];
foreach (is_array($manifest) ? $manifest : [] as $entry) {
    $qrAssert(is_array($entry) && array_keys($entry) === $allowedManifestKeys, 'Manifest record contains only approved handoff fields in canonical order.');
    if (is_array($entry) && isset($entry['sessionId'])) {
        $manifestBySession[(string) $entry['sessionId']] = $entry;
    }
}
foreach ($rawTokens as $token) {
    $qrAssert(!str_contains($manifestRaw, $token) && !str_contains($manifestRaw, hash('sha256', $token)), 'Manifest contains neither a raw token nor its SHA-256 hash.');
    foreach ($qrFiles as $file) {
        $qrAssert(!str_contains(basename($file), $token), 'QR filename never contains raw token material.');
    }
}

$qrNode = (string) getenv('TALENTHUB_QR_NODE');
$qrAssert($qrNode !== '' && is_file($qrNode), 'TALENTHUB_QR_NODE points to the approved bundled Node runtime for private decode verification.');
$runCaptured = static function (array $command): array {
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        return [2, '', 'process unavailable'];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    return [proc_close($process), (string) $stdout, (string) $stderr];
};
foreach ($sessions as $session) {
    $entry = $manifestBySession[$session['id']] ?? null;
    $qrAssert(is_array($entry), 'Every database QR session has one manifest record.');
    if (!is_array($entry)) {
        continue;
    }
    $pngPath = $handoff->outputDirectory() . DIRECTORY_SEPARATOR . $entry['file'];
    $tempBase = tempnam(sys_get_temp_dir(), 'talenthub-qr-decode-');
    if ($tempBase === false) {
        $qrAssert(false, 'Private QR decode temporary path can be allocated.');
        continue;
    }
    $rgbaPath = $tempBase . '.rgba';
    $metadataPath = $tempBase . '.json';
    try {
        [$convertCode] = $runCaptured([$qrPython, $root . '/tests/helpers/qr-png-to-rgba.py', $pngPath, $rgbaPath, $metadataPath]);
        [$decodeCode, $decodedToken] = $runCaptured([$qrNode, $root . '/tests/helpers/decode-qr-rgba.js', $rgbaPath, $metadataPath, $root . '/assets/js/vendor/jsQR.js']);
        $qrAssert($convertCode === 0 && $decodeCode === 0 && $decodedToken !== '', 'Every exported QR PNG decodes successfully with vendored jsQR.');
        $qrAssert($decodedToken !== '' && hash_equals((string) $session['tokenHash'], hash('sha256', $decodedToken)), 'Decoded QR token SHA-256 privately matches its database session hash.');
        $decodedToken = '';
    } finally {
        foreach ([$tempBase, $rgbaPath, $metadataPath] as $temporaryPath) {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}

$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY)');
$pdo->exec('CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, requestId TEXT, ipAddress TEXT, metadata TEXT, createdAt TEXT)');
$studentId = '51000000-0000-4000-8000-000000000001';
$actorId = '51000000-0000-4000-8000-000000000002';
$pdo->prepare('INSERT INTO student_profiles (id) VALUES (?)')->execute([$studentId]);

$notifications = new class implements TalentHub\Learner\Data\Contracts\NotificationRepository {
    public int $published = 0;
    public function listForUser(string $userId, int $limit = 25, int $offset = 0, bool $unreadOnly = false): array { return ['items' => [], 'total' => 0, 'limit' => $limit, 'offset' => $offset, 'hasMore' => false]; }
    public function unreadCount(string $userId): int { return 0; }
    public function markRead(string $userId, string $notificationId): ?array { return null; }
    public function markAllRead(string $userId): int { return 0; }
    public function preferencesForStudent(string $studentId): array { return []; }
    public function updatePreference(string $studentId, string $notificationType, bool $inAppEnabled, bool $emailEnabled): array { return ['studentId' => $studentId, 'notificationType' => $notificationType, 'inAppEnabled' => $inAppEnabled, 'emailEnabled' => $emailEnabled, 'updatedAt' => '']; }
    public function insertNotification(string $id, string $userId, ?string $eventKey, string $notificationType, string $title, string $message, ?string $deepLink, string $createdAt): bool { $this->published++; return true; }
};
$notificationService = new TalentHub\Learner\Data\Service\NotificationService($notifications);
$repository = new TalentHub\Learner\Data\Database\DatabaseCheckinRepository($pdo, $notificationService);
$service = new TalentHub\Learner\Data\Service\LearnerCheckinService($repository);

$publishedToken = $rawTokens[0];
try {
    $service->submit($studentId, $actorId, 'phase4-published-rejection', $publishedToken);
    $qrAssert(false, 'Published activity must reject check-in even when its QR session is active.');
} catch (TalentHub\Http\ApiException $exception) {
    $qrAssert($exception->errorCode === 'ACTIVITY_NOT_CHECKIN_ELIGIBLE', 'Published activity rejects check-in with ACTIVITY_NOT_CHECKIN_ELIGIBLE.');
}
$qrAssert($publishedToken === null, 'Learner check-in service clears the submitted raw token reference.');
$qrAssert((int) $pdo->query('SELECT COUNT(*) FROM checkins')->fetchColumn() === 0, 'Rejected published check-in leaves no partial check-in.');

$fixture = array_values($fixtures)[0];
$registrationId = '51000000-0000-4000-8000-000000000003';
$pdo->prepare("UPDATE activities SET status='ongoing' WHERE id=?")->execute([$fixture['activityId']]);
$pdo->prepare("INSERT INTO activity_registrations (id,activityId,studentId,status,registeredAt,updatedAt) VALUES (?,?,?,'approved',?,?)")
    ->execute([$registrationId, $fixture['activityId'], $studentId, '2026-08-25 00:00:00.000000', '2026-08-25 00:00:00.000000']);
$ongoingToken = $rawTokens[0];
$result = $service->submit($studentId, $actorId, 'phase4-isolated-checkin', $ongoingToken);
$qrAssert($ongoingToken === null && ($result['status'] ?? null) === 'confirmed', 'Only the isolated ongoing fixture completes a confirmed check-in.');
$qrAssert((int) $pdo->query('SELECT COUNT(*) FROM checkins')->fetchColumn() === 1, 'Isolated check-in transaction creates one check-in.');
$qrAssert((int) $pdo->query('SELECT COUNT(*) FROM experience_logs')->fetchColumn() === 1, 'Isolated check-in transaction creates one experience log.');
$qrAssert((int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 1, 'Isolated check-in transaction creates one audit row.');
$qrAssert((string) $pdo->query("SELECT status FROM activity_registrations WHERE id='{$registrationId}'")->fetchColumn() === 'attended', 'Isolated transaction moves its approved registration to attended.');
$qrAssert((int) $pdo->query("SELECT usedScans FROM activity_qr_sessions WHERE id='" . array_values($fixtures)[0]['id'] . "'")->fetchColumn() === 1, 'Successful isolated check-in increments usedScans exactly once.');
$qrAssert($notifications->published === 1, 'Successful isolated check-in publishes one notification through the production service contract.');

if ($qrFailures !== []) {
    fwrite(STDERR, "learner_school_activity_qr_fixture_test: RED\n- " . implode("\n- ", $qrFailures) . "\n");
    exit(1);
}

echo "learner_school_activity_qr_fixture_test: OK\n";
