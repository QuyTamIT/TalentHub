<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\CareerRoleBenchmark;
use TalentHub\Learner\Ai\Matching\JobMatchScorer;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Matching\SkillGapResolver;
use TalentHub\Learner\Ai\Model\ModelJobMatchEngine;
use TalentHub\Learner\Ai\Provider\ProviderRequest;
use TalentHub\Learner\Ai\Provider\ProviderResponse;

$failures = [];
$assert = static function (bool $ok, string $message) use (&$failures): void { if (!$ok) $failures[] = $message; };
$rejects = static function (callable $fn, string $message) use (&$failures): void { try { $fn(); $failures[] = $message; } catch (InvalidArgumentException) {} };

$role = new CareerRoleBenchmark('backend_developer', 'Backend Developer', 'technology', [
    ['code' => 'php', 'label' => 'PHP', 'minimum_score' => 70, 'weight' => 60.0, 'required' => true],
    ['code' => 'sql', 'label' => 'SQL', 'minimum_score' => 70, 'weight' => 40.0, 'required' => true],
], []);
$candidate = OpportunityCandidate::fromEvidence(['source_type' => 'opportunity', 'source_id' => 'job-2', 'safe_value' => [
    'catalog_id' => 'job-2', 'item_type' => 'internship', 'enterprise_id' => 'ent-2', 'title' => 'Thực tập Backend PHP',
    'provider_name' => 'Công ty Phần mềm', 'status' => 'active', 'url' => '/app/learner/opportunity.php?type=internship&id=job-2',
    'required_skills' => [['code' => 'php', 'minimum_score' => 70, 'label' => 'PHP'], ['code' => 'sql', 'minimum_score' => 70, 'label' => 'SQL']],
]]);
$profile = LearnerOpportunityProfile::fromInput(new RecommendationInput(['skills' => [['code' => 'php', 'score' => 85], ['code' => 'sql', 'score' => 45]]], [], [], [
    ['source_type' => 'skill', 'source_id' => 'php-1', 'observed_at' => null, 'safe_value' => ['code' => 'php']],
    ['source_type' => 'skill', 'source_id' => 'sql-2', 'observed_at' => null, 'safe_value' => ['code' => 'sql']],
]));
$match = (new JobMatchScorer())->score($profile, $candidate, $role);
$gap = (new SkillGapResolver())->resolve($match);
$context = new RecommendationContext(['skills'], 'req-2', 'idem-2', 'student-2');
$valid = [[
    'catalog_id' => 'job-2',
    'analysis' => 'Vị trí Backend PHP hiện chưa phù hợp hoàn toàn vì tổng mức sẵn sàng còn dưới ngưỡng đề xuất. Kỹ năng PHP là điểm mạnh vì đã vượt ngưỡng benchmark của nghề. SQL vẫn thấp hơn yêu cầu nên đây là khoảng kỹ năng cần ưu tiên cải thiện. Việc luyện tập truy vấn dữ liệu sẽ giúp hồ sơ sẵn sàng hơn cho vị trí này.',
    'strength_skill_codes' => ['php'], 'gap_skill_codes' => ['sql'],
    'gap_explanations' => [['skill_code' => 'sql', 'explanation' => 'SQL chưa đạt ngưỡng benchmark nên cần tăng cường thực hành truy vấn dữ liệu.']],
    'evidence_ref_ids' => ['skill:php-1', 'skill:sql-2', 'opportunity:job-2'],
]];
$authorizer = new class implements ProviderAttemptAuthorizer {
    public int $calls = 0;
    public function beforeAttempt(int $attemptNumber): ConsentDecision { $this->calls++; return new ConsentDecision([], '2026-08-31T00:00:00+00:00', ConsentDecision::REQUIRED_SCOPES); }
};
$provider = new class(ProviderResponse::success($valid)) implements RecommendationProvider {
    public int $calls = 0;
    public function __construct(private ProviderResponse $response) {}
    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse { $this->calls++; $authorizer->beforeAttempt(1); return $this->response; }
};
$engine = new ModelJobMatchEngine($provider, $authorizer);
$out = $engine->generate($profile, [$candidate], ['job-2' => $match], ['job-2' => $gap], $context);
$assert(count($out) === 1 && $out[0]->catalogId() === 'job-2', 'Engine must return validated analyses.');
$assert($provider->calls === 1 && $authorizer->calls === 1, 'Engine must make one provider call and pass the consent authorizer.');

$failedProvider = new class implements RecommendationProvider {
    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse { $authorizer->beforeAttempt(1); return ProviderResponse::failure('provider_unavailable'); }
};
$rejects(fn () => (new ModelJobMatchEngine($failedProvider, $authorizer))->generate($profile, [$candidate], ['job-2' => $match], ['job-2' => $gap], $context), 'Provider failure must be surfaced.');
$fabricated = $valid; $fabricated[0]['url'] = 'https://evil.example/job';
$badProvider = new class(ProviderResponse::success($fabricated)) implements RecommendationProvider {
    public function __construct(private ProviderResponse $response) {}
    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse { $authorizer->beforeAttempt(1); return $this->response; }
};
$rejects(fn () => (new ModelJobMatchEngine($badProvider, $authorizer))->generate($profile, [$candidate], ['job-2' => $match], ['job-2' => $gap], $context), 'Fabricated canonical fields must be rejected.');

if ($failures !== []) { fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n"); exit(1); }
echo "OK job match model engine\n";
