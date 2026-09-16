<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Validation\RoadmapAnalysisValidator;
use TalentHub\Learner\Ai\Model\RoadmapPromptRegistry;
use TalentHub\Learner\Ai\Grounding\GroundedProseGuard;
use TalentHub\Learner\Ai\Matching\{CareerRoleBenchmark, JobMatchScorer, LearnerOpportunityProfile, OpportunityCandidate};

$errors = [];
$assert = static function (bool $ok, string $why) use (&$errors): void {
    if (!$ok) {
        $errors[] = $why;
    }
};

$refs = ['evidence-001'];
$phases = [];
foreach ([[0, 30, 'discover'], [31, 60, 'practice'], [61, 90, 'breakthrough']] as $i => [$start, $end, $code]) {
    $tasks = [];
    for ($j = 1; $j <= 3; $j++) {
        $tasks[] = [
            'position' => $j,
            'title' => 'Luyện truy vấn dữ liệu',
            'description' => 'Viết truy vấn SQL trên bảng dữ liệu mẫu, lưu truy vấn và đối chiếu kết quả với yêu cầu của bài tập.',
            'estimated_minutes' => 60,
            'action' => ['type' => 'self_task'],
            'evidence_ref_ids' => $refs,
        ];
    }
    $phases[] = [
        'position' => $i + 1,
        'start_day' => $start,
        'end_day' => $end,
        'code' => $code,
        'title' => 'Luyện kỹ năng dữ liệu',
        'goal' => 'Củng cố kỹ năng truy vấn dữ liệu.',
        'skill_focus' => 'Truy vấn dữ liệu',
        'deliverable' => 'Bộ truy vấn có kết quả đối chiếu',
        'effort_label' => 'Thời lượng đề xuất theo từng bài tập',
        'metric_label' => 'Số truy vấn có kết quả chính xác',
        'evidence_ref_ids' => $refs,
        'tasks' => $tasks,
    ];
}
$direction = ['code' => 'data', 'label' => 'Phân tích dữ liệu', 'rationale' => 'Hướng luyện tập đề xuất dựa trên kỹ năng hiện có.'];
$baseRoadmap = [
    'executive_summary' => 'Lộ trình này đề xuất luyện tập theo từng bước và đối chiếu kết quả để củng cố kỹ năng.',
    'primary_direction' => $direction,
    'alternative_directions' => [$direction, $direction],
    'insights' => [
        ['category' => 'strength', 'title' => 'Điểm mạnh', 'summary' => 'Dữ liệu kỹ năng là căn cứ để đề xuất bài tập.', 'evidence_ref_ids' => $refs],
        ['category' => 'improvement', 'title' => 'Cải thiện', 'summary' => 'Dữ liệu kỹ năng là căn cứ để đề xuất bài tập.', 'evidence_ref_ids' => $refs],
        ['category' => 'potential', 'title' => 'Tiềm năng', 'summary' => 'Dữ liệu kỹ năng là căn cứ để đề xuất bài tập.', 'evidence_ref_ids' => $refs],
    ],
    'phases' => $phases,
    'recommended_activity_source_ids' => [],
];

$engineMetadata = [
    'origin' => 'model',
    'provider' => 'gemini',
    'model_version' => 'gemini-1.5-pro',
    'prompt_version' => RoadmapPromptRegistry::VERSION,
    'confidence_band' => 'high',
];

$input = new RecommendationInput(['skills' => [], 'assessments' => []], [], [], [
    ['source_type' => 'assessment', 'source_id' => 'evidence-001', 'observed_at' => null, 'safe_value' => ['code' => 'logic', 'score' => 90]],
]);

// --- TEST 1: RoadmapAnalysisValidator REJECTS payload containing talent_map ---
$payloadWithTalentMap = $baseRoadmap;
$payloadWithTalentMap['talent_map'] = [
    ['field' => 'Tư duy Logic & Hệ thống', 'score' => 0.99, 'evidence_ref_ids' => $refs],
    ['field' => 'Kỹ năng Thực hành & Thao tác', 'score' => 0.75, 'evidence_ref_ids' => $refs],
    ['field' => 'Tổ chức & Điều phối', 'score' => 0.80, 'evidence_ref_ids' => $refs],
];

$validator = new RoadmapAnalysisValidator($refs, [], [], $input);
try {
    $validator->fromProviderPayload($payloadWithTalentMap, $engineMetadata);
    $assert(false, 'RoadmapAnalysisValidator MUST reject payload containing talent_map');
} catch (InvalidArgumentException $e) {
    $assert(true, 'RoadmapAnalysisValidator successfully rejected talent_map');
}

// --- TEST 2: RoadmapAnalysisValidator ACCEPTS clean payload without talent_map ---
try {
    $cleanAnalysis = $validator->fromProviderPayload($baseRoadmap, $engineMetadata);
    $assert($cleanAnalysis instanceof RoadmapAnalysis, 'RoadmapAnalysisValidator accepts clean payload without talent_map');
    $assert($cleanAnalysis->talentMap() === [], 'RoadmapAnalysis talentMap must be empty');
} catch (Throwable $e) {
    $assert(false, 'RoadmapAnalysisValidator should accept clean payload without talent_map: ' . $e->getMessage());
}

// --- TEST 3: RoadmapPromptRegistry output schema and instructions ---
$prompt = (new RoadmapPromptRegistry())->create($input, new RecommendationContext(['skills'], 'request', 'key', 'student'));
$schema = $prompt->payload()['output_schema'] ?? [];
$requiredFields = $schema['required'] ?? [];
$assert(!in_array('talent_map', $requiredFields, true), 'talent_map must NOT be in output schema required fields');
$assert(!isset($schema['properties']['talent_map']), 'talent_map must NOT be in output schema properties');
$instructionsText = implode(' ', $prompt->payload()['instructions'] ?? []);
$assert(!str_contains($instructionsText, 'talent_map phải có đúng ba record'), 'Prompt instructions must not demand talent_map');

// --- TEST 4: DatabaseRoadmapRepository filters talent_map on read (hydrate) ---
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec(<<<'SQL'
CREATE TABLE learner_recommendation_input_snapshots (id TEXT PRIMARY KEY, contentHash TEXT);
CREATE TABLE learner_recommendation_runs (id TEXT PRIMARY KEY, studentId TEXT, snapshotId TEXT, engineType TEXT, status TEXT, ruleVersion TEXT, provider TEXT, modelVersion TEXT, promptVersion TEXT, fallbackReason TEXT);
CREATE TABLE learner_ai_roadmaps (
    id TEXT PRIMARY KEY, studentId TEXT, runId TEXT, versionNumber INTEGER, contractVersion TEXT,
    status TEXT, executiveSummary TEXT, primaryDirectionJson TEXT, alternativeDirectionsJson TEXT,
    insightsJson TEXT, confidenceBand TEXT, evidenceSummaryJson TEXT, providerRequestId TEXT,
    responseHash TEXT, generatedAt TEXT, supersededAt TEXT, createdAt TEXT,
    freshness_status TEXT, stale_since TEXT, last_refresh_error TEXT, next_retry_at TEXT, refresh_job_id TEXT
);
CREATE TABLE learner_ai_roadmap_phases (
    id TEXT PRIMARY KEY, roadmapId TEXT, position INTEGER, startDay INTEGER, endDay INTEGER,
    code TEXT, title TEXT, goal TEXT, skillFocus TEXT, deliverable TEXT, effortLabel TEXT,
    metricLabel TEXT, evidenceJson TEXT
);
CREATE TABLE learner_ai_roadmap_tasks (
    id TEXT PRIMARY KEY, phaseId TEXT, position INTEGER, title TEXT, description TEXT,
    estimatedMinutes INTEGER, actionType TEXT, targetId TEXT, evidenceJson TEXT
);
CREATE TABLE learner_ai_roadmap_task_events (
    id TEXT PRIMARY KEY, taskId TEXT, status TEXT, occurredAt TEXT, createdAt TEXT
);
SQL);

$studentId = '00000000-0000-4000-8000-000000000001';
$pdo->exec("INSERT INTO learner_recommendation_input_snapshots VALUES ('snap-1', 'hash-1')");
$pdo->exec("INSERT INTO learner_recommendation_runs VALUES ('run-1', '{$studentId}', 'snap-1', 'model', 'completed', NULL, 'gemini', '1.5', 'prompt-1', NULL)");

$oldInsightsJsonWithTalentMap = json_encode([
    'items' => [
        ['category' => 'strength', 'title' => 'Tốt', 'summary' => 'Rất tốt', 'evidence_ref_ids' => ['evidence-001']]
    ],
    '__ai_extended' => [
        'talent_map' => [
            ['field' => 'Tư duy Logic & Hệ thống', 'score' => 0.95],
            ['field' => 'Kỹ năng Thực hành & Thao tác', 'score' => 0.85],
        ],
        'strengths' => [],
        'improvements' => [],
        'potential_paths' => [],
        'trend_signals' => [],
        'growth_hypotheses' => [],
        'confidence' => 0.9,
        'evidence' => ['evidence-001'],
    ]
], JSON_THROW_ON_ERROR);

$pdo->exec("INSERT INTO learner_ai_roadmaps VALUES (
    'road-1', '{$studentId}', 'run-1', 1, 'learner-roadmap-1.0.0', 'active',
    'Executive summary', '{\"code\":\"data\",\"label\":\"Data\",\"rationale\":\"Why\"}', '[]',
    '{$oldInsightsJsonWithTalentMap}', 'high', '{}', 'req-1', 'hash-1', '2026-09-15 00:00:00', NULL, '2026-09-15 00:00:00',
    'ready', NULL, NULL, NULL, NULL
)");

$repo = new \TalentHub\Learner\Ai\Persistence\DatabaseRoadmapRepository($pdo);
$activeRoadmap = $repo->latestForStudent($studentId);
$assert(is_array($activeRoadmap), 'Active roadmap should be fetched');
$assert(($activeRoadmap['talent_map'] ?? null) === [], 'DatabaseRoadmapRepository::hydrate must filter out talent_map to [] on read');

// --- TEST 5: DatabaseSchoolCredentialRepository filters talent_map on read ---
$schoolCredRepo = new \TalentHub\Learner\Data\Database\DatabaseSchoolCredentialRepository($pdo);
$credAnalysis = $schoolCredRepo->latestRoadmapAnalysis($studentId);
$assert(is_array($credAnalysis), 'School credential roadmap analysis fetched');
$assert(($credAnalysis['talent_map'] ?? null) === [], 'DatabaseSchoolCredentialRepository must return talent_map => []');

// --- TEST 6: LearnerOpportunityProfile filters out non-scored / unverified skills ---
$unverifiedInput = new RecommendationInput([
    'skills' => [
        ['code' => 'verified_skill', 'level_score' => 80, 'score_state' => 'scored'],
        ['code' => 'unverified_skill', 'level_score' => 90, 'score_state' => 'unverified'],
        ['code' => 'missing_source_skill', 'level_score' => 95, 'score_state' => 'missing_source'],
        ['code' => 'evidence_only_skill', 'level_score' => null, 'score_state' => 'evidence_only'],
    ],
    'assessments' => [],
], [], [], []);
$oppProfile = LearnerOpportunityProfile::fromInput($unverifiedInput);
$assert($oppProfile->skills() === ['verified_skill' => 80], 'LearnerOpportunityProfile must only retain scored skills, ignoring unverified/missing_source/evidence_only');

// --- TEST 7: Teacher Students Page must not read talent_map ---
$teacherPageCode = file_get_contents(dirname(__DIR__) . '/app/teacher/students/index.php');
$assert(!str_contains($teacherPageCode, 'Kỹ năng thực tế từ Hồ sơ năng lực (talent_map'), 'Teacher students/index.php must not have talent_map branch');
$assert(!str_contains($teacherPageCode, "['talent_map']"), 'Teacher students/index.php must not read talent_map');

// --- TEST 8: student-data.php must not fallback to talent_map when skills is empty ---
$studentDataCode = file_get_contents(dirname(__DIR__) . '/app/learner/includes/student-data.php');
$assert(!str_contains($studentDataCode, '$talentMap = is_array($skillAnalysis[\'talent_map\']'), 'student-data.php must not fall back to talent_map');

// --- TEST 9: learner-ai-roadmap.js must not ingest payload.talent_map ---
$roadmapJs = file_get_contents(dirname(__DIR__) . '/assets/js/learner-ai-roadmap.js');
$assert(!str_contains($roadmapJs, 'completeTalentMap(payload?.talent_map)'), 'learner-ai-roadmap.js must not ingest payload.talent_map');

if ($errors !== []) {
    echo "FAIL: learner_ai_no_competency_scores_test:\n";
    foreach ($errors as $err) {
        echo "  - $err\n";
    }
    exit(1);
}

echo "learner_ai_no_competency_scores_test: OK\n";
exit(0);