<?php

declare(strict_types=1);

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Http\ApiException;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/onboarding.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(<<<'SQL'
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL UNIQUE);
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
INSERT INTO student_profiles(id, userId) VALUES('student-1', 'user-1');
INSERT INTO learner_onboarding_states(studentId, status) VALUES('student-1', 'pending');
SQL);

$sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'talenthub-onboarding-endpoint-' . bin2hex(random_bytes(6));
$session = new SessionManager([
    'name' => 'TALENTHUBONBOARDINGTEST',
    'lifetime' => 7200,
    'secure' => false,
    'sameSite' => 'Lax',
    'savePath' => $sessionPath,
]);
$session->start();
$_SESSION = [
    'user' => ['id' => 'user-1', 'email' => 'student@example.test', 'fullName' => 'Student', 'role' => 'student', 'status' => 'active'],
    'csrfToken' => 'csrf-test-token',
];

$destination = learner_onboarding_decide(
    $pdo,
    $session,
    ['action' => 'accept', 'csrfToken' => 'csrf-test-token'],
    'POST',
    'request-accept',
    '127.0.0.1',
);
$assert($destination === '/app/learner/assessment.php?code=holland', 'Accept redirects to Holland.');
$assert($pdo->query("SELECT status FROM learner_onboarding_states WHERE studentId='student-1'")->fetchColumn() === 'accepted', 'Accept persists accepted status.');

$pdo->exec("UPDATE learner_onboarding_states SET status='pending', acceptedAt=NULL WHERE studentId='student-1'");
$csrfError = null;
try {
    learner_onboarding_decide($pdo, $session, ['action' => 'accept', 'csrfToken' => 'wrong'], 'POST', 'request-csrf', null);
} catch (ApiException $exception) {
    $csrfError = $exception;
}
$assert($csrfError?->status === 403, 'Invalid CSRF returns 403.');
$assert($pdo->query("SELECT status FROM learner_onboarding_states WHERE studentId='student-1'")->fetchColumn() === 'pending', 'Invalid CSRF does not mutate state.');

foreach ([
    ['GET', ['action' => 'accept'], 405],
    ['POST', ['action' => 'unknown', 'csrfToken' => 'csrf-test-token'], 422],
] as [$method, $post, $expectedStatus]) {
    $error = null;
    try {
        learner_onboarding_decide($pdo, $session, $post, $method, 'request-invalid', null);
    } catch (ApiException $exception) {
        $error = $exception;
    }
    $assert($error?->status === $expectedStatus, "{$method}/unknown request is rejected.");
}

$destination = learner_onboarding_decide(
    $pdo,
    $session,
    ['action' => 'decline', 'csrfToken' => 'csrf-test-token'],
    'POST',
    'request-decline',
    null,
);
$assert($destination === '/login.php?onboarding=declined', 'Decline redirects to fixed login URL.');
$assert($session->user() === null, 'Decline destroys the session.');
$assert($pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='learner.onboarding_declined'")->fetchColumn() === 1, 'Decline is audited.');

echo "learner_onboarding_endpoint_test: OK\n";
