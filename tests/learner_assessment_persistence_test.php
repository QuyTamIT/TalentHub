<?php

declare(strict_types=1);

use TalentHub\Learner\Assessment\Scoring\HollandScorer;
use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use TalentHub\Learner\Data\Database\DatabaseAssessmentRepository;
use TalentHub\Learner\Data\Database\DatabaseAssessmentWriteRepository;
use TalentHub\Learner\Data\RepositoryFactory;
use TalentHub\Learner\Data\Service\LearnerAssessmentService;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/includes/assessment-data.php';

const ASSESSMENT_STUDENT_A = '11111111-1111-4111-8111-111111111111';
const ASSESSMENT_STUDENT_B = '22222222-2222-4222-8222-222222222222';
const ASSESSMENT_STUDENT_C = '33333333-3333-4333-8333-333333333333';
const ASSESSMENT_STUDENT_D = '44444444-4444-4444-8444-444444444444';
const ASSESSMENT_STUDENT_E = '55555555-5555-4555-8555-555555555555';
const ASSESSMENT_TEST_ID = '33333333-3333-4333-8333-000000000001';
const ASSESSMENT_VERSION_ID = '44444444-4444-4444-8444-444444444444';

function assessment_write_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function assessment_write_expect_exception(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (\RuntimeException) {
        return;
    }

    assessment_write_assert(false, $message);
}

function assessment_write_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE student_profiles (id CHAR(36) NOT NULL PRIMARY KEY, userId CHAR(36) NOT NULL UNIQUE)');
    $pdo->exec('CREATE TABLE talent_tests (id CHAR(36) NOT NULL PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, type TEXT NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE test_questions (id CHAR(36) NOT NULL PRIMARY KEY, testId CHAR(36) NOT NULL, code TEXT NOT NULL, content TEXT NOT NULL, optionsJson TEXT NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL, FOREIGN KEY (testId) REFERENCES talent_tests(id))');
    $pdo->exec('CREATE TABLE test_attempts (id CHAR(36) NOT NULL PRIMARY KEY, testId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, status TEXT NOT NULL, startedAt TEXT NOT NULL, submittedAt TEXT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL, FOREIGN KEY (testId) REFERENCES talent_tests(id), FOREIGN KEY (studentId) REFERENCES student_profiles(id))');
    $pdo->exec('CREATE TABLE test_results (id CHAR(36) NOT NULL PRIMARY KEY, attemptId CHAR(36) NOT NULL UNIQUE, resultCode TEXT NOT NULL, summary TEXT NOT NULL, dimensionScoresJson TEXT NOT NULL, scoringVersion TEXT NOT NULL, createdAt TEXT NOT NULL, FOREIGN KEY (attemptId) REFERENCES test_attempts(id))');
    $pdo->exec('CREATE TABLE learner_assessment_versions (id CHAR(36) NOT NULL PRIMARY KEY, testId CHAR(36) NOT NULL, version TEXT NOT NULL, scoringVersion TEXT NOT NULL, schemaHash CHAR(64) NOT NULL, status TEXT NOT NULL, publishedAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE (testId, version), FOREIGN KEY (testId) REFERENCES talent_tests(id))');
    $pdo->exec('CREATE TABLE learner_assessment_question_versions (id CHAR(36) NOT NULL PRIMARY KEY, versionId CHAR(36) NOT NULL, questionId CHAR(36) NOT NULL, position INTEGER NOT NULL, dimensionCode TEXT NOT NULL, required INTEGER NOT NULL, createdAt TEXT NOT NULL, UNIQUE (versionId, questionId), UNIQUE (versionId, position), FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id), FOREIGN KEY (questionId) REFERENCES test_questions(id))');
    $pdo->exec('CREATE TABLE learner_assessment_attempt_metadata (id CHAR(36) NOT NULL PRIMARY KEY, attemptId CHAR(36) NOT NULL UNIQUE, versionId CHAR(36) NOT NULL, status TEXT NOT NULL, expiresAt TEXT NULL, submittedAt TEXT NULL, inputHash CHAR(64) NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL, FOREIGN KEY (attemptId) REFERENCES test_attempts(id), FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id))');
    $pdo->exec('CREATE TABLE learner_assessment_answers (id CHAR(36) NOT NULL PRIMARY KEY, attemptId CHAR(36) NOT NULL, questionId CHAR(36) NOT NULL, answerJson TEXT NOT NULL, answeredAt TEXT NOT NULL, UNIQUE (attemptId, questionId), FOREIGN KEY (attemptId) REFERENCES learner_assessment_attempt_metadata(attemptId), FOREIGN KEY (questionId) REFERENCES test_questions(id))');
    $pdo->exec('CREATE TABLE notifications (id CHAR(36) NOT NULL PRIMARY KEY, userId CHAR(36) NOT NULL, eventKey TEXT NULL, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT NULL, readAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE (userId, eventKey))');
    $pdo->exec('CREATE TABLE learner_notification_preferences (studentId CHAR(36) NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL DEFAULT 1, emailEnabled INTEGER NOT NULL DEFAULT 0, updatedAt TEXT NOT NULL, PRIMARY KEY (studentId, notificationType))');

    $pdo->exec("INSERT INTO student_profiles (id, userId) VALUES ('" . ASSESSMENT_STUDENT_A . "', 'aaaaaaaa-aaaa-4aaa-8aaa-000000000001'), ('" . ASSESSMENT_STUDENT_B . "', 'aaaaaaaa-aaaa-4aaa-8aaa-000000000002'), ('" . ASSESSMENT_STUDENT_C . "', 'aaaaaaaa-aaaa-4aaa-8aaa-000000000003'), ('" . ASSESSMENT_STUDENT_D . "', 'aaaaaaaa-aaaa-4aaa-8aaa-000000000004'), ('" . ASSESSMENT_STUDENT_E . "', 'aaaaaaaa-aaaa-4aaa-8aaa-000000000005')");
    $pdo->exec("INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES ('" . ASSESSMENT_TEST_ID . "', 'holland_high', 'Holland High', 'holland', 'published', '2026-08-16T00:00:00+00:00', '2026-08-16T00:00:00+00:00')");
    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt) VALUES ('" . ASSESSMENT_VERSION_ID . "', '" . ASSESSMENT_TEST_ID . "', '1.0.0', 'holland-riasec-1.0', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'published', '2026-08-16T00:00:00+00:00', '2026-08-16T00:00:00+00:00')");

    $optionsJson = json_encode([['value' => 1, 'label' => '1'], ['value' => 2, 'label' => '2'], ['value' => 3, 'label' => '3'], ['value' => 4, 'label' => '4'], ['value' => 5, 'label' => '5']]);
    foreach (['R', 'I', 'A', 'S', 'E', 'C'] as $position => $dimension) {
        $questionId = sprintf('55555555-5555-4555-8555-%012d', $position + 1);
        $questionVersionId = sprintf('66666666-6666-4666-8666-%012d', $position + 1);
        $required = $dimension === 'C' ? 0 : 1;
        $pdo->exec("INSERT INTO test_questions (id, testId, code, content, optionsJson, status, createdAt, updatedAt) VALUES ('{$questionId}', '" . ASSESSMENT_TEST_ID . "', 'Q_{$dimension}', 'Question {$dimension}', '{$optionsJson}', 'published', '2026-08-16T00:00:00+00:00', '2026-08-16T00:00:00+00:00')");
        $pdo->exec("INSERT INTO learner_assessment_question_versions (id, versionId, questionId, position, dimensionCode, required, createdAt) VALUES ('{$questionVersionId}', '" . ASSESSMENT_VERSION_ID . "', '{$questionId}', " . ($position + 1) . ", '{$dimension}', {$required}, '2026-08-16T00:00:00+00:00')");
    }

    return $pdo;
}

assessment_write_assert(interface_exists(\TalentHub\Learner\Data\Contracts\AssessmentWriteRepository::class), 'assessment write contract exists');

$pdo = assessment_write_fixture();
$scorers = new ScorerRegistry([
    'holland-riasec-1.0' => new HollandScorer(),
]);
$repository = new DatabaseAssessmentWriteRepository($pdo, $scorers);
$readRepository = new DatabaseAssessmentRepository($pdo);
$service = new LearnerAssessmentService($readRepository, $repository);

$attempt = $service->startOrResume(ASSESSMENT_STUDENT_A, 'holland', 'high');
assessment_write_assert($attempt['student_id'] === ASSESSMENT_STUDENT_A, 'started attempt is owned by the requesting learner');
assessment_write_assert($attempt['assessment_version'] === '1.0.0', 'started attempt retains the approved assessment version');
assessment_write_assert($attempt['scoring_version'] === 'holland-riasec-1.0', 'started attempt retains the approved scoring version');

assessment_write_expect_exception(
    static fn (): array => $service->saveAnswer(ASSESSMENT_STUDENT_B, $attempt['id'], '55555555-5555-4555-8555-000000000001', 5),
    'another learner cannot write an owned attempt'
);
assessment_write_assert((int) $pdo->query('SELECT COUNT(*) FROM learner_assessment_answers')->fetchColumn() === 0, 'cross-learner write creates no answer');

$answers = [5, 4, 3, 2, 1, 2];
foreach ($answers as $position => $answer) {
    $questionId = sprintf('55555555-5555-4555-8555-%012d', $position + 1);
    $saved = $service->saveAnswer(ASSESSMENT_STUDENT_A, $attempt['id'], $questionId, $answer);
    assessment_write_assert(($saved['answers'][$questionId] ?? null) === $answer, 'saved answer is returned from canonical state');
}

$submitted = $service->submit(ASSESSMENT_STUDENT_A, $attempt['id']);
assessment_write_assert($submitted['assessment_id'] === ASSESSMENT_TEST_ID, 'submitted result keeps assessment identity');
assessment_write_assert($submitted['assessment_version'] === '1.0.0', 'submitted result keeps assessment version identity');
assessment_write_assert($submitted['scoring_version'] === 'holland-riasec-1.0', 'submitted result retains approved scoring version');
assessment_write_assert(preg_match('/^[a-f0-9]{64}$/', (string) $submitted['input_hash']) === 1, 'submitted result retains an input hash');
assessment_write_assert($submitted['result_code'] === 'RIA', 'approved Holland scoring calculates a deterministic result');

$optionalAttempt = $service->startOrResume(ASSESSMENT_STUDENT_C, 'holland', 'high');
foreach ([5, 4, 3, 2, 1] as $position => $answer) {
    $questionId = sprintf('55555555-5555-4555-8555-%012d', $position + 1);
    $service->saveAnswer(ASSESSMENT_STUDENT_C, $optionalAttempt['id'], $questionId, $answer);
}
$optionalSubmitted = $service->submit(ASSESSMENT_STUDENT_C, $optionalAttempt['id']);
assessment_write_assert($optionalSubmitted['result_code'] === 'RIA', 'an unanswered optional question does not block deterministic scoring');
assessment_write_assert(($optionalSubmitted['dimension_scores']['C'] ?? null) === 0, 'an unanswered optional dimension has a neutral score');

$missingRequiredAttempt = $service->startOrResume(ASSESSMENT_STUDENT_D, 'holland', 'high');
$service->saveAnswer(ASSESSMENT_STUDENT_D, $missingRequiredAttempt['id'], '55555555-5555-4555-8555-000000000001', 5);
assessment_write_expect_exception(
    static fn (): array => $service->submit(ASSESSMENT_STUDENT_D, $missingRequiredAttempt['id']),
    'an unanswered required question still rejects submission'
);

$nonNumericRequiredAttempt = $service->startOrResume(ASSESSMENT_STUDENT_E, 'holland', 'high');
foreach ([5, 'not-a-number', 3, 2, 1] as $position => $answer) {
    $questionId = sprintf('55555555-5555-4555-8555-%012d', $position + 1);
    $service->saveAnswer(ASSESSMENT_STUDENT_E, $nonNumericRequiredAttempt['id'], $questionId, $answer);
}
assessment_write_expect_exception(
    static fn (): array => $service->submit(ASSESSMENT_STUDENT_E, $nonNumericRequiredAttempt['id']),
    'a non-numeric required answer still rejects submission'
);

$replayed = $service->submit(ASSESSMENT_STUDENT_A, $attempt['id']);
assessment_write_assert($replayed === $submitted, 'the same submit request returns the existing canonical result');
assessment_write_assert((int) $pdo->query("SELECT COUNT(*) FROM test_results WHERE attemptId = '" . $attempt['id'] . "'")->fetchColumn() === 1, 'idempotent submit creates exactly one result row');

assessment_write_expect_exception(
    static fn (): array => $service->saveAnswer(ASSESSMENT_STUDENT_A, $attempt['id'], '55555555-5555-4555-8555-000000000001', 1),
    'a submitted attempt cannot accept a changed answer'
);
assessment_write_assert($pdo->query("SELECT answerJson FROM learner_assessment_answers WHERE attemptId = '" . $attempt['id'] . "' AND questionId = '55555555-5555-4555-8555-000000000001'")->fetchColumn() === '5', 'submitted answer remains immutable');

assessment_write_expect_exception(
    static fn (): array => $service->submit(ASSESSMENT_STUDENT_B, $attempt['id']),
    'another learner cannot read a submitted result'
);
assessment_write_expect_exception(
    static fn (): array => $service->startOrResume(ASSESSMENT_STUDENT_A, 'unknown_code', 'high'),
    'a missing assessment cannot start an attempt'
);

$factory = new RepositoryFactory('database', $pdo);
assessment_write_assert($factory->assessmentWrite() instanceof \TalentHub\Learner\Data\Contracts\AssessmentWriteRepository, 'database factory exposes the write contract');
learner_configure_data(['source' => 'database', 'pdo' => $pdo, 'student_id' => ASSESSMENT_STUDENT_A]);
assessment_write_assert(learner_assessment_write_service() instanceof LearnerAssessmentService, 'learner assessment helper exposes the server write service');

echo "learner_assessment_persistence_test: OK\n";
