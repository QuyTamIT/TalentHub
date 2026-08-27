<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Queue\AiAudienceResolver;
use TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;
use TalentHub\Modules\Business\Repository\BusinessWorkflowRepository;
use TalentHub\Modules\School\Repository\SchoolProjectRepository;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;

require_once dirname(__DIR__) . '/src/Http/ApiException.php';
require_once dirname(__DIR__) . '/src/Support/Uuid.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once dirname(__DIR__) . '/src/Modules/Teacher/Repository/TeacherActivityRepository.php';
require_once dirname(__DIR__) . '/src/Modules/Business/Repository/BusinessWorkflowRepository.php';
require_once dirname(__DIR__) . '/src/Modules/School/Repository/SchoolProjectRepository.php';

function catalog_production_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$schema = [
    'CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT, status TEXT)',
    'CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, gradeLevel TEXT)',
    'CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT, userId TEXT)',
    'CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT, schoolId TEXT)',
    'CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, createdByTeacherId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT, createdAt TEXT, updatedAt TEXT)',
    // Canonical activity_details schema mirrors the production migration
    // Database/migrations/20260825000100_create_activity_details.php so that
    // TeacherActivityRepository::activitySelectSql() can resolve every column
    // it LEFT JOINs without runtime "no such column" failures.
    'CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, responsibleTeacherId TEXT, audienceScope TEXT NOT NULL DEFAULT "school_only", displayCategory TEXT NOT NULL DEFAULT "", filterCategory TEXT NOT NULL DEFAULT "", summary TEXT NOT NULL DEFAULT "", description TEXT NOT NULL DEFAULT "", experienceHighlights TEXT NOT NULL DEFAULT "[]", skillTags TEXT NOT NULL DEFAULT "[]", eligibilityRules TEXT NOT NULL DEFAULT "[]", benefitItems TEXT NOT NULL DEFAULT "[]", locationName TEXT NOT NULL DEFAULT "", locationAddress TEXT, deliveryMode TEXT NOT NULL DEFAULT "in_person", onlineMeetingUrl TEXT, organizerName TEXT NOT NULL DEFAULT "", organizerContact TEXT, organizerEmail TEXT, organizerPhone TEXT, coverImageUrl TEXT, coverImageAlt TEXT, feeAmount REAL NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT "VND", targetAudience TEXT NOT NULL DEFAULT "", certificateLabel TEXT, createdAt TEXT, updatedAt TEXT)',
    'CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT, cancellationClosesAt TEXT, approvalMode TEXT, createdAt TEXT, updatedAt TEXT)',
    'CREATE TABLE activity_experience_policies (activityId TEXT PRIMARY KEY, confirmedHours REAL NOT NULL DEFAULT 0, createdAt TEXT, updatedAt TEXT)',
    'CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT)',
    'CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT, status TEXT, verificationStatus TEXT, email TEXT)',
    'CREATE TABLE enterprise_members (id TEXT PRIMARY KEY, enterpriseId TEXT, userId TEXT, memberRole TEXT)',
    'CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT, email TEXT)',
    'CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, field TEXT, location TEXT, slots INTEGER, deadline TEXT, status TEXT, audience TEXT, createdAt TEXT, updatedAt TEXT)',
    'CREATE TABLE internship_post_target_schools (postId TEXT, schoolId TEXT)',
    'CREATE TABLE school_enterprise_partnerships (schoolId TEXT, enterpriseId TEXT, status TEXT)',
    'CREATE TABLE internship_applications (id TEXT PRIMARY KEY, postId TEXT, studentId TEXT, status TEXT, appliedAt TEXT)',
    'CREATE TABLE projects (id TEXT PRIMARY KEY, schoolId TEXT, mentorTeacherId TEXT, title TEXT, category TEXT, description TEXT, projectUrl TEXT, fundingGoal TEXT, startAt TEXT, endAt TEXT, status TEXT, createdAt TEXT, updatedAt TEXT)',
    'CREATE TABLE project_members (id TEXT PRIMARY KEY, projectId TEXT, studentId TEXT, status TEXT)',
    'CREATE TABLE project_sponsorships (id TEXT PRIMARY KEY, projectId TEXT, enterpriseId TEXT, amount NUMERIC, status TEXT)',
    'CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, requestId TEXT, metadata TEXT, createdAt TEXT)',
    'CREATE TABLE learner_ai_catalog_items (catalog_id TEXT PRIMARY KEY, item_type TEXT, category TEXT, title TEXT, summary TEXT, publish_status TEXT, deadline_at TEXT, eligibility_json TEXT, capacity INTEGER, enrolled_count INTEGER, url TEXT, action_json TEXT, school_id TEXT, tenant_id TEXT, updated_at TEXT)',
    'CREATE TABLE learner_ai_data_outbox (id TEXT PRIMARY KEY, aggregate_type TEXT, aggregate_id TEXT, tenant_id TEXT, event_type TEXT, aggregate_version INTEGER, payload_hash TEXT, affected_student_ids TEXT, delivery_status TEXT, occurred_at TEXT, delivered_at TEXT)',
];
foreach ($schema as $statement) $pdo->exec($statement);

$pdo->exec("INSERT INTO schools VALUES ('school-a','School A','active'),('school-b','School B','active')");
$pdo->exec("INSERT INTO classes VALUES ('class-a','school-a','10'),('class-b','school-b','11')");
$pdo->exec("INSERT INTO student_profiles VALUES ('student-a','class-a','user-a'),('student-b','class-a','user-b'),('student-c','class-b','user-c')");
$pdo->exec("INSERT INTO users VALUES ('user-school','School User','school@talenthub.test')");
$pdo->exec("INSERT INTO teacher_profiles VALUES ('teacher-a','user-teacher','school-a')");
$pdo->exec("INSERT INTO enterprises VALUES ('enterprise-a','Enterprise A','active','verified','ent@talenthub.test')");
$pdo->exec("INSERT INTO activities VALUES
    ('activity-open','school-a','teacher-a','Open workshop','technical','2035-03-01 08:00:00','2035-03-01 12:00:00',20,'draft','2026-01-01','2026-01-01'),
    ('activity-full','school-a','teacher-a','Full workshop','technical','2035-03-02 08:00:00','2035-03-02 12:00:00',1,'published','2026-01-01','2026-01-01')");
// Seed activity_details with every canonical column used by TeacherActivityRepository::activitySelectSql()
// plus registration policies and experience hours so that assertPublishable() can complete the
// draft -> published transition and the AiAudienceResolver outbox can be emitted transactionally.
$pdo->exec("INSERT INTO activity_details VALUES
    ('activity-open','teacher-a','school_only','technical','technical','Open workshop summary','Open workshop description','[]','[]','[]','[]','Lab',NULL,'in_person',NULL,'Teacher A',NULL,NULL,NULL,NULL,NULL,0.0,'VND','Students',NULL,'2026-01-01 00:00:00.000000','2026-01-01 00:00:00.000000'),
    ('activity-full','teacher-a','school_only','technical','technical','Full workshop summary','Full workshop description','[]','[]','[]','[]','Lab',NULL,'in_person',NULL,'Teacher A',NULL,NULL,NULL,NULL,NULL,0.0,'VND','Students',NULL,'2026-01-01 00:00:00.000000','2026-01-01 00:00:00.000000')");
$pdo->exec("INSERT INTO activity_registration_policies VALUES
    ('activity-open','2025-01-01 00:00:00','2035-02-28 00:00:00','2035-02-28 00:00:00','automatic','2026-01-01 00:00:00.000000','2026-01-01 00:00:00.000000'),
    ('activity-full','2025-01-01 00:00:00','2035-02-28 00:00:00','2035-02-28 00:00:00','automatic','2026-01-01 00:00:00.000000','2026-01-01 00:00:00.000000')");
$pdo->exec("INSERT INTO activity_experience_policies VALUES
    ('activity-open',2.0,'2026-01-01 00:00:00.000000','2026-01-01 00:00:00.000000'),
    ('activity-full',2.0,'2026-01-01 00:00:00.000000','2026-01-01 00:00:00.000000')");
$pdo->exec("INSERT INTO activity_registrations VALUES ('reg-full','activity-full','student-b','approved')");
$pdo->exec("INSERT INTO internship_posts VALUES
    ('post-open','enterprise-a','AI Internship','technology','Remote',2,'2035-04-01 00:00:00','draft','public','2026-01-01 00:00:00','2026-01-01 00:00:00'),
    ('post-expired','enterprise-a','Expired Internship','technology','Remote',2,'2020-04-01 00:00:00','active','public','2026-01-01 00:00:00','2026-01-01 00:00:00')");
$pdo->exec("INSERT INTO internship_applications VALUES ('accepted-other','post-open','student-b','accepted','2026-01-01 00:00:00')");
$pdo->exec("INSERT INTO projects VALUES ('project-open','school-a',NULL,'Robotics project','technical','Build a robot','/projects/project-open',NULL,'2026-01-01 00:00:00','2035-05-01 00:00:00','in_progress','2026-01-01 00:00:00','2026-01-01 00:00:00')");

$audiences = new AiAudienceResolver($pdo);
catalog_production_assert($audiences->schoolStudents('school-a') === ['student-a', 'student-b'], 'school audience resolves only students in the target school in stable order');
catalog_production_assert($audiences->internshipStudents('post-open') === ['student-a', 'student-b', 'student-c'], 'public opportunity audience resolves all eligible learners without PII');

$teacher = new TeacherActivityRepository($pdo);
catalog_production_assert($teacher->advanceStatus('teacher-a', 'activity-open', 'draft', 'published'), 'teacher can publish an activity');
$activityEvent = $pdo->query("SELECT affected_student_ids, tenant_id FROM learner_ai_data_outbox WHERE aggregate_type='activity' AND aggregate_id='activity-open' AND event_type='activity.published'")->fetch(PDO::FETCH_ASSOC);
catalog_production_assert(json_decode((string) $activityEvent['affected_student_ids'], true) === ['student-a', 'student-b'] && $activityEvent['tenant_id'] === 'school-a', 'activity publish writes a transactional school-tenant audience outbox event');

$business = new BusinessWorkflowRepository($pdo);
catalog_production_assert($business->transitionPost('enterprise-a', 'post-open', 'draft', 'active'), 'enterprise can publish an internship post');
$postEvent = $pdo->query("SELECT affected_student_ids FROM learner_ai_data_outbox WHERE aggregate_type='internship_post' AND aggregate_id='post-open' AND event_type='opportunity.published'")->fetchColumn();
catalog_production_assert(json_decode((string) $postEvent, true) === ['student-a', 'student-b', 'student-c'], 'opportunity publish writes a transactional eligible-audience outbox event');

$schoolProjects = new SchoolProjectRepository($pdo);
$schoolProjects->updateProject('school-a', 'school-user', 'project-open', ['description' => 'Updated project']);
$projectEvent = $pdo->query("SELECT affected_student_ids FROM learner_ai_data_outbox WHERE aggregate_type='project' AND aggregate_id='project-open' AND event_type='project.updated'")->fetchColumn();
catalog_production_assert(json_decode((string) $projectEvent, true) === ['student-a', 'student-b'], 'project update refreshes the prospective school audience, not only existing members');

$opportunities = new DatabaseOpportunitySource($pdo, new DateTimeImmutable('2029-01-01T00:00:00+00:00'));
$opportunityRows = $opportunities->forStudent('student-a');
catalog_production_assert(array_column($opportunityRows, 'opportunity_id') === ['activity-open', 'post-open'], 'production opportunity source excludes expired/full/inaccessible records and keeps deterministic order');
foreach ($opportunityRows as $row) {
    catalog_production_assert(isset($row['catalog_id'], $row['action'], $row['url'], $row['availability']), 'production opportunities expose the catalog action contract');
}
$internship = array_values(array_filter($opportunityRows, static fn (array $row): bool => $row['opportunity_id'] === 'post-open'))[0] ?? [];
catalog_production_assert(($internship['availability']['enrolled'] ?? null) === 1 && ($internship['availability']['remaining'] ?? null) === 1, 'internship availability reflects accepted applications and remaining slots');
$pdo->exec("INSERT INTO internship_applications VALUES ('already-applied','post-open','student-a','submitted','2026-01-01 00:00:00')");
catalog_production_assert(!in_array('post-open', array_column($opportunities->forStudent('student-a'), 'opportunity_id'), true), 'learner is not recommended an internship already applied to');

$catalog = new DatabaseCatalogSource($pdo, new DateTimeImmutable('2029-01-01T00:00:00+00:00'));
$catalogRows = $catalog->readForStudent('student-a');
catalog_production_assert(in_array('project-open', array_column($catalogRows, 'catalog_id'), true), 'active school project is dynamically visible as a catalog recommendation');

echo "learner_ai_catalog_production_integration_test: OK\n";
