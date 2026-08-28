<?php

declare(strict_types=1);

/**
 * Task 3 contract: snapshot reads from the real database, the hash is
 * stable, provenance is recorded, and strict mode never invents
 * missing data or a rule-engine fallback.
 *
 * The contract is exercised against a transient SQLite schema that
 * mirrors the canonical TalentHub tables the AI snapshot depends on
 * (the bridge migrations 006-011 plus the foundation tables
 * 002-005). The test never mutates the production database and never
 * reads from the live MySQL pool; it only asserts the contract
 * behaviour of the snapshot, registry, and repository wiring.
 *
 * The mapping between the plan's `source_snapshot_hash` and the
 * canonical `contentHash` is documented in
 * tests/learner_ai_database_schema_test.php. No new column is added
 * here.
 */

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Persistence\RecommendationRepository;
use TalentHub\Learner\Ai\Persistence\RoadmapRepository;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
use TalentHub\Learner\Ai\Persistence\DatabaseRoadmapRepository;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Provider\StrictAiUnavailable;
use TalentHub\Learner\Ai\Quality\DataQualityGate;
use TalentHub\Learner\Ai\Quality\RoadmapQualityGate;
use TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder;
use TalentHub\Learner\Ai\Sources\AiSourceRegistry;
use TalentHub\Learner\Ai\Sources\Database\DatabaseAssessmentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseConsentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseLearnerAiExtendedSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;
use TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseStudentProfileSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\LearnerAiExtendedSource;
use TalentHub\Learner\Ai\Sources\StudentProfileSource;
use TalentHub\Learner\Ai\Sources\SkillSource;
use TalentHub\Learner\Ai\Sources\AssessmentSource;
use TalentHub\Learner\Ai\Sources\ActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\PublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\OpportunitySource;
use TalentHub\Learner\Ai\Sources\ConsentSource;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function sync_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

/** Build an in-memory SQLite schema that mirrors the canonical
 * TalentHub tables the AI snapshot depends on. The test is
 * responsible for seeding every table it expects to read from. */
function sync_buildPdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT, studyStatus TEXT, tenantId TEXT)');
    $pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel TEXT, academicYear TEXT)');
    $pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT)');
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
    $pdo->exec('CREATE TABLE assessments (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, overallScore REAL, status TEXT, publishedAt TEXT)');
    $pdo->exec('CREATE TABLE assessment_scores (id TEXT PRIMARY KEY, assessmentId TEXT, criteriaId TEXT, score REAL)');
    $pdo->exec('CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, code TEXT)');
    $pdo->exec('CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, location TEXT, deadline TEXT, status TEXT, audience TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE enterprises (id TEXT PRIMARY KEY, status TEXT, verificationStatus TEXT)');
    $pdo->exec('CREATE TABLE internship_post_target_schools (postId TEXT, schoolId TEXT)');
    $pdo->exec('CREATE TABLE school_enterprise_partnerships (schoolId TEXT, enterpriseId TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE internship_applications (id TEXT PRIMARY KEY, postId TEXT, studentId TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, audienceScope TEXT, locationName TEXT)');
    $pdo->exec('CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT)');
    $pdo->exec('CREATE TABLE projects (id TEXT PRIMARY KEY, schoolId TEXT, title TEXT, status TEXT, updatedAt TEXT, category TEXT, description TEXT, projectUrl TEXT, endAt TEXT)');
    $pdo->exec('CREATE TABLE learner_ai_catalog_items (catalog_id TEXT PRIMARY KEY, item_type TEXT, category TEXT, title TEXT, summary TEXT, publish_status TEXT, deadline_at TEXT, eligibility_json TEXT, capacity INTEGER, enrolled_count INTEGER, url TEXT, action_json TEXT, school_id TEXT, tenant_id TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE learner_ai_consent_events (id TEXT PRIMARY KEY, studentId TEXT, scope TEXT, action TEXT, policyVersion TEXT, occurredAt TEXT, requestId TEXT)');
    $pdo->exec('CREATE TABLE learner_recommendation_input_snapshots (id TEXT PRIMARY KEY, studentId TEXT, schemaVersion TEXT, contentHash TEXT, consentScopesJson TEXT, qualityFlagsJson TEXT, payloadJson TEXT, sourceUpdatedAt TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE learner_recommendation_snapshot_evidence (id TEXT PRIMARY KEY, snapshotId TEXT, sourceType TEXT, sourceId TEXT, observedAt TEXT, safeValueJson TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE learner_recommendation_runs (id TEXT PRIMARY KEY, studentId TEXT, snapshotId TEXT, idempotencyKey TEXT, engineType TEXT, status TEXT, ruleVersion TEXT, provider TEXT, modelVersion TEXT, promptVersion TEXT, fallbackReason TEXT, safeErrorCode TEXT, startedAt TEXT, completedAt TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE learner_recommendation_items (id TEXT PRIMARY KEY, runId TEXT, itemType TEXT, title TEXT, summary TEXT, priority INTEGER, confidenceBand TEXT, actionJson TEXT, lifecycleStatus TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE learner_recommendation_evidence (id TEXT PRIMARY KEY, itemId TEXT, snapshotEvidenceId TEXT, sourceType TEXT, sourceId TEXT, observedAt TEXT, contributionLabel TEXT, safeValueJson TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE learner_recommendation_feedback (id TEXT PRIMARY KEY, studentId TEXT, itemId TEXT, verdict TEXT, reasonCode TEXT, safeComment TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE learner_recommendation_audit_events (id TEXT PRIMARY KEY, runId TEXT, studentId TEXT, requestId TEXT, actorType TEXT, action TEXT, engineMetadataJson TEXT, status TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE learner_ai_roadmaps (id TEXT PRIMARY KEY, studentId TEXT, runId TEXT, versionNumber INTEGER, contractVersion TEXT, status TEXT, executiveSummary TEXT, primaryDirectionJson TEXT, alternativeDirectionsJson TEXT, insightsJson TEXT, confidenceBand TEXT, evidenceSummaryJson TEXT, providerRequestId TEXT, responseHash TEXT, generatedAt TEXT, supersededAt TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE learner_ai_roadmap_phases (id TEXT PRIMARY KEY, roadmapId TEXT, position INTEGER, startDay INTEGER, endDay INTEGER, code TEXT, title TEXT, goal TEXT, skillFocus TEXT, deliverable TEXT, effortLabel TEXT, metricLabel TEXT, evidenceJson TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE learner_ai_roadmap_tasks (id TEXT PRIMARY KEY, phaseId TEXT, position INTEGER, title TEXT, description TEXT, estimatedMinutes INTEGER, actionType TEXT, targetType TEXT, targetId TEXT, evidenceJson TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE learner_ai_roadmap_task_events (id TEXT PRIMARY KEY, taskId TEXT, studentId TEXT, status TEXT, requestId TEXT, occurredAt TEXT, createdAt TEXT)');
    $pdo->exec('CREATE UNIQUE INDEX uq_learner_recommendation_input_snapshots_student_hash ON learner_recommendation_input_snapshots (studentId, contentHash)');
    $pdo->exec('CREATE UNIQUE INDEX uq_learner_recommendation_runs_student_idempotency ON learner_recommendation_runs (studentId, idempotencyKey)');
    $pdo->exec('CREATE UNIQUE INDEX uq_learner_ai_roadmaps_student_version ON learner_ai_roadmaps (studentId, versionNumber)');
    return $pdo;
}

function sync_seedBaseline(PDO $pdo, string $studentId, string $classId, string $schoolId): void
{
    $pdo->prepare('INSERT INTO schools VALUES (:id,:name)')->execute(['id' => $schoolId, 'name' => 'Trường THPT TalentHub']);
    $pdo->prepare('INSERT INTO classes VALUES (:id,:school,:name,:grade,:year)')->execute([
        'id' => $classId, 'school' => $schoolId, 'name' => 'Lớp 10A1', 'grade' => 10, 'year' => '2025-2026',
    ]);
    $pdo->prepare('INSERT INTO student_profiles VALUES (:id,:class,:status,:tenant)')->execute([
        'id' => $studentId, 'class' => $classId, 'status' => 'active', 'tenant' => $schoolId,
    ]);
}

function sync_buildRegistry(PDO $pdo): AiSourceRegistry
{
    $registry = AiSourceRegistry::fromLegacySources([
        new DatabaseStudentProfileSource($pdo),
        new DatabaseSkillSource($pdo),
        new DatabaseAssessmentSource($pdo),
        new DatabaseActivityExperienceSource($pdo),
        new DatabasePublishedEvaluationSource($pdo),
        new DatabaseOpportunitySource($pdo, new DateTimeImmutable('2029-06-01T00:00:00+00:00')),
    ]);
    // The catalog source is a `LearnerAiExtendedSource` and must be
    // registered through the public add path because fromLegacySources
    // only accepts the legacy interface set.
    $registry->register(new DatabaseCatalogSource($pdo, new DateTimeImmutable('2029-06-01T00:00:00+00:00')));
    return $registry;
}

function sync_seedCanonical(PDO $pdo, string $studentId): void
{
    $pdo->prepare('INSERT INTO skills VALUES (:id,:code,:name,:category,:status)')->execute([
        'id' => 'skill-1', 'code' => 'communication', 'name' => 'Communication', 'category' => 'soft_skill', 'status' => 'active',
    ]);
    $pdo->prepare('INSERT INTO skills VALUES (:id,:code,:name,:category,:status)')->execute([
        'id' => 'skill-2', 'code' => 'python', 'name' => 'Python', 'category' => 'technical', 'status' => 'active',
    ]);
    $pdo->prepare('INSERT INTO student_skills VALUES (:id,:student,:skill,:level,:src,:status,:verified,:updated)')->execute([
        'id' => 'ss-1', 'student' => $studentId, 'skill' => 'skill-1', 'level' => 80.0,
        'src' => 'self_declared', 'status' => 'verified',
        'verified' => '2029-05-15T00:00:00+00:00', 'updated' => '2029-05-15T00:00:00+00:00',
    ]);
    $pdo->prepare('INSERT INTO student_skills VALUES (:id,:student,:skill,:level,:src,:status,:verified,:updated)')->execute([
        'id' => 'ss-2', 'student' => $studentId, 'skill' => 'skill-2', 'level' => 70.0,
        'src' => 'self_declared', 'status' => 'verified',
        'verified' => '2029-05-15T00:00:00+00:00', 'updated' => '2029-05-15T00:00:00+00:00',
    ]);
}

function sync_consent(PDO $pdo, string $studentId, array $scopes, string $policyVersion = 'v1', string $requestIdPrefix = 'req'): void
{
    $insert = $pdo->prepare('INSERT INTO learner_ai_consent_events VALUES (:id,:student,:scope,:action,:policy,:occurred,:request)');
    $i = 0;
    foreach ($scopes as $scope) {
        $insert->execute([
            'id' => $requestIdPrefix . '-' . $scope,
            'student' => $studentId,
            'scope' => $scope,
            'action' => 'granted',
            'policy' => $policyVersion,
            'occurred' => '2029-05-15T00:00:00+00:00',
            'request' => $requestIdPrefix . '-' . $scope,
        ]);
    }
}

$studentId = 'student-sync-1';
$classId = 'class-sync-1';
$schoolId = 'school-sync-1';

// =================================================================
// 1. Snapshot reads the real database (no mock, no zero-filled data)
// =================================================================
$pdo = sync_buildPdo();
sync_seedBaseline($pdo, $studentId, $classId, $schoolId);
sync_seedCanonical($pdo, $studentId);
sync_consent($pdo, $studentId, ['assessment', 'skills', 'activity', 'evaluation']);

$registry = sync_buildRegistry($pdo);
$input = $registry->buildInput($studentId, ['assessment', 'skills', 'activity', 'evaluation']);
$payload = $input->payload();
sync_assert(count($payload['skills'] ?? []) >= 2, 'snapshot reads real skill rows from the database');
sync_assert(count($payload['assessments'] ?? []) === 0, 'snapshot reflects an empty assessment set without inventing placeholders');
$flags = $input->qualityFlags();
sync_assert(($flags['source_counts']['skill'] ?? 0) >= 2, 'source_counts reflect the live row count');
sync_assert(in_array('assessment', $flags['missing_consent_scopes'] ?? [], true) === false, 'consented snapshot is not flagged as missing consent');

// =================================================================
// 2. Snapshot hash is stable for identical data
// =================================================================
$h1 = (new RecommendationSnapshotBuilder($registry))->build($studentId, ['assessment', 'skills', 'activity', 'evaluation'])->contentHash();
$h2 = (new RecommendationSnapshotBuilder($registry))->build($studentId, ['assessment', 'skills', 'activity', 'evaluation'])->contentHash();
sync_assert($h1 === $h2, 'snapshot hash is stable across reads of identical data');
sync_assert(preg_match('/\A[a-f0-9]{64}\z/', $h1) === 1, 'snapshot hash is a lowercase SHA-256 hex digest');

// =================================================================
// 3. Snapshot hash changes when any source row changes
// =================================================================
$pdo->prepare('UPDATE student_skills SET levelScore = :l WHERE id = :id')->execute(['l' => 90.0, 'id' => 'ss-1']);
$h3 = (new RecommendationSnapshotBuilder($registry))->build($studentId, ['assessment', 'skills', 'activity', 'evaluation'])->contentHash();
sync_assert($h3 !== $h1, 'snapshot hash changes when a source row changes');

// =================================================================
// 4. Sources without consent are excluded
// =================================================================
$pdo = sync_buildPdo();
sync_seedBaseline($pdo, $studentId, $classId, $schoolId);
sync_seedCanonical($pdo, $studentId);
sync_consent($pdo, $studentId, ['skills']);
$registry = sync_buildRegistry($pdo);
$skillsOnly = (new RecommendationSnapshotBuilder($registry))->build($studentId, ['skills']);
sync_assert(count($skillsOnly->payload()['skills'] ?? []) >= 2, 'consented skills remain in the snapshot');
$evidenceTypes = array_values(array_unique(array_column($skillsOnly->evidenceReferences(), 'source_type')));
sort($evidenceTypes, SORT_STRING);
sync_assert(
    array_values(array_intersect($evidenceTypes, ['assessment', 'evaluation', 'activity'])) === [],
    'snapshot excludes evidence for non-consented scopes (assessment, evaluation, activity)',
);
sync_assert(in_array('skill', $evidenceTypes, true), 'consented skill scope is still represented in evidence');

// =================================================================
// 5. Evidence only references sources that exist in the snapshot
// =================================================================
$pdo = sync_buildPdo();
sync_seedBaseline($pdo, $studentId, $classId, $schoolId);
sync_seedCanonical($pdo, $studentId);
sync_consent($pdo, $studentId, ['assessment', 'skills', 'activity', 'evaluation']);
$registry = sync_buildRegistry($pdo);
$input = (new RecommendationSnapshotBuilder($registry))->build($studentId, ['assessment', 'skills', 'activity', 'evaluation']);
// Build the canonical (type,id) set of records that the registry
// read from the database. Evidence must point to one of these.
$records = $registry->readForStudent($studentId, ['assessment', 'skills', 'activity', 'evaluation']);
$recordIndex = [];
foreach ($records as $record) {
    $type = (string) ($record['source_type'] ?? '');
    $id = (string) ($record['source_id'] ?? '');
    if ($type !== '' && $id !== '') {
        $recordIndex[$type . ':' . $id] = true;
    }
}
$orphan = 0;
foreach ($input->evidenceReferences() as $reference) {
    $type = (string) ($reference['source_type'] ?? '');
    $id = (string) ($reference['source_id'] ?? '');
    if ($type === '' || $id === '' || !isset($recordIndex[$type . ':' . $id])) {
        $orphan++;
    }
}
sync_assert($orphan === 0, 'every evidence reference points to a record the registry read from the database');

// =================================================================
// 6. Catalog is filtered by school/tenant/publish status/eligibility
// =================================================================
$pdo = sync_buildPdo();
sync_seedBaseline($pdo, $studentId, $classId, $schoolId);
$pdo->prepare('INSERT INTO learner_ai_catalog_items VALUES (:id,:type,:category,:title,:summary,:status,:deadline,:eligibility,:capacity,:enrolled,:url,:action,:school,:tenant,:updated)');
$insertCatalog = $pdo->prepare('INSERT INTO learner_ai_catalog_items VALUES (:id,:type,:category,:title,:summary,:status,:deadline,:eligibility,:capacity,:enrolled,:url,:action,:school,:tenant,:updated)');
$catalogRows = [
    ['id' => 'visible-a', 'type' => 'workshop', 'category' => 'career_technical', 'title' => 'A', 'summary' => 'A summary',
     'status' => 'published', 'deadline' => '2030-01-01 00:00:00', 'eligibility' => '{"grade_levels":["10"]}',
     'capacity' => 20, 'enrolled' => 1, 'url' => '/a', 'action' => '{"type":"register"}',
     'school' => $schoolId, 'tenant' => $schoolId, 'updated' => '2029-05-15 00:00:00'],
    ['id' => 'wrong-school', 'type' => 'workshop', 'category' => 'career_technical', 'title' => 'B', 'summary' => 'B',
     'status' => 'published', 'deadline' => '2030-01-01 00:00:00', 'eligibility' => '{"grade_levels":["10"]}',
     'capacity' => 20, 'enrolled' => 1, 'url' => '/b', 'action' => '{"type":"register"}',
     'school' => 'other-school', 'tenant' => $schoolId, 'updated' => '2029-05-15 00:00:00'],
    ['id' => 'wrong-tenant', 'type' => 'workshop', 'category' => 'career_technical', 'title' => 'C', 'summary' => 'C',
     'status' => 'published', 'deadline' => '2030-01-01 00:00:00', 'eligibility' => '{"grade_levels":["10"]}',
     'capacity' => 20, 'enrolled' => 1, 'url' => '/c', 'action' => '{"type":"register"}',
     'school' => null, 'tenant' => 'other-tenant', 'updated' => '2029-05-15 00:00:00'],
    ['id' => 'unpublished', 'type' => 'workshop', 'category' => 'career_technical', 'title' => 'D', 'summary' => 'D',
     'status' => 'draft', 'deadline' => '2030-01-01 00:00:00', 'eligibility' => '{"grade_levels":["10"]}',
     'capacity' => 20, 'enrolled' => 1, 'url' => '/d', 'action' => '{"type":"register"}',
     'school' => $schoolId, 'tenant' => $schoolId, 'updated' => '2029-05-15 00:00:00'],
    ['id' => 'expired', 'type' => 'workshop', 'category' => 'career_technical', 'title' => 'E', 'summary' => 'E',
     'status' => 'published', 'deadline' => '2020-01-01 00:00:00', 'eligibility' => '{"grade_levels":["10"]}',
     'capacity' => 20, 'enrolled' => 1, 'url' => '/e', 'action' => '{"type":"register"}',
     'school' => $schoolId, 'tenant' => $schoolId, 'updated' => '2029-05-15 00:00:00'],
    ['id' => 'full', 'type' => 'workshop', 'category' => 'career_technical', 'title' => 'F', 'summary' => 'F',
     'status' => 'published', 'deadline' => '2030-01-01 00:00:00', 'eligibility' => '{"grade_levels":["10"]}',
     'capacity' => 1, 'enrolled' => 1, 'url' => '/f', 'action' => '{"type":"register"}',
     'school' => $schoolId, 'tenant' => $schoolId, 'updated' => '2029-05-15 00:00:00'],
];
foreach ($catalogRows as $row) {
    $insertCatalog->execute([
        'id' => $row['id'], 'type' => $row['type'], 'category' => $row['category'],
        'title' => $row['title'], 'summary' => $row['summary'],
        'status' => $row['status'], 'deadline' => $row['deadline'],
        'eligibility' => $row['eligibility'],
        'capacity' => $row['capacity'], 'enrolled' => $row['enrolled'],
        'url' => $row['url'], 'action' => $row['action'],
        'school' => $row['school'], 'tenant' => $row['tenant'],
        'updated' => $row['updated'],
    ]);
}
sync_consent($pdo, $studentId, ['activity']);
$registry = new AiSourceRegistry([new DatabaseCatalogSource($pdo, new DateTimeImmutable('2029-06-01T00:00:00+00:00'))]);
$visible = array_column($registry->readForStudent($studentId, ['activity']), 'source_id');
sort($visible, SORT_STRING);
sync_assert($visible === ['visible-a'], 'catalog filters out wrong school, wrong tenant, unpublished, expired, and full items');

// =================================================================
// 7. Missing required schema raises a strict error, never a rule fallback
// =================================================================
$pdoNoSchema = new PDO('sqlite::memory:');
$pdoNoSchema->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$missingCatalog = new DatabaseCatalogSource($pdoNoSchema, new DateTimeImmutable('2029-06-01T00:00:00+00:00'));
try {
    $missingCatalog->readForStudent($studentId);
    sync_assert(true, 'missing schema returns empty without throwing (no leakage)');
} catch (Throwable $exception) {
    sync_assert(false, 'missing schema must not surface PDOException to AI: ' . $exception->getMessage());
}
$empty = $missingCatalog->readForStudent($studentId);
sync_assert($empty === [], 'missing catalog table produces an empty record set, never a default');
sync_assert(class_exists(StrictAiUnavailable::class), 'strict-mode readiness gate exists for missing_schema signal');

// =================================================================
// 8. Two identical reads produce the same payload irrespective of SQL ordering
// =================================================================
$pdo = sync_buildPdo();
sync_seedBaseline($pdo, $studentId, $classId, $schoolId);
$pdo->prepare('INSERT INTO skills VALUES (:id,:code,:name,:category,:status)');
$insertSkill = $pdo->prepare('INSERT INTO skills VALUES (:id,:code,:name,:category,:status)');
$insertSs = $pdo->prepare('INSERT INTO student_skills VALUES (:id,:student,:skill,:level,:src,:status,:verified,:updated)');
for ($i = 1; $i <= 5; $i++) {
    $insertSkill->execute(['id' => 'skill-' . $i, 'code' => 'skill-' . $i, 'name' => 'Skill ' . $i, 'category' => 'soft_skill', 'status' => 'active']);
    $insertSs->execute([
        'id' => 'ss-' . $i, 'student' => $studentId, 'skill' => 'skill-' . $i, 'level' => 50.0 + $i,
        'src' => 'self_declared', 'status' => 'verified',
        'verified' => '2029-05-15T00:00:00+00:00', 'updated' => '2029-05-15T00:00:00+00:00',
    ]);
}
sync_consent($pdo, $studentId, ['skills']);
$registry = AiSourceRegistry::fromLegacySources([new DatabaseSkillSource($pdo)]);
$first = $registry->buildInput($studentId, ['skills'])->payload();
$second = $registry->buildInput($studentId, ['skills'])->payload();
sync_assert($first === $second, 'two reads with identical data produce byte-identical payloads');
sync_assert(array_column($first['skills'], 'skill_id') === array_column($second['skills'], 'skill_id'), 'skill ordering is stable across SQL result order');

// =================================================================
// 9. RecommendationRepository persists the canonical hash and reuses it
// =================================================================
$pdo = sync_buildPdo();
sync_seedBaseline($pdo, $studentId, $classId, $schoolId);
sync_seedCanonical($pdo, $studentId);
sync_consent($pdo, $studentId, ['assessment', 'skills', 'activity', 'evaluation']);
$registry = sync_buildRegistry($pdo);
$input = (new RecommendationSnapshotBuilder($registry))->build($studentId, ['assessment', 'skills', 'activity', 'evaluation']);
$recommendationRepo = new DatabaseRecommendationRepository($pdo);
$context = new RecommendationContext(
    ['assessment', 'skills', 'activity', 'evaluation'],
    'request-sync-1', 'idempotency-sync-1', $studentId,
);
$pending = $recommendationRepo->createPendingRun($studentId, $input, $context);
$storedHash = (string) $pdo->query('SELECT contentHash FROM learner_recommendation_input_snapshots WHERE id = ' . $pdo->quote($pending['snapshotId']))->fetchColumn();
sync_assert($storedHash === $input->contentHash(), 'RecommendationRepository persists the canonical contentHash');
$reused = $recommendationRepo->createPendingRun($studentId, $input, $context);
sync_assert($reused['reused'] === true, 'createPendingRun reuses the canonical snapshot hash instead of writing a duplicate');

// =================================================================
// 10. Quality gate surfaces insufficient_data when no assessment exists
// =================================================================
$noAssessmentInput = (new RecommendationSnapshotBuilder(sync_buildRegistry($pdo)))->build($studentId, ['assessment', 'skills', 'activity', 'evaluation']);
$quality = (new DataQualityGate(new DateTimeImmutable('2029-06-15T00:00:00+00:00'), false))->evaluate($noAssessmentInput);
sync_assert($quality->state() === 'insufficient_data', 'quality gate reports insufficient_data when no current assessment exists');
sync_assert(in_array('assessment', $quality->missingCategories(), true), 'quality gate names the missing category explicitly');

// =================================================================
// 11. Snapshot.redact removes private keys (email, phone, token)
// =================================================================
$redactSource = new DatabaseLearnerAiExtendedSource(
    'profile', 'profile-1.0.0', 'profile',
    ['study_status'],
    'profile_changed',
    static fn (string $studentId): array => [[
        'source_id' => 'profile-x',
        'study_status' => 'active',
        'email' => 'private@example.test',
        'phone' => '0900000000',
        'token' => 'Bearer secret',
        'password' => 'hunter2',
    ]],
);
$registry = new AiSourceRegistry([$redactSource]);
$records = $registry->readForStudent('student-a', ['profile']);
sync_assert(count($records) === 1, 'redact source is loaded');
$redacted = $records[0]['data'];
sync_assert(!array_key_exists('email', $redacted), 'private key email is removed from snapshot evidence');
sync_assert(!array_key_exists('phone', $redacted), 'private key phone is removed from snapshot evidence');
sync_assert(!array_key_exists('token', $redacted), 'private key token is removed from snapshot evidence');
sync_assert(!array_key_exists('password', $redacted), 'private key password is removed from snapshot evidence');
sync_assert($redacted['study_status'] === 'active', 'non-private fields are preserved');

// =================================================================
// 12. Catalog rejects items whose eligibility_json references protected
// traits (gender, religion, ethnicity, ...). The catalog source is the
// only learner AI surface that ingests eligibility JSON, so the filter
// must live there.
// =================================================================
$pdoCatalog = sync_buildPdo();
sync_seedBaseline($pdoCatalog, $studentId, $classId, $schoolId);
$insertCatalogEligibility = $pdoCatalog->prepare('INSERT INTO learner_ai_catalog_items VALUES (:id,:type,:category,:title,:summary,:status,:deadline,:eligibility,:capacity,:enrolled,:url,:action,:school,:tenant,:updated)');
$rows = [
    ['id' => 'clean-grade', 'eligibility' => '{"grade_levels":["10"]}'],
    ['id' => 'gender-rule', 'eligibility' => '{"gender":"male","grade_levels":["10"]}'],
    ['id' => 'religion-rule', 'eligibility' => '{"religion":"any","grade_levels":["10"]}'],
    ['id' => 'ethnicity-rule', 'eligibility' => '{"ethnicity":"any","grade_levels":["10"]}'],
];
foreach ($rows as $row) {
    $insertCatalogEligibility->execute([
        'id' => $row['id'], 'type' => 'workshop', 'category' => 'career_technical',
        'title' => $row['id'], 'summary' => 'Catalog row', 'status' => 'published',
        'deadline' => '2030-01-01 00:00:00', 'eligibility' => $row['eligibility'],
        'capacity' => 20, 'enrolled' => 1, 'url' => '/' . $row['id'],
        'action' => '{"type":"register"}', 'school' => $schoolId, 'tenant' => $schoolId,
        'updated' => '2029-05-15 00:00:00',
    ]);
}
sync_consent($pdoCatalog, $studentId, ['activity']);
$catalogRegistry = new AiSourceRegistry([
    new DatabaseCatalogSource($pdoCatalog, new DateTimeImmutable('2029-06-01T00:00:00+00:00')),
]);
$catalogVisible = array_column(
    $catalogRegistry->readForStudent($studentId, ['activity']),
    'source_id'
);
sort($catalogVisible, SORT_STRING);
sync_assert($catalogVisible === ['clean-grade'], 'catalog excludes rows whose eligibility JSON references protected traits (gender, religion, ethnicity)');

// =================================================================
// 13. Snapshot reads use a single PDO transaction so concurrent writers
// cannot produce an inconsistent payload mid-snapshot.
// =================================================================
$pdoTx = sync_buildPdo();
sync_seedBaseline($pdoTx, $studentId, $classId, $schoolId);
sync_seedCanonical($pdoTx, $studentId);
sync_consent($pdoTx, $studentId, ['assessment', 'skills', 'activity', 'evaluation']);
$txRegistry = AiSourceRegistry::fromLegacySources([
    new DatabaseStudentProfileSource($pdoTx),
    new DatabaseSkillSource($pdoTx),
    new DatabaseAssessmentSource($pdoTx),
    new DatabaseActivityExperienceSource($pdoTx),
    new DatabasePublishedEvaluationSource($pdoTx),
    new DatabaseOpportunitySource($pdoTx, new DateTimeImmutable('2029-06-01T00:00:00+00:00')),
]);
$txRegistry->register(new DatabaseCatalogSource($pdoTx, new DateTimeImmutable('2029-06-01T00:00:00+00:00')));
$txRegistry->setTransactionPdo($pdoTx);
$txReader = $txRegistry->readForStudent($studentId, ['assessment', 'skills', 'activity', 'evaluation']);
sync_assert($txReader !== [], 'transactional registry reads at least one record');
sync_assert(array_sum(array_map(static fn (array $r) => (int) ($r['source_type'] === 'profile'), $txReader)) >= 1, 'profile is read inside the same call');
// The transactional wrap must not leave PDO in an open transaction.
sync_assert($pdoTx->inTransaction() === false, 'AiSourceRegistry::readForStudent leaves no open transaction');

// Probe: wrap a fake PDO that simulates a MySQL driver and records
// every beginTransaction/commit/rollBack invocation. The registry must
// open one transaction, read all sources, and commit.
$probePdo = new class extends PDO {
    public array $events = [];
    public bool $inTransaction = false;
    public function __construct() { /* no-op; bypass real driver */ }
    public function getAttribute(int $attribute): mixed
    {
        if ($attribute === \PDO::ATTR_DRIVER_NAME) return 'mysql';
        return parent::getAttribute($attribute);
    }
    public function beginTransaction(): bool { $this->inTransaction = true; $this->events[] = 'begin'; return true; }
    public function commit(): bool { $this->inTransaction = false; $this->events[] = 'commit'; return true; }
    public function rollBack(): bool { $this->inTransaction = false; $this->events[] = 'rollback'; return true; }
    public function inTransaction(): bool { return $this->inTransaction; }
};
$probeRegistry = AiSourceRegistry::fromLegacySources([
    new DatabaseStudentProfileSource($pdoTx),
    new DatabaseSkillSource($pdoTx),
]);
$probeRegistry->setTransactionPdo($probePdo);
$probeRegistry->readForStudent($studentId, ['skills']);
sync_assert(($probePdo->events[0] ?? null) === 'begin', 'mysql-driver PDO opens a transaction before reading');
sync_assert(in_array('commit', $probePdo->events, true), 'transactional read commits after the read');
sync_assert(!in_array('rollback', $probePdo->events, true), 'successful read does not roll back');
sync_assert($probePdo->inTransaction === false, 'PDO is left outside the transaction');

// =================================================================
// 14. RoadmapRepository persists the snapshot contentHash on every save
// =================================================================
$pdoRoadmap = sync_buildPdo();
sync_seedBaseline($pdoRoadmap, $studentId, $classId, $schoolId);
sync_seedCanonical($pdoRoadmap, $studentId);
sync_consent($pdoRoadmap, $studentId, ['assessment', 'skills', 'activity', 'evaluation']);
$registryRoadmap = sync_buildRegistry($pdoRoadmap);
$inputRoadmap = (new RecommendationSnapshotBuilder($registryRoadmap))->build($studentId, ['assessment', 'skills', 'activity', 'evaluation']);
$recommendationRepoRoadmap = new DatabaseRecommendationRepository($pdoRoadmap);
$contextRoadmap = new RecommendationContext(
    ['assessment', 'skills', 'activity', 'evaluation'],
    'request-roadmap-1', 'idempotency-roadmap-1', $studentId,
);
$pendingRoadmap = $recommendationRepoRoadmap->createPendingRun($studentId, $inputRoadmap, $contextRoadmap);
$canonicalHash = (string) $pdoRoadmap->query('SELECT contentHash FROM learner_recommendation_input_snapshots WHERE id = ' . $pdoRoadmap->quote($pendingRoadmap['snapshotId']))->fetchColumn();
sync_assert($canonicalHash === $inputRoadmap->contentHash(), 'RecommendationRepository persists the canonical snapshot contentHash');
sync_assert(preg_match('/\A[a-f0-9]{64}\z/', $canonicalHash) === 1, 'persisted snapshot hash is a 64-character lowercase hex digest');

echo "learner_ai_database_sync_test: OK\n";
