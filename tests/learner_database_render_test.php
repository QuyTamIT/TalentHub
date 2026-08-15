<?php

declare(strict_types=1);

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
database_render_assert(!str_contains($studentDataSource, "source' => 'database'"), 'page does not create a second learner database configuration');
database_render_assert(str_contains($studentDataSource, 'DatabaseConnectionException'), 'database outage has a controlled page boundary');
database_render_assert(str_contains($studentDataSource, 'runtime-unavailable.php'), 'database outage renders the safe 503 page');

function database_render_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function database_render_page(string $path, array $query = []): string
{
    $_GET = $query;
    set_error_handler(
        static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    );
    ob_start();
    try {
        include $path;
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
    'CREATE TABLE talent_tests (id TEXT PRIMARY KEY, name TEXT, type TEXT, dimensions TEXT)',
    'CREATE TABLE test_questions (id TEXT PRIMARY KEY, testId TEXT, content TEXT, options TEXT)',
    'CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT, studentId TEXT, startedAt TEXT, completedAt TEXT)',
    'CREATE TABLE test_results (id TEXT PRIMARY KEY, attemptId TEXT, resultCode TEXT, summary TEXT, dimensionScores TEXT)',
    'CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, createdByTeacherId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT)',
    'CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT)',
    'CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT, status TEXT, logoUrl TEXT, industry TEXT, description TEXT, email TEXT, phone TEXT, website TEXT, address TEXT, verificationStatus TEXT, verificationNote TEXT, verifiedAt TEXT, verifiedBy TEXT, createdAt TEXT, updatedAt TEXT)',
    'CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, location TEXT, deadline TEXT, status TEXT)',
    'CREATE TABLE internship_applications (id TEXT PRIMARY KEY, postId TEXT, studentId TEXT, status TEXT, cvUrl TEXT, reviewerNote TEXT)',
] as $schemaStatement) {
    $database->exec($schemaStatement);
}

$ids = [
    'student' => '11111111-1111-4111-8111-111111111111',
    'user' => '22222222-2222-4222-8222-222222222222',
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
    'closed_activity' => '99999999-9999-4999-8999-999999999996',
    'completed_activity' => '99999999-9999-4999-8999-999999999995',
    'expired_activity' => '99999999-9999-4999-8999-999999999994',
    'teacher' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
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
    ['INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus) VALUES (?, ?, ?, ?, ?, ?)', [$ids['student'], $ids['user'], $ids['class'], '2009-01-02', '0900000000', 'active']],
    ['INSERT INTO talent_tests (id, name, type, dimensions) VALUES (?, ?, ?, ?)', [$ids['assessment'], 'Holland RIASEC', 'RIASEC', json_encode(['R', 'I', 'A', 'S', 'E', 'C'])]],
    ['INSERT INTO test_questions (id, testId, content, options) VALUES (?, ?, ?, ?)', [$ids['question'], $ids['assessment'], 'Database Holland question', json_encode([1, 2, 3, 4, 5])]],
    ['INSERT INTO test_attempts (id, testId, studentId, startedAt, completedAt) VALUES (?, ?, ?, ?, ?)', [$ids['attempt'], $ids['assessment'], $ids['student'], '2026-08-14 08:00:00', '2026-08-14 08:10:00']],
    ['INSERT INTO test_attempts (id, testId, studentId, startedAt, completedAt) VALUES (?, ?, ?, ?, ?)', [$ids['in_progress_attempt'], $ids['assessment'], $ids['student'], '2026-08-14 09:00:00', null]],
    ['INSERT INTO test_results (id, attemptId, resultCode, summary, dimensionScores) VALUES (?, ?, ?, ?, ?)', [$ids['result'], $ids['attempt'], 'RIA', 'Database result', json_encode(['R' => 90, 'I' => 80, 'A' => 70, 'S' => 60, 'E' => 50, 'C' => 40])]],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['activity'], $ids['school'], $ids['teacher'], 'IoT Lab — Database', 'Kỹ thuật', '2026-09-01 09:00:00', '2026-09-01 12:00:00', 30, 'published']],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['draft_activity'], $ids['school'], $ids['teacher'], 'Draft Learner Activity', 'Kỹ thuật', '2026-09-02 09:00:00', '2026-09-02 12:00:00', 30, 'draft']],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['cancelled_activity'], $ids['school'], $ids['teacher'], 'Cancelled Learner Activity', 'Kỹ thuật', '2026-09-03 09:00:00', '2026-09-03 12:00:00', 30, 'cancelled']],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['closed_activity'], $ids['school'], $ids['teacher'], 'Closed Learner Activity', 'Kỹ thuật', '2026-09-04 09:00:00', '2026-09-04 12:00:00', 30, 'closed']],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['completed_activity'], $ids['school'], $ids['teacher'], 'Completed Learner Activity', 'Kỹ thuật', '2026-09-05 09:00:00', '2026-09-05 12:00:00', 30, 'completed']],
    ['INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['expired_activity'], $ids['school'], $ids['teacher'], 'Expired Registration Activity', 'Kỹ thuật', '2026-08-01 09:00:00', '2026-08-01 12:00:00', 30, 'published']],
    ['INSERT INTO activity_registrations (id, activityId, studentId, status) VALUES (?, ?, ?, ?)', [$ids['registration'], $ids['activity'], $ids['student'], 'registered']],
    ['INSERT INTO enterprises (id, name, status, logoUrl, industry, description, email, phone, website, address, verificationStatus, verificationNote, verifiedAt, verifiedBy, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['enterprise'], 'Database Enterprise', 'active', null, 'Technology', 'Schema-backed description', 'enterprise@example.test', '0900000001', 'https://example.test', 'Hà Nội', 'verified', null, null, null, '2026-08-01', '2026-08-14']],
    ['INSERT INTO enterprises (id, name, status, logoUrl, industry, description, email, phone, website, address, verificationStatus, verificationNote, verifiedAt, verifiedBy, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['inactive_enterprise'], 'Inactive Database Enterprise', 'inactive', null, 'Technology', 'Hidden inactive partner', 'inactive@example.test', null, null, null, 'verified', null, null, null, '2026-08-01', '2026-08-14']],
    ['INSERT INTO enterprises (id, name, status, logoUrl, industry, description, email, phone, website, address, verificationStatus, verificationNote, verifiedAt, verifiedBy, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$ids['unapproved_enterprise'], 'Pending Database Enterprise', 'active', null, 'Technology', 'Hidden pending partner', 'pending@example.test', null, null, null, 'pending', null, null, null, '2026-08-01', '2026-08-14']],
    ['INSERT INTO internship_posts (id, enterpriseId, title, location, deadline, status) VALUES (?, ?, ?, ?, ?, ?)', [$ids['post'], $ids['enterprise'], 'Database Internship', 'Hà Nội', '2026-12-01', 'active']],
    ['INSERT INTO internship_posts (id, enterpriseId, title, location, deadline, status) VALUES (?, ?, ?, ?, ?, ?)', [$ids['draft_post'], $ids['enterprise'], 'Draft Database Internship', 'Hà Nội', '2026-12-01', 'draft']],
    ['INSERT INTO internship_posts (id, enterpriseId, title, location, deadline, status) VALUES (?, ?, ?, ?, ?, ?)', [$ids['cancelled_post'], $ids['enterprise'], 'Cancelled Database Internship', 'Hà Nội', '2026-12-01', 'cancelled']],
    ['INSERT INTO internship_posts (id, enterpriseId, title, location, deadline, status) VALUES (?, ?, ?, ?, ?, ?)', [$ids['inactive_partner_post'], $ids['inactive_enterprise'], 'Inactive Partner Internship', 'Hà Nội', '2026-12-01', 'active']],
    ['INSERT INTO internship_posts (id, enterpriseId, title, location, deadline, status) VALUES (?, ?, ?, ?, ?, ?)', [$ids['unapproved_partner_post'], $ids['unapproved_enterprise'], 'Pending Partner Internship', 'Hà Nội', '2026-12-01', 'active']],
    ['INSERT INTO internship_applications (id, postId, studentId, status, cvUrl, reviewerNote) VALUES (?, ?, ?, ?, ?, ?)', [$ids['application'], $ids['post'], $ids['student'], 'submitted', '/cv/test.pdf', null]],
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
require_once $root . '/app/learner/includes/student-data.php';
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
database_render_assert(count(learner_activity_catalog()) === 4, 'activity compatibility list excludes draft and cancelled rows');
database_render_assert(learner_activity_find($ids['draft_activity']) === null, 'draft activity detail is inaccessible');
database_render_assert(learner_activity_find($ids['cancelled_activity']) === null, 'cancelled activity detail is inaccessible');

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

foreach (['closed_activity', 'completed_activity', 'expired_activity'] as $blockedActivity) {
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
database_render_assert(str_contains($pages['assessment'], 'Bài test chưa sẵn sàng'), 'invalid database Holland set renders unavailable state');
database_render_assert(!str_contains($pages['assessment'], 'data-assessment-start'), 'invalid database Holland set has no start action');
database_render_assert(str_contains($pages['assessment-result'], 'Database result'), 'assessment result renders database attempt');
database_render_assert(
    str_contains($pages['assessment-result'], '"assessment_id":"' . $ids['assessment'] . '"'),
    'result boot data uses the canonical assessment UUID'
);
database_render_assert(
    str_contains($pages['discover'], '"assessment_id":"' . $ids['assessment'] . '"'),
    'discover boot data uses the canonical assessment UUID'
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
    'result page keeps a render target for a newly submitted local attempt'
);
database_render_assert(
    str_contains($resultWithoutDatabaseHistory, '"assessment_id":"' . $ids['assessment'] . '"'),
    'local result shell still filters storage by canonical assessment UUID'
);

echo "learner_database_render_test: OK\n";
