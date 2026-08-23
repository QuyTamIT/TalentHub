<?php

declare(strict_types=1);

/**
 * Phase 6 / Task 9 — published-only Teacher evaluation access (RED-first).
 *
 * Contract under test:
 * - publishedEvaluationsForStudent() returns only evaluations with
 *   status = 'published' AND publishedAt IS NOT NULL, ordered publishedAt DESC.
 * - Teacher drafts, published rows without a publish timestamp, and another
 *   Student's evaluations never appear, in objects or in raw JSON.
 * - Each evaluation exposes its criteria, reviewer name and activity title.
 * - The endpoint labels this section 'teacher_published_evaluation', a distinct
 *   source from the automated 'assessment_engine' history section.
 * - The existing unfiltered evaluationsForStudent() contract is unchanged.
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
const TEACHER_PROFILE = '66666666-6666-4666-8666-666666666666';
const DRAFT_SECRET = 'DRAFT-SECRET-COMMENT';
const UNPUBLISHED_SECRET = 'NULL-PUBLISHED-AT-SECRET';
const STUDENT_B_SECRET = 'STUDENT-B-SECRET-COMMENT';

if (($argv[1] ?? '') === '--worker') {
    $databasePath = (string) ($argv[2] ?? '');
    $query = (string) ($argv[3] ?? '');
    $userId = (string) ($argv[4] ?? STUDENT_A_USER);
    $role = (string) ($argv[5] ?? 'student');

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
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/app/learner/api/v1/assessments.php?' . $query;
    $_SERVER['HTTP_X_REQUEST_ID'] = '01JPHASE6EVALUATIONTEST001';
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

$databasePath = tempnam(sys_get_temp_dir(), 'talenthub-phase6-evaluation-');
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
CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL);
CREATE TABLE activities (id TEXT PRIMARY KEY, title TEXT, category TEXT, startAt TEXT, endAt TEXT, status TEXT);
CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, minScore NUMERIC, maxScore NUMERIC);
CREATE TABLE assessments (
    id TEXT PRIMARY KEY, teacherId TEXT NOT NULL, studentId TEXT NOT NULL, activityId TEXT NOT NULL,
    overallScore NUMERIC, comment TEXT, status TEXT NOT NULL, publishedAt TEXT, createdAt TEXT, updatedAt TEXT
);
CREATE TABLE assessment_scores (id TEXT PRIMARY KEY, assessmentId TEXT NOT NULL, criteriaId TEXT NOT NULL, score NUMERIC);

-- Present but empty: the response must still render both sections side by side.
CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, type TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE learner_assessment_versions (id TEXT PRIMARY KEY, testId TEXT NOT NULL, version TEXT NOT NULL, scoringVersion TEXT NOT NULL, schemaHash TEXT NOT NULL, status TEXT NOT NULL, publishedAt TEXT);
CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, startedAt TEXT, submittedAt TEXT);
CREATE TABLE learner_assessment_attempt_metadata (attemptId TEXT PRIMARY KEY, versionId TEXT NOT NULL, status TEXT NOT NULL, expiresAt TEXT, submittedAt TEXT, inputHash TEXT);
CREATE TABLE test_results (id TEXT PRIMARY KEY, attemptId TEXT NOT NULL, resultCode TEXT, summary TEXT, dimensionScoresJson TEXT, scoringVersion TEXT, createdAt TEXT NOT NULL);

INSERT INTO roles VALUES ('role-student','student'),('role-teacher','teacher');
INSERT INTO permissions VALUES ('permission-profile','student_profile.read_own');
INSERT INTO role_permissions VALUES ('role-student','permission-profile');
INSERT INTO assessment_criteria VALUES
 ('c1000000-0000-4000-8000-000000000001','expertise','Chuyên môn',0,40),
 ('c1000000-0000-4000-8000-000000000002','teamwork','Làm việc nhóm',0,20);
SQL
    );

    $pdo->prepare('INSERT INTO users VALUES (?,?,?,?,?)')
        ->execute([STUDENT_A_USER, 'role-student', 'active', 'Student A', 'a@example.test']);
    $pdo->prepare('INSERT INTO users VALUES (?,?,?,?,?)')
        ->execute([STUDENT_B_USER, 'role-student', 'active', 'Student B', 'b@example.test']);
    $pdo->prepare('INSERT INTO users VALUES (?,?,?,?,?)')
        ->execute([TEACHER_USER, 'role-teacher', 'active', 'Cô Lê Thị Hương', 't@example.test']);
    $pdo->prepare('INSERT INTO student_profiles VALUES (?,?)')->execute([STUDENT_A_PROFILE, STUDENT_A_USER]);
    $pdo->prepare('INSERT INTO student_profiles VALUES (?,?)')->execute([STUDENT_B_PROFILE, STUDENT_B_USER]);
    $pdo->prepare('INSERT INTO teacher_profiles VALUES (?,?)')->execute([TEACHER_PROFILE, TEACHER_USER]);
    $pdo->prepare('INSERT INTO activities VALUES (?,?,?,?,?,?)')
        ->execute(['a1000000-0000-4000-8000-000000000001', 'IoT Lab', 'stem', '2026-07-01 08:00:00.000000', '2026-07-01 12:00:00.000000', 'completed']);
    $pdo->prepare('INSERT INTO activities VALUES (?,?,?,?,?,?)')
        ->execute(['a1000000-0000-4000-8000-000000000002', 'CLB Công nghệ', 'stem', '2026-06-01 08:00:00.000000', '2026-06-01 12:00:00.000000', 'completed']);

    $evaluation = static function (
        PDO $pdo,
        string $id,
        string $studentId,
        string $activityId,
        string $status,
        ?string $publishedAt,
        string $comment,
        float $overallScore = 90.0
    ): void {
        $pdo->prepare('INSERT INTO assessments VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([
            $id,
            TEACHER_PROFILE,
            $studentId,
            $activityId,
            $overallScore,
            $comment,
            $status,
            $publishedAt,
            '2026-06-01 00:00:00.000000',
            '2026-06-01 00:00:00.000000',
        ]);
        $pdo->prepare('INSERT INTO assessment_scores VALUES (?,?,?,?)')
            ->execute(['f1' . substr($id, 2), $id, 'c1000000-0000-4000-8000-000000000001', 36]);
        $pdo->prepare('INSERT INTO assessment_scores VALUES (?,?,?,?)')
            ->execute(['f2' . substr($id, 2), $id, 'c1000000-0000-4000-8000-000000000002', 18]);
    };

    $published1 = 'e1000000-0000-4000-8000-000000000001';
    $published2 = 'e1000000-0000-4000-8000-000000000002';
    $draft = 'e1000000-0000-4000-8000-000000000003';
    $publishedNoTimestamp = 'e1000000-0000-4000-8000-000000000004';
    $otherStudent = 'e1000000-0000-4000-8000-000000000005';

    $evaluation($pdo, $published1, STUDENT_A_PROFILE, 'a1000000-0000-4000-8000-000000000001', 'published', '2026-07-15 09:00:00.000000', 'Tiến bộ rõ rệt.');
    $evaluation($pdo, $published2, STUDENT_A_PROFILE, 'a1000000-0000-4000-8000-000000000002', 'published', '2026-06-15 09:00:00.000000', 'Tinh thần hợp tác tốt.', 84.0);
    $evaluation($pdo, $draft, STUDENT_A_PROFILE, 'a1000000-0000-4000-8000-000000000001', 'draft', null, DRAFT_SECRET);
    $evaluation($pdo, $publishedNoTimestamp, STUDENT_A_PROFILE, 'a1000000-0000-4000-8000-000000000002', 'published', null, UNPUBLISHED_SECRET);
    $evaluation($pdo, $otherStudent, STUDENT_B_PROFILE, 'a1000000-0000-4000-8000-000000000001', 'published', '2026-08-01 09:00:00.000000', STUDENT_B_SECRET);

    $repository = new DatabaseAssessmentRepository($pdo);

    // The pre-existing unfiltered read must keep its semantics (Phase 1 foundation contract).
    $unfiltered = $repository->evaluationsForStudent(STUDENT_A_PROFILE);
    $assert(count($unfiltered) === 4, 'evaluationsForStudent stays unfiltered, got ' . count($unfiltered) . '.');

    $publishedOnly = $repository->publishedEvaluationsForStudent(STUDENT_A_PROFILE);
    $assert(count($publishedOnly) === 2, 'Only published evaluations with a publish timestamp are returned, got ' . count($publishedOnly) . '.');
    $assert(array_column($publishedOnly, 'id') === [$published1, $published2], 'Published evaluations are ordered publishedAt DESC.');

    foreach ($publishedOnly as $row) {
        $id = (string) $row['id'];
        $assert($row['student_id'] === STUDENT_A_PROFILE, "Published evaluation {$id} belongs to the authenticated Student.");
        $assert($row['status'] === 'approved' || $row['status'] === 'published', "Published evaluation {$id} reports a published state.");
        $assert(trim((string) ($row['published_at'] ?? '')) !== '', "Published evaluation {$id} exposes its publish timestamp.");
        $assert(trim((string) ($row['reviewer_name'] ?? '')) !== '', "Published evaluation {$id} exposes the reviewing Teacher name.");
        $assert(trim((string) ($row['activity_title'] ?? '')) !== '', "Published evaluation {$id} exposes its activity title.");
        $assert(count($row['scores'] ?? []) === 2, "Published evaluation {$id} exposes its criteria scores.");
        foreach ($row['scores'] as $score) {
            $assert(trim((string) ($score['criteria_name'] ?? '')) !== '', "Score on {$id} names its criterion.");
            $assert($score['score'] !== null, "Score on {$id} carries a value.");
            $assert($score['max_score'] !== null, "Score on {$id} carries its maximum.");
            $assert($score['min_score'] !== null, "Score on {$id} carries its minimum.");
        }
    }

    $serialized = json_encode($publishedOnly, JSON_THROW_ON_ERROR);
    foreach ([DRAFT_SECRET, UNPUBLISHED_SECRET, STUDENT_B_SECRET, $draft, $publishedNoTimestamp, $otherStudent, STUDENT_B_PROFILE] as $secret) {
        $assert(!str_contains($serialized, $secret), "Published evaluations never carry '{$secret}'.");
    }

    $otherStudentRows = $repository->publishedEvaluationsForStudent(STUDENT_B_PROFILE);
    $assert(count($otherStudentRows) === 1, 'Published evaluations are per-Student, not global.');

    $run = static function (
        string $query,
        string $userId = STUDENT_A_USER,
        string $role = 'student'
    ) use ($databasePath, $assert): array {
        $command = [PHP_BINARY, __FILE__, '--worker', $databasePath, $query, $userId, $role];
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

    $automated = $response['payload']['data']['assessment_history'] ?? null;
    $teacher = $response['payload']['data']['teacher_evaluations'] ?? null;
    $assert(is_array($automated), 'The response keeps an automated assessment_history section.');
    $assert(is_array($teacher), 'The response keeps a separate teacher_evaluations section.');
    $assert(
        ($automated['source'] ?? null) === 'assessment_engine',
        'The automated section is labelled assessment_engine.'
    );
    $assert(
        ($teacher['source'] ?? null) === 'teacher_published_evaluation',
        'The Teacher section is labelled teacher_published_evaluation.'
    );
    $assert(
        ($automated['source'] ?? null) !== ($teacher['source'] ?? null),
        'The two sections never share a source label.'
    );
    $assert(count($teacher['items'] ?? []) === 2, 'The Teacher section returns only published evaluations.');
    $assert(
        ($teacher['items'][0]['id'] ?? null) === $published1,
        'The Teacher section preserves publishedAt DESC ordering.'
    );

    foreach ([DRAFT_SECRET, UNPUBLISHED_SECRET, STUDENT_B_SECRET, $draft, $publishedNoTimestamp, $otherStudent, STUDENT_B_PROFILE] as $secret) {
        $assert(!str_contains($response['raw'], $secret), "The response body never leaks '{$secret}'.");
    }

    $teacherActor = $run('view=history', TEACHER_USER, 'teacher');
    $assert($teacherActor['status'] === 403, 'A Teacher cannot read Student evaluations through the learner endpoint.');
    $assert(
        ($teacherActor['payload']['error']['code'] ?? null) === 'PERMISSION_DENIED',
        'A Teacher receives PERMISSION_DENIED.'
    );
    $assert(!str_contains($teacherActor['raw'], DRAFT_SECRET), 'A denied Teacher request leaks no draft comment.');

    $anonymous = $run('view=history', 'anonymous');
    $assert($anonymous['status'] === 401, 'An anonymous request is rejected 401.');
    $assert(
        ($anonymous['payload']['error']['code'] ?? null) === 'AUTH_REQUIRED',
        'An anonymous request returns AUTH_REQUIRED.'
    );
    $assert(!str_contains($anonymous['raw'], DRAFT_SECRET), 'An anonymous request leaks no draft comment.');
} finally {
    unset($pdo, $repository);
    gc_collect_cycles();
    if (is_string($databasePath) && is_file($databasePath)) {
        unlink($databasePath);
    }
}

echo "learner_published_evaluation_access_test: {$assertions} assertions passed\n";
