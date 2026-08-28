<?php
declare(strict_types=1);

namespace TalentHub\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Service/EducationBandResolver.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function api_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException('FAIL: ' . $message);
    }
}

function createApiTestPdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, studyStatus TEXT, tenantId TEXT)');
    $pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel TEXT, academicYear TEXT, educationBand TEXT)');
    $pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT, level TEXT)');
    $pdo->exec('CREATE TABLE skills (id TEXT PRIMARY KEY, code TEXT, name TEXT, category TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE student_skills (id TEXT PRIMARY KEY, studentId TEXT, skillId TEXT, levelScore REAL, sourceType TEXT, verificationStatus TEXT, verifiedAt TEXT, updatedAt TEXT)');
    $pdo->exec('CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT, type TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE test_attempts (id TEXT PRIMARY KEY, studentId TEXT, testId TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE test_results (id TEXT PRIMARY KEY, attemptId TEXT, resultCode TEXT, dimensionScoresJson TEXT)');
    $pdo->exec('CREATE TABLE learner_assessment_attempt_metadata (id TEXT PRIMARY KEY, attemptId TEXT, versionId TEXT, status TEXT, submittedAt TEXT)');
    $pdo->exec('CREATE TABLE learner_assessment_versions (id TEXT PRIMARY KEY, version TEXT, scoringVersion TEXT, status TEXT, publishedAt TEXT)');
    $pdo->exec('CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, checkinId TEXT, hours REAL, status TEXT, confirmedAt TEXT)');
    $pdo->exec('CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT, status TEXT, confirmedAt TEXT)');
    $pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT)');
    $pdo->exec('CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, audienceScope TEXT, filterCategory TEXT, locationName TEXT)');
    $pdo->exec('CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT)');
    $pdo->exec('CREATE TABLE assessments (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, overallScore REAL, status TEXT, publishedAt TEXT)');
    $pdo->exec('CREATE TABLE assessment_scores (id TEXT PRIMARY KEY, assessmentId TEXT, criteriaId TEXT, score REAL)');
    $pdo->exec('CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, code TEXT)');
    $pdo->exec('CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, location TEXT, deadline TEXT, status TEXT, audience TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE enterprises (id TEXT PRIMARY KEY, status TEXT, verificationStatus TEXT)');
    $pdo->exec('CREATE TABLE learner_ai_catalog_items (catalog_id TEXT PRIMARY KEY, item_type TEXT, category TEXT, title TEXT, summary TEXT, publish_status TEXT, deadline_at TEXT, eligibility_json TEXT, capacity INTEGER, enrolled_count INTEGER, url TEXT, action_json TEXT, school_id TEXT, tenant_id TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE learner_ai_consent_events (id TEXT PRIMARY KEY, studentId TEXT, scope TEXT, action TEXT, policyVersion TEXT, occurredAt TEXT, requestId TEXT)');
    $pdo->exec('CREATE TABLE learner_ai_roadmaps (id TEXT PRIMARY KEY, studentId TEXT, versionNumber INTEGER, roadmapId TEXT, status TEXT, modelVersion TEXT, primaryDirectionJson TEXT, alternativeDirectionsJson TEXT, overallGoal TEXT, summaryHtml TEXT, createdAt TEXT, supersededAt TEXT)');
    $pdo->exec('CREATE TABLE action_rate_limit_events (id TEXT PRIMARY KEY, actionKey TEXT NOT NULL, identifier TEXT NOT NULL, ipAddress TEXT, occurredAt TEXT NOT NULL, expireAt TEXT NOT NULL)');

    return $pdo;
}

$pdo = createApiTestPdo();
$studentId = '00000000-0000-4000-8000-000000000001';
$schoolId = '00000000-0000-4000-8000-000000000002';
$classId = '00000000-0000-4000-8000-000000000003';

// Seed profile
$pdo->exec("INSERT INTO schools (id, name, level) VALUES ('{$schoolId}', 'THPT Chuyen TalentHub', 'THPT')");
$pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, educationBand) VALUES ('{$classId}', '{$schoolId}', '11A1', '11', '2025-2026', 'high')");
$pdo->exec("INSERT INTO student_profiles (id, userId, classId, studyStatus, tenantId) VALUES ('{$studentId}', 'usr-1', '{$classId}', 'active', '{$schoolId}')");

// Seed consents
$pdo->exec("INSERT INTO learner_ai_consent_events VALUES ('c-1', '{$studentId}', 'activity', 'granted', '1.0', '2026-01-01 00:00:00', 'req-1')");
$pdo->exec("INSERT INTO learner_ai_consent_events VALUES ('c-2', '{$studentId}', 'skills', 'granted', '1.0', '2026-01-01 00:00:00', 'req-2')");
$pdo->exec("INSERT INTO learner_ai_consent_events VALUES ('c-3', '{$studentId}', 'assessment', 'granted', '1.0', '2026-01-01 00:00:00', 'req-3')");

// Seed skills
$pdo->exec("INSERT INTO skills (id, code, name, category, status) VALUES ('sk-1', 'data_analysis', 'Phân tích dữ liệu', 'tech', 'active')");
$pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, updatedAt) VALUES ('ss-1', '{$studentId}', 'sk-1', 85.0, 'assessment', 'verified', '2026-02-01 00:00:00', '2026-02-01 00:00:00')");

// Seed Holland Assessment
$pdo->exec("INSERT INTO talent_tests (id, code, type, status) VALUES ('t-1', 'HOLLAND', 'holland', 'published')");
$pdo->exec("INSERT INTO test_attempts (id, studentId, testId, status) VALUES ('att-1', '{$studentId}', 't-1', 'submitted')");
$pdo->exec("INSERT INTO test_results (id, attemptId, resultCode, dimensionScoresJson) VALUES ('res-1', 'att-1', 'IAS', '{\"I\":28,\"A\":24,\"S\":20,\"R\":10,\"E\":8,\"C\":6}')");
$pdo->exec("INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status, submittedAt) VALUES ('meta-1', 'att-1', 'v1', 'submitted', '2026-02-10 10:00:00')");
$pdo->exec("INSERT INTO learner_assessment_versions (id, version, scoringVersion, status, publishedAt) VALUES ('v1', '1.0', '1.0', 'published', '2026-01-01 00:00:00')");

// Seed Group Candidates
$action1 = json_encode([
    'type' => 'join_group',
    'catalog_id' => 'grp-1',
    'match_profile' => [
        'skill_codes' => ['data_analysis'],
        'assessment_directions' => ['holland' => ['I', 'A']],
        'education_bands' => ['high']
    ]
]);
$pdo->exec("INSERT INTO learner_ai_catalog_items VALUES ('grp-1', 'group', 'study_group', 'Nhóm Data', 'Mô tả', 'published', '2026-09-30 23:59:59', '[]', 20, 5, '/app/learner/groups.php?id=grp-1', '$action1', '{$schoolId}', '{$schoolId}', '2026-08-01 00:00:00')");

// Community candidate
$actionComm = json_encode([
    'type' => 'open_catalog_item',
    'catalog_id' => 'comm-1',
    'match_profile' => [
        'skill_codes' => ['data_analysis'],
        'assessment_directions' => ['holland' => ['I']],
        'education_bands' => ['high']
    ]
]);
$pdo->exec("INSERT INTO learner_ai_catalog_items VALUES ('comm-1', 'community', 'tech_community', 'Cộng đồng AI', 'Mô tả', 'published', '2026-09-30 23:59:59', '[]', 50, 10, '/app/learner/group-detail.php?id=comm-1', '$actionComm', '{$schoolId}', '{$schoolId}', '2026-08-01 00:00:00')");

// Test GET matching service resolution
$consentPolicy = new \TalentHub\Learner\Ai\Consent\ConsentPolicy(new \TalentHub\Learner\Ai\Sources\Database\DatabaseConsentSource($pdo));
$catalogSource = new \TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource($pdo);
$registry = \TalentHub\Learner\Ai\Sources\AiSourceRegistry::fromLegacySources([
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseStudentProfileSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseAssessmentSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource($pdo),
]);
$registry->setTransactionPdo($pdo);
$snapshotBuilder = new \TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder($registry);
$educationBandResolver = new \TalentHub\Learner\Assessment\Service\EducationBandResolver($pdo);
$service = new \TalentHub\Learner\Ai\Service\GroupMatchingService($pdo, $catalogSource, $consentPolicy, $snapshotBuilder, $educationBandResolver);

// 1. Test Match list
$items = $service->match($studentId, 10);
api_assert(count($items) === 2, 'two matching candidates returned');
api_assert($items[0]['analysis_origin'] === 'evidence_match', 'analysis_origin is evidence_match');

// 2. Test Resolve join_group Action
$resJoin = $service->resolveAction($studentId, 'grp-1', 'join_group');
api_assert($resJoin['state'] === 'action_ready', 'join_group resolves action_ready');
api_assert($resJoin['url'] === '/app/learner/groups.php?id=grp-1', 'url is correct safe relative url');

// 3. Test Resolve open_catalog_item Action
$resOpen = $service->resolveAction($studentId, 'comm-1', 'open_catalog_item');
api_assert($resOpen['state'] === 'catalog_opened', 'open_catalog_item resolves catalog_opened');
api_assert($resOpen['url'] === '/app/learner/group-detail.php?id=comm-1', 'url is correct catalog url');

// 4. Test Resolve Action for Invalid or Unavailable Catalog ID
$resInvalid = $service->resolveAction($studentId, 'unknown-id', 'join_group');
api_assert($resInvalid['state'] === 'join_unavailable', 'unknown id resolves join_unavailable');
api_assert($resInvalid['url'] === null, 'unavailable action has null url');

echo "learner_ai_group_matching_api_test: OK\n";