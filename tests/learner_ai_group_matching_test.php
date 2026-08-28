<?php
declare(strict_types=1);

namespace TalentHub\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Service\GroupMatchingService;
use TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder;
use TalentHub\Learner\Ai\Sources\AiSourceRegistry;
use TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseAssessmentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseConsentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;
use TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseStudentProfileSource;
use TalentHub\Learner\Assessment\Service\EducationBandResolver;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Service/EducationBandResolver.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function group_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException('FAIL: ' . $message);
    }
}

function createGroupMatchingPdo(): PDO
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

    return $pdo;
}

$pdo = createGroupMatchingPdo();
$nowUtc = new DateTimeImmutable('2026-08-28T12:00:00Z', new DateTimeZone('UTC'));
$studentId = '00000000-0000-4000-8000-000000000001';
$schoolId = '00000000-0000-4000-8000-000000000002';
$classId = '00000000-0000-4000-8000-000000000003';

// 1. Seed School, Class, Student
$pdo->exec("INSERT INTO schools (id, name, level) VALUES ('{$schoolId}', 'THPT Chuyen TalentHub', 'THPT')");
$pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, educationBand) VALUES ('{$classId}', '{$schoolId}', '11A1', '11', '2025-2026', 'high')");
$pdo->exec("INSERT INTO student_profiles (id, userId, classId, studyStatus, tenantId) VALUES ('{$studentId}', 'usr-1', '{$classId}', 'active', '{$schoolId}')");

// 2. Seed Consents (all granted)
$pdo->exec("INSERT INTO learner_ai_consent_events VALUES ('c-1', '{$studentId}', 'activity', 'granted', '1.0', '2026-01-01 00:00:00', 'req-1')");
$pdo->exec("INSERT INTO learner_ai_consent_events VALUES ('c-2', '{$studentId}', 'skills', 'granted', '1.0', '2026-01-01 00:00:00', 'req-2')");
$pdo->exec("INSERT INTO learner_ai_consent_events VALUES ('c-3', '{$studentId}', 'assessment', 'granted', '1.0', '2026-01-01 00:00:00', 'req-3')");

// 3. Seed Verified Skill: data_analysis
$pdo->exec("INSERT INTO skills (id, code, name, category, status) VALUES ('sk-1', 'data_analysis', 'Phân tích dữ liệu', 'tech', 'active')");
$pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, updatedAt) VALUES ('ss-1', '{$studentId}', 'sk-1', 85.0, 'assessment', 'verified', '2026-02-01 00:00:00', '2026-02-01 00:00:00')");

// 4. Seed Holland Assessment (Investigative / Artistic / Social)
$pdo->exec("INSERT INTO talent_tests (id, code, type, status) VALUES ('t-1', 'HOLLAND', 'holland', 'published')");
$pdo->exec("INSERT INTO test_attempts (id, studentId, testId, status) VALUES ('att-1', '{$studentId}', 't-1', 'submitted')");
$pdo->exec("INSERT INTO test_results (id, attemptId, resultCode, dimensionScoresJson) VALUES ('res-1', 'att-1', 'IAS', '{\"I\":28,\"A\":24,\"S\":20,\"R\":10,\"E\":8,\"C\":6}')");
$pdo->exec("INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status, submittedAt) VALUES ('meta-1', 'att-1', 'v1', 'submitted', '2026-02-10 10:00:00')");
$pdo->exec("INSERT INTO learner_assessment_versions (id, version, scoringVersion, status, publishedAt) VALUES ('v1', '1.0', '1.0', 'published', '2026-01-01 00:00:00')");

// 5. Seed Registered Activity on Saturday 14:00-16:00 (Weekday 6)
$pdo->exec("INSERT INTO activities (id, schoolId, title, category, startAt, endAt, capacity, status) VALUES ('act-1', '{$schoolId}', 'Hội thảo AI', 'workshop', '2026-08-29 14:00:00', '2026-08-29 16:00:00', 50, 'published')");
$pdo->exec("INSERT INTO activity_registrations (id, activityId, studentId, status) VALUES ('reg-1', 'act-1', '{$studentId}', 'attended')");
$pdo->exec("INSERT INTO checkins (id, registrationId, status, confirmedAt) VALUES ('chk-1', 'reg-1', 'confirmed', '2026-08-29 14:05:00')");
$pdo->exec("INSERT INTO experience_logs (id, studentId, activityId, checkinId, hours, status, confirmedAt) VALUES ('exp-1', '{$studentId}', 'act-1', 'chk-1', 2.0, 'confirmed', '2026-08-29 16:00:00')");

// 6. Seed Candidates in learner_ai_catalog_items:
// Candidate 1: High Match Group (Skill + Holland + High Band + Compatible Schedule Sat 09:00-11:00)
$action1 = json_encode([
    'type' => 'join_group',
    'catalog_id' => 'grp-high-match',
    'match_profile' => [
        'skill_codes' => ['data_analysis'],
        'assessment_directions' => ['holland' => ['I', 'A']],
        'education_bands' => ['high'],
        'schedule_slots' => [
            ['weekday' => 6, 'start' => '09:00', 'end' => '11:00']
        ]
    ]
]);
$pdo->exec("INSERT INTO learner_ai_catalog_items VALUES ('grp-high-match', 'group', 'study_group', 'Nhóm Nghiên cứu Dữ liệu Trẻ', 'Nhóm học tập chuyên sâu', 'published', '2026-09-30 23:59:59', '[]', 20, 5, '/app/learner/groups.php?id=grp-high-match', '$action1', '{$schoolId}', '{$schoolId}', '2026-08-01 00:00:00')");

// Candidate 2: Schedule Conflict Group (Sat 14:30-16:30 conflicts with registered activity Sat 14:00-16:00)
$action2 = json_encode([
    'type' => 'join_group',
    'catalog_id' => 'grp-conflict',
    'match_profile' => [
        'skill_codes' => ['data_analysis'],
        'assessment_directions' => ['holland' => ['I']],
        'schedule_slots' => [
            ['weekday' => 6, 'start' => '14:30', 'end' => '16:30']
        ]
    ]
]);
$pdo->exec("INSERT INTO learner_ai_catalog_items VALUES ('grp-conflict', 'group', 'study_group', 'Nhóm Trùng Lịch', 'Trùng lịch thứ 7', 'published', '2026-09-30 23:59:59', '[]', 15, 2, '/app/learner/groups.php?id=grp-conflict', '$action2', '{$schoolId}', '{$schoolId}', '2026-08-01 00:00:00')");

// Candidate 3: Protected Trait in eligibility_json (Gender/Religion) -> Must be rejected
$action3 = json_encode([
    'type' => 'join_group',
    'catalog_id' => 'grp-protected',
    'match_profile' => [
        'skill_codes' => ['data_analysis'],
        'assessment_directions' => ['holland' => ['I']]
    ]
]);
$pdo->exec("INSERT INTO learner_ai_catalog_items VALUES ('grp-protected', 'group', 'study_group', 'Nhóm Phân biệt', 'Chứa protected trait', 'published', '2026-09-30 23:59:59', '{\"gender\":\"female\"}', 20, 1, '/app/learner/groups.php?id=grp-protected', '$action3', '{$schoolId}', '{$schoolId}', '2026-08-01 00:00:00')");

// Candidate 4: Draft candidate -> Must be excluded
$pdo->exec("INSERT INTO learner_ai_catalog_items VALUES ('grp-draft', 'group', 'study_group', 'Nhóm Bản nháp', 'Draft', 'draft', '2026-09-30 23:59:59', '[]', 20, 1, null, '$action1', '{$schoolId}', '{$schoolId}', '2026-08-01 00:00:00')");

// Candidate 5: Full candidate -> Must be excluded
$pdo->exec("INSERT INTO learner_ai_catalog_items VALUES ('grp-full', 'group', 'study_group', 'Nhóm Đã đầy', 'Full', 'published', '2026-09-30 23:59:59', '[]', 10, 10, null, '$action1', '{$schoolId}', '{$schoolId}', '2026-08-01 00:00:00')");

// Candidate 6: Expired candidate -> Must be excluded
$pdo->exec("INSERT INTO learner_ai_catalog_items VALUES ('grp-expired', 'group', 'study_group', 'Nhóm Hết hạn', 'Expired', 'published', '2026-08-01 00:00:00', '[]', 20, 1, null, '$action1', '{$schoolId}', '{$schoolId}', '2026-08-01 00:00:00')");

// Candidate 7: Wrong school candidate -> Must be excluded
$pdo->exec("INSERT INTO learner_ai_catalog_items VALUES ('grp-other-school', 'group', 'study_group', 'Nhóm Trường khác', 'Other school', 'published', '2026-09-30 23:59:59', '[]', 20, 1, null, '$action1', 'sch-2', 'sch-2', '2026-08-01 00:00:00')");

// Instantiate Service
$consentPolicy = new ConsentPolicy(new DatabaseConsentSource($pdo));
$catalogSource = new DatabaseCatalogSource($pdo);
$registry = AiSourceRegistry::fromLegacySources([
    new DatabaseStudentProfileSource($pdo),
    new DatabaseSkillSource($pdo),
    new DatabaseAssessmentSource($pdo),
    new DatabaseActivityExperienceSource($pdo),
    new DatabasePublishedEvaluationSource($pdo),
    new DatabaseOpportunitySource($pdo),
    new DatabaseCatalogSource($pdo),
]);
$registry->setTransactionPdo($pdo);
$snapshotBuilder = new RecommendationSnapshotBuilder($registry);
$educationBandResolver = new EducationBandResolver($pdo);
$service = new GroupMatchingService($pdo, $catalogSource, $consentPolicy, $snapshotBuilder, $educationBandResolver, null, $nowUtc);

// Test 1: Match execution and limit clamping
$matches = $service->match($studentId, 10);
group_assert(count($matches) <= 10, 'limit is enforced');
group_assert(count($matches) >= 1, 'at least one candidate matches');

// Test 2: First match has high score and evidence
$first = $matches[0];
group_assert($first['catalog_id'] === 'grp-high-match', 'best candidate is ranked first');
group_assert($first['score'] === 100, 'full dimensional overlap earns 100 score');
group_assert($first['analysis_origin'] === 'evidence_match', 'deterministic matching is labeled truthfully');
group_assert(!empty($first['evidence']), 'every match has database evidence');
group_assert(count($first['evidence']) >= 3, 'multiple evidence dimensions captured');

// Test 3: Protected traits are never evidence & protected candidate rejected
$allCatalogIds = array_column($matches, 'catalog_id');
group_assert(!in_array('grp-protected', $allCatalogIds, true), 'protected eligibility candidate is rejected');
group_assert(!in_array('grp-draft', $allCatalogIds, true), 'draft candidate is rejected');
group_assert(!in_array('grp-full', $allCatalogIds, true), 'full candidate is rejected');
group_assert(!in_array('grp-expired', $allCatalogIds, true), 'expired candidate is rejected');
group_assert(!in_array('grp-other-school', $allCatalogIds, true), 'cross-school candidate is rejected');

foreach ($first['evidence'] as $ev) {
    group_assert(($ev['source_type'] ?? '') !== 'protected_trait', 'protected traits are never evidence');
}

// Test 4: Conflict candidate has reduced score
$conflictMatch = null;
foreach ($matches as $m) {
    if ($m['catalog_id'] === 'grp-conflict') {
        $conflictMatch = $m;
        break;
    }
}
if ($conflictMatch !== null) {
    group_assert($conflictMatch['score'] < $first['score'], 'schedule conflict candidate has lower score');
}

// Test 5: Action payload contains safe stripped format
group_assert(isset($first['action']['type']) && $first['action']['type'] === 'join_group', 'action type normalized');
group_assert(isset($first['action']['catalog_id']) && $first['action']['catalog_id'] === 'grp-high-match', 'action catalog_id matches');
group_assert(!isset($first['action']['match_profile']), 'match_profile is stripped from client action');

// Test 6: Resolve Action (JIT validation)
$actionRes = $service->resolveAction($studentId, 'grp-high-match', 'join_group');
group_assert($actionRes['state'] === 'action_ready', 'available group resolves action_ready');
group_assert($actionRes['url'] === '/app/learner/groups.php?id=grp-high-match', 'safe relative URL returned');

$fullActionRes = $service->resolveAction($studentId, 'grp-full', 'join_group');
group_assert($fullActionRes['state'] === 'join_unavailable', 'full group resolves join_unavailable');
group_assert($fullActionRes['url'] === null, 'unavailable group has null url');

$nonexistentRes = $service->resolveAction($studentId, 'nonexistent-id', 'join_group');
group_assert($nonexistentRes['state'] === 'join_unavailable', 'nonexistent group resolves join_unavailable');

// Test 7: Consent revocation
$pdo->exec("INSERT INTO learner_ai_consent_events VALUES ('c-rev', '{$studentId}', 'activity', 'revoked', '1.0', '2026-08-28 12:00:00', 'req-rev')");
$revokedMatches = $service->match($studentId, 10);
group_assert($revokedMatches === [], 'revoked activity consent returns empty matches');

echo "learner_ai_group_matching_test: OK\n";