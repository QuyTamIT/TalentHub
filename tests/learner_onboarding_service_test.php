<?php

declare(strict_types=1);

use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Repository\LearnerOnboardingRepository;
use TalentHub\Modules\Student\Service\LearnerOnboardingService;

require_once dirname(__DIR__) . '/bin/bootstrap.php';

function onboarding_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function seed_submitted_result(PDO $pdo, string $studentId, string $code, string $suffix = ''): void
{
    $testId = 'test-' . $studentId . '-' . $code . $suffix;
    $attemptId = 'attempt-' . $studentId . '-' . $code . $suffix;
    $pdo->prepare('INSERT INTO talent_tests(id, code, type) VALUES(?, ?, ?)')
        ->execute([$testId, $code, str_starts_with($code, 'holland') ? 'interest' : 'personality']);
    $pdo->prepare("INSERT INTO test_attempts(id, testId, studentId, status) VALUES(?, ?, ?, 'submitted')")
        ->execute([$attemptId, $testId, $studentId]);
    $pdo->prepare('INSERT INTO test_results(id, attemptId) VALUES(?, ?)')
        ->execute(['result-' . $studentId . '-' . $code . $suffix, $attemptId]);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(<<<'SQL'
CREATE TABLE learner_onboarding_states (
    studentId TEXT PRIMARY KEY,
    status TEXT NOT NULL,
    acceptedAt TEXT NULL,
    completedAt TEXT NULL,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT NOT NULL, type TEXT NOT NULL);
CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE test_results (id TEXT PRIMARY KEY, attemptId TEXT NOT NULL UNIQUE);
CREATE TABLE audit_logs (
    id TEXT PRIMARY KEY,
    userId TEXT NULL,
    action TEXT NOT NULL,
    entityType TEXT NULL,
    entityId TEXT NULL,
    requestId TEXT NULL,
    ipAddress TEXT NULL,
    metadata TEXT NULL,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO learner_onboarding_states(studentId, status) VALUES('new-student', 'pending');
SQL);

$service = new LearnerOnboardingService(new LearnerOnboardingRepository($pdo));

$legacy = $service->progress('legacy-student');
onboarding_assert($legacy['required'] === false, 'Missing row exempts existing student.');
onboarding_assert($legacy['status'] === 'not_required', 'Legacy progress has explicit status.');

$pending = $service->progress('new-student');
onboarding_assert($pending['required'] === true && $pending['status'] === 'pending', 'New student starts pending.');
onboarding_assert($pending['next_code'] === null, 'Pending student must accept before a next assessment is exposed.');

$accepted = $service->accept('new-student', 'new-user', 'request-1', '127.0.0.1');
onboarding_assert($accepted['status'] === 'accepted' && $accepted['next_code'] === 'holland', 'Acceptance starts Holland.');
onboarding_assert(LearnerOnboardingService::normalizeCode('multiple_intelligence_college') === 'multiple_intelligence', 'Band suffix normalizes.');
onboarding_assert(LearnerOnboardingService::normalizeCode('personality') === null, 'Broad type is not a required assessment code.');

$sequenceError = null;
try {
    $service->assertAssessmentAccessible('new-student', 'disc_high');
} catch (ApiException $exception) {
    $sequenceError = $exception;
}
onboarding_assert($sequenceError?->errorCode === 'ONBOARDING_SEQUENCE_REQUIRED', 'Later assessment is rejected by sequence gate.');
$service->assertAssessmentAccessible('new-student', 'holland_high');

seed_submitted_result($pdo, 'new-student', 'holland_high');
seed_submitted_result($pdo, 'new-student', 'holland_college', '-duplicate');
seed_submitted_result($pdo, 'new-student', 'mbti_high');
seed_submitted_result($pdo, 'new-student', 'disc_high');
seed_submitted_result($pdo, 'new-student', 'personality');

$three = $service->reconcile('new-student', 'new-user', 'request-2', null);
onboarding_assert($three['completed_count'] === 3 && $three['next_code'] === 'multiple_intelligence', 'Three distinct required tests remain gated.');

$service->assertAssessmentAccessible('new-student', 'disc_high');
$service->assertAssessmentAccessible('new-student', 'multiple_intelligence_high');

seed_submitted_result($pdo, 'other-student', 'multiple_intelligence_high');
onboarding_assert($service->reconcile('new-student', 'new-user', 'request-3', null)['status'] === 'accepted', 'Other learner cannot complete onboarding.');

seed_submitted_result($pdo, 'new-student', 'multiple_intelligence_high');
$completed = $service->reconcile('new-student', 'new-user', 'request-4', null);
onboarding_assert($completed['status'] === 'completed' && $completed['completed_count'] === 4, 'Four owned types complete onboarding.');

$events = $pdo->query('SELECT action, metadata FROM audit_logs ORDER BY createdAt, rowid')->fetchAll();
onboarding_assert(array_column($events, 'action') === ['learner.onboarding_accepted', 'learner.onboarding_completed'], 'Accepted and completed transitions are audited once.');
$metadata = json_decode((string) $events[1]['metadata'], true, 512, JSON_THROW_ON_ERROR);
onboarding_assert(array_keys($metadata) === ['from', 'to', 'completedCodes'], 'Audit metadata is allow-listed.');

$pdo->exec("INSERT INTO learner_onboarding_states(studentId, status) VALUES('declining-student', 'pending')");
$service->decline('declining-student', 'declining-user', 'request-5', null);
$decline = $pdo->query("SELECT action FROM audit_logs WHERE userId='declining-user'")->fetchColumn();
onboarding_assert($decline === 'learner.onboarding_declined', 'Decline is audited without changing onboarding state.');

echo "learner_onboarding_service_test: OK\n";
