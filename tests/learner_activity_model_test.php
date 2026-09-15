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
    public array $requests = [];
    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse {
        $this->requests[] = $request->payload();
        $authorizer->beforeAttempt(1);
        if ($this->mode === 'fail') return ProviderResponse::failure('provider_unavailable');
        return ProviderResponse::success([['activity_id'=>$this->mode === 'invent' ? 'unknown' : 'a', 'analysis'=>'Hoạt động có thể là cơ hội luyện tập kỹ năng teamwork. Bạn nên đặt mục tiêu nhỏ và xin góp ý sau mỗi buổi để theo dõi tiến bộ.', 'evidence_ref_ids'=>['activity:a', 'skill:teamwork']]]);
    }
};
$input = new RecommendationInput([
    'skills'=>[['code'=>'teamwork','score'=>40]],
    'certificates'=>[['id'=>'cert-1','title'=>'Teamwork Basics','issuer'=>'External Org','issue_date'=>'2026-08-01','verification_status'=>'unverified']],
], [], [], [[
    'source_type'=>'certificate', 'source_id'=>'cert-1', 'observed_at'=>'2026-08-01',
    'safe_value'=>['title'=>'Teamwork Basics','issuer'=>'External Org','issue_date'=>'2026-08-01','verification_status'=>'unverified'],
]]);
$items = [['activity_id'=>'a','title'=>'Workshop','score'=>66,'fit_reasons'=>['teamwork'],'skills_to_develop'=>['teamwork']]];
$engine = new ModelActivityMatchEngine($provider, $authorizer);
$result = $engine->generate($input, $items);
if ($result[0]['score'] !== 66 || !str_contains($result[0]['why_fit'], 'teamwork')) throw new RuntimeException('Grounded model result missing');
$activityPrompt = $provider->requests[0]['input'] ?? [];
if (($activityPrompt['certificates'][0]['verification_status'] ?? null) !== 'unverified') throw new RuntimeException('Activity prompt must preserve unverified certificate context');
if (($activityPrompt['assessment_signals'] ?? null) !== []) throw new RuntimeException('Activity prompt lost assessment signal field');
if (($result[0]['score'] ?? null) !== 66) throw new RuntimeException('Certificate context must not change deterministic activity score');
$payloadOnly = new RecommendationInput([
    'skills'=>[['code'=>'teamwork','score'=>40]],
    'certificates'=>[['id'=>'cert-1','title'=>'Teamwork Basics','verification_status'=>'unverified']],
], [], [], []);
$payloadOnlyResult = $engine->generate($payloadOnly, $items);
if (($provider->requests[1]['input']['certificates'] ?? null) !== []) throw new RuntimeException('Payload-only certificates must not bypass evidence requirements');
if ($payloadOnlyResult[0]['score'] !== 66) throw new RuntimeException('Missing certificate evidence must not change deterministic score');
foreach (['invent','fail'] as $mode) {
    $provider->mode = $mode;
    $rejected = false;
    try { $engine->generate($input, $items); } catch (RuntimeException|InvalidArgumentException $e) { $rejected = true; }
    if (!$rejected) throw new RuntimeException('Must reject unknown activity/provider failure without fallback');
}
echo "[PASS] Model analysis; immutable score; unknown activity rejected; no fallback on failure\n";

$emptyProvider = new class implements RecommendationProvider {
    public string $mode = 'ok';
    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse {
        $authorizer->beforeAttempt(1);
        if ($this->mode === 'fail') return ProviderResponse::failure('provider_unavailable');
        $data = $request->payload()['input'];
        if ($data['candidates'][0]['skills'] !== []) throw new RuntimeException('Missing tags must remain missing');
        $row = ['analysis'=>'Hoạt động hiện chưa có thông tin kỹ năng để đối chiếu với nhu cầu rèn luyện teamwork của bạn. Bạn có thể xem nội dung và cân nhắc tham gia theo sở thích để mở rộng trải nghiệm.', 'evidence_ref_ids'=>['activity:a','skill:teamwork']];
        if ($this->mode === 'invent') $row['evidence_ref_ids'][] = 'activity:unknown';
        if ($this->mode === 'missing_need') $row['evidence_ref_ids'] = ['activity:a','skill:php'];
        if ($this->mode === 'extra') $row['score'] = 90;
        if ($this->mode === 'blank') $row['analysis'] = str_repeat(' ', 100);
        return ProviderResponse::success($this->mode === 'duplicate' ? [$row,$row] : [$row]);
    }
};
$emptyInput = new RecommendationInput(['skills'=>[['code'=>'teamwork','score'=>40],['code'=>'php','score'=>90]]], [], [], []);
$emptyEngine = new ModelActivityMatchEngine($emptyProvider, $authorizer);
$candidates = [new \TalentHub\Learner\Ai\Matching\ActivityCandidate('a', 'Workshop', [], '')];
$empty = $emptyEngine->explainNoMatches($emptyInput, $candidates);
if ($empty['no_match_reason'] !== 'skill_mismatch') throw new RuntimeException('Missing activity tags must not invent a skill overlap');
foreach (['invent','missing_need','extra','blank','duplicate','fail'] as $mode) {
    $emptyProvider->mode = $mode;
    $rejected = false;
    try { $emptyEngine->explainNoMatches($emptyInput, $candidates); } catch (RuntimeException|InvalidArgumentException $e) { $rejected = true; }
    if (!$rejected) throw new RuntimeException('Invalid empty activity analysis was accepted: ' . $mode);
}
echo "[PASS] Empty analysis uses actual tags and rejects invalid evidence, output and provider failures\n";
