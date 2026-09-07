<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\CareerRoleBenchmark;
use TalentHub\Learner\Ai\Matching\JobMatchAnalysisValidator;
use TalentHub\Learner\Ai\Matching\JobMatchScorer;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Matching\SkillGapResolver;
use TalentHub\Learner\Ai\Model\JobMatchPromptRegistry;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$rejects = static function (callable $callback, string $message) use (&$failures): void {
    try {
        $callback();
        $failures[] = $message;
    } catch (InvalidArgumentException) {
    }
};

$role = new CareerRoleBenchmark('data_analyst', 'Data Analyst', 'data', [
    ['code' => 'python', 'label' => 'Python', 'minimum_score' => 70, 'weight' => 60.0, 'required' => true],
    ['code' => 'sql', 'label' => 'SQL', 'minimum_score' => 75, 'weight' => 40.0, 'required' => true],
], []);
$candidate = OpportunityCandidate::fromEvidence([
    'source_type' => 'opportunity', 'source_id' => 'job-1',
    'safe_value' => [
        'catalog_id' => 'job-1', 'item_type' => 'internship', 'enterprise_id' => 'enterprise-1',
        'title' => 'Thực tập sinh phân tích dữ liệu', 'provider_name' => 'Công ty Dữ liệu Việt',
        'summary' => 'Tham gia phân tích dữ liệu kinh doanh.', 'location' => 'Hà Nội',
        'status' => 'active', 'url' => '/app/learner/opportunity.php?type=internship&id=job-1',
        'required_skills' => [
            ['code' => 'python', 'minimum_score' => 70, 'label' => 'Python'],
            ['code' => 'sql', 'minimum_score' => 75, 'label' => 'SQL'],
        ],
    ],
]);
$profile = LearnerOpportunityProfile::fromInput(new RecommendationInput(
    ['education_band' => 'college', 'profile' => ['name' => 'Nguyễn Văn Bí Mật', 'email' => 'secret@example.test', 'phone' => '0900000000'], 'skills' => [
        ['code' => 'python', 'score' => 85], ['code' => 'sql', 'score' => 45],
    ]], [], [], [
        ['source_type' => 'skill', 'source_id' => 'python-1', 'observed_at' => null, 'safe_value' => ['code' => 'python']],
        ['source_type' => 'skill', 'source_id' => 'sql-1', 'observed_at' => null, 'safe_value' => ['code' => 'sql']],
    ],
));
$match = (new JobMatchScorer())->score($profile, $candidate, $role);
$gap = (new SkillGapResolver())->resolve($match);
$context = new RecommendationContext(['profile', 'skills'], 'req-1', 'idem-1', 'student-1');

$request = JobMatchPromptRegistry::create($profile, [$candidate], ['job-1' => $match], ['job-1' => $gap], $context);
$payload = $request->payload();
$assert(JobMatchPromptRegistry::VERSION === 'learner-job-match-1.3.0', 'The grounded educator contract must use a new prompt version.');
$encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$assert(($payload['input']['candidate_allow_list'][0]['catalog_id'] ?? '') === 'job-1', 'Prompt must contain the canonical job id.');
$assert(($payload['input']['deterministic_scores']['job-1']['total_score'] ?? -1) === $match->score()->totalScore(), 'Prompt must carry the deterministic score breakdown.');
$assert(($payload['input']['skill_gaps']['job-1']['skills_missing'][0]['code'] ?? '') === 'sql', 'Prompt must carry the canonical skill gap.');
$encodedInput = json_encode($payload['input'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$assert(!str_contains($encodedInput, 'email') && !str_contains($encodedInput, 'phone'), 'Prompt input must not contain PII fields; system instructions may prohibit them.');
$assert(!str_contains($encoded, 'Nguyễn Văn Bí Mật') && !str_contains($encoded, 'secret@example.test') && !str_contains($encoded, '0900000000'), 'Prompt must not contain PII values.');
$schema = $payload['output_schema'] ?? [];
$assert(!str_contains(json_encode($schema, JSON_THROW_ON_ERROR), 'score'), 'Gemini output schema must contain no score field.');
$itemSchema = $schema['properties']['items']['items'] ?? [];
$assert(($itemSchema['required'] ?? null) === ['catalog_id', 'analysis', 'evidence_ref_ids'], 'Gemini must return prose and evidence only; canonical skill facts stay backend-owned.');
$assert(($itemSchema['properties']['catalog_id']['enum'] ?? null) === ['job-1'], 'Gemini catalog ids must be constrained by the candidate allow-list.');
$assert(in_array('opportunity:job-1', $itemSchema['properties']['evidence_ref_ids']['items']['enum'] ?? [], true), 'Gemini evidence ids must be constrained by the evidence allow-list.');

$minimal = [[
    'catalog_id' => 'job-1',
    'analysis' => 'Vị trí này hiện chưa phù hợp hoàn toàn vì tổng mức sẵn sàng của bạn còn dưới ngưỡng đề xuất. Điểm mạnh nổi bật là khả năng dùng Python đã vượt ngưỡng yêu cầu của nghề. Kỹ năng SQL vẫn còn khoảng cách so với benchmark nên cần được ưu tiên củng cố. Nếu luyện tập thêm SQL qua hoạt động phù hợp, mức độ sẵn sàng của bạn sẽ rõ ràng hơn.',
    'evidence_ref_ids' => ['skill:python-1', 'skill:sql-1', 'opportunity:job-1'],
]];
$validator = new JobMatchAnalysisValidator();
$grounded = $validator->validate($minimal, [$candidate], ['job-1' => $match], ['job-1' => $gap], $profile);
$assert(($grounded[0]->strengthSkillCodes() ?? null) === ['python'], 'Backend must derive strength codes from deterministic scoring.');
$assert(($grounded[0]->gapSkillCodes() ?? null) === ['sql'], 'Backend must derive gap codes from deterministic scoring.');
$assert(($grounded[0]->gapExplanations()[0]['skill_code'] ?? null) === 'sql', 'Backend must derive grounded gap explanations from Skill Gap data.');

$valid = [[
    'catalog_id' => 'job-1',
    'analysis' => 'Vị trí này hiện chưa phù hợp hoàn toàn vì tổng mức sẵn sàng của bạn còn dưới ngưỡng đề xuất. Điểm mạnh nổi bật là khả năng dùng Python đã vượt ngưỡng yêu cầu của nghề. Kỹ năng SQL vẫn còn khoảng cách so với benchmark nên cần được ưu tiên củng cố. Nếu luyện tập thêm SQL qua hoạt động phù hợp, mức độ sẵn sàng của bạn sẽ rõ ràng hơn.',
    'strength_skill_codes' => ['python'],
    'gap_skill_codes' => ['sql'],
    'gap_explanations' => [['skill_code' => 'sql', 'explanation' => 'SQL hiện thấp hơn ngưỡng benchmark của vị trí và cần được luyện tập thêm.']],
    'evidence_ref_ids' => ['skill:python-1', 'skill:sql-1', 'opportunity:job-1'],
]];
$analyses = $validator->validate($valid, [$candidate], ['job-1' => $match], ['job-1' => $gap], $profile);
$assert(count($analyses) === 1 && $analyses[0]->catalogId() === 'job-1', 'Validator must return a grounded analysis value object.');

$unknownJob = $valid;
$unknownJob[0]['catalog_id'] = 'invented-job';
$rejects(fn () => $validator->validate($unknownJob, [$candidate], ['job-1' => $match], ['job-1' => $gap], $profile), 'Unknown jobs must be rejected.');
$unknownSkill = $valid;
$unknownSkill[0]['gap_skill_codes'] = ['docker'];
$rejects(fn () => $validator->validate($unknownSkill, [$candidate], ['job-1' => $match], ['job-1' => $gap], $profile), 'Unknown gap codes must be rejected.');
$fabricated = $valid;
$fabricated[0]['title'] = 'Vị trí do mô hình tự tạo';
$rejects(fn () => $validator->validate($fabricated, [$candidate], ['job-1' => $match], ['job-1' => $gap], $profile), 'Fabricated canonical fields must be rejected.');
$badEvidence = $valid;
$badEvidence[0]['evidence_ref_ids'] = ['profile:unknown'];
$rejects(fn () => $validator->validate($badEvidence, [$candidate], ['job-1' => $match], ['job-1' => $gap], $profile), 'Unknown evidence refs must be rejected.');
$badSentences = $valid;
$badSentences[0]['analysis'] = 'Phù hợp với bạn.';
$rejects(fn () => $validator->validate($badSentences, [$candidate], ['job-1' => $match], ['job-1' => $gap], $profile), 'Analysis outside 3-4 Vietnamese sentences must be rejected.');
$duplicate = [$valid[0], $valid[0]];
$rejects(fn () => $validator->validate($duplicate, [$candidate], ['job-1' => $match], ['job-1' => $gap], $profile), 'Duplicate jobs must be rejected.');

$lowProfile = LearnerOpportunityProfile::fromInput(new RecommendationInput(
    ['education_band' => 'college', 'skills' => [['code' => 'python', 'score' => 5], ['code' => 'sql', 'score' => 5]]], [], [], [
        ['source_type' => 'skill', 'source_id' => 'python-low', 'observed_at' => null, 'safe_value' => ['code' => 'python']],
        ['source_type' => 'skill', 'source_id' => 'sql-low', 'observed_at' => null, 'safe_value' => ['code' => 'sql']],
    ],
));
$lowMatch = (new JobMatchScorer())->score($lowProfile, $candidate, $role);
$lowGap = (new SkillGapResolver())->resolve($lowMatch);
$lowRequest = JobMatchPromptRegistry::create($lowProfile, [$candidate], ['job-1' => $lowMatch], ['job-1' => $lowGap], $context);
$lowPrompt = json_encode($lowRequest->payload()['instructions'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$assert(str_contains($lowPrompt, 'dưới 40') && str_contains($lowPrompt, 'chưa phù hợp'), 'Prompt must explicitly require detailed not-fit reasoning for a below-threshold near match.');
$lowInvalid = $valid;
$lowInvalid[0]['strength_skill_codes'] = [];
$lowInvalid[0]['gap_skill_codes'] = ['python', 'sql'];
$lowInvalid[0]['gap_explanations'] = [
    ['skill_code' => 'python', 'explanation' => 'Python hiện thấp hơn benchmark bắt buộc của vị trí phân tích dữ liệu.'],
    ['skill_code' => 'sql', 'explanation' => 'SQL hiện thấp hơn benchmark bắt buộc của vị trí phân tích dữ liệu.'],
];
$lowInvalid[0]['analysis'] = 'Vị trí này phù hợp với định hướng phân tích dữ liệu của bạn. Công việc sẽ tạo cơ hội sử dụng Python và SQL trong thực tế. Các nhiệm vụ có thể giúp bạn tích lũy thêm kinh nghiệm chuyên môn. Bạn có thể cân nhắc ứng tuyển để phát triển bản thân.';
$rejects(fn () => $validator->validate($lowInvalid, [$candidate], ['job-1' => $lowMatch], ['job-1' => $lowGap], $lowProfile), 'Below-threshold analysis must explicitly explain that the position is not yet suitable.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "OK job match Gemini contract\n";
