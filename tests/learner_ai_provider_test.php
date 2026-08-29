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
require_once __DIR__ . '/learner_ai_test_consent.php';

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
$slowLocalConfig = RecommendationConfig::fromEnvironment(array_replace($localEnvironment, [
    'TALENTHUB_AI_TIMEOUT_SECONDS' => '30',
]));
provider_assert($slowLocalConfig->timeoutSeconds() === 30, 'local model inference supports a bounded 30-second timeout');
provider_expect(
    static fn (): RecommendationConfig => RecommendationConfig::fromEnvironment(array_replace($localEnvironment, [
        'TALENTHUB_AI_TIMEOUT_SECONDS' => '31',
    ])),
    'provider timeout remains capped above 30 seconds',
);

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
    'APP_ENV' => 'test',
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
    'TALENTHUB_AI_STRICT_MODE_OVERRIDE' => 'false',
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
$context = new RecommendationContext(['activity', 'assessment', 'evaluation', 'skills'], 'request-provider-0001', 'idempotency-provider-0001', 'student-provider-1');
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
    learner_ai_test_consent_gate(),
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
    learner_ai_test_consent_gate(),
);
provider_assert($unsafeEngine->generate($input, $context)->engineType() === 'rule', 'unsafe provider output falls back to rules');

$emptyEngine = new ModelRecommendationEngine(
    new FakeRecommendationProvider(ProviderResponse::success([])),
    $fallback,
    $promptRegistry,
    new RecommendationRateLimiter(2, 3, 60, static fn (): int => 1_000),
    $config,
    new RecommendationResultValidator(),
    learner_ai_test_consent_gate(),
);
$emptyResult = $emptyEngine->generate($input, $context);
provider_assert(
    $emptyResult->engineType() === 'rule' && $emptyResult->fallbackReason() === 'invalid_model_response',
    'empty provider output falls back to rules instead of completing an itemless model run',
);

$httpCalls = [];
$http = new HttpRecommendationProvider($config, static function (string $url, array $headers, string $body, int $timeout) use (&$httpCalls): array {
    $httpCalls[] = compact('url', 'headers', 'body', 'timeout');
    return count($httpCalls) === 1
        ? ['status' => 502, 'headers' => [], 'body' => 'temporarily unavailable']
        : ['status' => 200, 'headers' => [], 'body' => json_encode(['items' => []], JSON_THROW_ON_ERROR)];
});
$httpResponse = $http->generate($request, learner_ai_test_provider_authorizer($input, $context));
provider_assert($httpResponse->isSuccess() && count($httpCalls) === 2 && $httpCalls[0]['timeout'] === 2, 'HTTP provider makes one bounded 502 retry using configured timeout');
provider_assert(($httpCalls[0]['headers']['Authorization'] ?? '') === 'Bearer secret-provider-key', 'HTTP provider supplies its authorization only to the injected transport');

$geminiConfig = RecommendationConfig::fromEnvironment([
    'APP_ENV' => 'test',
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => 'gemini',
    'TALENTHUB_AI_MODEL' => 'gemini-3.7-flash',
    'TALENTHUB_AI_API_URL' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.7-flash:generateContent',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'generativelanguage.googleapis.com',
    'TALENTHUB_AI_API_KEY' => 'gemini-test-key-never-log',
]);
$geminiCalls = [];
$gemini = new HttpRecommendationProvider($geminiConfig, static function (string $url, array $headers, string $body, int $timeout) use (&$geminiCalls): array {
    $geminiCalls[] = compact('url', 'headers', 'body', 'timeout');
    return ['status' => 200, 'headers' => [], 'body' => json_encode(['items' => []], JSON_THROW_ON_ERROR)];
});
$geminiResponse = $gemini->generate($request, learner_ai_test_provider_authorizer($input, $context));
provider_assert($geminiResponse->isSuccess(), 'native Gemini recommendation request succeeds');
$geminiCall = $geminiCalls[0] ?? [];
provider_assert(($geminiCall['headers']['x-goog-api-key'] ?? '') === 'gemini-test-key-never-log', 'native Gemini API key uses x-goog-api-key');
provider_assert(!isset($geminiCall['headers']['Authorization']), 'native Gemini request does not use Bearer authorization');
$geminiPayload = json_decode((string) ($geminiCall['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
provider_assert(isset($geminiPayload['systemInstruction']['parts'][0]['text']), 'native Gemini recommendation includes system instruction');
provider_assert(isset($geminiPayload['contents'][0]['parts'][0]['text']), 'native Gemini recommendation includes user contents');
provider_assert(($geminiPayload['generationConfig']['responseFormat']['text']['mimeType'] ?? null) === 'APPLICATION_JSON', 'native Gemini recommendation uses the official JSON response-format enum');
provider_assert(
    ($geminiPayload['generationConfig']['responseFormat']['text']['schema'] ?? null) === ($request->payload()['output_schema'] ?? null),
    'native Gemini recommendation sends the validated output schema in Gemini responseFormat rather than only inside prompt text',
);
provider_assert(($geminiPayload['generationConfig']['maxOutputTokens'] ?? 0) >= 8192, 'native Gemini recommendation reserves enough output tokens for a complete structured response');
provider_assert(
    str_contains((string) ($geminiPayload['systemInstruction']['parts'][0]['text'] ?? ''), 'item_type must be one of strength, improvement, development, activity, roadmap, group, community.'),
    'native Gemini recommendation explicitly constrains item types to the domain allow-list',
);
provider_assert(
    ($request->payload()['output_schema']['properties']['items']['items']['properties']['item_type']['enum'] ?? null) === ['strength', 'improvement', 'development', 'activity', 'roadmap', 'group', 'community'],
    'provider request schema constrains item types to the domain allow-list',
);
provider_assert(
    str_contains((string) ($geminiPayload['systemInstruction']['parts'][0]['text'] ?? ''), 'action.type must be one of develop_skill, continue_technical_activity, practice_presentation, explore_career_group, register_activity, join_group, open_catalog_item.'),
    'native Gemini recommendation explicitly constrains action types to the validator allow-list',
);
provider_assert(
    count($request->payload()['output_schema']['properties']['items']['items']['properties']['action']['oneOf'] ?? []) === 7,
    'provider request schema defines each supported action shape',
);
provider_assert(!isset($geminiPayload['messages']), 'native Gemini recommendation does not use chat-completions messages');

$rateCalls = 0;
$rateLimited = new HttpRecommendationProvider($config, static function () use (&$rateCalls): array {
    $rateCalls += 1;
    return ['status' => 429, 'headers' => ['retry-after' => '3'], 'body' => 'rate limited'];
});
$rateResponse = $rateLimited->generate($request, learner_ai_test_provider_authorizer($input, $context));
provider_assert(!$rateResponse->isSuccess() && $rateResponse->errorCode() === 'rate_limited' && $rateResponse->retryAfterSeconds() === 3 && $rateCalls === 1, 'HTTP provider honors 429 retry-after without a busy-loop');

$malformed = new HttpRecommendationProvider($config, static fn (): array => ['status' => 200, 'headers' => [], 'body' => '{not-json']);
provider_assert($malformed->generate($request, learner_ai_test_provider_authorizer($input, $context))->errorCode() === 'malformed_response', 'malformed provider JSON is a typed internal failure');
$outage = new HttpRecommendationProvider($config, static function (): array { throw new RuntimeException('network unavailable'); });
provider_assert($outage->generate($request, learner_ai_test_provider_authorizer($input, $context))->errorCode() === 'provider_unavailable', 'provider outage is a typed internal failure');

$limiter = new RecommendationRateLimiter(1, 2, 60, static fn (): int => 1_000);
provider_assert($limiter->acquire('student-a')->allowed(), 'first per-student provider request is allowed');
provider_assert(!$limiter->acquire('student-a')->allowed(), 'per-student limit is enforced');
provider_assert($limiter->acquire('student-b')->allowed(), 'global limit permits a second learner');
provider_assert(!$limiter->acquire('student-c')->allowed(), 'global limit is enforced');

echo "learner_ai_provider_test: OK\n";
