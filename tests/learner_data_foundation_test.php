<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Enums\ActivityStatus;
use TalentHub\Learner\Data\Contracts\ActivityRepository;
use TalentHub\Learner\Data\Contracts\ApplicationRepository;
use TalentHub\Learner\Data\Contracts\AssessmentRepository;
use TalentHub\Learner\Data\Contracts\EcosystemRepository;
use TalentHub\Learner\Data\Contracts\StudentRepository;
use TalentHub\Learner\Data\Exceptions\LearnerDataConfigurationException;
use TalentHub\Learner\Data\Exceptions\LearnerDataQueryException;
use TalentHub\Learner\Data\RepositoryFactory;
use TalentHub\Learner\Data\ReadModel\ActivityReadModel;
use TalentHub\Learner\Data\ReadModel\ApplicationReadModel;
use TalentHub\Learner\Data\ReadModel\AssessmentReadModel;
use TalentHub\Learner\Data\ReadModel\EcosystemReadModel;
use TalentHub\Learner\Data\ReadModel\StudentReadModel;
use TalentHub\Learner\Data\Support\KeyMapper;
use TalentHub\Learner\Data\Support\LearnerViewAdapter;
use TalentHub\Learner\Data\Support\Uuid;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function foundation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function foundation_expect_exception(callable $callback, string $className, string $messageFragment): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        foundation_assert($exception instanceof $className, "expected {$className}, got " . $exception::class);
        foundation_assert(
            str_contains($exception->getMessage(), $messageFragment),
            "exception contains {$messageFragment}"
        );
        return;
    }

    foundation_assert(false, "expected {$className}");
}

foundation_assert(
    KeyMapper::toSnake(['studentId' => 'student-1', 'profile' => ['schoolId' => 'school-1']])
        === ['student_id' => 'student-1', 'profile' => ['school_id' => 'school-1']],
    'camelCase keys map recursively to snake_case'
);

$mockUuid = Uuid::fromMockLegacy('student', 'student-demo-001');
foundation_assert(Uuid::isValid($mockUuid), 'mock legacy id maps to a valid UUID');
foundation_assert(
    $mockUuid === Uuid::fromMockLegacy('student', 'student-demo-001'),
    'mock UUID is deterministic'
);
foundation_assert(
    ActivityStatus::normalize('team-value') === ActivityStatus::Unknown,
    'unapproved activity status maps to unknown'
);
foundation_assert((new RepositoryFactory())->source() === 'mock', 'factory defaults to mock');

foundation_expect_exception(
    static fn (): RepositoryFactory => new RepositoryFactory('database'),
    LearnerDataConfigurationException::class,
    'requires an injected PDO'
);

foundation_expect_exception(
    static fn (): RepositoryFactory => new RepositoryFactory('other'),
    LearnerDataConfigurationException::class,
    'Unsupported learner data source'
);

$mockFactory = new RepositoryFactory();
$studentRepository = $mockFactory->student([[
    'id' => 'student-demo-001',
    'school_id' => 'school-demo-001',
    'class_id' => 'class-demo-001',
    'user_id' => 'user-demo-001',
    'name' => 'Nguyễn Văn A',
    'study_status' => 'team-pending',
]]);
foundation_assert($studentRepository instanceof StudentRepository, 'factory returns the student contract');
$mockStudent = $studentRepository->findById('student-demo-001');
foundation_assert($mockStudent !== null, 'mock student supports legacy lookup');
foundation_assert(Uuid::isValid($mockStudent['student_id']), 'mock student_id is a UUID');
foundation_assert(Uuid::isValid($mockStudent['school_id']), 'mock school_id is a UUID');
foundation_assert(Uuid::isValid($mockStudent['class_id']), 'mock class_id is a UUID');
foundation_assert(Uuid::isValid($mockStudent['user_id']), 'mock user_id is a UUID');
foundation_assert(($mockStudent['legacy_id'] ?? null) === 'student-demo-001', 'mock student retains legacy id');
foundation_assert(($mockStudent['id_origin'] ?? null) === 'mock_compat', 'mock UUID origin is explicit');
foundation_assert(($mockStudent['study_status'] ?? null) === 'unknown', 'mock student status supports unknown');
foundation_assert(
    $studentRepository->findById($mockStudent['student_id']) === $mockStudent,
    'mock student supports canonical UUID lookup'
);

$camelCaseStudentRepository = $mockFactory->student([[
    'studentId' => 'student-camel-001',
    'schoolId' => 'school-camel-001',
    'classId' => 'class-camel-001',
    'userId' => 'user-camel-001',
    'studyStatus' => 'active',
]]);
$camelCaseStudent = $camelCaseStudentRepository->findById('student-camel-001');
foundation_assert($camelCaseStudent !== null, 'camelCase studentId supports legacy lookup');
foundation_assert(
    ($camelCaseStudent['student_id'] ?? null) === Uuid::fromMockLegacy('student', 'student-camel-001'),
    'camelCase studentId normalizes to the mock student UUID contract'
);

$assessmentRepository = $mockFactory->assessment(
    [['id' => 'holland', 'name' => 'Holland']],
    [['id' => 'holland-r-01', 'assessment_id' => 'holland', 'prompt' => 'Câu hỏi']],
    [[
        'id' => 'attempt-1',
        'student_id' => 'student-demo-001',
        'assessment_id' => 'holland',
        'status' => 'submitted',
    ]],
    [[
        'id' => 'evaluation-1',
        'student_id' => 'student-demo-001',
        'activity_id' => 'iot-lab',
        'status' => 'team-approved',
    ]]
);
foundation_assert($assessmentRepository instanceof AssessmentRepository, 'factory returns assessment contract');
$mockAssessment = $assessmentRepository->findById('holland');
foundation_assert($mockAssessment !== null && Uuid::isValid($mockAssessment['id']), 'assessment id is normalized');
foundation_assert(count($assessmentRepository->questionsFor('holland')) === 1, 'assessment questions accept legacy id');
foundation_assert(
    count($assessmentRepository->attemptsFor('student-demo-001', 'holland')) === 1,
    'assessment attempts are scoped by shared keys'
);
$mockEvaluations = $assessmentRepository->evaluationsForStudent('student-demo-001');
foundation_assert(count($mockEvaluations) === 1, 'mock evaluations are student scoped');
foundation_assert($mockEvaluations[0]['status'] === 'unknown', 'evaluation status supports unknown');
foundation_assert(Uuid::isValid($mockEvaluations[0]['activity_id']), 'evaluation activity_id is normalized');

$activityRepository = $mockFactory->activity(
    [[
        'id' => 'iot-lab',
        'school_id' => 'school-demo-001',
        'title' => 'IoT Lab',
        'status' => 'published',
    ]],
    [[
        'id' => 'registration-1',
        'student_id' => 'student-demo-001',
        'activity_id' => 'iot-lab',
        'status' => 'new-team-status',
    ]]
);
foundation_assert($activityRepository instanceof ActivityRepository, 'factory returns activity contract');
$mockActivity = $activityRepository->findById('iot-lab');
foundation_assert($mockActivity !== null && Uuid::isValid($mockActivity['activity_id']), 'activity_id is normalized');
$mockRegistrations = $activityRepository->registrationsFor('student-demo-001');
foundation_assert(count($mockRegistrations) === 1, 'activity registrations are student scoped');
foundation_assert($mockRegistrations[0]['status'] === 'unknown', 'registration status supports unknown');
foundation_assert($mockRegistrations[0]['activity_id'] === $mockActivity['activity_id'], 'activity foreign key is stable');

$camelCaseMockActivity = $mockFactory->activity([[
    'id' => 'camel-activity',
    'schoolId' => 'camel-school',
    'filterCategory' => 'Kỹ thuật',
    'startAt' => '2026-08-20T09:00:00+07:00',
    'approvalMode' => 'automatic',
    'status' => 'published',
]])->all()[0];
foundation_assert(isset($camelCaseMockActivity['school_id']), 'mock keys become snake_case before foreign key normalization');
foundation_assert(isset($camelCaseMockActivity['filter_category']), 'mock presentation keys become snake_case');
foundation_assert(isset($camelCaseMockActivity['start_at']), 'mock datetime keys become snake_case');
foundation_assert(!isset($camelCaseMockActivity['schoolId']), 'mock contract does not leak camelCase keys');

$mockAssessmentWithDimensionKeys = $mockFactory->assessment([], [], [[
    'id' => 'case-preserving-attempt',
    'studentId' => 'student-demo-001',
    'assessmentId' => 'holland',
    'result' => ['scores' => ['R' => 90, 'I' => 80]],
]])->attemptsFor('student-demo-001', 'holland')[0];
foundation_assert(
    array_keys($mockAssessmentWithDimensionKeys['result']['scores']) === ['R', 'I'],
    'snake_case mapping preserves semantic RIASEC keys'
);

$ecosystemRepository = $mockFactory->ecosystem(
    [
        ['id' => 'school-demo-001', 'type' => 'school', 'name' => 'School'],
        ['id' => 'enterprise-demo-001', 'type' => 'enterprise', 'name' => 'Enterprise'],
    ],
    [[
        'id' => 1,
        'type' => 'internship',
        'partner_id' => 'enterprise-demo-001',
        'partner_type' => 'enterprise',
        'status' => 'team-review',
    ]]
);
foundation_assert($ecosystemRepository instanceof EcosystemRepository, 'factory returns ecosystem contract');
$mockEnterprise = $ecosystemRepository->findPartner('enterprise', 'enterprise-demo-001');
foundation_assert($mockEnterprise !== null && Uuid::isValid($mockEnterprise['enterprise_id']), 'enterprise_id is normalized');
$mockOpportunity = $ecosystemRepository->findOpportunity('internship', '1');
foundation_assert($mockOpportunity !== null && $mockOpportunity['status'] === 'unknown', 'opportunity status supports unknown');
foundation_assert(
    count($ecosystemRepository->opportunitiesForPartner('enterprise-demo-001')) === 1,
    'partner opportunity lookup accepts a legacy id'
);

$applicationRepository = $mockFactory->application([[
    'id' => 'application-1',
    'student_id' => 'student-demo-001',
    'opportunity_id' => 1,
    'enterprise_id' => 'enterprise-demo-001',
    'school_id' => 'school-demo-001',
    'status' => 'reviewing',
]]);
foundation_assert($applicationRepository instanceof ApplicationRepository, 'factory returns application contract');
$mockApplications = $applicationRepository->forStudent('student-demo-001');
foundation_assert(count($mockApplications) === 1, 'applications are scoped to the learner');
foundation_assert(Uuid::isValid($mockApplications[0]['student_id']), 'application student_id is normalized');
foundation_assert(Uuid::isValid($mockApplications[0]['school_id']), 'application school_id is normalized when present');
foundation_assert(
    $applicationRepository->forStudent('student-demo-002') === [],
    'applications never leak across students'
);

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT, passwordHash TEXT, fullName TEXT, roles TEXT, status TEXT, createdAt TEXT)');
$database->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT, status TEXT)');
$database->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel INTEGER, academicYear TEXT)');
$database->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, dateOfBirth TEXT, phone TEXT, studyStatus TEXT)');
$database->exec('CREATE TABLE talent_tests (id TEXT PRIMARY KEY, name TEXT, type TEXT, dimensions TEXT)');
$database->exec('CREATE TABLE test_questions (id TEXT PRIMARY KEY, testId TEXT, content TEXT, options TEXT)');
$database->exec('CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT, studentId TEXT, startedAt TEXT, completedAt TEXT)');
$database->exec('CREATE TABLE test_results (id TEXT PRIMARY KEY, attemptId TEXT, resultCode TEXT, summary TEXT, dimensionScores TEXT)');
$database->exec('CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, name TEXT, minScore REAL, maxScore REAL)');
$database->exec('CREATE TABLE assessments (id TEXT PRIMARY KEY, teacherId TEXT, studentId TEXT, activityId TEXT, overallScore REAL, comment TEXT, status TEXT)');
$database->exec('CREATE TABLE assessment_scores (id TEXT PRIMARY KEY, assessmentId TEXT, criteriaId TEXT, score REAL)');

$ids = [
    'student' => '11111111-1111-4111-8111-111111111111',
    'user' => '22222222-2222-4222-8222-222222222222',
    'class' => '33333333-3333-4333-8333-333333333333',
    'school' => '44444444-4444-4444-8444-444444444444',
    'assessment' => '55555555-5555-4555-8555-555555555555',
    'question' => '66666666-6666-4666-8666-666666666666',
    'attempt' => '77777777-7777-4777-8777-777777777777',
    'result' => '88888888-8888-4888-8888-888888888888',
    'activity' => '99999999-9999-4999-8999-999999999999',
    'draft_activity' => '99999999-9999-4999-8999-999999999998',
    'cancelled_activity' => '99999999-9999-4999-8999-999999999997',
    'teacher' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'registration' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'enterprise' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
    'post' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
    'application' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
    'evaluation' => '12121212-1212-4121-8121-121212121212',
    'criterion' => '13131313-1313-4131-8131-131313131313',
    'score' => '14141414-1414-4141-8141-141414141414',
];

$insert = $database->prepare('INSERT INTO schools (id, name, status) VALUES (:id, :name, :status)');
$insert->execute(['id' => $ids['school'], 'name' => 'Trường Demo', 'status' => 'active']);
$insert = $database->prepare('INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES (:id, :schoolId, :name, :gradeLevel, :academicYear)');
$insert->execute(['id' => $ids['class'], 'schoolId' => $ids['school'], 'name' => '11A2', 'gradeLevel' => 11, 'academicYear' => '2026']);
$insert = $database->prepare('INSERT INTO users (id, email, passwordHash, fullName, roles, status, createdAt) VALUES (:id, :email, :passwordHash, :fullName, :roles, :status, :createdAt)');
$insert->execute(['id' => $ids['user'], 'email' => 'learner@example.test', 'passwordHash' => 'test-only', 'fullName' => 'Database Learner', 'roles' => 'student', 'status' => 'active', 'createdAt' => '2026-08-14']);
$insert = $database->prepare('INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus) VALUES (:id, :userId, :classId, :dateOfBirth, :phone, :studyStatus)');
$insert->execute(['id' => $ids['student'], 'userId' => $ids['user'], 'classId' => $ids['class'], 'dateOfBirth' => '2009-01-02', 'phone' => '0900000000', 'studyStatus' => 'team-pending']);
$insert = $database->prepare('INSERT INTO talent_tests (id, name, type, dimensions) VALUES (:id, :name, :type, :dimensions)');
$insert->execute(['id' => $ids['assessment'], 'name' => 'Holland', 'type' => 'RIASEC', 'dimensions' => json_encode(['R', 'I', 'A', 'S', 'E', 'C'])]);
$insert = $database->prepare('INSERT INTO test_questions (id, testId, content, options) VALUES (:id, :testId, :content, :options)');
$insert->execute(['id' => $ids['question'], 'testId' => $ids['assessment'], 'content' => 'Câu hỏi database', 'options' => json_encode([1, 2, 3, 4, 5])]);
$insert = $database->prepare('INSERT INTO test_attempts (id, testId, studentId, startedAt, completedAt) VALUES (:id, :testId, :studentId, :startedAt, :completedAt)');
$insert->execute(['id' => $ids['attempt'], 'testId' => $ids['assessment'], 'studentId' => $ids['student'], 'startedAt' => '2026-08-14 08:00:00', 'completedAt' => '2026-08-14 08:10:00']);
$insert = $database->prepare('INSERT INTO test_results (id, attemptId, resultCode, summary, dimensionScores) VALUES (:id, :attemptId, :resultCode, :summary, :dimensionScores)');
$insert->execute(['id' => $ids['result'], 'attemptId' => $ids['attempt'], 'resultCode' => 'RIA', 'summary' => 'Kết quả database', 'dimensionScores' => json_encode(['R' => 90])]);
$insert = $database->prepare('INSERT INTO assessment_criteria (id, name, minScore, maxScore) VALUES (:id, :name, :minScore, :maxScore)');
$insert->execute(['id' => $ids['criterion'], 'name' => 'Chuyên môn', 'minScore' => 0, 'maxScore' => 40]);
$insert = $database->prepare('INSERT INTO assessments (id, teacherId, studentId, activityId, overallScore, comment, status) VALUES (:id, :teacherId, :studentId, :activityId, :overallScore, :comment, :status)');
$insert->execute(['id' => $ids['evaluation'], 'teacherId' => $ids['teacher'], 'studentId' => $ids['student'], 'activityId' => $ids['activity'], 'overallScore' => 36, 'comment' => 'Database evaluation', 'status' => 'team-approved']);
$insert = $database->prepare('INSERT INTO assessment_scores (id, assessmentId, criteriaId, score) VALUES (:id, :assessmentId, :criteriaId, :score)');
$insert->execute(['id' => $ids['score'], 'assessmentId' => $ids['evaluation'], 'criteriaId' => $ids['criterion'], 'score' => 36]);

$databaseFactory = new RepositoryFactory('database', $database);
$databaseStudent = $databaseFactory->student()->findById($ids['student']);
foundation_assert($databaseStudent !== null, 'database student is read through injected PDO');
foundation_assert($databaseStudent['student_id'] === $ids['student'], 'database student UUID remains authoritative');
foundation_assert($databaseStudent['school_id'] === $ids['school'], 'database school_id is normalized');
foundation_assert($databaseStudent['full_name'] === 'Database Learner', 'database camelCase maps to snake_case');
foundation_assert($databaseStudent['study_status'] === 'unknown', 'unapproved database student status is unknown');
foundation_assert(!isset($databaseStudent['legacy_id']), 'database records never receive mock legacy metadata');

$databaseAssessments = $databaseFactory->assessment();
$databaseDefinition = $databaseAssessments->findById($ids['assessment']);
foundation_assert($databaseDefinition !== null, 'database assessment definition is readable');
foundation_assert($databaseDefinition['dimensions'] === ['R', 'I', 'A', 'S', 'E', 'C'], 'assessment JSON is decoded');
$databaseQuestions = $databaseAssessments->questionsFor($ids['assessment']);
foundation_assert(count($databaseQuestions) === 1, 'database assessment questions are filtered');
foundation_assert($databaseQuestions[0]['assessment_id'] === $ids['assessment'], 'testId maps to assessment_id');
foundation_assert($databaseQuestions[0]['options'] === [1, 2, 3, 4, 5], 'question JSON options are decoded');
$databaseAttempts = $databaseAssessments->attemptsFor($ids['student'], $ids['assessment']);
foundation_assert(count($databaseAttempts) === 1, 'database attempts use student and assessment filters');
foundation_assert($databaseAttempts[0]['status'] === 'submitted', 'completedAt derives submitted status');
foundation_assert($databaseAttempts[0]['result']['dimension_scores'] === ['R' => 90], 'result JSON is decoded');
$databaseEvaluations = $databaseAssessments->evaluationsForStudent($ids['student']);
foundation_assert(count($databaseEvaluations) === 1, 'database teacher evaluations are student scoped');
foundation_assert($databaseEvaluations[0]['activity_id'] === $ids['activity'], 'evaluation activity_id is normalized');
foundation_assert($databaseEvaluations[0]['status'] === 'unknown', 'unapproved evaluation status is unknown');
foundation_assert($databaseEvaluations[0]['scores'][0]['criteria_name'] === 'Chuyên môn', 'evaluation criteria join is normalized');

$emptyDatabase = new PDO('sqlite::memory:');
$emptyDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foundation_expect_exception(
    static fn (): ?array => (new RepositoryFactory('database', $emptyDatabase))->student()->findById($ids['student']),
    LearnerDataQueryException::class,
    'DatabaseStudentRepository.findById'
);

$database->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, createdByTeacherId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT)');
$database->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT)');
$database->exec('CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT, status TEXT, logoUrl TEXT, industry TEXT, description TEXT, email TEXT, phone TEXT, website TEXT, address TEXT, verificationStatus TEXT, verificationNote TEXT, verifiedAt TEXT, verifiedBy TEXT, createdAt TEXT, updatedAt TEXT)');
$database->exec('CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, location TEXT, deadline TEXT, status TEXT)');
$database->exec('CREATE TABLE internship_applications (id TEXT PRIMARY KEY, postId TEXT, studentId TEXT, status TEXT, cvUrl TEXT, reviewerNote TEXT)');

$insert = $database->prepare('INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (:id, :schoolId, :createdByTeacherId, :title, :category, :startAt, :endAt, :capacity, :status)');
$insert->execute(['id' => $ids['activity'], 'schoolId' => $ids['school'], 'createdByTeacherId' => $ids['teacher'], 'title' => 'Database Activity', 'category' => 'Kỹ thuật', 'startAt' => '2026-09-01 09:00:00', 'endAt' => '2026-09-01 12:00:00', 'capacity' => 30, 'status' => 'published']);
$insert->execute(['id' => $ids['draft_activity'], 'schoolId' => $ids['school'], 'createdByTeacherId' => $ids['teacher'], 'title' => 'Draft Activity', 'category' => 'Kỹ thuật', 'startAt' => '2026-09-02 09:00:00', 'endAt' => '2026-09-02 12:00:00', 'capacity' => 30, 'status' => 'draft']);
$insert->execute(['id' => $ids['cancelled_activity'], 'schoolId' => $ids['school'], 'createdByTeacherId' => $ids['teacher'], 'title' => 'Cancelled Activity', 'category' => 'Kỹ thuật', 'startAt' => '2026-09-03 09:00:00', 'endAt' => '2026-09-03 12:00:00', 'capacity' => 30, 'status' => 'cancelled']);
$insert = $database->prepare('INSERT INTO activity_registrations (id, activityId, studentId, status) VALUES (:id, :activityId, :studentId, :status)');
$insert->execute(['id' => $ids['registration'], 'activityId' => $ids['activity'], 'studentId' => $ids['student'], 'status' => 'team-confirmed']);
$insert = $database->prepare('INSERT INTO enterprises (id, name, status, logoUrl, industry, description, email, phone, website, address, verificationStatus, verificationNote, verifiedAt, verifiedBy, createdAt, updatedAt) VALUES (:id, :name, :status, :logoUrl, :industry, :description, :email, :phone, :website, :address, :verificationStatus, :verificationNote, :verifiedAt, :verifiedBy, :createdAt, :updatedAt)');
$insert->execute(['id' => $ids['enterprise'], 'name' => 'Database Enterprise', 'status' => 'active', 'logoUrl' => null, 'industry' => 'Technology', 'description' => 'Schema-backed description', 'email' => 'enterprise@example.test', 'phone' => '0900000001', 'website' => 'https://example.test', 'address' => 'Hà Nội', 'verificationStatus' => 'verified', 'verificationNote' => null, 'verifiedAt' => null, 'verifiedBy' => null, 'createdAt' => '2026-08-01', 'updatedAt' => '2026-08-14']);
$insert = $database->prepare('INSERT INTO internship_posts (id, enterpriseId, title, location, deadline, status) VALUES (:id, :enterpriseId, :title, :location, :deadline, :status)');
$insert->execute(['id' => $ids['post'], 'enterpriseId' => $ids['enterprise'], 'title' => 'Database Internship', 'location' => 'Hà Nội', 'deadline' => '2026-12-01', 'status' => 'active']);
$insert = $database->prepare('INSERT INTO internship_applications (id, postId, studentId, status, cvUrl, reviewerNote) VALUES (:id, :postId, :studentId, :status, :cvUrl, :reviewerNote)');
$insert->execute(['id' => $ids['application'], 'postId' => $ids['post'], 'studentId' => $ids['student'], 'status' => 'team-shortlisted', 'cvUrl' => '/cv/demo.pdf', 'reviewerNote' => null]);

$databaseActivities = $databaseFactory->activity();
$visibleDatabaseActivities = $databaseActivities->all();
foundation_assert(count($visibleDatabaseActivities) === 1, 'database activity list excludes draft and cancelled rows');
$databaseActivity = $databaseActivities->findById($ids['activity']);
foundation_assert($databaseActivity !== null, 'database activity is readable');
foundation_assert($databaseActivity['activity_id'] === $ids['activity'], 'database activity_id is normalized');
foundation_assert($databaseActivity['school_id'] === $ids['school'], 'activity school_id is normalized');
foundation_assert($databaseActivity['status'] === 'published', 'approved activity status is preserved');
foundation_assert($databaseActivities->findById($ids['draft_activity']) === null, 'database activity detail hides draft rows');
foundation_assert($databaseActivities->findById($ids['cancelled_activity']) === null, 'database activity detail hides cancelled rows');
$databaseRegistrations = $databaseActivities->registrationsFor($ids['student']);
foundation_assert(count($databaseRegistrations) === 1, 'database registrations are student scoped');
foundation_assert($databaseRegistrations[0]['status'] === 'unknown', 'unapproved registration status is unknown');

$databaseEcosystem = $databaseFactory->ecosystem();
$databasePartners = $databaseEcosystem->partners();
foundation_assert(count($databasePartners) === 2, 'database ecosystem reads schools and enterprises');
$databaseEnterprise = $databaseEcosystem->findPartner('enterprise', $ids['enterprise']);
foundation_assert($databaseEnterprise !== null, 'database enterprise lookup works');
foundation_assert($databaseEnterprise['enterprise_id'] === $ids['enterprise'], 'enterprise_id is normalized');
$databaseOpportunity = $databaseEcosystem->findOpportunity('internship', $ids['post']);
foundation_assert($databaseOpportunity !== null, 'database internship opportunity is readable');
foundation_assert($databaseOpportunity['enterprise_id'] === $ids['enterprise'], 'opportunity enterprise_id is normalized');
foundation_assert(
    count($databaseEcosystem->opportunitiesForPartner($ids['enterprise'], true)) === 1,
    'active database opportunities are partner scoped'
);

$databaseApplications = $databaseFactory->application();
$studentApplications = $databaseApplications->forStudent($ids['student']);
foundation_assert(count($studentApplications) === 1, 'database applications are student scoped');
foundation_assert($studentApplications[0]['application_id'] === $ids['application'], 'database application id is normalized');
foundation_assert($studentApplications[0]['status'] === 'unknown', 'unapproved application status is unknown');
foundation_assert($studentApplications[0]['enterprise_id'] === $ids['enterprise'], 'application enterprise_id is normalized');
foundation_assert(
    $databaseApplications->forStudent('ffffffff-ffff-4fff-8fff-ffffffffffff') === [],
    'database applications never leak across students'
);

$databaseRepositoryFiles = glob(dirname(__DIR__) . '/app/learner/data/Database/*.php') ?: [];
$assessmentWriteRepositoryPath = dirname(__DIR__) . '/app/learner/data/Database/DatabaseAssessmentWriteRepository.php';
foreach ($databaseRepositoryFiles as $databaseRepositoryFile) {
    if ($databaseRepositoryFile === $assessmentWriteRepositoryPath) {
        continue;
    }
    $databaseRepositorySource = file_get_contents($databaseRepositoryFile);
    foundation_assert($databaseRepositorySource !== false, 'database repository source is readable');
    foundation_assert(
        preg_match('/\b(CREATE|ALTER|DROP|INSERT|UPDATE|DELETE)\b/i', $databaseRepositorySource) !== 1,
        basename($databaseRepositoryFile) . ' contains read-only SQL only'
    );
}
$assessmentWriteRepositorySource = file_get_contents($assessmentWriteRepositoryPath);
foundation_assert($assessmentWriteRepositorySource !== false, 'assessment write repository source is readable');
foundation_assert(
    preg_match('/\b(CREATE|ALTER|DROP|DELETE|TRUNCATE)\b/i', $assessmentWriteRepositorySource) !== 1,
    'assessment write repository has no destructive SQL'
);
foundation_assert(
    preg_match('/\b(INSERT|UPDATE)\b/i', $assessmentWriteRepositorySource) === 1,
    'assessment write repository contains the explicit persistence operations'
);
$abstractDatabaseSource = file_get_contents(dirname(__DIR__) . '/app/learner/data/Database/AbstractDatabaseRepository.php');
foundation_assert(str_contains((string) $abstractDatabaseSource, '->prepare('), 'database reads use prepared statements');
foundation_assert(!str_contains((string) $abstractDatabaseSource, '->query('), 'database reads do not use direct query calls');

foundation_assert(learner_data_config()['source'] === 'mock', 'learner config defaults to mock');
foundation_assert(learner_current_student_id() === 'student-demo-001', 'mock config uses the demo learner id');
learner_configure_data(['source' => 'database']);
foundation_expect_exception(
    static fn (): RepositoryFactory => learner_repository_factory(),
    LearnerDataConfigurationException::class,
    'requires an injected PDO'
);
learner_configure_data(['source' => 'mock']);
learner_configure_data(['source' => ' DATABASE ']);
foundation_expect_exception(
    static fn (): string => learner_current_student_id(),
    LearnerDataConfigurationException::class,
    'requires an explicit student_id'
);
learner_configure_data(['source' => 'mock']);

$legacyView = LearnerViewAdapter::record([
    'id' => Uuid::fromMockLegacy('activity', 'iot-lab'),
    'legacy_id' => 'iot-lab',
    'activity_id' => Uuid::fromMockLegacy('activity', 'iot-lab'),
    'legacy_activity_id' => 'iot-lab',
    'school_id' => Uuid::fromMockLegacy('school', 'school-demo-001'),
    'legacy_school_id' => 'school-demo-001',
    'id_origin' => 'mock_compat',
]);
foundation_assert($legacyView['id'] === 'iot-lab', 'view adapter restores the legacy route id');
foundation_assert($legacyView['activity_id'] === 'iot-lab', 'view adapter restores legacy shared keys');
foundation_assert($legacyView['school_id'] === 'school-demo-001', 'view adapter restores legacy foreign keys');
foundation_assert(!isset($legacyView['legacy_id'], $legacyView['id_origin']), 'view adapter hides repository metadata');

$studentView = StudentReadModel::fromRecord([]);
foreach (['name', 'initials', 'class', 'school', 'email', 'location', 'verified'] as $field) {
    foundation_assert(array_key_exists($field, $studentView), "student read model supplies {$field}");
}

$assessmentView = AssessmentReadModel::definition(['id' => $ids['assessment'], 'name' => 'Holland']);
foreach (['short_name', 'description', 'version', 'duration_minutes', 'question_count', 'disclaimer'] as $field) {
    foundation_assert(array_key_exists($field, $assessmentView), "assessment read model supplies {$field}");
}
$numericOptionQuestion = AssessmentReadModel::question([
    'id' => $ids['question'],
    'assessment_id' => $ids['assessment'],
    'prompt' => 'Câu hỏi schema',
    'dimension' => 'R',
    'options' => [1, ['value' => 2, 'label' => 'Hơi giống tôi']],
]);
foundation_assert(
    $numericOptionQuestion['options'] === [
        ['value' => 1, 'label' => '1'],
        ['value' => 2, 'label' => 'Hơi giống tôi'],
    ],
    'assessment read model normalizes numeric and object options'
);

$validHollandQuestions = [];
foreach (str_split('RIASEC') as $dimension) {
    foreach (range(1, 4) as $number) {
        $validHollandQuestions[] = [
            'id' => strtolower($dimension) . '-' . $number,
            'prompt' => "Câu {$dimension} {$number}",
            'dimension' => $dimension,
            'options' => [1, 2, 3, 4, 5],
        ];
    }
}
foundation_assert(AssessmentReadModel::isHollandReady($validHollandQuestions), '24 valid Holland questions are ready');
$allRealisticQuestions = array_map(
    static fn (array $question): array => array_replace($question, ['dimension' => 'R']),
    $validHollandQuestions
);
foundation_assert(
    !AssessmentReadModel::isHollandReady($allRealisticQuestions),
    '24 Holland questions from only the R dimension are unavailable'
);
$skewedDimensionQuestions = $validHollandQuestions;
$skewedDimensionQuestions[4]['dimension'] = 'R';
foundation_assert(
    !AssessmentReadModel::isHollandReady($skewedDimensionQuestions),
    'Holland questions with a 5R/3I distribution are unavailable'
);
$missingDimensionGroupQuestions = array_map(
    static fn (array $question): array => ($question['dimension'] ?? null) === 'C'
        ? array_replace($question, ['dimension' => 'E'])
        : $question,
    $validHollandQuestions
);
foundation_assert(
    !AssessmentReadModel::isHollandReady($missingDimensionGroupQuestions),
    'Holland questions without one RIASEC dimension group are unavailable'
);
$missingDimensionQuestions = $validHollandQuestions;
unset($missingDimensionQuestions[0]['dimension']);
foundation_assert(
    !AssessmentReadModel::isHollandReady($missingDimensionQuestions),
    'Holland questions without an explicit dimension are unavailable'
);
foundation_assert(
    !AssessmentReadModel::isHollandReady(array_slice($validHollandQuestions, 0, 23)),
    'Holland requires exactly 24 valid questions'
);
$invalidLikertQuestions = $validHollandQuestions;
$invalidLikertQuestions[0]['options'] = [1, 2, 3, 4, 4.5];
foundation_assert(
    !AssessmentReadModel::isHollandReady($invalidLikertQuestions),
    'Holland only accepts the exact Likert values 1, 2, 3, 4, 5'
);

$attemptView = AssessmentReadModel::attempt([
    'id' => $ids['attempt'],
    'status' => 'submitted',
    'completed_at' => '2026-08-14 08:10:00',
    'result' => ['result_code' => 'RIA', 'dimension_scores' => ['R' => 90, 'I' => 80, 'A' => 70, 'S' => 60, 'E' => 50, 'C' => 40]],
]);
foundation_assert(isset($attemptView['assessment_version'], $attemptView['submitted_at']), 'attempt read model supplies render fields');
foundation_assert(isset($attemptView['result']['code'], $attemptView['result']['scores']), 'attempt result shape is compatible');
$draftAttemptView = AssessmentReadModel::attempt([
    'id' => 'draft-attempt',
    'status' => 'in_progress',
    'result' => null,
]);
foundation_assert($draftAttemptView['result'] === null, 'in-progress attempt keeps result null');
$completedAttempts = AssessmentReadModel::completedAttempts([
    $draftAttemptView,
    $attemptView,
    AssessmentReadModel::attempt([
        'id' => 'completed-without-result',
        'status' => 'completed',
        'result' => null,
    ]),
    AssessmentReadModel::attempt([
        'id' => 'completed-invalid-result',
        'status' => 'completed',
        'result' => [
            'result_code' => 'RIA',
            'primary_dimension' => 'R',
            'dimension_scores' => ['X' => 90],
        ],
    ]),
    AssessmentReadModel::attempt([
        'id' => 'completed-partial-result',
        'status' => 'completed',
        'result' => [
            'result_code' => 'RIA',
            'primary_dimension' => 'R',
            'dimension_scores' => ['R' => 90],
        ],
    ]),
]);
foundation_assert(
    array_column($completedAttempts, 'id') === [$ids['attempt']],
    'result history only contains submitted or completed attempts with valid results'
);

$activityView = ActivityReadModel::activity([
    'id' => $ids['activity'],
    'title' => 'Schema Activity',
    'start_at' => '2026-09-01 09:00:00',
    'capacity' => 0,
]);
foreach (['summary', 'description', 'location', 'tone', 'participants', 'format', 'cost', 'approval_mode', 'registration_closes_at', 'skills', 'requirements', 'benefits'] as $field) {
    foundation_assert(array_key_exists($field, $activityView), "activity read model supplies {$field}");
}
foundation_assert($activityView['capacity'] > 0, 'activity read model prevents division by zero');
foundation_assert(array_key_exists('can_register', $activityView), 'activity read model exposes can_register');
foundation_assert(
    ActivityReadModel::canRegister([
        'status' => 'published',
        'registration_opens_at' => '2026-08-01T00:00:00+00:00',
        'registration_closes_at' => '2026-08-31T23:59:59+00:00',
    ], new DateTimeImmutable('2026-08-14T00:00:00+00:00')),
    'published activity inside its registration window can register'
);
foreach (['draft', 'cancelled', 'closed', 'completed'] as $blockedStatus) {
    foundation_assert(
        !ActivityReadModel::canRegister([
            'status' => $blockedStatus,
            'registration_opens_at' => '2026-08-01T00:00:00+00:00',
            'registration_closes_at' => '2026-08-31T23:59:59+00:00',
        ], new DateTimeImmutable('2026-08-14T00:00:00+00:00')),
        "{$blockedStatus} activity cannot register"
    );
}
foundation_assert(
    !ActivityReadModel::canRegister([
        'status' => 'active',
        'registration_opens_at' => '2026-08-01T00:00:00+00:00',
        'registration_closes_at' => '2026-08-13T23:59:59+00:00',
    ], new DateTimeImmutable('2026-08-14T00:00:00+00:00')),
    'expired activity registration window is blocked'
);
foundation_assert(
    !ActivityReadModel::canRegister([
        'status' => 'active',
        'registration_opens_at' => '2026-08-15T00:00:00+00:00',
        'registration_closes_at' => '2026-08-31T23:59:59+00:00',
    ], new DateTimeImmutable('2026-08-14T00:00:00+00:00')),
    'activity registration before its opening time is blocked'
);

$partnerView = EcosystemReadModel::partner(['id' => $ids['enterprise'], 'type' => 'enterprise', 'name' => 'Schema Enterprise']);
foreach (['logo_text', 'verified', 'location', 'description', 'opportunity_count', 'highlights', 'programs', 'facilities'] as $field) {
    foundation_assert(array_key_exists($field, $partnerView), "partner read model supplies {$field}");
}
$opportunityView = EcosystemReadModel::opportunity([
    'id' => $ids['post'],
    'type' => 'internship',
    'enterprise_id' => $ids['enterprise'],
    'enterprise_name' => 'Schema Enterprise',
    'title' => 'Schema Opportunity',
    'deadline' => '2026-12-01',
]);
foreach (['partner_id', 'partner_type', 'partner_name', 'status_label', 'field', 'slots', 'work_type', 'duration', 'description', 'requirements', 'skills', 'benefits'] as $field) {
    foundation_assert(array_key_exists($field, $opportunityView), "opportunity read model supplies {$field}");
}

$applicationView = ApplicationReadModel::application([
    'id' => $ids['application'],
    'opportunity_id' => $ids['post'],
    'enterprise_name' => 'Schema Enterprise',
]);
foreach (['opportunity_type', 'title', 'partner_name', 'submitted_at', 'updated_at', 'status_label', 'can_withdraw', 'timeline'] as $field) {
    foundation_assert(array_key_exists($field, $applicationView), "application read model supplies {$field}");
}
foundation_assert($activityView['data_notes'] !== [], 'safe activity defaults are documented in the read model');

require_once dirname(__DIR__) . '/app/learner/includes/activity-data.php';
require_once dirname(__DIR__) . '/app/learner/includes/assessment-data.php';
require_once dirname(__DIR__) . '/app/learner/includes/ecosystem-data.php';
foundation_assert(learner_activity_find('iot-lab') !== null, 'legacy activity adapter remains available');
foundation_assert(learner_assessment_definition('holland') !== null, 'legacy assessment adapter remains available');
foundation_assert(
    learner_ecosystem_opportunity('internship', 1) !== null,
    'legacy ecosystem opportunity adapter remains available'
);
$legacyApplications = learner_ecosystem_applications();
foundation_assert($legacyApplications !== [], 'legacy application adapter remains available');
foreach ($legacyApplications as $legacyApplication) {
    foundation_assert(isset($legacyApplication['student_id']), 'application adapter exposes student_id');
    foundation_assert(isset($legacyApplication['enterprise_id']), 'application adapter exposes enterprise_id');
}

echo "learner_data_foundation_test: OK\n";
