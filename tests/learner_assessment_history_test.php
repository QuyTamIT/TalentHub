<?php

declare(strict_types=1);

/**
 * Phase 6 / Task 9 — complete own assessment history (RED-first).
 *
 * Contract under test:
 * - completeHistory() returns every submitted-and-scored attempt across all four
 *   frameworks and all three education bands, ordered submittedAt DESC.
 * - Every row carries its assessment version and scoring version reference.
 * - In-progress attempts and submitted-without-result attempts are excluded.
 * - Another Student's attempts never appear, in objects or in raw JSON.
 * - GET /app/learner/api/v1/assessments.php?view=history exposes it read-only,
 *   under the distinct source label 'assessment_engine'.
 *
 * Runs on a disposable SQLite schema. Never opens a shared or primary database.
 */

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Database\DatabaseAssessmentRepository;

const STUDENT_A_PROFILE = '11111111-1111-4111-8111-111111111111';
const STUDENT_A_USER = '33333333-3333-4333-8333-333333333333';
const STUDENT_B_PROFILE = '22222222-2222-4222-8222-222222222222';
const STUDENT_B_USER = '44444444-4444-4444-8444-444444444444';
const TEACHER_USER = '55555555-5555-4555-8555-555555555555';
const STUDENT_B_SECRET = 'STUDENT-B-SECRET-SUMMARY';

if (($argv[1] ?? '') === '--worker') {
    $databasePath = (string) ($argv[2] ?? '');
    $query = (string) ($argv[3] ?? '');
    $userId = (string) ($argv[4] ?? STUDENT_A_USER);
    $role = (string) ($argv[5] ?? 'student');
    $method = strtoupper((string) ($argv[6] ?? 'GET'));

    $GLOBALS['__TALENTHUB_TEST_PDO__'] = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($userId !== 'anonymous') {
        $GLOBALS['__TALENTHUB_TEST_SESSION__'] = [
            'user' => [
                'id' => $userId,
                'email' => $userId . '@example.test',
                'fullName' => 'Fixture ' . $role,
                'role' => $role,
                'status' => 'active',
            ],
            'csrfToken' => 'csrf-test-token',
        ];
    }

    parse_str($query, $parsed);
    $_GET = is_array($parsed) ? $parsed : [];
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/app/learner/api/v1/assessments.php?' . $query;
    $_SERVER['HTTP_X_REQUEST_ID'] = '01JPHASE6HISTORYTEST000001';
    register_shutdown_function(static function (): void {
        fwrite(STDERR, '__HTTP_STATUS_CODE:' . http_response_code() . '__');
    });

    require dirname(__DIR__) . '/app/learner/api/v1/assessments.php';
    exit(0);
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$databasePath = tempnam(sys_get_temp_dir(), 'talenthub-phase6-history-');
$assert(is_string($databasePath) && $databasePath !== '', 'Disposable SQLite path is available.');

try {
    $pdo = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(<<<'SQL'
CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT NOT NULL);
CREATE TABLE permissions (id TEXT PRIMARY KEY, code TEXT NOT NULL);
CREATE TABLE role_permissions (roleId TEXT NOT NULL, permissionId TEXT NOT NULL, PRIMARY KEY(roleId,permissionId));
CREATE TABLE users (id TEXT PRIMARY KEY, roleId TEXT NOT NULL, status TEXT NOT NULL, fullName TEXT NOT NULL, email TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL);
CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, type TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE learner_assessment_versions (id TEXT PRIMARY KEY, testId TEXT NOT NULL, version TEXT NOT NULL, scoringVersion TEXT NOT NULL, schemaHash TEXT NOT NULL, status TEXT NOT NULL, publishedAt TEXT);
CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, startedAt TEXT, submittedAt TEXT);
CREATE TABLE learner_assessment_attempt_metadata (attemptId TEXT PRIMARY KEY, versionId TEXT NOT NULL, status TEXT NOT NULL, expiresAt TEXT, submittedAt TEXT, inputHash TEXT);
CREATE TABLE test_results (id TEXT PRIMARY KEY, attemptId TEXT NOT NULL, resultCode TEXT, summary TEXT, dimensionScoresJson TEXT, scoringVersion TEXT, createdAt TEXT NOT NULL);

-- Present but empty: the Teacher section must stay a separate, distinctly labelled section.
CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL);
CREATE TABLE activities (id TEXT PRIMARY KEY, title TEXT, category TEXT, startAt TEXT, endAt TEXT, status TEXT);
CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, minScore NUMERIC, maxScore NUMERIC);
CREATE TABLE assessments (
    id TEXT PRIMARY KEY, teacherId TEXT NOT NULL, studentId TEXT NOT NULL, activityId TEXT NOT NULL,
    overallScore NUMERIC, comment TEXT, status TEXT NOT NULL, publishedAt TEXT, createdAt TEXT, updatedAt TEXT
);
CREATE TABLE assessment_scores (id TEXT PRIMARY KEY, assessmentId TEXT NOT NULL, criteriaId TEXT NOT NULL, score NUMERIC);

INSERT INTO roles VALUES ('role-student','student'),('role-teacher','teacher');
INSERT INTO permissions VALUES ('permission-profile','student_profile.read_own');
INSERT INTO role_permissions VALUES ('role-student','permission-profile');
SQL
    );

    $pdo->prepare('INSERT INTO users VALUES (?,?,?,?,?)')
        ->execute([STUDENT_A_USER, 'role-student', 'active', 'Student A', 'a@example.test']);
    $pdo->prepare('INSERT INTO users VALUES (?,?,?,?,?)')
        ->execute([STUDENT_B_USER, 'role-student', 'active', 'Student B', 'b@example.test']);
    $pdo->prepare('INSERT INTO users VALUES (?,?,?,?,?)')
        ->execute([TEACHER_USER, 'role-teacher', 'active', 'Teacher T', 't@example.test']);
    $pdo->prepare('INSERT INTO student_profiles VALUES (?,?)')->execute([STUDENT_A_PROFILE, STUDENT_A_USER]);
    $pdo->prepare('INSERT INTO student_profiles VALUES (?,?)')->execute([STUDENT_B_PROFILE, STUDENT_B_USER]);

    /** Every framework and every band is represented so the read cannot be code-specific. */
    $tests = [
        'holland_high' => ['aaaa0001-0000-4000-8000-000000000001', 'Holland High', 'interest'],
        'mbti_middle' => ['aaaa0002-0000-4000-8000-000000000002', 'MBTI Middle', 'personality'],
        'disc_college' => ['aaaa0003-0000-4000-8000-000000000003', 'DISC College', 'personality'],
        'multiple_intelligence_high' => ['aaaa0004-0000-4000-8000-000000000004', 'MI High', 'aptitude'],
        'holland_middle' => ['aaaa0005-0000-4000-8000-000000000005', 'Holland Middle', 'interest'],
        'mbti_high' => ['aaaa0006-0000-4000-8000-000000000006', 'MBTI High', 'personality'],
    ];
    $versionIds = [];
    $index = 0;
    foreach ($tests as $code => [$testId, $name, $type]) {
        $index++;
        $versionId = sprintf('bbbb000%d-0000-4000-8000-00000000000%d', $index, $index);
        $versionIds[$code] = $versionId;
        $pdo->prepare('INSERT INTO talent_tests VALUES (?,?,?,?,?)')
            ->execute([$testId, $code, $name, $type, 'published']);
        $pdo->prepare('INSERT INTO learner_assessment_versions VALUES (?,?,?,?,?,?,?)')
            ->execute([$versionId, $testId, '1.0.0', 'v1.' . $index, str_repeat((string) $index, 64), 'published', '2026-08-01 00:00:00.000000']);
    }

    $attempt = static function (
        PDO $pdo,
        array $versionIds,
        array $tests,
        string $suffix,
        string $code,
        string $studentId,
        string $status,
        ?string $submittedAt,
        ?string $resultCode = null,
        ?string $summary = null
    ): void {
        $attemptId = sprintf('cccc%s-0000-4000-8000-0000000000%s', substr($suffix . '0000', 0, 4), substr($suffix . '00', 0, 2));
        $pdo->prepare('INSERT INTO test_attempts VALUES (?,?,?,?,?,?)')
            ->execute([$attemptId, $tests[$code][0], $studentId, $status, '2026-08-10 08:00:00.000000', $submittedAt]);
        $pdo->prepare('INSERT INTO learner_assessment_attempt_metadata VALUES (?,?,?,?,?,?)')
            ->execute([$attemptId, $versionIds[$code], $status, null, $submittedAt, hash('sha256', $attemptId)]);
        if ($resultCode !== null) {
            $pdo->prepare('INSERT INTO test_results VALUES (?,?,?,?,?,?,?)')
                ->execute([
                    sprintf('dddd%s-0000-4000-8000-0000000000%s', substr($suffix . '0000', 0, 4), substr($suffix . '00', 0, 2)),
                    $attemptId,
                    $resultCode,
                    $summary ?? ('Summary ' . $resultCode),
                    json_encode(['R' => 9, 'I' => 7], JSON_THROW_ON_ERROR),
                    'v1.' . $suffix,
                    $submittedAt ?? '2026-08-10 08:00:00.000000',
                ]);
        }
    };

    // Student A: four submitted-and-scored attempts spanning all four frameworks.
    $attempt($pdo, $versionIds, $tests, '1', 'holland_high', STUDENT_A_PROFILE, 'submitted', '2026-08-20 10:00:00.000000', 'RIA');
    $attempt($pdo, $versionIds, $tests, '2', 'mbti_middle', STUDENT_A_PROFILE, 'submitted', '2026-08-19 10:00:00.000000', 'INTJ');
    $attempt($pdo, $versionIds, $tests, '3', 'disc_college', STUDENT_A_PROFILE, 'submitted', '2026-08-18 10:00:00.000000', 'D');
    $attempt($pdo, $versionIds, $tests, '4', 'multiple_intelligence_high', STUDENT_A_PROFILE, 'submitted', '2026-08-17 10:00:00.000000', 'LOGIC');
    // Excluded: still in progress, and submitted without a scored result.
    $attempt($pdo, $versionIds, $tests, '5', 'holland_middle', STUDENT_A_PROFILE, 'in_progress', null);
    $attempt($pdo, $versionIds, $tests, '6', 'mbti_high', STUDENT_A_PROFILE, 'submitted', '2026-08-21 10:00:00.000000');
    // Excluded: another Student's scored attempt, carrying a traceable secret.
    $attempt($pdo, $versionIds, $tests, '7', 'holland_high', STUDENT_B_PROFILE, 'submitted', '2026-08-22 10:00:00.000000', 'ESB', STUDENT_B_SECRET);

    $repository = new DatabaseAssessmentRepository($pdo);
    $history = $repository->completeHistory(STUDENT_A_PROFILE);

    $assert(count($history) === 4, 'Complete history returns every submitted-and-scored attempt, got ' . count($history) . '.');
    $assert(
        array_column($history, 'assessment_code') === ['holland_high', 'mbti_middle', 'disc_college', 'multiple_intelligence_high'],
        'Complete history is ordered submittedAt DESC across frameworks.'
    );

    foreach ($history as $row) {
        $code = (string) $row['assessment_code'];
        $assert($row['student_id'] === STUDENT_A_PROFILE, "History row {$code} belongs to the authenticated Student.");
        $assert($row['status'] === 'submitted', "History row {$code} is submitted.");
        $assert(($row['assessment_version'] ?? '') === '1.0.0', "History row {$code} references its assessment version.");
        $assert(trim((string) ($row['scoring_version'] ?? '')) !== '', "History row {$code} references its scoring version.");
        $assert(trim((string) ($row['version_id'] ?? '')) !== '', "History row {$code} references the version row it was scored against.");
        $assert(trim((string) ($row['result_id'] ?? '')) !== '', "History row {$code} references its result.");
        $assert(trim((string) ($row['assessment_type'] ?? '')) !== '', "History row {$code} exposes its framework type.");
        $assert(is_array($row['dimension_scores'] ?? null), "History row {$code} decodes dimension scores.");
    }

    $codes = array_column($history, 'assessment_code');
    $assert(!in_array('holland_middle', $codes, true), 'In-progress attempts are excluded from complete history.');
    $assert(!in_array('mbti_high', $codes, true), 'Submitted-without-result attempts are excluded from complete history.');

    $serialized = json_encode($history, JSON_THROW_ON_ERROR);
    $assert(!str_contains($serialized, STUDENT_B_SECRET), 'Complete history never carries another Student summary.');
    $assert(!str_contains($serialized, STUDENT_B_PROFILE), 'Complete history never carries another Student identifier.');

    $otherHistory = $repository->completeHistory(STUDENT_B_PROFILE);
    $assert(count($otherHistory) === 1, 'Complete history is per-Student, not global.');

    $run = static function (
        string $query,
        string $userId = STUDENT_A_USER,
        string $role = 'student',
        string $method = 'GET'
    ) use ($databasePath, $assert): array {
        $command = [PHP_BINARY, __FILE__, '--worker', $databasePath, $query, $userId, $role, $method];
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        $assert(is_resource($process), 'Endpoint worker starts.');
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $status = 0;
        if (preg_match('/__HTTP_STATUS_CODE:(\d+)__/', $stderr, $matches) === 1) {
            $status = (int) $matches[1];
        }
        $decoded = json_decode($stdout, true);
        $assert(is_array($decoded), "Endpoint returns JSON for '{$query}': {$stdout} {$stderr}");
        return ['status' => $status, 'payload' => $decoded, 'raw' => $stdout];
    };

    $response = $run('view=history');
    $assert($response['status'] === 200, 'view=history responds 200 for the authenticated Student.');
    $section = $response['payload']['data']['assessment_history'] ?? null;
    $assert(is_array($section), 'view=history exposes an assessment_history section.');
    $assert(($section['source'] ?? null) === 'assessment_engine', 'Automated history carries the assessment_engine source label.');
    $assert(count($section['items'] ?? []) === 4, 'view=history returns the complete own history.');
    $assert(
        ($section['items'][0]['assessment_code'] ?? null) === 'holland_high',
        'view=history preserves submittedAt DESC ordering.'
    );
    $assert(!str_contains($response['raw'], STUDENT_B_SECRET), 'view=history never leaks another Student summary.');
    $assert(!str_contains($response['raw'], STUDENT_B_PROFILE), 'view=history never leaks another Student identifier.');

    $teacherSection = $response['payload']['data']['teacher_evaluations'] ?? null;
    $assert(is_array($teacherSection), 'The response keeps a separate teacher_evaluations section.');
    $assert(
        ($teacherSection['source'] ?? null) === 'teacher_published_evaluation',
        'The Teacher section carries its own distinct source label.'
    );
    $assert(
        ($teacherSection['source'] ?? null) !== ($section['source'] ?? null),
        'Automated results and Teacher evaluations never share a source label.'
    );
    $assert($teacherSection['items'] === [], 'With no published Teacher evaluation the section is empty, not absent.');

    $unknownView = $run('view=nonsense');
    $assert($unknownView['status'] === 422, 'An unknown view is rejected 422.');
    $assert(
        ($unknownView['payload']['error']['code'] ?? null) === 'VALIDATION_FAILED',
        'An unknown view returns VALIDATION_FAILED.'
    );

    $teacher = $run('view=history', TEACHER_USER, 'teacher');
    $assert($teacher['status'] === 403, 'A Teacher cannot read Student history through the learner endpoint.');
    $assert(
        ($teacher['payload']['error']['code'] ?? null) === 'PERMISSION_DENIED',
        'A Teacher receives PERMISSION_DENIED.'
    );

    $anonymous = $run('view=history', 'anonymous');
    $assert($anonymous['status'] === 401, 'An anonymous request is rejected 401.');
    $assert(
        ($anonymous['payload']['error']['code'] ?? null) === 'AUTH_REQUIRED',
        'An anonymous request returns AUTH_REQUIRED.'
    );

    $mutation = $run('view=history', STUDENT_A_USER, 'student', 'POST');
    $assert($mutation['status'] === 405, 'The history view stays read-only.');
    $assert(
        ($mutation['payload']['error']['code'] ?? null) === 'METHOD_NOT_ALLOWED',
        'A POST to the history view returns METHOD_NOT_ALLOWED.'
    );

} finally {
    unset($pdo, $repository);
    gc_collect_cycles();
    if (is_string($databasePath) && is_file($databasePath)) {
        unlink($databasePath);
    }
}

echo "learner_assessment_history_test: {$assertions} assertions passed\n";
