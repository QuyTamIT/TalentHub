<?php

declare(strict_types=1);

use TalentHub\Learner\Assessment\Scoring\HollandScorer;
use TalentHub\Learner\Assessment\Scoring\MbtiScorer;
use TalentHub\Learner\Assessment\Scoring\DiscScorer;
use TalentHub\Learner\Assessment\Scoring\MultipleIntelligenceScorer;
use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use TalentHub\Learner\Assessment\Service\AssessmentCatalogService;
use TalentHub\Learner\Assessment\Service\EducationBandResolver;
use TalentHub\Learner\Data\Database\DatabaseAssessmentRepository;
use TalentHub\Learner\Data\Database\DatabaseAssessmentWriteRepository;
use TalentHub\Learner\Data\Mock\MockAssessmentRepository;
use TalentHub\Learner\Data\Service\LearnerAssessmentService;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

const STUDENT_HIGH_ID = '11111111-1111-4111-8111-111111111111';
const STUDENT_MIDDLE_ID = '22222222-2222-4222-8222-222222222222';
const STUDENT_COLLEGE_ID = '33333333-3333-4333-8333-333333333333';
const STUDENT_OTHER_ID = '44444444-4444-4444-8444-444444444444';

const TEST_HOLLAND_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-000000000001';
const TEST_MBTI_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-000000000002';
const TEST_DISC_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-000000000003';
const TEST_MI_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-000000000004';
const TEST_DRAFT_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-000000000005';
const TEST_ROGUE_SUFFIX_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-000000000006';

const VERSION_HOLLAND_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-000000000001';
const VERSION_MBTI_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-000000000002';
const VERSION_DISC_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-000000000003';
const VERSION_MI_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-000000000004';
const VERSION_HOLLAND_V2_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-000000000005';
const VERSION_ROGUE_SUFFIX_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-000000000006';

const IN_PROGRESS_ATTEMPT_ID = 'cccccccc-cccc-4ccc-8ccc-000000000001';
const SUBMITTED_RECENT_ATTEMPT_ID = 'cccccccc-cccc-4ccc-8ccc-000000000002';
const SUBMITTED_OLD_ATTEMPT_ID = 'cccccccc-cccc-4ccc-8ccc-000000000003';
const EXPIRED_ATTEMPT_ID = 'cccccccc-cccc-4ccc-8ccc-000000000004';

function catalog_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function catalog_expect_exception(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (\Throwable) {
        return;
    }

    catalog_assert(false, $message);
}

function catalog_test_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    // Schools and classes for band resolution
    $pdo->exec('CREATE TABLE schools (id CHAR(36) NOT NULL PRIMARY KEY, name TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE classes (id CHAR(36) NOT NULL PRIMARY KEY, schoolId CHAR(36) NOT NULL, name TEXT NOT NULL, gradeLevel INTEGER NOT NULL, academicYear TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE users (id CHAR(36) NOT NULL PRIMARY KEY, email TEXT NOT NULL, fullName TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE student_profiles (id CHAR(36) NOT NULL PRIMARY KEY, userId CHAR(36) NOT NULL, classId CHAR(36) NULL)');

    // Assessment canonical schema
    $pdo->exec('CREATE TABLE talent_tests (id CHAR(36) NOT NULL PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, type TEXT NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE test_questions (id CHAR(36) NOT NULL PRIMARY KEY, testId CHAR(36) NOT NULL, code TEXT NOT NULL, content TEXT NOT NULL, optionsJson TEXT NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL, FOREIGN KEY (testId) REFERENCES talent_tests(id))');
    $pdo->exec('CREATE TABLE learner_assessment_versions (id CHAR(36) NOT NULL PRIMARY KEY, testId CHAR(36) NOT NULL, version TEXT NOT NULL, scoringVersion TEXT NOT NULL, schemaHash CHAR(64) NOT NULL, status TEXT NOT NULL, publishedAt TEXT NULL, createdAt TEXT NOT NULL, FOREIGN KEY (testId) REFERENCES talent_tests(id))');
    $pdo->exec('CREATE TABLE learner_assessment_question_versions (id CHAR(36) NOT NULL PRIMARY KEY, versionId CHAR(36) NOT NULL, questionId CHAR(36) NOT NULL, position INTEGER NOT NULL, dimensionCode TEXT NOT NULL, required INTEGER NOT NULL, createdAt TEXT NOT NULL, FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id), FOREIGN KEY (questionId) REFERENCES test_questions(id))');
    $pdo->exec('CREATE TABLE test_attempts (id CHAR(36) NOT NULL PRIMARY KEY, testId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, status TEXT NOT NULL, startedAt TEXT NOT NULL, submittedAt TEXT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL, FOREIGN KEY (testId) REFERENCES talent_tests(id), FOREIGN KEY (studentId) REFERENCES student_profiles(id))');
    $pdo->exec('CREATE TABLE learner_assessment_attempt_metadata (id CHAR(36) NOT NULL PRIMARY KEY, attemptId CHAR(36) NOT NULL UNIQUE, versionId CHAR(36) NOT NULL, status TEXT NOT NULL, expiresAt TEXT NULL, submittedAt TEXT NULL, inputHash CHAR(64) NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL, FOREIGN KEY (attemptId) REFERENCES test_attempts(id), FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id))');
    $pdo->exec('CREATE TABLE learner_assessment_answers (id CHAR(36) NOT NULL PRIMARY KEY, attemptId CHAR(36) NOT NULL, questionId CHAR(36) NOT NULL, answerJson TEXT NOT NULL, answeredAt TEXT NOT NULL, UNIQUE (attemptId, questionId), FOREIGN KEY (attemptId) REFERENCES learner_assessment_attempt_metadata(attemptId), FOREIGN KEY (questionId) REFERENCES test_questions(id))');
    $pdo->exec('CREATE TABLE test_results (id CHAR(36) NOT NULL PRIMARY KEY, attemptId CHAR(36) NOT NULL UNIQUE, resultCode TEXT NOT NULL, summary TEXT NOT NULL, dimensionScoresJson TEXT NOT NULL, scoringVersion TEXT NOT NULL, createdAt TEXT NOT NULL, FOREIGN KEY (attemptId) REFERENCES test_attempts(id))');

    // Seed school and classes
    $schoolId = '00000000-0000-4000-8000-000000000001';
    $classHighId = '00000000-0000-4000-8000-000000000002';
    $classMiddleId = '00000000-0000-4000-8000-000000000003';
    $classPrimaryId = '00000000-0000-4000-8000-000000000004';

    $pdo->exec("INSERT INTO schools (id, name) VALUES ('{$schoolId}', 'High School')");
    $pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES ('{$classHighId}', '{$schoolId}', '11A', 11, '2026-2027')");
    $pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES ('{$classMiddleId}', '{$schoolId}', '8B', 8, '2026-2027')");
    $pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES ('{$classPrimaryId}', '{$schoolId}', '5C', 5, '2026-2027')");

    // Seed students
    $pdo->exec("INSERT INTO users (id, email, fullName) VALUES ('u1', 's1@test.local', 'High Student'), ('u2', 's2@test.local', 'Middle Student'), ('u3', 's3@test.local', 'College Student'), ('u4', 's4@test.local', 'Other Student')");
    $pdo->exec("INSERT INTO student_profiles (id, userId, classId) VALUES ('" . STUDENT_HIGH_ID . "', 'u1', '{$classHighId}')");
    $pdo->exec("INSERT INTO student_profiles (id, userId, classId) VALUES ('" . STUDENT_MIDDLE_ID . "', 'u2', '{$classMiddleId}')");
    $pdo->exec("INSERT INTO student_profiles (id, userId, classId) VALUES ('" . STUDENT_COLLEGE_ID . "', 'u3', NULL)");
    $pdo->exec("INSERT INTO student_profiles (id, userId, classId) VALUES ('" . STUDENT_OTHER_ID . "', 'u4', '{$classPrimaryId}')");

    // Seed 4 published tests for 'high' band + 1 draft test
    $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $pdo->exec("INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES ('" . TEST_HOLLAND_ID . "', 'holland_high', 'Holland High', 'holland', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES ('" . TEST_MBTI_ID . "', 'mbti_high', 'MBTI High', 'mbti', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES ('" . TEST_DISC_ID . "', 'disc_high', 'DISC High', 'disc', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES ('" . TEST_MI_ID . "', 'multiple_intelligence_high', 'MI High', 'multiple_intelligence', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES ('" . TEST_DRAFT_ID . "', 'draft_test', 'Draft Test', 'skills', 'draft', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES ('" . TEST_ROGUE_SUFFIX_ID . "', 'roguehigh', 'Invalid High Suffix', 'invalid', 'published', '{$now}', '{$now}')");

    // Seed versions
    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt) VALUES ('" . VERSION_HOLLAND_ID . "', '" . TEST_HOLLAND_ID . "', '1.0.0', 'holland-riasec-1.0', 'hash1', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt) VALUES ('" . VERSION_MBTI_ID . "', '" . TEST_MBTI_ID . "', '1.0.0', 'mbti-education-1.0', 'hash2', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt) VALUES ('" . VERSION_DISC_ID . "', '" . TEST_DISC_ID . "', '1.0.0', 'disc-education-1.0', 'hash3', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt) VALUES ('" . VERSION_MI_ID . "', '" . TEST_MI_ID . "', '1.0.0', 'multiple-intelligence-1.0', 'hash4', 'published', '{$now}', '{$now}')");
    $newer = (new DateTimeImmutable($now, new DateTimeZone('UTC')))->modify('+1 second')->format('Y-m-d H:i:s');
    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt) VALUES ('" . VERSION_HOLLAND_V2_ID . "', '" . TEST_HOLLAND_ID . "', '2.0.0', 'holland-riasec-1.0', 'hash5', 'published', '{$newer}', '{$newer}')");
    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt) VALUES ('" . VERSION_ROGUE_SUFFIX_ID . "', '" . TEST_ROGUE_SUFFIX_ID . "', '1.0.0', 'holland-riasec-1.0', 'hash6', 'published', '{$now}', '{$now}')");

    // Seed questions
    $optionsJson = json_encode([['value' => 1, 'label' => '1'], ['value' => 2, 'label' => '2'], ['value' => 3, 'label' => '3'], ['value' => 4, 'label' => '4'], ['value' => 5, 'label' => '5']]);
    foreach (['R', 'I', 'A', 'S', 'E', 'C'] as $idx => $dim) {
        $qId = sprintf('q-h-%02d', $idx + 1);
        $qvId = sprintf('qv-h-%02d', $idx + 1);
        $pdo->exec("INSERT INTO test_questions (id, testId, code, content, optionsJson, status, createdAt, updatedAt) VALUES ('{$qId}', '" . TEST_HOLLAND_ID . "', 'Q_H_{$dim}', 'Holland Question {$dim}', '{$optionsJson}', 'published', '{$now}', '{$now}')");
        $pdo->exec("INSERT INTO learner_assessment_question_versions (id, versionId, questionId, position, dimensionCode, required, createdAt) VALUES ('{$qvId}', '" . VERSION_HOLLAND_ID . "', '{$qId}', " . ($idx + 1) . ", '{$dim}', 1, '{$now}')");
        $pdo->exec("INSERT INTO learner_assessment_question_versions (id, versionId, questionId, position, dimensionCode, required, createdAt) VALUES ('qv-h2-" . sprintf('%02d', $idx + 1) . "', '" . VERSION_HOLLAND_V2_ID . "', '{$qId}', " . ($idx + 1) . ", '{$dim}', 1, '{$newer}')");
    }

    // Seed 1 in-progress attempt for holland
    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+30 days')->format('Y-m-d H:i:s');
    $pdo->exec("INSERT INTO test_attempts (id, testId, studentId, status, startedAt, submittedAt, createdAt, updatedAt) VALUES ('" . IN_PROGRESS_ATTEMPT_ID . "', '" . TEST_HOLLAND_ID . "', '" . STUDENT_HIGH_ID . "', 'in_progress', '{$now}', NULL, '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status, expiresAt, submittedAt, inputHash, createdAt, updatedAt) VALUES ('meta-1', '" . IN_PROGRESS_ATTEMPT_ID . "', '" . VERSION_HOLLAND_ID . "', 'in_progress', '{$expiresAt}', NULL, NULL, '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO learner_assessment_answers (id, attemptId, questionId, answerJson, answeredAt) VALUES ('ans-1', '" . IN_PROGRESS_ATTEMPT_ID . "', 'q-h-01', '5', '{$now}')");

    // Seed 1 submitted attempt 30 days ago for MBTI
    $submitted30DaysAgo = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-30 days')->format('Y-m-d H:i:s');
    $pdo->exec("INSERT INTO test_attempts (id, testId, studentId, status, startedAt, submittedAt, createdAt, updatedAt) VALUES ('" . SUBMITTED_RECENT_ATTEMPT_ID . "', '" . TEST_MBTI_ID . "', '" . STUDENT_HIGH_ID . "', 'submitted', '{$submitted30DaysAgo}', '{$submitted30DaysAgo}', '{$submitted30DaysAgo}', '{$submitted30DaysAgo}')");
    $pdo->exec("INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status, expiresAt, submittedAt, inputHash, createdAt, updatedAt) VALUES ('meta-2', '" . SUBMITTED_RECENT_ATTEMPT_ID . "', '" . VERSION_MBTI_ID . "', 'submitted', NULL, '{$submitted30DaysAgo}', 'fakehash', '{$submitted30DaysAgo}', '{$submitted30DaysAgo}')");
    $pdo->exec("INSERT INTO test_results (id, attemptId, resultCode, summary, dimensionScoresJson, scoringVersion, createdAt) VALUES ('res-2', '" . SUBMITTED_RECENT_ATTEMPT_ID . "', 'ESTJ', 'MBTI summary', '{\"E\":100}', 'mbti-education-1.0', '{$submitted30DaysAgo}')");

    // Seed one submitted attempt older than 90 days and one expired draft.
    $submitted120DaysAgo = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-120 days')->format('Y-m-d H:i:s');
    $pdo->exec("INSERT INTO test_attempts (id, testId, studentId, status, startedAt, submittedAt, createdAt, updatedAt) VALUES ('" . SUBMITTED_OLD_ATTEMPT_ID . "', '" . TEST_DISC_ID . "', '" . STUDENT_OTHER_ID . "', 'submitted', '{$submitted120DaysAgo}', '{$submitted120DaysAgo}', '{$submitted120DaysAgo}', '{$submitted120DaysAgo}')");
    $pdo->exec("INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status, expiresAt, submittedAt, inputHash, createdAt, updatedAt) VALUES ('meta-3', '" . SUBMITTED_OLD_ATTEMPT_ID . "', '" . VERSION_DISC_ID . "', 'submitted', NULL, '{$submitted120DaysAgo}', 'oldhash', '{$submitted120DaysAgo}', '{$submitted120DaysAgo}')");

    $expiredAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d H:i:s');
    $started40DaysAgo = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-40 days')->format('Y-m-d H:i:s');
    $pdo->exec("INSERT INTO test_attempts (id, testId, studentId, status, startedAt, submittedAt, createdAt, updatedAt) VALUES ('" . EXPIRED_ATTEMPT_ID . "', '" . TEST_MI_ID . "', '" . STUDENT_OTHER_ID . "', 'in_progress', '{$started40DaysAgo}', NULL, '{$started40DaysAgo}', '{$started40DaysAgo}')");
    $pdo->exec("INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status, expiresAt, submittedAt, inputHash, createdAt, updatedAt) VALUES ('meta-4', '" . EXPIRED_ATTEMPT_ID . "', '" . VERSION_MI_ID . "', 'in_progress', '{$expiredAt}', NULL, NULL, '{$started40DaysAgo}', '{$started40DaysAgo}')");

    return $pdo;
}

// 1. Check Classes and Interfaces exist
catalog_assert(class_exists(EducationBandResolver::class), 'EducationBandResolver class must exist');
catalog_assert(class_exists(AssessmentCatalogService::class), 'AssessmentCatalogService class must exist');

$pdo = catalog_test_fixture();
$bandResolver = new EducationBandResolver($pdo);

// Test EducationBandResolver:
// Grade 11 -> high
catalog_assert($bandResolver->resolve(STUDENT_HIGH_ID, null) === 'high', 'Grade 11 resolves to high');
catalog_assert($bandResolver->resolve(STUDENT_HIGH_ID, 'college') === 'high', 'Known grade cannot be overridden by a conflicting confirmed band');
// Grade 8 -> middle
catalog_assert($bandResolver->resolve(STUDENT_MIDDLE_ID, null) === 'middle', 'Grade 8 resolves to middle');
// Grade 5 (primary) without confirmedBand -> throws exception
catalog_expect_exception(
    static fn () => $bandResolver->resolve(STUDENT_OTHER_ID, null),
    'Grade 5 without confirmed band throws exception'
);
// Explicit confirmed band overrides / resolves
catalog_assert($bandResolver->resolve(STUDENT_COLLEGE_ID, 'college') === 'college', 'College band with confirmedBand resolves');
catalog_assert($bandResolver->resolve(STUDENT_OTHER_ID, 'middle') === 'middle', 'Confirmed band resolves for student without matching grade');
// Invalid confirmed band throws exception
catalog_expect_exception(
    static fn () => $bandResolver->resolve(STUDENT_HIGH_ID, 'invalid_band'),
    'Invalid confirmed band is rejected'
);

$scorers = new ScorerRegistry([
    'holland-riasec-1.0' => new HollandScorer(),
    'mbti-education-1.0' => new MbtiScorer(),
    'disc-education-1.0' => new DiscScorer(),
    'multiple-intelligence-1.0' => new MultipleIntelligenceScorer(),
]);

$readRepo = new DatabaseAssessmentRepository($pdo);
$writeRepo = new DatabaseAssessmentWriteRepository($pdo, $scorers);
$catalogService = new AssessmentCatalogService($readRepo, $bandResolver);
$attemptService = new LearnerAssessmentService($readRepo, $writeRepo);

$serviceConstructor = new ReflectionMethod(LearnerAssessmentService::class, '__construct');
$serviceParameters = $serviceConstructor->getParameters();
catalog_assert(count($serviceParameters) === 2 && $serviceConstructor->getNumberOfRequiredParameters() === 2, 'LearnerAssessmentService requires exact read and write constructor dependencies');
catalog_assert((string) $serviceParameters[0]->getType() === TalentHub\Learner\Data\Contracts\AssessmentRepository::class, 'First service dependency is AssessmentRepository');
catalog_assert((string) $serviceParameters[1]->getType() === TalentHub\Learner\Data\Contracts\AssessmentWriteRepository::class, 'Second service dependency is AssessmentWriteRepository');
catalog_assert(!method_exists(LearnerAssessmentService::class, 'start'), 'Legacy start method is removed after startOrResume contract replacement');

// Catalog Test
$catalog = $catalogService->catalog(STUDENT_HIGH_ID, 'high');
catalog_assert(count($catalog['assessments']) === 4, 'all four published high-band assessments are listed');
catalog_assert($catalog['education_band'] === 'high', 'confirmed band is stable');

$byCode = [];
foreach ($catalog['assessments'] as $item) {
    $byCode[$item['code']] = $item;
}

catalog_assert(isset($byCode['holland']), 'Holland assessment is in catalog');
catalog_assert($byCode['holland']['attempt_status'] === 'in_progress', 'Holland attempt status is in_progress');
catalog_assert($byCode['holland']['progress'] > 0, 'Holland has progress calculated from saved answers');
catalog_assert($byCode['holland']['version'] === '2.0.0', 'Catalog exposes only the newest published version per assessment');

catalog_assert(isset($byCode['mbti']), 'MBTI assessment is in catalog');
catalog_assert($byCode['mbti']['attempt_status'] === 'retake_locked', 'MBTI submitted 30 days ago is retake_locked');
catalog_assert($byCode['mbti']['next_retake_at'] !== null, 'MBTI next_retake_at is provided');

catalog_assert(isset($byCode['disc']), 'DISC assessment is in catalog');
catalog_assert($byCode['disc']['attempt_status'] === 'not_started', 'DISC attempt status is not_started');
catalog_assert($byCode['disc']['progress'] === 0, 'DISC progress is 0');

catalog_assert(!isset($byCode['draft_test']), 'Draft test does not appear in catalog');

// Start / Resume Test
$resumed = $attemptService->startOrResume(STUDENT_HIGH_ID, 'holland', 'high');
catalog_assert($resumed['id'] === IN_PROGRESS_ATTEMPT_ID, 'existing writable attempt is resumed');
catalog_assert($resumed['assessment_version'] === '1.0.0', 'resumed attempt remains bound to its original version');

// Retake inside 90 days is rejected
catalog_expect_exception(
    static fn () => $attemptService->startOrResume(STUDENT_HIGH_ID, 'mbti', 'high'),
    'retake inside 90 days is rejected'
);

$allowedRetake = $attemptService->startOrResume(STUDENT_OTHER_ID, 'disc', 'high');
catalog_assert($allowedRetake['id'] !== SUBMITTED_OLD_ATTEMPT_ID, 'retake after 90 days creates a new attempt');
catalog_assert($allowedRetake['expires_at'] !== null, 'new resumable draft exposes its 30-day expiry');

$replacementDraft = $attemptService->startOrResume(STUDENT_OTHER_ID, 'multiple_intelligence', 'high');
catalog_assert($replacementDraft['id'] !== EXPIRED_ATTEMPT_ID, 'expired draft is not resumed');
$expiredStatuses = $pdo->query("SELECT a.status AS attempt_status, m.status AS metadata_status FROM test_attempts a INNER JOIN learner_assessment_attempt_metadata m ON m.attemptId = a.id WHERE a.id = '" . EXPIRED_ATTEMPT_ID . "'")->fetch(PDO::FETCH_ASSOC);
catalog_assert($expiredStatuses['attempt_status'] === 'expired' && $expiredStatuses['metadata_status'] === 'expired', 'expired draft is closed before replacement');

// Owned attempt reads
$owned = $attemptService->ownedAttempt(STUDENT_HIGH_ID, IN_PROGRESS_ATTEMPT_ID);
catalog_assert($owned['id'] === IN_PROGRESS_ATTEMPT_ID, 'owned attempt is returned for student');
catalog_assert($owned['student_id'] === STUDENT_HIGH_ID, 'student id matches');

// Other student reading ownedAttempt throws without leaking
catalog_expect_exception(
    static fn () => $attemptService->ownedAttempt(STUDENT_OTHER_ID, IN_PROGRESS_ATTEMPT_ID),
    'other learner cannot read attempt'
);

// History
$history = $attemptService->history(STUDENT_HIGH_ID, 'mbti');
catalog_assert(count($history) === 1, 'history returns submitted attempt');
catalog_assert($history[0]['result_code'] === 'ESTJ', 'history contains submitted result code');

$otherHistory = $attemptService->history(STUDENT_OTHER_ID, 'mbti');
catalog_assert(count($otherHistory) === 0, 'other learner has empty history');

// Mock Assessment Repository parity test
$mockRepo = new MockAssessmentRepository([], [], []);
catalog_assert(method_exists($mockRepo, 'publishedCatalog'), 'Mock repository has publishedCatalog');
catalog_assert(method_exists($mockRepo, 'publishedAssessment'), 'Mock repository has publishedAssessment');
catalog_assert(method_exists($mockRepo, 'questionsForVersion'), 'Mock repository has questionsForVersion');
catalog_assert(method_exists($mockRepo, 'ownedAttempt'), 'Mock repository has ownedAttempt');
catalog_assert(method_exists($mockRepo, 'history'), 'Mock repository has history');

echo "learner_assessment_catalog_test: OK\n";
