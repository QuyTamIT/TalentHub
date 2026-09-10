<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Model\RoadmapPromptRegistry;
use TalentHub\Learner\Ai\Validation\RoadmapAnalysisValidator;

$errors = [];
$assert = static function (bool $ok, string $message) use (&$errors): void {
    if (!$ok) {
        $errors[] = $message;
    }
};

$promptRegistry = new RoadmapPromptRegistry();

$input = new RecommendationInput(
    [
        'profile' => [
            'study_status' => 'studying',
            'school_name' => 'Trường THPT Chuyên',
            'class_name' => '11 Tin',
            'grade_level' => 'THPT',
            'academic_year' => '2025-2026',
        ],
        'skills' => [
            ['code' => 'sql', 'level_score' => 75],
        ],
        'assessments' => [
            [
                'test_type' => 'holland',
                'result_code' => 'RIA',
                'dimension_scores' => ['R' => 40, 'I' => 35, 'A' => 30],
            ],
        ],
    ],
    [],
    [],
    [
        [
            'source_type' => 'skill',
            'source_id' => 'sql-1',
            'observed_at' => null,
            'safe_value' => ['code' => 'sql', 'level_score' => 75],
        ],
        [
            'source_type' => 'assessment',
            'source_id' => 'holland-1',
            'observed_at' => null,
            'safe_value' => [
                'test_type' => 'holland',
                'result_code' => 'RIA',
                'dimension_scores' => ['R' => 40, 'I' => 35, 'A' => 30],
            ],
        ],
    ]
);

$context = new RecommendationContext(['skills', 'assessments'], 'request-1', 'key-1', 'student-1');

$request = $promptRegistry->create($input, $context);
$payload = $request->payload();
$instructions = $payload['instructions'] ?? [];

$expectedInstruction1 = 'Luôn phân tích từ 2 đến 3 điểm mạnh nổi bật (strengths) và từ 2 đến 3 hướng tiềm năng mở rộng (potential_paths) phù hợp nhất với học viên dựa trên kết hợp kết quả các bài đánh giá. Mỗi record phải trích dẫn evidence_ref_ids được cung cấp.';
$expectedInstruction2 = 'potential_paths nêu rõ tên hướng phát triển hoặc vai trò tiềm năng kèm lý giải ngắn gọn trong trường label (catalog_id là tùy chọn, chỉ điền khi có catalog evidence tương ứng).';
$expectedInstruction3 = 'Nếu có improvements, trend_signals hoặc growth_hypotheses thì mỗi record phải trích dẫn evidence_ref_ids được cung cấp.';

$assert(
    in_array($expectedInstruction1, $instructions, true),
    'Instructions must require 2-3 strengths and 2-3 potential_paths with evidence.'
);
$assert(
    in_array($expectedInstruction2, $instructions, true),
    'Instructions must specify label format and optional catalog_id for potential_paths.'
);
$assert(
    in_array($expectedInstruction3, $instructions, true),
    'Instructions must include refined evidence citations for improvements, trend_signals, or growth_hypotheses.'
);

$refs = $request->evidenceReferenceIds();
$phases = [];
foreach ([[0, 30, 'discover'], [31, 60, 'practice'], [61, 90, 'breakthrough']] as $i => [$start, $end, $code]) {
    $tasks = [];
    for ($j = 1; $j <= 3; $j++) {
        $tasks[] = [
            'position' => $j,
            'title' => 'Luyện tập kỹ năng phân tích',
            'description' => 'Thực hiện bài tập phân tích dữ liệu mẫu và đối chiếu kết quả với tiêu chí học tập.',
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
        'title' => 'Giai đoạn phát triển năng lực',
        'goal' => 'Rèn luyện và củng cố phương pháp phân tích có hệ thống.',
        'skill_focus' => 'Tư duy phân tích',
        'deliverable' => 'Báo cáo tổng hợp bài tập hoàn thành',
        'effort_label' => 'Thời lượng đề xuất theo từng bài tập',
        'metric_label' => 'Số bài tập hoàn thành chính xác',
        'evidence_ref_ids' => $refs,
        'tasks' => $tasks,
    ];
}

$direction = [
    'code' => 'data_analyst',
    'label' => 'Phân tích dữ liệu',
    'rationale' => 'Phù hợp với năng lực tư duy logic và kỹ năng hiện tại.',
];

$mockPayload = [
    'executive_summary' => 'Lộ trình phát triển được xây dựng dựa trên kết quả đánh giá thực tế và năng lực của bạn.',
    'primary_direction' => $direction,
    'alternative_directions' => [
        [
            'code' => 'software_engineer',
            'label' => 'Phát triển phần mềm',
            'rationale' => 'Phát huy thế mạnh tư duy giải quyết vấn đề.',
        ],
        [
            'code' => 'system_analyst',
            'label' => 'Phân tích hệ thống',
            'rationale' => 'Kết hợp khả năng tổ chức và phân tích logic.',
        ],
    ],
    'insights' => [
        [
            'category' => 'strength',
            'title' => 'Nền tảng tư duy logic',
            'summary' => 'Kết quả đánh giá cho thấy khả năng suy luận có phương pháp rõ ràng.',
            'evidence_ref_ids' => [$refs[0]],
        ],
        [
            'category' => 'improvement',
            'title' => 'Kỹ năng trình bày giải pháp',
            'summary' => 'Nên rèn luyện thêm cách hệ thống hóa và trình bày kết quả phân tích.',
            'evidence_ref_ids' => [$refs[0]],
        ],
        [
            'category' => 'potential',
            'title' => 'Khả năng thích ứng công nghệ mới',
            'summary' => 'Có triển vọng học nhanh các công cụ dữ liệu hiện đại khi được hướng dẫn.',
            'evidence_ref_ids' => [$refs[1] ?? $refs[0]],
        ],
    ],
    'phases' => $phases,
    'recommended_activity_source_ids' => [],
    'talent_map' => [
        ['field' => 'Tư duy Logic & Hệ thống', 'score' => 0.8, 'evidence_ref_ids' => [$refs[0]]],
        ['field' => 'Kỹ năng Thực hành & Thao tác', 'score' => 0.7, 'evidence_ref_ids' => [$refs[0]]],
        ['field' => 'Tổ chức & Điều phối', 'score' => 0.6, 'evidence_ref_ids' => [$refs[1] ?? $refs[0]]],
    ],
    'strengths' => [
        [
            'text' => 'Tư duy logic mạch lạc trong việc giải quyết vấn đề kỹ thuật.',
            'evidence_ref_ids' => [$refs[0]],
        ],
        [
            'text' => 'Khả năng tập trung và tìm tòi qua các bài đánh giá năng lực.',
            'evidence_ref_ids' => [$refs[1] ?? $refs[0]],
        ],
    ],
    'potential_paths' => [
        [
            'label' => 'Kỹ sư Dữ liệu: Phù hợp với năng lực tư duy logic và kỹ năng xử lý dữ liệu.',
            'evidence_ref_ids' => [$refs[0]],
        ],
        [
            'label' => 'Chuyên viên Phân tích Nghiệp vụ: Phát huy thế mạnh phân tích và tổ chức thông tin.',
            'evidence_ref_ids' => [$refs[1] ?? $refs[0]],
        ],
    ],
];

$engineMetadata = [
    'origin' => 'model',
    'provider' => 'test-provider',
    'model_version' => 'test-model',
    'prompt_version' => RoadmapPromptRegistry::VERSION,
    'confidence_band' => 'high',
    'provider_request_id' => 'test-request-123',
    'response_hash' => hash('sha256', 'test-response-payload'),
];

$validator = new RoadmapAnalysisValidator(
    $request->evidenceReferenceIds(),
    $payload['allowed_activity_ids'] ?? [],
    $payload['allowed_catalog_ids'] ?? [],
    $input
);

try {
    $analysis = $validator->fromProviderPayload($mockPayload, $engineMetadata);
    $validator->validate($analysis);

    $assert(
        count($analysis->strengths()) === 2,
        'RoadmapAnalysis strengths count must be 2.'
    );
    $assert(
        count($analysis->potentialPaths()) === 2,
        'RoadmapAnalysis potential_paths count must be 2.'
    );
    $assert(
        !isset($analysis->potentialPaths()[0]['catalog_id']),
        'potential_paths should not require catalog_id when omitted.'
    );
} catch (Throwable $e) {
    $assert(false, 'RoadmapAnalysisValidator failed to validate mock payload: ' . $e->getMessage());
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "learner_roadmap_prompt_registry_test: OK\n";
