<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Consent\ProviderConsentGate;
use TalentHub\Learner\Ai\Contracts\RoadmapProvider;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Model\ModelRoadmapEngine;
use TalentHub\Learner\Ai\Model\RoadmapPromptRegistry;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
use TalentHub\Learner\Ai\Persistence\DatabaseRoadmapRepository;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Provider\ProviderRequest;
use TalentHub\Learner\Ai\Provider\RoadmapProviderResponse;
use TalentHub\Learner\Ai\Quality\RoadmapQualityGate;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Rules\RuleRoadmapEngine;
use TalentHub\Learner\Ai\Service\RoadmapService;
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

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function e2e_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function e2e_create_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, studyStatus TEXT, tenantId TEXT)');
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
    $pdo->exec('CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, audienceScope TEXT, filterCategory TEXT, locationName TEXT)');
    $pdo->exec('CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT)');
    $pdo->exec('CREATE TABLE assessments (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, overallScore REAL, status TEXT, publishedAt TEXT)');
    $pdo->exec('CREATE TABLE assessment_scores (id TEXT PRIMARY KEY, assessmentId TEXT, criteriaId TEXT, score REAL)');
    $pdo->exec('CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, code TEXT)');
    $pdo->exec('CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, location TEXT, deadline TEXT, status TEXT, audience TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE enterprises (id TEXT PRIMARY KEY, status TEXT, verificationStatus TEXT)');
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
    $pdo->exec('CREATE TABLE learner_ai_data_outbox (id TEXT PRIMARY KEY, aggregate_type TEXT, aggregate_id TEXT, tenant_id TEXT, event_type TEXT, aggregate_version INTEGER, payload_hash TEXT, affected_student_ids TEXT, delivery_status TEXT, occurred_at TEXT)');

    return $pdo;
}

final class FakeRoadmapTestProvider implements RoadmapProvider
{
    private bool $shouldFail = false;

    public function __construct(private array $payload)
    {
    }

    public function setFail(bool $fail): void
    {
        $this->shouldFail = $fail;
    }

    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): RoadmapProviderResponse
    {
        $authorizer->beforeAttempt(1);
        if ($this->shouldFail) {
            return RoadmapProviderResponse::failure('provider_unavailable');
        }
        return RoadmapProviderResponse::success(
            $this->payload,
            'prov-req-e2e-001',
            hash('sha256', json_encode($this->payload)),
        );
    }
}

// -------------------------------------------------------------
// Setup database and fixtures
// -------------------------------------------------------------
$pdo = e2e_create_pdo();

$studentId = 'student-e2e-0000000000000001';
$schoolId = 'school-e2e-001';
$classId = 'class-e2e-001';

$pdo->exec("INSERT INTO schools (id, name) VALUES ('{$schoolId}', 'THPT Chuyên Thăng Long')");
$pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES ('{$classId}', '{$schoolId}', '10A1', '10', '2026-2027')");
$pdo->exec("INSERT INTO student_profiles (id, userId, classId, studyStatus, tenantId) VALUES ('{$studentId}', 'user-e2e-1', '{$classId}', 'active', 'tenant-1')");

// Consent granted for 4 scopes
foreach (['assessment', 'skills', 'activity', 'evaluation'] as $scope) {
    $pdo->exec("INSERT INTO learner_ai_consent_events (id, studentId, scope, action, policyVersion, occurredAt, requestId) VALUES ('consent-{$scope}', '{$studentId}', '{$scope}', 'granted', 'v1', '2026-08-20T00:00:00Z', 'req-consent')");
}

// 4 Assessment families: holland, mbti, disc, multiple_intelligence
$assessments = [
    'test-holland' => ['type' => 'holland', 'code' => 'HOLLAND', 'scores' => '{"R":80,"I":75,"A":60,"S":50,"E":45,"C":40}'],
    'test-mbti' => ['type' => 'mbti', 'code' => 'MBTI', 'scores' => '{"E":30,"I":70,"S":20,"N":80,"T":75,"F":25,"J":85,"P":15}'],
    'test-disc' => ['type' => 'disc', 'code' => 'DISC', 'scores' => '{"D":60,"I":40,"S":50,"C":70}'],
    'test-mi' => ['type' => 'multiple_intelligence', 'code' => 'MI', 'scores' => '{"logical":85,"linguistic":70,"spatial":60,"musical":40,"bodily":50,"interpersonal":65,"intrapersonal":80,"naturalist":45}'],
];

$pdo->exec("INSERT INTO learner_assessment_versions (id, version, scoringVersion, status, publishedAt) VALUES ('ver-1', '1.0', '1.0', 'published', '2026-01-01T00:00:00Z')");
foreach ($assessments as $testId => $meta) {
    $pdo->exec("INSERT INTO talent_tests (id, code, type, status) VALUES ('{$testId}', '{$meta['code']}', '{$meta['type']}', 'published')");
    $attemptId = "att-{$testId}";
    $pdo->exec("INSERT INTO test_attempts (id, studentId, testId, status) VALUES ('{$attemptId}', '{$studentId}', '{$testId}', 'submitted')");
    $pdo->exec("INSERT INTO test_results (id, attemptId, resultCode, dimensionScoresJson) VALUES ('res-{$testId}', '{$attemptId}', 'HIGH', '{$meta['scores']}')");
    $pdo->exec("INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status, submittedAt) VALUES ('meta-{$testId}', '{$attemptId}', 'ver-1', 'submitted', '2026-08-20T10:00:00Z')");
}

// Verified skills
$pdo->exec("INSERT INTO skills (id, code, name, category, status) VALUES ('skill-comm', 'communication', 'Kỹ năng giao tiếp', 'soft_skills', 'active')");
$pdo->exec("INSERT INTO skills (id, code, name, category, status) VALUES ('skill-py', 'python', 'Lập trình Python', 'technical', 'active')");
$pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, updatedAt) VALUES ('ss-1', '{$studentId}', 'skill-comm', 80.0, 'school', 'verified', '2026-08-20T00:00:00Z', '2026-08-20T00:00:00Z')");
$pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, updatedAt) VALUES ('ss-2', '{$studentId}', 'skill-py', 75.0, 'school', 'verified', '2026-08-20T00:00:00Z', '2026-08-20T00:00:00Z')");

// Confirmed check-in & experience
$actId1 = '00000000-0000-4000-8000-000000000101';
$futureStart = gmdate('Y-m-d H:i:s', time() + 86400 * 5);
$futureEnd = gmdate('Y-m-d H:i:s', time() + 86400 * 6);
$regOpens = gmdate('Y-m-d H:i:s', time() - 86400);
$regCloses = gmdate('Y-m-d H:i:s', time() + 86400 * 4);
$pdo->exec("INSERT INTO activities (id, schoolId, title, category, startAt, endAt, capacity, status) VALUES ('{$actId1}', '{$schoolId}', 'Hội thảo Công nghệ AI', 'workshop', '{$futureStart}', '{$futureEnd}', 50, 'published')");
$pdo->exec("INSERT INTO activity_details (activityId, audienceScope, filterCategory, locationName) VALUES ('{$actId1}', 'school_only', 'workshop', 'Hội trường A')");
$pdo->exec("INSERT INTO activity_registration_policies (activityId, registrationOpensAt, registrationClosesAt) VALUES ('{$actId1}', '{$regOpens}', '{$regCloses}')");

// Opportunity / Catalog
$pdo->exec("INSERT INTO learner_ai_catalog_items (catalog_id, item_type, category, title, summary, publish_status, deadline_at, eligibility_json, capacity, enrolled_count, url, action_json, school_id, tenant_id, updated_at) VALUES ('{$actId1}', 'activity', 'workshop', 'Hội thảo Công nghệ AI', 'Hội thảo chuyên sâu về AI', 'published', '2026-08-31 23:59:59', '[]', 50, 10, '', '{\"type\":\"register_activity\",\"activity_id\":\"{$actId1}\"}', '{$schoolId}', 'tenant-1', '2026-08-20T00:00:00Z')");

// Provider payload
$mockPayload = [
    'executive_summary' => 'Học viên thể hiện tiềm năng vượt trội trong tư duy logic, phân tích hệ thống và kỹ năng lập trình.',
    'primary_direction' => [
        'code' => 'software_engineer',
        'label' => 'Kỹ sư phần mềm và Trí tuệ nhân tạo',
        'rationale' => 'Kết hợp mạnh mẽ giữa tư duy logic và xu hướng khám phá công nghệ.',
    ],
    'alternative_directions' => [
        [
            'code' => 'data_scientist',
            'label' => 'Chuyên viên Khoa học dữ liệu',
            'rationale' => 'Năng khiếu toán học và khả năng phân tích dữ liệu chuyên sâu.',
        ],
        [
            'code' => 'product_manager',
            'label' => 'Quản lý Sản phẩm Công nghệ',
            'rationale' => 'Khả năng tổ chức, điều phối và kết nối liên ngành tốt.',
        ],
    ],
    'insights' => [
        [
            'category' => 'strength',
            'title' => 'Tư duy phân tích và giải quyết vấn đề',
            'summary' => 'Học viên giải quyết các bài toán logic một cách có hệ thống và chặt chẽ.',
            'evidence_ref_ids' => ['evidence-001'],
        ],
        [
            'category' => 'improvement',
            'title' => 'Kỹ năng trình bày và thuyết phục',
            'summary' => 'Cần tích cực rèn luyện thuyết trình ý tưởng dự án trước đám đông.',
            'evidence_ref_ids' => ['evidence-001'],
        ],
        [
            'category' => 'potential',
            'title' => 'Tiềm năng dẫn dắt dự án học tập',
            'summary' => 'Có triển vọng điều phối nhóm hoàn thành các dự án phức tạp.',
            'evidence_ref_ids' => ['evidence-001'],
        ],
    ],
    'talent_map' => [
        ['field' => 'Tư duy Logic & Hệ thống', 'score' => 0.85, 'evidence_ref_ids' => ['evidence-001']],
        ['field' => 'Kỹ năng Thực hành & Thao tác', 'score' => 0.75, 'evidence_ref_ids' => ['evidence-001']],
        ['field' => 'Tổ chức & Điều phối', 'score' => 0.65, 'evidence_ref_ids' => ['evidence-001']],
    ],
    'recommended_activity_source_ids' => [$actId1],
    'phases' => [
        [
            'position' => 1,
            'start_day' => 0,
            'end_day' => 30,
            'code' => 'discover',
            'title' => 'Khám phá và xây dựng nền tảng',
            'goal' => 'Nắm vững kiến thức nền tảng về thuật toán và cấu trúc dữ liệu.',
            'skill_focus' => 'Lập trình cơ bản và tư duy thuật toán',
            'deliverable' => 'Hoàn thành 10 bài tập thuật toán cơ bản',
            'effort_label' => 'Khoảng 4–6 giờ mỗi tuần',
            'metric_label' => 'Số bài tập hoàn thành trên hệ thống',
            'evidence_ref_ids' => ['evidence-001'],
            'tasks' => [
                ['position' => 1, 'title' => 'Ôn tập cú pháp Python nâng cao', 'description' => 'Luyện tập các hàm và module cơ bản.', 'estimated_minutes' => 60, 'action' => ['type' => 'self_task'], 'evidence_ref_ids' => ['evidence-001']],
                ['position' => 2, 'title' => 'Tìm hiểu cấu trúc danh sách liên kết', 'description' => 'Đọc tài liệu và cài đặt cấu trúc dữ liệu.', 'estimated_minutes' => 90, 'action' => ['type' => 'self_task'], 'evidence_ref_ids' => ['evidence-001']],
                ['position' => 3, 'title' => 'Đăng ký tham gia Hội thảo AI', 'description' => 'Tham gia hội thảo để tiếp cận kiến thức mới.', 'estimated_minutes' => 120, 'action' => ['type' => 'register_activity', 'activity_source_id' => $actId1], 'evidence_ref_ids' => ['evidence-001']],
            ],
        ],
        [
            'position' => 2,
            'start_day' => 31,
            'end_day' => 60,
            'code' => 'practice',
            'title' => 'Thực hành dự án và nâng cao kỹ năng',
            'goal' => 'Ứng dụng kiến thức vào xây dựng một ứng dụng nhỏ có tính ứng dụng.',
            'skill_focus' => 'Phát triển dự án phần mềm mini',
            'deliverable' => 'Sản phẩm mã nguồn mini project trên GitHub',
            'effort_label' => 'Khoảng 6–8 giờ mỗi tuần',
            'metric_label' => 'Mức độ hoàn thiện các tính năng cốt lõi',
            'evidence_ref_ids' => ['evidence-001'],
            'tasks' => [
                ['position' => 1, 'title' => 'Thiết kế kiến trúc ứng dụng', 'description' => 'Vẽ sơ đồ luồng dữ liệu và thiết kế cơ sở dữ liệu.', 'estimated_minutes' => 90, 'action' => ['type' => 'self_task'], 'evidence_ref_ids' => ['evidence-001']],
                ['position' => 2, 'title' => 'Lập trình các tính năng chính', 'description' => 'Viết code và kiểm thử từng hàm chức năng.', 'estimated_minutes' => 120, 'action' => ['type' => 'self_task'], 'evidence_ref_ids' => ['evidence-001']],
                ['position' => 3, 'title' => 'Viết tài liệu hướng dẫn sử dụng', 'description' => 'Tạo file README chi tiết cho dự án.', 'estimated_minutes' => 60, 'action' => ['type' => 'self_task'], 'evidence_ref_ids' => ['evidence-001']],
            ],
        ],
        [
            'position' => 3,
            'start_day' => 61,
            'end_day' => 90,
            'code' => 'breakthrough',
            'title' => 'Bứt phá và chuẩn bị định hướng',
            'goal' => 'Hoàn thiện hồ sơ năng lực và chia sẻ kết quả dự án với cộng đồng.',
            'skill_focus' => 'Thuyết trình và đóng gói sản phẩm',
            'deliverable' => 'Bản thuyết trình sản phẩm và báo cáo tổng kết',
            'effort_label' => 'Khoảng 5–7 giờ mỗi tuần',
            'metric_label' => 'Đánh giá từ giáo viên hướng dẫn',
            'evidence_ref_ids' => ['evidence-001'],
            'tasks' => [
                ['position' => 1, 'title' => 'Tối ưu hóa hiệu năng ứng dụng', 'description' => 'Refactor code và xử lý ngoại lệ.', 'estimated_minutes' => 90, 'action' => ['type' => 'self_task'], 'evidence_ref_ids' => ['evidence-001']],
                ['position' => 2, 'title' => 'Chuẩn bị slide thuyết trình', 'description' => 'Tổng hợp các điểm nổi bật và bài học kinh nghiệm.', 'estimated_minutes' => 60, 'action' => ['type' => 'self_task'], 'evidence_ref_ids' => ['evidence-001']],
                ['position' => 3, 'title' => 'Đánh giá và phản hồi kết quả', 'description' => 'Thu thập góp ý từ thầy cô và bạn bè.', 'estimated_minutes' => 60, 'action' => ['type' => 'self_task'], 'evidence_ref_ids' => ['evidence-001']],
            ],
        ],
    ],
];

$config = RecommendationConfig::fromEnvironment([
    'APP_ENV' => 'production',
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => 'fake',
    'TALENTHUB_AI_MODEL' => 'e2e-gemini-model-v1',
    'TALENTHUB_AI_API_URL' => 'https://ai.example.test/v1/generate',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'ai.example.test',
    'TALENTHUB_AI_API_KEY' => 'test-key-e2e',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '100',
    'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
    'TALENTHUB_AI_PILOT_APPROVAL_REFERENCE' => 'ref-e2e-2026',
    'TALENTHUB_AI_PILOT_PAUSED' => 'false',
]);

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

$provider = new FakeRoadmapTestProvider($mockPayload);
$consentPolicy = new ConsentPolicy(new DatabaseConsentSource($pdo));
$consentGate = new ProviderConsentGate($consentPolicy, ['assessment'], ['assessment']);

$modelEngine = new ModelRoadmapEngine(
    $provider,
    new RuleRoadmapEngine(),
    new RoadmapPromptRegistry(),
    new RecommendationRateLimiter(10, 10, 60, static fn (): int => 1_000),
    $config,
    $consentGate,
);

$roadmapsRepo = new DatabaseRoadmapRepository($pdo);
$runsRepo = new DatabaseRecommendationRepository($pdo);

$service = new RoadmapService(
    $roadmapsRepo,
    new RuleRoadmapEngine(),
    static fn (string $id): bool => hash_equals($studentId, $id),
    static fn (string $id) => $consentPolicy->decision($id)->withServiceScopes(['assessment']),
    static fn (string $id, array $scopes) => $snapshotBuilder->buildForRoadmap($id, $scopes),
    static fn ($input) => (new RoadmapQualityGate())->evaluate($input),
    static fn (string $id, $input, $ctx) => $runsRepo->createPendingRoadmapRun($id, $input, $ctx),
    static fn (string $id, string $runId, $analysis) => $runsRepo->completeRoadmapRun($id, $runId, $analysis),
    static fn (string $id, string $runId, string $code) => $runsRepo->failRun($id, $runId, $code),
    $modelEngine,
    $config,
    new \TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy(),
    [
        'stage' => '50',
        'error_budget' => true,
        'freshness_sla' => true,
        'validator_pass_rate' => true,
        'privacy_review' => true,
        'rollback_drill' => true,
        'approval_reference' => 'ref-e2e-2026',
        'enabled' => true,
        'shadow_gate_approved' => true,
        'pilot_paused' => false,
        'completed_stages' => ['pilot', '10', '25', '50'],
        'visible_percent' => 100,
        'unified_policy_verified' => true,
        'last_known_good_verified' => true,
        'queue_monitoring_verified' => true,
    ],
);

// -------------------------------------------------------------
// Step 1: Initial Generation
// -------------------------------------------------------------
$created = $service->generate($studentId, 'req-e2e-001', 'idemp-e2e-0000000000000001');
if (($created['state'] ?? '') !== 'ready_model') {
    var_dump($created);
}

e2e_assert(($created['state'] ?? '') === 'ready_model', 'strict roadmap returns ready_model state');
e2e_assert(($created['analysis_origin'] ?? '') === 'model', 'analysis_origin must be model');
e2e_assert(($created['model_version'] ?? '') === 'e2e-gemini-model-v1', 'model_version is recorded');
e2e_assert(count($created['phases'] ?? []) === 3, 'roadmap has exactly 3 phases');
e2e_assert(array_column($created['phases'], 'start_day') === [0, 31, 61], 'phase start days are 0, 31, 61');
e2e_assert(array_column($created['phases'], 'end_day') === [30, 60, 90], 'phase end days are 30, 60, 90');
e2e_assert(array_column($created['phases'], 'code') === ['discover', 'practice', 'breakthrough'], 'phase codes are discover, practice, breakthrough');

foreach ($created['phases'] as $p) {
    e2e_assert(count($p['tasks']) >= 3 && count($p['tasks']) <= 5, 'each phase has between 3 and 5 actionable tasks');
}

$firstTask = $created['phases'][0]['tasks'][0];
$taskId = $firstTask['task_id'];
$roadmapId = $created['roadmap_id'];

// -------------------------------------------------------------
// Step 2: Idempotency & Reuse
// -------------------------------------------------------------
$reused = $service->generate($studentId, 'req-e2e-001', 'idemp-e2e-0000000000000001');
e2e_assert(($reused['reused'] ?? false) === true, 'same idempotency key returns reused=true');
e2e_assert($reused['roadmap_id'] === $roadmapId, 'reused roadmap has matching roadmap_id');

// -------------------------------------------------------------
// Step 3: Task Status Updates
// -------------------------------------------------------------
$update1 = $service->updateTask($studentId, $taskId, 'in_progress', 'req-task-001');
e2e_assert(($update1['state'] ?? '') === 'task_updated', 'task transitioned to in_progress');
e2e_assert(($update1['status'] ?? '') === 'in_progress', 'status is in_progress');

$update2 = $service->updateTask($studentId, $taskId, 'completed', 'req-task-002');
e2e_assert(($update2['state'] ?? '') === 'task_updated', 'task transitioned to completed');
e2e_assert(($update2['status'] ?? '') === 'completed', 'status is completed');

$update2Repeat = $service->updateTask($studentId, $taskId, 'completed', 'req-task-002');
e2e_assert(($update2Repeat['reused'] ?? false) === true, 'task update is idempotent per request ID');

$invalidTransition = $service->updateTask($studentId, $taskId, 'not_started', 'req-task-003');
e2e_assert(($invalidTransition['state'] ?? '') === 'invalid_task_transition', 'invalid transition is rejected');

// -------------------------------------------------------------
// Step 4: Feedback
// -------------------------------------------------------------
$fb1 = $service->feedback($studentId, $roadmapId, 'helpful', 'useful_direction', 'req-fb-001');
e2e_assert(($fb1['state'] ?? '') === 'feedback_saved', 'feedback is saved');
e2e_assert(($fb1['reused'] ?? false) === false, 'initial feedback is not reused');

$fb1Repeat = $service->feedback($studentId, $roadmapId, 'helpful', 'useful_direction', 'req-fb-001');
e2e_assert(($fb1Repeat['reused'] ?? false) === true, 'repeated feedback request is reused');
$v1InputHash = (string) $pdo->query("SELECT snapshots.contentHash FROM learner_ai_roadmaps AS roadmaps INNER JOIN learner_recommendation_runs AS runs ON runs.id = roadmaps.runId INNER JOIN learner_recommendation_input_snapshots AS snapshots ON snapshots.id = runs.snapshotId WHERE roadmaps.id = '{$roadmapId}'")->fetchColumn();

// Recommendation-item feedback must also be exactly-once for the API idempotency key.
$feedbackRunId = 'run-feedback-e2e-001';
$feedbackItemId = 'item-feedback-e2e-001';
$pdo->exec("INSERT INTO learner_recommendation_runs (id, studentId, snapshotId, idempotencyKey, engineType, status, startedAt, createdAt) VALUES ('{$feedbackRunId}', '{$studentId}', NULL, 'feedback-fixture-key', 'model', 'completed', '2026-08-20T00:00:00Z', '2026-08-20T00:00:00Z')");
$pdo->exec("INSERT INTO learner_recommendation_items (id, runId, itemType, title, summary, priority, confidenceBand, actionJson, lifecycleStatus, createdAt) VALUES ('{$feedbackItemId}', '{$feedbackRunId}', 'activity', 'Feedback fixture', 'Feedback fixture', 1, 'high', '{}', 'active', '2026-08-20T00:00:00Z')");
$itemFeedback1 = $runsRepo->appendFeedbackWithRequestId($studentId, $feedbackItemId, 'helpful', 'relevant', null, 'item-feedback-request-0001');
$itemFeedback2 = $runsRepo->appendFeedbackWithRequestId($studentId, $feedbackItemId, 'helpful', 'relevant', null, 'item-feedback-request-0001');
$itemFeedbackConflict = $runsRepo->appendFeedbackWithRequestId($studentId, $feedbackItemId, 'not_helpful', 'not_relevant', null, 'item-feedback-request-0001');
e2e_assert(($itemFeedback1['reused'] ?? true) === false, 'first item feedback request is persisted');
e2e_assert(($itemFeedback2['reused'] ?? false) === true, 'same item feedback request is reused');
e2e_assert(($itemFeedbackConflict['state'] ?? null) === 'idempotency_conflict', 'same idempotency key rejects a different feedback payload');
e2e_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_feedback WHERE itemId = '{$feedbackItemId}'")->fetchColumn() === 1, 'idempotent item feedback creates one domain row');
e2e_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_ai_data_outbox WHERE event_type = 'recommendation.feedback' AND aggregate_id = (SELECT id FROM learner_recommendation_feedback WHERE itemId = '{$feedbackItemId}')")->fetchColumn() === 1, 'idempotent item feedback creates one outbox row');

// Failure injection: domain feedback and its outbox event commit atomically.
$feedbackCountBeforeFailure = (int) $pdo->query('SELECT COUNT(*) FROM learner_recommendation_feedback')->fetchColumn();
$pdo->exec('ALTER TABLE learner_ai_data_outbox RENAME TO learner_ai_data_outbox_unavailable');
$rollbackObserved = false;
try {
    $runsRepo->appendFeedbackWithRequestId($studentId, $feedbackItemId, 'not_helpful', 'not_relevant', null, 'item-feedback-request-rollback');
} catch (Throwable) {
    $rollbackObserved = true;
}
$pdo->exec('ALTER TABLE learner_ai_data_outbox_unavailable RENAME TO learner_ai_data_outbox');
e2e_assert($rollbackObserved, 'outbox failure is surfaced to the caller');
e2e_assert((int) $pdo->query('SELECT COUNT(*) FROM learner_recommendation_feedback')->fetchColumn() === $feedbackCountBeforeFailure, 'outbox failure rolls back the domain feedback row');

// -------------------------------------------------------------
// Step 5: Forced Refresh Version 2
// -------------------------------------------------------------
$v2Result = $service->generate($studentId, 'req-e2e-002', 'idemp-e2e-0000000000000002', forceRefresh: true);
e2e_assert(($v2Result['state'] ?? '') === 'ready_model', 'forced refresh creates ready_model');
e2e_assert(($v2Result['version'] ?? 0) === 2, 'new roadmap has version 2');
$v2InputHash = (string) $pdo->query("SELECT snapshots.contentHash FROM learner_ai_roadmaps AS roadmaps INNER JOIN learner_recommendation_runs AS runs ON runs.id = roadmaps.runId INNER JOIN learner_recommendation_input_snapshots AS snapshots ON snapshots.id = runs.snapshotId WHERE roadmaps.id = '" . $v2Result['roadmap_id'] . "'")->fetchColumn();
e2e_assert($v1InputHash !== '' && $v2InputHash !== '' && !hash_equals($v1InputHash, $v2InputHash), 'feedback changes the next roadmap snapshot hash');

$v1Fetch = $service->version($studentId, 1);
e2e_assert(($v1Fetch['version'] ?? 0) === 1, 'version 1 is still retrievable');

// -------------------------------------------------------------
// Step 6: Provider Failure with LKG (Last-Known-Good)
// -------------------------------------------------------------
$provider->setFail(true);
$lkgResult = $service->generate($studentId, 'req-e2e-003', 'idemp-e2e-0000000000000003', forceRefresh: true);
e2e_assert(($lkgResult['state'] ?? '') === 'stale_model', 'provider failure with existing roadmap returns stale_model');
e2e_assert(($lkgResult['freshness_status'] ?? '') === 'stale', 'freshness_status is stale');
e2e_assert(($lkgResult['last_known_good'] ?? false) === true, 'last_known_good is true');
e2e_assert(($lkgResult['analysis_origin'] ?? '') === 'model', 'analysis_origin remains model');
e2e_assert(($lkgResult['engine']['rule_version'] ?? null) === null, 'rule_version is null under strict mode');

// -------------------------------------------------------------
// Step 7: Provider Failure without LKG (Clean student)
// -------------------------------------------------------------
$cleanStudentId = 'student-e2e-0000000000000002';
$pdo->exec("INSERT INTO student_profiles (id, userId, classId, studyStatus, tenantId) VALUES ('{$cleanStudentId}', 'user-e2e-2', '{$classId}', 'active', 'tenant-1')");
foreach (['assessment', 'skills', 'activity', 'evaluation'] as $scope) {
    $pdo->exec("INSERT INTO learner_ai_consent_events (id, studentId, scope, action, policyVersion, occurredAt, requestId) VALUES ('consent-clean-{$scope}', '{$cleanStudentId}', '{$scope}', 'granted', 'v1', '2026-08-20T00:00:00Z', 'req-consent-clean')");
}
foreach ($assessments as $testId => $meta) {
    $attemptId = "att-clean-{$testId}";
    $pdo->exec("INSERT INTO test_attempts (id, studentId, testId, status) VALUES ('{$attemptId}', '{$cleanStudentId}', '{$testId}', 'submitted')");
    $pdo->exec("INSERT INTO test_results (id, attemptId, resultCode, dimensionScoresJson) VALUES ('res-clean-{$testId}', '{$attemptId}', 'HIGH', '{$meta['scores']}')");
    $pdo->exec("INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status, submittedAt) VALUES ('meta-clean-{$testId}', '{$attemptId}', 'ver-1', 'submitted', '2026-08-20T10:00:00Z')");
}

$cleanService = new RoadmapService(
    $roadmapsRepo,
    new RuleRoadmapEngine(),
    static fn (string $id): bool => hash_equals($cleanStudentId, $id),
    static fn (string $id) => $consentPolicy->decision($id)->withServiceScopes(['assessment']),
    static fn (string $id, array $scopes) => $snapshotBuilder->buildForRoadmap($id, $scopes),
    static fn ($input) => (new RoadmapQualityGate())->evaluate($input),
    static fn (string $id, $input, $ctx) => $runsRepo->createPendingRoadmapRun($id, $input, $ctx),
    static fn (string $id, string $runId, $analysis) => $runsRepo->completeRoadmapRun($id, $runId, $analysis),
    static fn (string $id, string $runId, string $code) => $runsRepo->failRun($id, $runId, $code),
    $modelEngine,
    $config,
    new \TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy(),
    [
        'stage' => '50',
        'error_budget' => true,
        'freshness_sla' => true,
        'validator_pass_rate' => true,
        'privacy_review' => true,
        'rollback_drill' => true,
        'approval_reference' => 'ref-e2e-2026',
        'enabled' => true,
        'shadow_gate_approved' => true,
        'pilot_paused' => false,
        'completed_stages' => ['pilot', '10', '25', '50'],
        'visible_percent' => 100,
        'unified_policy_verified' => true,
        'last_known_good_verified' => true,
        'queue_monitoring_verified' => true,
    ],
);

$cleanUnavailable = $cleanService->generate($cleanStudentId, 'req-e2e-clean-1', 'idemp-e2e-clean-0000000000000001');
e2e_assert(($cleanUnavailable['state'] ?? '') === 'provider_unavailable', 'provider failure without LKG returns provider_unavailable');
e2e_assert(($cleanUnavailable['analysis_origin'] ?? null) === null, 'clean failure has null analysis_origin');
e2e_assert(($cleanUnavailable['engine']['rule_version'] ?? null) === null, 'clean failure does not fallback to rule');

foreach (['recommendation-feedback.php', 'ai-roadmap-task.php'] as $mutationEndpoint) {
    $source = (string) file_get_contents(dirname(__DIR__) . '/app/learner/api/v1/' . $mutationEndpoint);
    e2e_assert(!str_contains($source, 'refresh_snapshot_hash'), $mutationEndpoint . ' does not expose raw snapshot hashes');
}
$feedbackEndpointSource = (string) file_get_contents(dirname(__DIR__) . '/app/learner/api/v1/recommendation-feedback.php');
$feedbackLimiterPosition = strpos($feedbackEndpointSource, 'new PersistentActionRateLimiter');
$feedbackBranchPosition = strpos($feedbackEndpointSource, "if (\$roadmapId !== '')");
e2e_assert(is_int($feedbackLimiterPosition) && is_int($feedbackBranchPosition) && $feedbackLimiterPosition < $feedbackBranchPosition, 'both item and roadmap feedback are rate limited before branching');
e2e_assert(str_contains($feedbackEndpointSource, "'IDEMPOTENCY_CONFLICT'"), 'feedback API maps changed idempotency payloads to conflict');

echo "learner_ai_end_to_end_test: OK\n";
