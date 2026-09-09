<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Provider\ProviderRequest;
use TalentHub\Learner\Ai\Provider\ProviderResponse;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Model\ModelActivityMatchEngine;
$authorizer = new class implements ProviderAttemptAuthorizer {
    public function beforeAttempt(int $attemptNumber): ConsentDecision { return new ConsentDecision([], '2026-09-08', ConsentDecision::REQUIRED_SCOPES); }
};
$provider = new class implements RecommendationProvider {
    public string $mode = 'ok';
    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse {
        $authorizer->beforeAttempt(1);
        if ($this->mode === 'fail') return ProviderResponse::failure('provider_unavailable');
        return ProviderResponse::success([['activity_id'=>$this->mode === 'invent' ? 'unknown' : 'a', 'analysis'=>'Hoạt động có thể là cơ hội luyện tập kỹ năng teamwork. Bạn nên đặt mục tiêu nhỏ và xin góp ý sau mỗi buổi để theo dõi tiến bộ.', 'evidence_ref_ids'=>['activity:a', 'skill:teamwork']]]);
    }
};
$input = new RecommendationInput(['skills'=>[['code'=>'teamwork','score'=>40]]], [], [], []);
$items = [['activity_id'=>'a','title'=>'Workshop','score'=>66,'fit_reasons'=>['teamwork'],'skills_to_develop'=>['teamwork']]];
$engine = new ModelActivityMatchEngine($provider, $authorizer);
$result = $engine->generate($input, $items);
if ($result[0]['score'] !== 66 || !str_contains($result[0]['why_fit'], 'teamwork')) throw new RuntimeException('Grounded model result missing');
foreach (['invent','fail'] as $mode) {
    $provider->mode = $mode;
    $rejected = false;
    try { $engine->generate($input, $items); } catch (RuntimeException|InvalidArgumentException $e) { $rejected = true; }
    if (!$rejected) throw new RuntimeException('Must reject unknown activity/provider failure without fallback');
}
echo "[PASS] Model analysis; immutable score; unknown activity rejected; no fallback on failure\n";
