<?php

declare(strict_types=1);

$databaseRenderCompleted = false;
register_shutdown_function(static function () use (&$databaseRenderCompleted): void {
    if (!$databaseRenderCompleted) {
        fwrite(STDERR, "Assertion failed: database render test exited before completing its assertions\n");
        exit(1);
    }
});

$root = dirname(__DIR__);
putenv('APP_ENV=test');
putenv('TALENTHUB_LEARNER_SOURCE=mock');
$_ENV['APP_ENV'] = 'test';
$_ENV['TALENTHUB_LEARNER_SOURCE'] = 'mock';
$_SERVER['APP_ENV'] = 'test';
$_SERVER['TALENTHUB_LEARNER_SOURCE'] = 'mock';
require_once $root . '/app/learner/data/bootstrap.php';

$studentDataSource = file_get_contents(dirname(__DIR__) . '/app/learner/includes/student-data.php');
database_render_assert(is_string($studentDataSource), 'student data source is readable');
database_render_assert(str_contains($studentDataSource, 'StudentAppContext'), 'production pages boot shared Student context');
database_render_assert(str_contains($studentDataSource, "\$appEnvironment === 'test'"), 'mock is restricted to test environment');
database_render_assert(
    str_contains($studentDataSource, 'learner_configure_authenticated_student_context($context)'),
    'page configures learner database from the authenticated canonical context'
);
database_render_assert(str_contains($studentDataSource, 'DatabaseConnectionException'), 'database outage has a controlled page boundary');
database_render_assert(str_contains($studentDataSource, 'runtime-unavailable.php'), 'database outage renders the safe 503 page');

function database_render_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

/**
 * Load the normal learner page context while bypassing only the HTTP auth guard.
 * PortalGuard has its own coverage; this integration fixture must keep the
 * in-memory PDO injected below instead of redirecting or connecting to live DB.
 *
 * @return array<string,mixed>
 */
function database_render_student_context(string $root): array
{
    $savedConfig = learner_data_config();
    $path = $root . '/app/learner/includes/student-data.php';
    $source = file_get_contents($path);
    database_render_assert(is_string($source), 'student page context source is readable');
    $source = preg_replace(
        "~require_once\s+__DIR__\s*\.\s*'/auth-guard\.php';\s*~",
        '',
        $source,
        1,
        $replacements
    );
    database_render_assert(is_string($source) && $replacements === 1, 'test harness bypasses exactly one auth guard include');
    $source = str_replace('__DIR__', var_export(dirname($path), true), $source);

    eval('?>' . $source);
    $variables = get_defined_vars();
    unset($variables['root'], $variables['path'], $variables['source'], $variables['replacements'], $variables['savedConfig']);
    learner_configure_data($savedConfig);
    return $variables;
}

function database_render_page(string $path, array $query = [], array $overrides = []): string
{
    $_GET = $query;
    extract(database_render_student_context(dirname(__DIR__)), EXTR_SKIP);
    extract($overrides, EXTR_OVERWRITE);
    $source = file_get_contents($path);
    database_render_assert(is_string($source), "page source {$path} is readable");
    $source = preg_replace(
        "~require\s+__DIR__\s*\.\s*'/includes/student-data\.php';\s*~",
        '',
        $source,
        1,
        $replacements
    );
    database_render_assert(is_string($source) && $replacements === 1, "page {$path} has one student-data include");
    $source = str_replace('__DIR__', var_export(dirname($path), true), $source);
    set_error_handler(
        static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    );
    ob_start();
    try {
        eval('?>' . $source);
        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    } finally {
        restore_error_handler();
    }
}

// Ephemeral fixture only: the table/column subset mirrors Database/Talenthub_DB.sql.
// It does not create or alter any project or persistent database.
$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ([
    'CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT, passwordHash TEXT, fullName TEXT, roles TEXT, status TEXT, createdAt TEXT)',
    'CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT, status TEXT)',
    'CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel INTEGER, academicYear TEXT)',
    'CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, dateOfBirth TEXT, phone TEXT, studyStatus TEXT)',
    'CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL)',
    'CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT, name TEXT, type TEXT, status TEXT)',
    'CREATE TABLE test_questions (id TEXT PRIMARY KEY, testId TEXT, code TEXT, content TEXT, optionsJson TEXT, status TEXT)',
    'CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT, studentId TEXT, status TEXT, startedAt TEXT, submittedAt TEXT)',
    'CREATE TABLE test_results (id TEXT PRIMARY KEY, attemptId TEXT, resultCode TEXT, summary TEXT, dimensionScoresJson TEXT, scoringVersion TEXT)',
    'CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, createdByTeacherId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT)',
    'CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT)',
    'CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, minScore NUMERIC, maxScore NUMERIC)',
    'CREATE TABLE assessments (id TEXT PRIMARY KEY, teacherId TEXT NOT NULL, studentId TEXT NOT NULL, activityId TEXT NOT NULL, overallScore NUMERIC, comment TEXT, status TEXT NOT NULL, publishedAt TEXT, version INTEGER NOT NULL, createdAt TEXT, updatedAt TEXT)',
    'CREATE TABLE assessment_scores (id TEXT PRIMARY KEY, assessmentId TEXT NOT NULL, criteriaId TEXT NOT NULL, score NUMERIC)',
    'CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT, status TEXT, logoUrl TEXT, industry TEXT, description TEXT, email TEXT, phone TEXT, website TEXT, address TEXT, verificationStatus TEXT, verificationNote TEXT, verifiedAt TEXT, verifiedBy TEXT, createdAt TEXT, updatedAt TEXT)',
    'CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, field TEXT, location TEXT, workType TEXT, duration TEXT, educationLevel TEXT, description TEXT, benefits TEXT, skillsJson TEXT, requirementsJson TEXT, slots INTEGER, deadline TEXT, createdAt TEXT, updatedAt TEXT, status TEXT)',
    'CREATE TABLE internship_applications (id TEXT PRIMARY KEY, postId TEXT, studentId TEXT, status TEXT, message TEXT, appliedAt TEXT, updatedAt TEXT)',
    'CREATE TABLE application_profile_snapshots (id TEXT PRIMARY KEY, applicationId TEXT, schemaVersion TEXT, snapshotPayload TEXT, createdAt TEXT)',
    'CREATE TABLE application_status_history (id TEXT PRIMARY KEY, applicationId TEXT, fromStatus TEXT, toStatus TEXT, changedByRole TEXT, createdAt TEXT)',
] as $schemaStatement) {
    $database->exec($schemaStatement);
}

$ids = [
    'student' => '11111111-1111-4111-8111-111111111111',
    'user' => '22222222-2222-4222-8222-222222222222',
    'other_user' => '22222222-2222-4222-8222-222222222223',
    'other_student' => '11111111-1111-4111-8111-111111111112',
    'class' => '33333333-3333-4333-8333-333333333333',
    'school' => '44444444-4444-4444-8444-444444444444',
    'inactive_school' => '44444444-4444-4444-8444-444444444443',
    'draft_school' => '44444444-4444-4444-8444-444444444442',
    'assessment' => '55555555-5555-4555-8555-555555555555',
    'question' => '66666666-6666-4666-8666-666666666666',
    'attempt' => '77777777-7777-4777-8777-777777777777',
    'in_progress_attempt' => '77777777-7777-4777-8777-777777777776',
    'result' => '88888888-8888-4888-8888-888888888888',
    'activity' => '99999999-9999-4999-8999-999999999999',
    'draft_activity' => '99999999-9999-4999-8999-999999999998',
    'cancelled_activity' => '99999999-9999-4999-8999-999999999997',
    'ongoing_activity' => '99999999-9999-4999-8999-999999999996',
    'completed_activity' => '99999999-9999-4999-8999-999999999995',
    'expired_activity' => '99999999-9999-4999-8999-999999999994',
    'archived_activity' => '99999999-9999-4999-8999-999999999993',
    'teacher' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'teacher_user' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaab',
    'criterion' => 'f1000000-0000-4000-8000-000000000001',
    'zero_max_criterion' => 'f1000000-0000-4000-8000-000000000002',
    'published_evaluation_1' => 'e1000000-0000-4000-8000-000000000001',
    'published_evaluation_2' => 'e1000000-0000-4000-8000-000000000002',
    'draft_evaluation' => 'e1000000-0000-4000-8000-000000000003',
    'other_evaluation' => 'e1000000-0000-4000-8000-000000000004',
    'registration' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'enterprise' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
    'inactive_enterprise' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccb',
    'unapproved_enterprise' => 'cccccccc-cccc-4ccc-8ccc-ccccccccccca',
    'post' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
    'draft_post' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddc',
    'cancelled_post' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddb',
    'inactive_partner_post' => 'dddddddd-dddd-4ddd-8ddd-ddddddddddda',
    'unapproved_partner_post' => 'dddddddd-dddd-4ddd-8ddd-ddddddddddd9',
    'application' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
];

$statements = [
    ['INSERT INTO schools (id, name, status) VALUES (?, ?, ?)', [$ids['school'], 'Database School', 'active']],
    ['INSERT INTO schools (id, name, status) VALUES (?, ?, ?)', [$ids['inactive_school'], 'Inactive Database School', 'inactive']],
    ['INSERT INTO schools (id, name, status) VALUES (?, ?, ?)', [$ids['draft_school'], 'Draft Database School', 'draft']],
    ['INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES (?, ?, ?, ?, ?)', [$ids['class'], $ids['school'], '11A2', 11, '2026']],
    ['INSERT INTO users (id, email, passwordHash, fullName, roles, status, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?)', [$ids['user'], 'learner@example.test', 'test-only', 'Database Learner', 'student', 'active', '2026-08-14']],
    ['INSERT INTO users (id, email, passwordHash, fullName, roles, status, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?)', [$ids['other_user'], 'other@example.test', 'test-only', 'Other Learner', 'student', 'active', '2026-08-14']],
    ['INSERT INTO users (id, email, passwordHash, fullName, roles, status, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?)', [$ids['teacher_user'], 'teacher@example.test', 'test-only', 'Database Teacher', 'teacher', 'active', '2026-08-14']],
    ['INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus) VALUES (?, ?, ?, ?, ?, ?)', [$ids['student'], $ids['user'], $ids['class'], '2009-01-02', '0900000000', 'active']],
    ['INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus) VALUES (?, ?, ?, ?, ?, ?)', [$ids['other_student'], $ids['other_user'], $ids['class'], '2009-02-03', '0900000002', 'active']],
    ['INSERT INTO teacher_profiles (id, userId) VALUES (?, ?)', [$ids['teacher'], $ids['teacher_user']]],
    ['INSERT INTO talent_tests (id, code, name, type, status) VALUES (?, ?, ?, ?, ?)', [$ids['assessment'], 'holland', 'Holland RIASEC', 'RIASEC', 'published']],
    ['INSERT INTO test_questions (id, testId, code, content, optionsJson, status) VALUES (?, ?, ?, ?, ?, ?)', [$ids['question'], $ids['assessment'], 'holland-q1', 'Database Holland question', json_encode([1, 2, 3, 4, 5]), 'published']],
    ['INSERT INTO test_attempts (id, testId, studentId, status, startedAt, submittedAt) VALUES (?, ?, ?, ?, ?, ?)', [$ids['attempt'], $ids['assessment'], $ids['student'], 'submitted', '2026-08-14 08:00:00', '2026-08-14 08:10:00']],
    ['INSERT INTO test_attempts (id, testId, studentId, status, startedAt, submittedAt) VALUES (?, ?, ?, ?, ?, ?)', [$ids['in_progress_attempt'], $ids['assessment'], $ids['student'], 'in_progress', '2026-08-14 09:00:00', null]],
    ['INSERT INTO test_results (id, attemptId, resultCode, summary, dimensionScoresJson, scoringVersion) VALUES (?, ?, ?, ?, ?, ?)', [$ids['result'], $ids['attempt'], 'RIA', 'Database result', json_encode(['R' => 90, 'I' => 80, 'A' => 70, 'S' => 60, 'E' => 50, 'C' => 40]), 'legacy-v1']],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['activity'], $ids['school'], $ids['teacher'], 'IoT Lab — Database', 'Kỹ thuật', '2026-09-01 09:00:00', '2026-09-01 12:00:00', 30, 'published']],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['draft_activity'], $ids['school'], $ids['teacher'], 'Draft Learner Activity', 'Kỹ thuật', '2026-09-02 09:00:00', '2026-09-02 12:00:00', 30, 'draft']],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['cancelled_activity'], $ids['school'], $ids['teacher'], 'Cancelled Learner Activity', 'Kỹ thuật', '2026-09-03 09:00:00', '2026-09-03 12:00:00', 30, 'cancelled']],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['ongoing_activity'], $ids['school'], $ids['teacher'], 'Ongoing Learner Activity', 'Kỹ thuật', '2026-09-04 09:00:00', '2026-09-04 12:00:00', 30, 'ongoing']],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['completed_activity'], $ids['school'], $ids['teacher'], 'Completed Learner Activity', 'Kỹ thuật', '2026-09-05 09:00:00', '2026-09-05 12:00:00', 30, 'completed']],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['expired_activity'], $ids['school'], $ids['teacher'], 'Expired Registration Activity', 'Kỹ thuật', '2026-08-01 09:00:00', '2026-08-01 12:00:00', 30, 'published']],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['archived_activity'], $ids['school'], $ids['teacher'], 'Archived Learner Activity', 'Kỹ thuật', '2026-09-06 09:00:00', '2026-09-06 12:00:00', 30, 'archived']],
    ['INSERT INTO activity_registrations (id, activityId, studentId, status) VALUES (?, ?, ?, ?)', [$ids['registration'], $ids['activity'], $ids['student'], 'approved']],
    ['INSERT INTO assessment_criteria (id, code, name, minScore, maxScore) VALUES (?, ?, ?, ?, ?)', [$ids['criterion'], 'initiative', 'Tính chủ động', 0, 100]],
    ['INSERT INTO assessment_criteria (id, code, name, minScore, maxScore) VALUES (?, ?, ?, ?, ?)', [$ids['zero_max_criterion'], 'not_scored', 'Chưa chấm', 0, 0]],
    ['INSERT INTO assessments (id, teacherId, studentId, activityId, overallScore, comment, status, publishedAt, version, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['published_evaluation_1'], $ids['teacher'], $ids['student'], $ids['activity'], 91, 'Published evaluation one', 'published', '2026-08-20 10:00:00', 1, '2026-08-20 09:00:00', '2026-08-20 10:00:00']],
    ['INSERT INTO assessments (id, teacherId, studentId, activityId, overallScore, comment, status, publishedAt, version, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['published_evaluation_2'], $ids['teacher'], $ids['student'], $ids['ongoing_activity'], 72, 'Published evaluation two', 'published', '2026-08-21 10:00:00', 1, '2026-08-21 09:00:00', '2026-08-21 10:00:00']],
    ['INSERT INTO assessments (id, teacherId, studentId, activityId, overallScore, comment, status, publishedAt, version, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['draft_evaluation'], $ids['teacher'], $ids['student'], $ids['activity'], 99, 'DRAFT-EVALUATION-SECRET', 'draft', null, 1, '2026-08-22 09:00:00', '2026-08-22 09:00:00']],
    ['INSERT INTO assessments (id, teacherId, studentId, activityId, overallScore, comment, status, publishedAt, version, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['other_evaluation'], $ids['teacher'], $ids['other_student'], $ids['activity'], 88, 'OTHER-STUDENT-EVALUATION-SECRET', 'published', '2026-08-22 10:00:00', 1, '2026-08-22 09:00:00', '2026-08-22 10:00:00']],
    ['INSERT INTO assessment_scores (id, assessmentId, criteriaId, score) VALUES (?, ?, ?, ?)', ['f2000000-0000-4000-8000-000000000001', $ids['published_evaluation_1'], $ids['criterion'], 91.5]],
    ['INSERT INTO assessment_scores (id, assessmentId, criteriaId, score) VALUES (?, ?, ?, ?)', ['f2000000-0000-4000-8000-000000000002', $ids['published_evaluation_2'], $ids['criterion'], 72]],
    ['INSERT INTO assessment_scores (id, assessmentId, criteriaId, score) VALUES (?, ?, ?, ?)', ['f2000000-0000-4000-8000-000000000003', $ids['published_evaluation_2'], $ids['zero_max_criterion'], 0]],
    ['INSERT INTO enterprises (id, name, status, logoUrl, industry, description, email, phone, website, address, verificationStatus, verificationNote, verifiedAt, verifiedBy, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['enterprise'], 'Database Enterprise', 'active', null, 'Technology', 'Schema-backed description', 'enterprise@example.test', '0900000001', 'https://example.test', 'Hà Nội', 'verified', null, null, null, '2026-08-01', '2026-08-14']],
    ['INSERT INTO enterprises (id, name, status, logoUrl, industry, description, email, phone, website, address, verificationStatus, verificationNote, verifiedAt, verifiedBy, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['inactive_enterprise'], 'Inactive Database Enterprise', 'inactive', null, 'Technology', 'Hidden inactive partner', 'inactive@example.test', null, null, null, 'verified', null, null, null, '2026-08-01', '2026-08-14']],
    ['INSERT INTO enterprises (id, name, status, logoUrl, industry, description, email, phone, website, address, verificationStatus, verificationNote, verifiedAt, verifiedBy, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['unapproved_enterprise'], 'Pending Database Enterprise', 'active', null, 'Technology', 'Hidden pending partner', 'pending@example.test', null, null, null, 'pending', null, null, null, '2026-08-01', '2026-08-14']],
    ['INSERT INTO internship_posts (id, enterpriseId, title, field, location, workType, duration, educationLevel, description, benefits, skillsJson, requirementsJson, slots, deadline, createdAt, updatedAt, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['post'], $ids['enterprise'], 'Database Internship', 'IT', 'Hà Nội', 'hybrid', '3 months', 'university', 'Canonical description', 'Mentoring', '["PHP"]', '["Student"]', 1, '2026-12-01', '2026-08-22 10:00:00', '2026-08-22 10:00:00', 'active']],
    ['INSERT INTO internship_posts (id, enterpriseId, title, field, location, workType, duration, educationLevel, description, benefits, skillsJson, requirementsJson, slots, deadline, createdAt, updatedAt, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['draft_post'], $ids['enterprise'], 'Draft Database Internship', 'IT', 'Hà Nội', 'hybrid', '3 months', 'university', 'Draft', null, '[]', '[]', 1, '2026-12-01', '2026-08-22 10:00:00', '2026-08-22 10:00:00', 'draft']],
    ['INSERT INTO internship_posts (id, enterpriseId, title, field, location, workType, duration, educationLevel, description, benefits, skillsJson, requirementsJson, slots, deadline, createdAt, updatedAt, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['cancelled_post'], $ids['enterprise'], 'Cancelled Database Internship', 'IT', 'Hà Nội', 'hybrid', '3 months', 'university', 'Cancelled', null, '[]', '[]', 1, '2026-12-01', '2026-08-22 10:00:00', '2026-08-22 10:00:00', 'cancelled']],
    ['INSERT INTO internship_posts (id, enterpriseId, title, field, location, workType, duration, educationLevel, description, benefits, skillsJson, requirementsJson, slots, deadline, createdAt, updatedAt, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['inactive_partner_post'], $ids['inactive_enterprise'], 'Inactive Partner Internship', 'IT', 'Hà Nội', 'hybrid', '3 months', 'university', 'Inactive', null, '[]', '[]', 1, '2026-12-01', '2026-08-22 10:00:00', '2026-08-22 10:00:00', 'active']],
    ['INSERT INTO internship_posts (id, enterpriseId, title, field, location, workType, duration, educationLevel, description, benefits, skillsJson, requirementsJson, slots, deadline, createdAt, updatedAt, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['unapproved_partner_post'], $ids['unapproved_enterprise'], 'Pending Partner Internship', 'IT', 'Hà Nội', 'hybrid', '3 months', 'university', 'Pending', null, '[]', '[]', 1, '2026-12-01', '2026-08-22 10:00:00', '2026-08-22 10:00:00', 'active']],
    ['INSERT INTO internship_applications (id, postId, studentId, status, message, appliedAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?)', [$ids['application'], $ids['post'], $ids['student'], 'submitted', 'Hello', '2026-08-22 10:00:00', '2026-08-22 10:00:00']],
    ['INSERT INTO application_profile_snapshots (id, applicationId, schemaVersion, snapshotPayload, createdAt) VALUES (?, ?, ?, ?, ?)', ['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa01', $ids['application'], '1.0.0', '{"schemaVersion":"1.0.0","skills":[]}', '2026-08-22 10:00:00']],
    ['INSERT INTO application_status_history (id, applicationId, fromStatus, toStatus, changedByRole, createdAt) VALUES (?, ?, ?, ?, ?, ?)', ['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa02', $ids['application'], null, 'submitted', 'student', '2026-08-22 10:00:00']],
];
foreach ($statements as [$sql, $parameters]) {
    $statement = $database->prepare($sql);
    $statement->execute($parameters);
}

learner_configure_data([
    'source' => 'database',
    'pdo' => $database,
    'student_id' => $ids['student'],
]);

learner_configure_data(['source' => 'mock']);
extract(database_render_student_context($root), EXTR_SKIP);
learner_configure_data([
    'source' => 'database',
    'pdo' => $database,
    'student_id' => $ids['student'],
]);
require_once $root . '/app/learner/includes/assessment-data.php';
require_once $root . '/app/learner/includes/activity-data.php';
require_once $root . '/app/learner/includes/ecosystem-data.php';

database_render_assert($student['name'] === 'Nguyễn Văn A', 'student mock remains deterministic in render test mode');
database_render_assert(array_key_exists('location', $student), 'student read model supplies missing UI fields');

$legacyAssessment = learner_assessment_definition('holland');
database_render_assert($legacyAssessment !== null, 'legacy Holland route resolves in database mode');
database_render_assert($legacyAssessment['id'] === $ids['assessment'], 'legacy Holland route maps to database UUID');
database_render_assert(count(learner_assessment_questions('holland')) === 1, 'legacy Holland route loads UUID questions');
$databaseQuestion = learner_assessment_questions('holland')[0];
database_render_assert(
    $databaseQuestion['options'] === [
        ['value' => 1, 'label' => '1'],
        ['value' => 2, 'label' => '2'],
        ['value' => 3, 'label' => '3'],
        ['value' => 4, 'label' => '4'],
        ['value' => 5, 'label' => '5'],
    ],
    'numeric database question options normalize to value-label objects'
);
$databaseHistory = learner_assessment_history($ids['student'], 'holland');
database_render_assert(count($databaseHistory) === 1, 'only completed database attempts appear in result history');
database_render_assert(
    !in_array($ids['in_progress_attempt'], array_column($databaseHistory, 'id'), true),
    'in-progress database attempt is absent from completed result history'
);

$legacyActivity = learner_activity_find('iot-lab');
database_render_assert($legacyActivity !== null, 'legacy activity route resolves in database mode');
database_render_assert($legacyActivity['id'] === $ids['activity'], 'legacy activity route maps to database UUID');
database_render_assert(count(learner_activity_catalog()) === 4, 'activity compatibility list excludes draft, cancelled, and archived rows');
database_render_assert(learner_activity_find($ids['draft_activity']) === null, 'draft activity detail is inaccessible');
database_render_assert(learner_activity_find($ids['cancelled_activity']) === null, 'cancelled activity detail is inaccessible');
database_render_assert(learner_activity_find($ids['archived_activity']) === null, 'archived activity detail is inaccessible');

database_render_assert(
    learner_ecosystem_opportunity('internship', 1) === null,
    'legacy numeric opportunity route fails safely instead of throwing a UUID error'
);
$databaseOpportunity = learner_ecosystem_opportunity('internship', $ids['post']);
database_render_assert($databaseOpportunity !== null, 'database opportunity UUID route resolves');
database_render_assert(array_key_exists('requirements', $databaseOpportunity), 'opportunity read model supplies missing UI fields');
database_render_assert(count(learner_ecosystem_schools()) === 1, 'only active schools are learner-visible');
database_render_assert(count(learner_ecosystem_enterprises()) === 1, 'only active approved enterprises are learner-visible');
database_render_assert(
    learner_ecosystem_partner('school', $ids['inactive_school']) === null,
    'inactive school detail is inaccessible'
);
database_render_assert(
    learner_ecosystem_partner('enterprise', $ids['inactive_enterprise']) === null,
    'inactive enterprise detail is inaccessible'
);
database_render_assert(
    learner_ecosystem_partner('enterprise', $ids['unapproved_enterprise']) === null,
    'unapproved enterprise detail is inaccessible'
);
foreach (['draft_post', 'cancelled_post', 'inactive_partner_post', 'unapproved_partner_post'] as $hiddenPost) {
    database_render_assert(
        learner_ecosystem_opportunity('internship', $ids[$hiddenPost]) === null,
        "{$hiddenPost} detail is inaccessible"
    );
}
database_render_assert(count(learner_ecosystem_opportunities()) === 1, 'only active approved opportunities are learner-visible');

$inactivePartnerPage = database_render_page(
    $root . '/app/learner/partner.php',
    ['type' => 'enterprise', 'id' => $ids['inactive_enterprise']]
);
database_render_assert(
    str_contains($inactivePartnerPage, 'Không tìm thấy đối tác')
        && !str_contains($inactivePartnerPage, 'Inactive Database Enterprise'),
    'inactive enterprise cannot be opened directly'
);
$draftOpportunityPage = database_render_page(
    $root . '/app/learner/opportunity.php',
    ['type' => 'internship', 'id' => $ids['draft_post']]
);
database_render_assert(
    str_contains($draftOpportunityPage, 'Không tìm thấy cơ hội')
        && !str_contains($draftOpportunityPage, 'Draft Database Internship'),
    'draft opportunity cannot be opened directly'
);

$pages = [
    'activities' => database_render_page($root . '/app/learner/activities.php'),
    'activity-detail' => database_render_page($root . '/app/learner/activity-detail.php', ['id' => 'iot-lab']),
    'assessment' => database_render_page($root . '/app/learner/assessment.php', ['id' => 'holland']),
    'assessment-result' => database_render_page($root . '/app/learner/assessment-result.php', ['id' => 'holland']),
    'discover' => database_render_page($root . '/app/learner/discover.php'),
    'ecosystem' => database_render_page($root . '/app/learner/ecosystem.php', ['tab' => 'opportunities']),
    'partner' => database_render_page($root . '/app/learner/partner.php', ['type' => 'enterprise', 'id' => $ids['enterprise']]),
    'opportunity' => database_render_page($root . '/app/learner/opportunity.php', ['type' => 'internship', 'id' => $ids['post']]),
];

foreach (['completed_activity', 'expired_activity'] as $blockedActivity) {
    $blockedActivityHtml = database_render_page(
        $root . '/app/learner/activity-detail.php',
        ['id' => $ids[$blockedActivity]]
    );
    database_render_assert(
        str_contains($blockedActivityHtml, 'data-register-current disabled'),
        "{$blockedActivity} registration action is disabled in PHP UI"
    );
    database_render_assert(
        str_contains($blockedActivityHtml, 'Đã đóng đăng ký'),
        "{$blockedActivity} shows closed registration state"
    );
}

foreach ($pages as $page => $html) {
    database_render_assert($html !== '', "{$page} renders in database mode");
    database_render_assert(!str_contains($html, 'Undefined array key'), "{$page} has no missing compatibility fields");
}

database_render_assert(str_contains($pages['activities'], 'IoT Lab — Database'), 'public activity is rendered');
database_render_assert(!str_contains($pages['activities'], 'Draft Learner Activity'), 'draft activity is absent from rendered list');
database_render_assert(!str_contains($pages['activities'], 'Cancelled Learner Activity'), 'cancelled activity is absent from rendered list');
database_render_assert(str_contains($pages['activity-detail'], 'IoT Lab — Database'), 'legacy activity detail renders');
database_render_assert(str_contains($pages['assessment'], 'data-assessment-runner'), 'assessment renders the API-backed runner shell');
database_render_assert(
    str_contains($pages['assessment'], 'assessment-attempts.php'),
    'assessment boot points to the canonical learner attempt API'
);
database_render_assert(str_contains($pages['assessment'], 'data-assessment-start'), 'assessment runner exposes its ready-state action');
database_render_assert(
    !str_contains($pages['assessment'], 'Database Holland question'),
    'assessment page does not embed repository questions as browser-local truth'
);
database_render_assert(
    str_contains($pages['assessment-result'], 'data-assessment-result-content'),
    'assessment result renders the API-backed result target'
);
database_render_assert(
    str_contains($pages['assessment-result'], 'assessment-attempts.php'),
    'result boot points to the canonical learner attempt API'
);
database_render_assert(
    preg_match('/data-advisory-disclaimer[\s\S]*?<\/div>\s*<\/div>\s*<section[\s\S]*?data-assessment-complete-history/', $pages['assessment-result']) === 1,
    'assessment histories are outside the primary result container and remain independently visible'
);
database_render_assert(
    str_contains($pages['discover'], 'data-catalog-endpoint="/app/learner/api/v1/assessments.php"'),
    'discover catalog loads from the canonical learner assessment API'
);
database_render_assert(str_contains($pages['ecosystem'], $ids['post']), 'ecosystem links use repository UUIDs');
foreach (['Draft Database Internship', 'Cancelled Database Internship', 'Inactive Partner Internship', 'Pending Partner Internship'] as $hiddenTitle) {
    database_render_assert(!str_contains($pages['ecosystem'], $hiddenTitle), "{$hiddenTitle} is absent from ecosystem");
}
database_render_assert(!str_contains($pages['ecosystem'], 'opportunity.php?type=internship&amp;id=1'), 'ecosystem does not emit numeric database routes');
database_render_assert(str_contains($pages['partner'], 'Database Enterprise'), 'partner page renders schema-backed partner');
database_render_assert(str_contains($pages['opportunity'], 'Database Internship'), 'opportunity page renders schema-backed opportunity');

$database->exec("DELETE FROM test_results WHERE attemptId = '" . $ids['attempt'] . "'");
$database->exec("DELETE FROM test_attempts WHERE id = '" . $ids['attempt'] . "'");
$resultWithoutDatabaseHistory = database_render_page(
    $root . '/app/learner/assessment-result.php',
    ['id' => 'holland', 'attempt' => 'local-submitted-attempt']
);
database_render_assert(
    str_contains($resultWithoutDatabaseHistory, 'data-assessment-result-content'),
    'result page keeps a server-backed render target without database history'
);
database_render_assert(
    str_contains($resultWithoutDatabaseHistory, '"assessmentCode":"holland"'),
    'result shell keeps the canonical assessment code and does not depend on a local attempt'
);

/* ── Evaluation page tests ── */
$evaluationOverrides = [
    'isDatabaseMode' => true,
    'evaluationTerms' => [],
    'defaultEvaluationTerm' => '',
];
$evaluationPage = database_render_page($root . '/app/learner/evaluation.php', [], $evaluationOverrides);
database_render_assert($evaluationPage !== '', 'evaluation page renders in database mode');
database_render_assert(!str_contains($evaluationPage, 'Undefined array key'), 'evaluation page has no missing array keys');
database_render_assert(
    str_contains($evaluationPage, 'Published evaluation one')
        && str_contains($evaluationPage, 'Published evaluation two'),
    'database evaluation page preserves every published evaluation even when publication dates share one inferred term'
);
database_render_assert(
    str_contains($evaluationPage, $ids['published_evaluation_1'])
        && str_contains($evaluationPage, $ids['published_evaluation_2']),
    'database evaluation JSON uses stable evaluation ids and does not overwrite same-period rows'
);
database_render_assert(
    !str_contains($evaluationPage, 'DRAFT-EVALUATION-SECRET')
        && !str_contains($evaluationPage, 'OTHER-STUDENT-EVALUATION-SECRET'),
    'database evaluation page leaks neither drafts nor another Student evaluation'
);
database_render_assert(
    !str_contains($evaluationPage, 'Học kỳ II · 2026–2027'),
    'database evaluation page does not invent a semester from publishedAt'
);
database_render_assert(
    !str_contains($evaluationPage, 'Xuất sắc') && !str_contains($evaluationPage, '>Tốt<'),
    'database evaluation page does not invent a classification from overallScore'
);
database_render_assert(
    str_contains($evaluationPage, '"score":91.5')
        && str_contains($evaluationPage, '"max":100'),
    'database evaluation payload preserves canonical decimal scores'
);
database_render_assert(
    str_contains($evaluationPage, '0/0')
        && str_contains($evaluationPage, '--learner-progress: 0%'),
    'database evaluation page safely renders a zero maximum without division by zero'
);
$learnerUiSource = file_get_contents($root . '/assets/js/learner.js');
database_render_assert(
    is_string($learnerUiSource)
        && str_contains($learnerUiSource, 'maximum > 0')
        && str_contains($learnerUiSource, "'--learner-progress', `\${percentage}%`"),
    'client-side evaluation switching also guards a zero maximum'
);
database_render_assert(
    str_contains($evaluationPage, 'learner-evaluation-data'),
    'evaluation page outputs the JSON data script block'
);
database_render_assert(
    str_contains($evaluationPage, 'data-evaluation-status'),
    'evaluation page includes the publication status indicator'
);
database_render_assert(
    preg_match('/<section[^>]*data-evaluation-empty[^>]*hidden[^>]*>/', $evaluationPage) === 1,
    'ready evaluation state keeps the empty section hidden'
);

$database->exec("DELETE FROM assessments WHERE status = 'published' AND studentId = '" . $ids['student'] . "'");
$emptyEvaluationPage = database_render_page($root . '/app/learner/evaluation.php', [], $evaluationOverrides);
database_render_assert(
    preg_match('/<section[^>]*data-evaluation-empty(?![^>]*hidden)[^>]*>/', $emptyEvaluationPage) === 1,
    'a successful zero-row evaluation read shows the empty state'
);
database_render_assert(
    preg_match('/<section[^>]*data-evaluation-error(?![^>]*hidden)[^>]*>/', $emptyEvaluationPage) !== 1,
    'a successful zero-row evaluation read does not show source-error'
);

$database->exec('DROP TABLE assessments');
$errorEvaluationPage = database_render_page($root . '/app/learner/evaluation.php', [], $evaluationOverrides);
database_render_assert(
    preg_match('/<section[^>]*data-evaluation-error(?![^>]*hidden)[^>]*>/', $errorEvaluationPage) === 1,
    'a failed database evaluation read shows source-error'
);
database_render_assert(
    preg_match('/<section[^>]*data-evaluation-empty(?![^>]*hidden)[^>]*>/', $errorEvaluationPage) !== 1,
    'source-error never renders as an empty evaluation state'
);
database_render_assert(
    !str_contains($errorEvaluationPage, 'no such table')
        && !str_contains($errorEvaluationPage, 'SQLSTATE'),
    'source-error output exposes neither SQL nor exception details'
);

$databaseRenderCompleted = true;
echo "learner_database_render_test: OK\n";
