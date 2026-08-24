<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Model\RoadmapPromptRegistry;
use TalentHub\Learner\Ai\Provider\HttpRoadmapProvider;
use TalentHub\Learner\Ai\Validation\RoadmapAnalysisValidator;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

$appEnv = strtolower(trim((string) (getenv('APP_ENV') ?: '')));
if (!in_array($appEnv, ['local', 'test'], true)) {
    fwrite(STDERR, "Roadmap provider smoke test is restricted to APP_ENV=local|test.\n");
    exit(2);
}

$codes = ['holland', 'mbti', 'disc', 'multiple_intelligence'];
$assessments = $evidence = [];
foreach ($codes as $index => $code) {
    $record = [
        'test_type' => $code,
        'result_code' => 'SYNTHETIC_' . ($index + 1),
        'dimension_scores' => ['dimension_a' => 60 + ($index * 5), 'dimension_b' => 40 - ($index * 3)],
        'submitted_at' => '2026-08-24T00:00:00.000000+00:00',
    ];
    $assessments[] = $record;
    $evidence[] = [
        'source_type' => 'assessment',
        'source_id' => 'synthetic-assessment-' . ($index + 1),
        'observed_at' => $record['submitted_at'],
        'safe_value' => $record,
    ];
}
$input = new RecommendationInput(
    ['profile' => ['study_status' => 'synthetic'], 'assessments' => $assessments, 'skills' => [], 'activities' => [], 'evaluations' => [], 'opportunities' => []],
    ['assessment' => '2026-08-24T00:00:00.000000+00:00'],
    ['allowed_scopes' => ['assessment'], 'missing_consent_scopes' => ['activity', 'evaluation', 'skills']],
    $evidence,
);
$context = new RecommendationContext(['assessment'], 'synthetic-smoke-request', 'synthetic-smoke-idempotency', 'synthetic-smoke-student');
$request = (new RoadmapPromptRegistry())->create($input, $context);
$authorizer = new class implements ProviderAttemptAuthorizer {
    public function beforeAttempt(int $attemptNumber): ConsentDecision
    {
        if ($attemptNumber < 1) throw new InvalidArgumentException('Attempt number must be positive.');
        return new ConsentDecision([
            'assessment' => ['action' => 'granted', 'policy_version' => 'synthetic-smoke-v1', 'occurred_at' => '2026-08-24T00:00:00+00:00', 'request_id' => 'synthetic-smoke-consent'],
        ], '2026-08-24T00:00:01+00:00');
    }
};

$safe = ['provider' => null, 'model' => null, 'success' => false, 'phase_count' => 0, 'task_count' => 0, 'response_hash_prefix' => null, 'error_code' => null];
try {
    $config = RecommendationConfig::fromEnvironment([]);
    $safe['provider'] = $config->provider();
    $safe['model'] = $config->model();
    $response = (new HttpRoadmapProvider($config))->generate($request, $authorizer);
    $safe['response_hash_prefix'] = $response->responseHash() === null ? null : substr($response->responseHash(), 0, 12);
    if (!$response->isSuccess()) {
        $safe['error_code'] = $response->errorCode();
    } else {
        $validator = new RoadmapAnalysisValidator($request->evidenceReferenceIds(), []);
        $analysis = $validator->fromProviderPayload($response->payload(), [
            'origin' => 'model', 'provider' => (string) $config->provider(), 'model_version' => (string) $config->model(),
            'prompt_version' => $request->promptVersion(), 'confidence_band' => 'medium',
            'provider_request_id' => $response->providerRequestId(), 'response_hash' => $response->responseHash(),
        ]);
        $validator->validate($analysis);
        $safe['success'] = true;
        $safe['phase_count'] = count($analysis->phases());
        $safe['task_count'] = array_sum(array_map(static fn ($phase): int => count($phase->tasks()), $analysis->phases()));
    }
} catch (Throwable) {
    $safe['error_code'] = 'smoke_validation_failed';
}

echo json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($safe['success'] ? 0 : 1);
