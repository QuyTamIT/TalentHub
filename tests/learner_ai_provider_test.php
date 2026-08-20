<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Model\ModelRecommendationEngine;
use TalentHub\Learner\Ai\Model\PromptRegistry;
use TalentHub\Learner\Ai\Provider\FakeRecommendationProvider;
use TalentHub\Learner\Ai\Provider\HttpRecommendationProvider;
use TalentHub\Learner\Ai\Provider\ProviderResponse;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function provider_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

function provider_expect(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException("Assertion failed: {$message}");
}

provider_assert(class_exists(RecommendationConfig::class), 'provider config exists');
provider_assert(interface_exists('TalentHub\\Learner\\Ai\\Contracts\\RecommendationProvider'), 'provider contract exists');
provider_assert(class_exists(ModelRecommendationEngine::class), 'model engine exists');

$disabled = RecommendationConfig::fromEnvironment([]);
provider_assert($disabled->enabled() === false, 'AI provider is disabled by default');
provider_expect(
    static fn (): RecommendationConfig => RecommendationConfig::fromEnvironment(['TALENTHUB_AI_ENABLED' => 'true']),
    'enabled provider rejects incomplete configuration',
);

$localEnvironment = [
    'APP_ENV' => 'local',
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => '9router_gemini',
    'TALENTHUB_AI_MODEL' => 'ag/gemini-3.7-flash-high',
    'TALENTHUB_AI_API_URL' => 'http://localhost:20128/v1/chat/completions',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'localhost',
    'TALENTHUB_AI_API_KEY' => 'local-test-key',
];
$localConfig = RecommendationConfig::fromEnvironment($localEnvironment);
provider_assert($localConfig->enabled(), 'local 9Router loopback is accepted');

$testLoopbackConfig = RecommendationConfig::fromEnvironment(array_replace($localEnvironment, [
    'APP_ENV' => 'test',
    'TALENTHUB_AI_API_URL' => 'http://127.0.0.1:20128/v1/chat/completions',
    'TALENTHUB_AI_ALLOWED_HOSTS' => '127.0.0.1',
]));
provider_assert($testLoopbackConfig->enabled(), 'test 9Router loopback is accepted');

provider_expect(
    static fn (): RecommendationConfig => RecommendationConfig::fromEnvironment(array_replace($localEnvironment, [
        'APP_ENV' => 'production',
    ])),
    'production rejects HTTP loopback',
);
provider_expect(
    static fn (): RecommendationConfig => RecommendationConfig::fromEnvironment(array_replace($localEnvironment, [
        'TALENTHUB_AI_API_URL' => 'http://192.168.1.20:20128/v1/chat/completions',
        'TALENTHUB_AI_ALLOWED_HOSTS' => '192.168.1.20',
    ])),
    'local environment rejects non-loopback HTTP hosts',
);
provider_expect(
    static fn (): RecommendationConfig => RecommendationConfig::fromEnvironment(array_replace($localEnvironment, [
        'TALENTHUB_AI_API_URL' => 'http://localhost:8080/v1/chat/completions',
    ])),
    'local loopback rejects an unapproved port',
);
provider_expect(
    static fn (): RecommendationConfig => RecommendationConfig::fromEnvironment(array_replace($localEnvironment, [
        'TALENTHUB_AI_API_URL' => 'http://user:pass@localhost:20128/v1/chat/completions',
    ])),
    'local loopback rejects URL credentials',
);
provider_expect(
    static fn (): RecommendationConfig => RecommendationConfig::fromEnvironment(array_replace($localEnvironment, [
        'TALENTHUB_AI_ALLOWED_HOSTS' => '127.0.0.1',
    ])),
    'local loopback requires an exact hostname allowlist match',
);

$config = RecommendationConfig::fromEnvironment([
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => 'fake',
    'TALENTHUB_AI_MODEL' => 'learner-test-1',
    'TALENTHUB_AI_API_URL' => 'https://ai.example.test/v1/recommendations',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'ai.example.test',
    'TALENTHUB_AI_API_KEY' => 'secret-provider-key',
    'TALENTHUB_AI_TIMEOUT_SECONDS' => '2',
    'TALENTHUB_AI_MAX_ATTEMPTS' => '2',
    'TALENTHUB_AI_PER_STUDENT_LIMIT' => '2',
    'TALENTHUB_AI_GLOBAL_LIMIT' => '3',
]);
provider_assert($config->timeoutSeconds() === 2 && $config->maxAttempts() === 2, 'provider configuration keeps bounded timeout and retries');
provider_assert(!str_contains(json_encode($config->diagnostics(), JSON_THROW_ON_ERROR), 'secret-provider-key'), 'provider diagnostics never expose an API key');

$input = new RecommendationInput(
    ['skills' => [['code' => 'iot', 'verification_status' => 'verified', 'email' => 'private@example.test']]],
    ['skills' => '2026-08-16T00:00:00.000000+00:00'],
    ['allowed_scopes' => ['skills']],
    [[
        'source_type' => 'skill', 'source_id' => 'private-skill-id', 'observed_at' => '2026-08-16T00:00:00.000000+00:00',
        'safe_value' => ['code' => 'iot', 'verification_status' => 'verified', 'email' => 'private@example.test'],
    ]],
);
$context = new RecommendationContext(['skills'], 'request-provider-0001', 'idempotency-provider-0001', 'student-provider-1');
$promptRegistry = new PromptRegistry();
$request = $promptRegistry->create($input, $context);
provider_assert($request->promptVersion() === 'learner-recommendation-1.0.0', 'provider request records a versioned prompt');
provider_assert($request->evidenceReferenceIds() === ['evidence-001'], 'provider uses opaque evidence references');
$requestJson = json_encode($request->payload(), JSON_THROW_ON_ERROR);
provider_assert(!str_contains($requestJson, 'private-skill-id') && !str_contains($requestJson, 'private@example.test'), 'provider request excludes private IDs and values');

$fallback = new class implements RecommendationEngine {
    public int $calls = 0;
    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult
    {
        $this->calls += 1;
        return new RecommendationResult('rule', 'fallback-rules-1.0.0', null, null, null, 'provider_unavailable', [
            new RecommendationItem(
                'strength', 'Gợi ý quy tắc', 'Dựa trên kỹ năng đã xác minh.', 20, 'medium',
                ['type' => 'develop_skill', 'skill_code' => 'iot'],
                [new RecommendationEvidence('skill', 'private-skill-id', null, 'rule_source', ['code' => 'iot'])],
            ),
        ]);
    }
};
$fake = new FakeRecommendationProvider(ProviderResponse::success([[
    'item_type' => 'strength', 'title' => 'Phát triển kỹ năng IoT', 'summary' => 'Bạn có thể tiếp tục rèn kỹ năng đã xác minh.',
    'priority' => 20, 'confidence_band' => 'medium', 'action' => ['type' => 'develop_skill', 'skill_code' => 'iot'],
    'evidence_ref_ids' => ['evidence-001'],
]]));
$engine = new ModelRecommendationEngine(
    $fake,
    $fallback,
    $promptRegistry,
    new RecommendationRateLimiter(2, 3, 60, static fn (): int => 1_000),
    $config,
    new RecommendationResultValidator(),
);
$modelResult = $engine->generate($input, $context);
provider_assert($modelResult->engineType() === 'model' && $modelResult->items()[0]->evidence()[0]->sourceId() === 'private-skill-id', 'model output resolves only supplied evidence references');

$unsafeProvider = new FakeRecommendationProvider(ProviderResponse::success([[
    'item_type' => 'strength', 'title' => 'Bạn chắc chắn được tuyển', 'summary' => 'Nội dung không an toàn',
    'priority' => 20, 'confidence_band' => 'medium', 'action' => ['type' => 'develop_skill', 'skill_code' => 'iot'],
    'evidence_ref_ids' => ['evidence-001'],
]]));
$unsafeEngine = new ModelRecommendationEngine(
    $unsafeProvider, $fallback, $promptRegistry,
    new RecommendationRateLimiter(2, 3, 60, static fn (): int => 1_000), $config, new RecommendationResultValidator(),
);
provider_assert($unsafeEngine->generate($input, $context)->engineType() === 'rule', 'unsafe provider output falls back to rules');

$httpCalls = [];
$http = new HttpRecommendationProvider($config, static function (string $url, array $headers, string $body, int $timeout) use (&$httpCalls): array {
    $httpCalls[] = compact('url', 'headers', 'body', 'timeout');
    return count($httpCalls) === 1
        ? ['status' => 502, 'headers' => [], 'body' => 'temporarily unavailable']
        : ['status' => 200, 'headers' => [], 'body' => json_encode(['items' => []], JSON_THROW_ON_ERROR)];
});
$httpResponse = $http->generate($request);
provider_assert($httpResponse->isSuccess() && count($httpCalls) === 2 && $httpCalls[0]['timeout'] === 2, 'HTTP provider makes one bounded 502 retry using configured timeout');
provider_assert(($httpCalls[0]['headers']['Authorization'] ?? '') === 'Bearer secret-provider-key', 'HTTP provider supplies its authorization only to the injected transport');

$rateCalls = 0;
$rateLimited = new HttpRecommendationProvider($config, static function () use (&$rateCalls): array {
    $rateCalls += 1;
    return ['status' => 429, 'headers' => ['retry-after' => '3'], 'body' => 'rate limited'];
});
$rateResponse = $rateLimited->generate($request);
provider_assert(!$rateResponse->isSuccess() && $rateResponse->errorCode() === 'rate_limited' && $rateResponse->retryAfterSeconds() === 3 && $rateCalls === 1, 'HTTP provider honors 429 retry-after without a busy-loop');

$malformed = new HttpRecommendationProvider($config, static fn (): array => ['status' => 200, 'headers' => [], 'body' => '{not-json']);
provider_assert($malformed->generate($request)->errorCode() === 'malformed_response', 'malformed provider JSON is a typed internal failure');
$outage = new HttpRecommendationProvider($config, static function (): array { throw new RuntimeException('network unavailable'); });
provider_assert($outage->generate($request)->errorCode() === 'provider_unavailable', 'provider outage is a typed internal failure');

$limiter = new RecommendationRateLimiter(1, 2, 60, static fn (): int => 1_000);
provider_assert($limiter->acquire('student-a')->allowed(), 'first per-student provider request is allowed');
provider_assert(!$limiter->acquire('student-a')->allowed(), 'per-student limit is enforced');
provider_assert($limiter->acquire('student-b')->allowed(), 'global limit permits a second learner');
provider_assert(!$limiter->acquire('student-c')->allowed(), 'global limit is enforced');

echo "learner_ai_provider_test: OK\n";
