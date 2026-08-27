<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Model\PromptRegistry;
use TalentHub\Learner\Ai\Provider\HttpRecommendationProvider;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once __DIR__ . '/learner_ai_rule_cases_fixture.php';

$config = RecommendationConfig::fromEnvironment([
    'TALENTHUB_AI_ENABLED' => 'true', 'TALENTHUB_AI_PROVIDER' => 'test', 'TALENTHUB_AI_MODEL' => 'model-v1',
    'TALENTHUB_AI_API_URL' => 'https://gateway.example.test/v1', 'TALENTHUB_AI_ALLOWED_HOSTS' => 'gateway.example.test',
    'TALENTHUB_AI_API_KEY' => 'test-key', 'TALENTHUB_AI_MAX_ATTEMPTS' => '1',
]);
$input = learner_rule_input([learner_rule_iot_skill()], [learner_rule_holland()], [learner_rule_technical_activity()], [learner_rule_evaluation()]);
$request = (new PromptRegistry())->create($input, learner_rule_context());
$authorizer = new class implements ProviderAttemptAuthorizer {
    public function beforeAttempt(int $attemptNumber): ConsentDecision {
        $events=[]; foreach(ConsentDecision::REQUIRED_SCOPES as $i=>$s) $events[$s]=['action'=>'granted','policy_version'=>'v1','occurred_at'=>'2026-08-23T00:00:00+00:00','request_id'=>(string)$i];
        return new ConsentDecision($events, '2026-08-23T00:00:00+00:00');
    }
};
$cases = [
    429 => ['rate_limited', '4xx'], 400 => ['provider_rejected', '4xx'], 503 => ['provider_unavailable', '5xx'],
];
foreach ($cases as $status => [$error, $class]) {
    $provider = new HttpRecommendationProvider($config, static fn(): array => ['status'=>$status, 'headers'=>[], 'body'=>'secret raw body']);
    $response = $provider->generate($request, $authorizer);
    if ($response->errorCode() !== $error || $response->safeStatusClass() !== $class) throw new RuntimeException("Incorrect provider mapping for {$status}");
    if (str_contains(json_encode($response->safeMetadata(), JSON_THROW_ON_ERROR), 'secret')) throw new RuntimeException('Provider response leaked body');
}
$network = new HttpRecommendationProvider($config, static function(): array { throw new RuntimeException('dns gateway.internal secret'); });
$response = $network->generate($request, $authorizer);
if ($response->errorCode() !== 'provider_unavailable' || $response->safeStatusClass() !== 'network') throw new RuntimeException('Network failure is not safely classified');

echo "learner_ai_provider_failure_matrix_test: OK\n";
