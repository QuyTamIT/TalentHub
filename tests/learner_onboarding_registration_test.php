<?php

declare(strict_types=1);

use TalentHub\Auth\Repository\AuthRepository;

require_once dirname(__DIR__) . '/bin/bootstrap.php';

function registration_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function registration_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(<<<'SQL'
CREATE TABLE users (
    id TEXT PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    passwordHash TEXT NOT NULL,
    fullName TEXT NOT NULL,
    roles TEXT NOT NULL,
    status TEXT NOT NULL
);
CREATE TABLE schools (id TEXT PRIMARY KEY, status TEXT NOT NULL);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL);
CREATE TABLE student_profiles (
    id TEXT PRIMARY KEY,
    userId TEXT NOT NULL,
    classId TEXT NOT NULL,
    dateOfBirth TEXT NOT NULL,
    phone TEXT NOT NULL,
    studyStatus TEXT NOT NULL
);
CREATE TABLE learner_onboarding_states (
    studentId TEXT PRIMARY KEY,
    status TEXT NOT NULL DEFAULT 'pending'
);
CREATE TABLE audit_logs (
    id TEXT PRIMARY KEY,
    userId TEXT NOT NULL,
    action TEXT NOT NULL,
    entityType TEXT NOT NULL,
    entityId TEXT NOT NULL
);
INSERT INTO schools(id, status) VALUES('school-1', 'active');
INSERT INTO classes(id, schoolId) VALUES('class-1', 'school-1');
SQL);

    return $pdo;
}

$validData = [
    'email' => 'new-student@example.test',
    'passwordHash' => password_hash('StrongPassword!1', PASSWORD_DEFAULT),
    'fullName' => 'New Student',
    'classId' => 'class-1',
    'dateOfBirth' => '2008-01-02',
    'phone' => '0900000000',
];

$pdo = registration_fixture();
$repository = new AuthRepository($pdo);
$userId = $repository->createStudent($validData, 'registration-request', '127.0.0.1');
$statement = $pdo->prepare('SELECT id FROM student_profiles WHERE userId = ?');
$statement->execute([$userId]);
$studentId = (string) $statement->fetchColumn();
$statement = $pdo->prepare('SELECT status FROM learner_onboarding_states WHERE studentId = ?');
$statement->execute([$studentId]);
$state = $statement->fetchColumn();

registration_assert($userId !== '' && $studentId !== '', 'Successful registration creates user and student profile.');
registration_assert($state === 'pending', 'Successful registration creates pending onboarding.');
registration_assert((int) $pdo->query('SELECT COUNT(*) FROM learner_onboarding_states')->fetchColumn() === 1, 'Exactly one onboarding row exists.');

$rollbackPdo = registration_fixture();
$rollbackPdo->exec(<<<'SQL'
CREATE TRIGGER reject_onboarding_insert
BEFORE INSERT ON learner_onboarding_states
BEGIN
    SELECT RAISE(ABORT, 'forced onboarding failure');
END;
SQL);
$rollbackRepository = new AuthRepository($rollbackPdo);
$failed = false;
try {
    $rollbackRepository->createStudent(
        array_replace($validData, ['email' => 'rollback@example.test']),
        'rollback-request',
        null,
    );
} catch (Throwable $exception) {
    $failed = str_contains($exception->getMessage(), 'forced onboarding failure');
}

registration_assert($failed, 'Onboarding insertion failure is propagated.');
foreach (['users', 'student_profiles', 'learner_onboarding_states', 'audit_logs'] as $table) {
    registration_assert(
        (int) $rollbackPdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() === 0,
        "Registration transaction rolls back {$table}.",
    );
}

echo "learner_onboarding_registration_test: OK\n";
