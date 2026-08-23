<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository;
use TalentHub\Learner\Data\Exceptions\LearnerDataQueryException;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function passport_data_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Schema setup
$pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT, email TEXT, status TEXT)');
$pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT, status TEXT)');
$pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel TEXT, academicYear TEXT, status TEXT)');
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, studyStatus TEXT)');
$pdo->exec('CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT, schoolId TEXT, isSchoolAdmin INT)');
$pdo->exec('CREATE TABLE skills (id TEXT PRIMARY KEY, code TEXT, name TEXT, category TEXT, status TEXT)');
$pdo->exec('CREATE TABLE student_skills (studentId TEXT, skillId TEXT, levelScore REAL, sourceType TEXT, verificationStatus TEXT, verifiedAt TEXT)');
$pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, createdByTeacherId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, status TEXT)');
$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT, registeredAt TEXT)');
$pdo->exec('CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT, qrSessionId TEXT, status TEXT, checkedInAt TEXT, confirmedAt TEXT)');
$pdo->exec('CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, checkinId TEXT, hours REAL, status TEXT, confirmedAt TEXT)');
$pdo->exec('CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT, name TEXT, type TEXT, status TEXT)');
$pdo->exec('CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT, studentId TEXT, status TEXT, startedAt TEXT, submittedAt TEXT)');
$pdo->exec('CREATE TABLE test_results (attemptId TEXT, resultCode TEXT, summary TEXT, dimensionScoresJson TEXT, scoringVersion TEXT, createdAt TEXT)');
$pdo->exec('CREATE TABLE assessments (id TEXT PRIMARY KEY, teacherId TEXT, studentId TEXT, activityId TEXT, overallScore REAL, comment TEXT, status TEXT, publishedAt TEXT, version INT)');
$pdo->exec('CREATE TABLE assessment_scores (assessmentId TEXT, criteriaId TEXT, score REAL)');
$pdo->exec('CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, code TEXT, name TEXT, minScore REAL, maxScore REAL, displayOrder INT, status TEXT)');

// 2. Fixture setup for Student A and Student B (foreign student)
$studentA = '0191316b-1000-4000-8000-000000000001';
$userA = '0191316b-2000-4000-8000-000000000001';
$studentB = '0191316b-1000-4000-8000-000000000002';
$userB = '0191316b-2000-4000-8000-000000000002';
$teacherUser = '0191316b-2000-4000-8000-000000000003';
$teacherProfile = '0191316b-3000-4000-8000-000000000003'; // Distinct teacher profile ID

$pdo->exec("INSERT INTO users VALUES ('{$userA}', 'Student Alpha', 'alpha@example.com', 'active')");
$pdo->exec("INSERT INTO users VALUES ('{$userB}', 'Student Beta', 'beta@example.com', 'active')");
$pdo->exec("INSERT INTO users VALUES ('{$teacherUser}', 'Teacher Minh', 'minh@example.com', 'active')");

$pdo->exec("INSERT INTO schools VALUES ('sch-1', 'THPT Chuyên', 'active')");
$pdo->exec("INSERT INTO classes VALUES ('cls-1', 'sch-1', '12A1', '12', '2025-2026', 'active')");

$pdo->exec("INSERT INTO student_profiles VALUES ('{$studentA}', '{$userA}', 'cls-1', 'studying')");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$studentB}', '{$userB}', 'cls-1', 'studying')");
$pdo->exec("INSERT INTO teacher_profiles VALUES ('{$teacherProfile}', '{$teacherUser}', 'sch-1', 0)");

$pdo->exec("INSERT INTO skills VALUES ('sk-1', 'PY', 'Python', 'technical', 'active')");
$pdo->exec("INSERT INTO skills VALUES ('sk-2', 'COMM', 'Communication', 'soft', 'active')");

$pdo->exec("INSERT INTO student_skills VALUES ('{$studentA}', 'sk-1', 85.0, 'assessment', 'verified', '2026-08-01 10:00:00')");
$pdo->exec("INSERT INTO student_skills VALUES ('{$studentB}', 'sk-2', 90.0, 'self', 'unverified', '2026-08-02 10:00:00')");

$pdo->exec("INSERT INTO activities VALUES ('act-1', 'sch-1', '{$teacherProfile}', 'AI Workshop', 'workshop', '2026-08-10 08:00:00', '2026-08-10 12:00:00', 'completed')");
$pdo->exec("INSERT INTO activities VALUES ('act-2', 'sch-1', '{$teacherProfile}', 'Hackathon', 'competition', '2026-08-15 08:00:00', '2026-08-15 18:00:00', 'completed')");

$pdo->exec("INSERT INTO activity_registrations VALUES ('reg-1', 'act-1', '{$studentA}', 'attended', '2026-08-01 09:00:00')");
$pdo->exec("INSERT INTO activity_registrations VALUES ('reg-2', 'act-2', '{$studentB}', 'attended', '2026-08-02 09:00:00')");

$pdo->exec("INSERT INTO checkins VALUES ('chk-1', 'reg-1', 'qr-1', 'confirmed', '2026-08-10 08:05:00', '2026-08-10 08:10:00')");
$pdo->exec("INSERT INTO checkins VALUES ('chk-2', 'reg-2', 'qr-2', 'confirmed', '2026-08-15 08:05:00', '2026-08-15 08:10:00')");

// Experience logs: Student A has 2.5 confirmed and 4.0 pending. Student B has 10.0 confirmed.
$pdo->exec("INSERT INTO experience_logs VALUES ('exp-1', '{$studentA}', 'act-1', 'chk-1', 2.5, 'confirmed', '2026-08-10 12:00:00')");
$pdo->exec("INSERT INTO experience_logs VALUES ('exp-2', '{$studentA}', 'act-1', 'chk-1', 4.0, 'pending', '2026-08-10 12:00:00')");
$pdo->exec("INSERT INTO experience_logs VALUES ('exp-3', '{$studentB}', 'act-2', 'chk-2', 10.0, 'confirmed', '2026-08-15 18:00:00')");

// Talent tests:
// att-1: submitted WITH persisted test_results
// att-2: in_progress
// att-no-res: submitted WITHOUT test_results (must NOT be returned)
// att-3: Student B attempt
$pdo->exec("INSERT INTO talent_tests VALUES ('test-1', 'LOGIC', 'Logic Test', 'standard', 'active')");
$pdo->exec("INSERT INTO test_attempts VALUES ('att-1', 'test-1', '{$studentA}', 'submitted', '2026-08-05 10:00:00', '2026-08-05 11:00:00')");
$pdo->exec("INSERT INTO test_attempts VALUES ('att-2', 'test-1', '{$studentA}', 'in_progress', '2026-08-06 10:00:00', NULL)");
$pdo->exec("INSERT INTO test_attempts VALUES ('att-no-res', 'test-1', '{$studentA}', 'submitted', '2026-08-06 15:00:00', '2026-08-06 16:00:00')");
$pdo->exec("INSERT INTO test_attempts VALUES ('att-3', 'test-1', '{$studentB}', 'submitted', '2026-08-07 10:00:00', '2026-08-07 11:00:00')");

$pdo->exec("INSERT INTO test_results VALUES ('att-1', 'PASS', 'Good logic', '{\"logic\":85}', 'v1', '2026-08-05 11:05:00')");
$pdo->exec("INSERT INTO test_results VALUES ('att-3', 'DISTINCTION', 'Student B Secret Result', '{\"logic\":99}', 'v1', '2026-08-07 11:05:00')");

// Assessments (Teacher evaluations): teacherId refers to teacher_profiles.id
$pdo->exec("INSERT INTO assessments VALUES ('eval-1', '{$teacherProfile}', '{$studentA}', 'act-1', 9.0, 'Excellent participation', 'published', '2026-08-11 10:00:00', 1)");
$pdo->exec("INSERT INTO assessments VALUES ('eval-2', '{$teacherProfile}', '{$studentA}', 'act-1', 7.0, 'Draft eval', 'draft', NULL, 1)");
$pdo->exec("INSERT INTO assessments VALUES ('eval-3', '{$teacherProfile}', '{$studentB}', 'act-2', 10.0, 'Student B Secret Evaluation', 'published', '2026-08-16 10:00:00', 1)");

$pdo->exec("INSERT INTO assessment_criteria VALUES ('crit-1', 'ENGAGE', 'Engagement', 0.0, 10.0, 1, 'active')");
$pdo->exec("INSERT INTO assessment_scores VALUES ('eval-1', 'crit-1', 9.0)");
$pdo->exec("INSERT INTO assessment_scores VALUES ('eval-3', 'crit-1', 10.0)");

// 3. Test DatabaseTalentPassportRepository
$repo = new DatabaseTalentPassportRepository($pdo);
$passportA = $repo->aggregateForStudent($studentA);

// Assert student identity
passport_data_assert(($passportA['student']['id'] ?? null) === $studentA, 'Student ID matches student A');
passport_data_assert(($passportA['student']['full_name'] ?? null) === 'Student Alpha', 'Student Alpha name resolved');
passport_data_assert(($passportA['student']['class_name'] ?? null) === '12A1', 'Class 12A1 resolved');
passport_data_assert(($passportA['student']['school_name'] ?? null) === 'THPT Chuyên', 'School resolved');

// Assert student skills isolation and realistic level score
passport_data_assert(count($passportA['skills']) === 1, 'Only 1 skill for student A');
passport_data_assert($passportA['skills'][0]['code'] === 'PY', 'Skill is Python');
passport_data_assert((float) $passportA['skills'][0]['level_score'] === 85.0, 'Skill levelScore is 85.0 directly from schema');
passport_data_assert(!in_array('Communication', array_column($passportA['skills'], 'name'), true), 'Student B skill not present');

// Assert experience hours and entries (only confirmed)
passport_data_assert($passportA['experience']['confirmed_hours'] === 2.5, 'Only confirmed experience is summed (2.5, not 6.5 or 12.5)');
passport_data_assert(count($passportA['experience']['confirmed_entries']) === 1, 'Only 1 confirmed entry for Student A');
passport_data_assert($passportA['experience']['confirmed_entries'][0]['id'] === 'exp-1', 'Correct confirmed entry');

// Assert automated assessment results (only submitted WITH test_results)
passport_data_assert(count($passportA['assessment_results']) === 1, 'Only 1 submitted assessment attempt WITH results for Student A');
passport_data_assert($passportA['assessment_results'][0]['attempt_id'] === 'att-1', 'Correct attempt ID');
$attemptIds = array_column($passportA['assessment_results'], 'attempt_id');
passport_data_assert(!in_array('att-no-res', $attemptIds, true), 'Submitted attempt without persisted result is excluded');
passport_data_assert(!str_contains(json_encode($passportA), 'Student B Secret Result'), 'Student B test results do not leak');

// Assert teacher evaluations (only published and teacher name resolved from users via teacher_profiles)
passport_data_assert(count($passportA['teacher_evaluations']) === 1, 'Only 1 published evaluation for Student A');
passport_data_assert($passportA['teacher_evaluations'][0]['id'] === 'eval-1', 'Correct evaluation ID');
passport_data_assert($passportA['teacher_evaluations'][0]['teacher_name'] === 'Teacher Minh', 'Teacher name resolved via teacher_profiles join');
passport_data_assert($passportA['teacher_evaluations'][0]['overall_score'] === 9.0, 'Correct overall score');
passport_data_assert(count($passportA['teacher_evaluations'][0]['criteria_scores']) === 1, 'Criteria scores included');
passport_data_assert(!str_contains(json_encode($passportA), 'Student B Secret Evaluation'), 'Student B evaluations do not leak');

// Assert absent optional facts
passport_data_assert($passportA['certificates'] === [], 'Absent certificates is empty array');
passport_data_assert($passportA['projects'] === [], 'Absent projects is empty array');
passport_data_assert($passportA['badges'] === [], 'Absent badges is empty array');
passport_data_assert($passportA['capabilities']['certificates'] === false, 'Capability certificates false');
passport_data_assert($passportA['capabilities']['projects'] === false, 'Capability projects false');
passport_data_assert($passportA['capabilities']['badges'] === false, 'Capability badges false');

// 4. Test partial / incompatible optional group behavior (e.g. certificates with missing columns)
$pdo->exec('CREATE TABLE certificates (id TEXT PRIMARY KEY, studentId TEXT)');
$passportIncompatible = $repo->aggregateForStudent($studentA);
passport_data_assert($passportIncompatible['certificates'] === [], 'Incompatible certificates schema returns empty array');
passport_data_assert($passportIncompatible['capabilities']['certificates'] === false, 'Incompatible certificates capability is false');

// 5. Test partial optional group behavior (projects table exists without project_members)
$pdo->exec('CREATE TABLE projects (id TEXT PRIMARY KEY, title TEXT, status TEXT, createdAt TEXT)');
$passportAfterPartial = $repo->aggregateForStudent($studentA);
passport_data_assert($passportAfterPartial['projects'] === [], 'Partial optional group projects returns empty array');
passport_data_assert($passportAfterPartial['capabilities']['projects'] === false, 'Partial optional group capability is false without SQL error');

// 6. Missing authenticated student identity must fail safely rather than fabricate a partial passport
$missingStudentRejected = false;
try {
    $repo->aggregateForStudent('ffffffff-ffff-4fff-8fff-ffffffffffff');
} catch (LearnerDataQueryException $exception) {
    $missingStudentRejected = $exception->getMessage() === 'Authenticated learner profile was not found.';
}
passport_data_assert($missingStudentRejected, 'Missing student throws a safe learner data exception');

// 7. Canonical future certificate schema is readable, while the legacy shape above remains unavailable
$pdo->exec('DROP TABLE certificates');
$pdo->exec('CREATE TABLE certificates (id TEXT PRIMARY KEY, studentId TEXT, title TEXT, issuingOrganization TEXT, issueDate TEXT, expiryDate TEXT, credentialId TEXT, credentialUrl TEXT, verificationStatus TEXT, verifiedBy TEXT, verifiedAt TEXT, createdAt TEXT, updatedAt TEXT)');
$pdo->exec('CREATE INDEX idx_certificates_student_status ON certificates(studentId, verificationStatus)');
$pdo->exec("INSERT INTO certificates VALUES ('cert-a', '{$studentA}', 'Canonical Certificate', 'TalentHub Academy', '2026-08-01', NULL, 'cred-a', 'https://example.test/a', 'verified', '{$teacherProfile}', '2026-08-02', '2026-08-01', '2026-08-02')");
$pdo->exec("INSERT INTO certificates VALUES ('cert-b', '{$studentB}', 'Student B Secret Certificate', 'TalentHub Academy', '2026-08-01', NULL, 'cred-b', 'https://example.test/b', 'verified', '{$teacherProfile}', '2026-08-02', '2026-08-01', '2026-08-02')");
$passportWithCertificate = $repo->aggregateForStudent($studentA);
passport_data_assert($passportWithCertificate['capabilities']['certificates'] === true, 'Canonical certificate capability is available');
passport_data_assert(count($passportWithCertificate['certificates']) === 1, 'Only authenticated student certificate is returned');
passport_data_assert(($passportWithCertificate['certificates'][0]['title'] ?? null) === 'Canonical Certificate', 'Canonical certificate is mapped');
passport_data_assert(!str_contains(json_encode($passportWithCertificate), 'Student B Secret Certificate'), 'Foreign certificate does not leak');

echo "learner_talent_passport_data_test: OK\n";
