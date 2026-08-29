<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Consent\ProviderConsentDenied;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Provider\HttpRoadmapProvider;
use TalentHub\Learner\Ai\Provider\ProviderRequest;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once __DIR__ . '/fixtures/learner_ai_roadmap_v1.php';

function roadmap_provider_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

function roadmap_provider_config(int $attempts = 2): RecommendationConfig
{
    return RecommendationConfig::fromEnvironment([
        'APP_ENV' => 'test', 'TALENTHUB_AI_ENABLED' => 'true', 'TALENTHUB_AI_PROVIDER' => '9router_gemini',
        'TALENTHUB_AI_MODEL' => 'ag/gemini-3.7-flash-low', 'TALENTHUB_AI_API_URL' => 'http://127.0.0.1:20128/v1/chat/completions',
        'TALENTHUB_AI_API_KEY' => 'test-key-never-log', 'TALENTHUB_AI_ALLOWED_HOSTS' => '127.0.0.1',
        'TALENTHUB_AI_TIMEOUT_SECONDS' => '3', 'TALENTHUB_AI_MAX_ATTEMPTS' => (string) $attempts,
        'TALENTHUB_AI_ROADMAP_TIMEOUT_SECONDS' => '3',
    ]);
}

function roadmap_provider_request(): ProviderRequest
{
    return new ProviderRequest('learner-roadmap-prompt-1.0.0', [
        'instructions' => ['Chỉ trả về JSON hợp lệ.'],
        'contract_version' => 'learner-roadmap-1.0.0',
        'output_schema' => [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'phases' => [
                    'type' => 'array',
                    'uniqueItems' => true,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'tasks' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'action' => [
                                            'oneOf' => [[
                                                'type' => 'object',
                                                'properties' => [
                                                    'type' => ['const' => 'self_task'],
                                                    'title' => ['type' => 'string', 'minLength' => 1, 'pattern' => '.+'],
                                                ],
                                            ]],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'input' => ['assessments' => [['test_type' => 'holland', 'dimension_scores' => ['R' => 80]]]],
        'evidence' => [['reference_id' => 'evidence-001']],
    ], [
        'evidence-001' => new RecommendationEvidence('assessment', 'result-1', '2026-08-20T00:00:00+00:00', 'provider_source', ['test_type' => 'holland']),
    ]);
}

function roadmap_provider_authorizer(?int $denyAttempt = null): ProviderAttemptAuthorizer
{
    return new class($denyAttempt) implements ProviderAttemptAuthorizer {
        public int $calls = 0;
        public function __construct(private readonly ?int $denyAttempt) {}
        public function beforeAttempt(int $attemptNumber): ConsentDecision
        {
            $this->calls++;
            if ($this->denyAttempt === $attemptNumber) throw new ProviderConsentDenied('consent_revoked');
            $events = [];
            foreach (ConsentDecision::REQUIRED_SCOPES as $scope) {
                $events[$scope] = ['action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-24T00:00:00+00:00', 'request_id' => 'req'];
            }
            return new ConsentDecision($events, '2026-08-24T00:00:01+00:00');
        }
    };
}

roadmap_provider_assert(class_exists(HttpRoadmapProvider::class), 'HTTP roadmap provider is loaded');
$fixture = learner_ai_roadmap_provider_fixture();
$rawDirect = json_encode($fixture, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$captured = [];
$direct = new HttpRoadmapProvider(roadmap_provider_config(), static function ($url, $headers, $body, $timeout) use (&$captured, $rawDirect): array {
    $captured = compact('url', 'headers', 'body', 'timeout');
    return ['status' => 200, 'headers' => ['X-Request-Id' => 'router_req_123'], 'body' => $rawDirect];
});
$directResponse = $direct->generate(roadmap_provider_request(), roadmap_provider_authorizer());
roadmap_provider_assert($directResponse->isSuccess(), 'direct structured JSON succeeds');
roadmap_provider_assert($directResponse->payload() === $fixture, 'direct payload is preserved');
roadmap_provider_assert($directResponse->providerRequestId() === 'router_req_123', 'safe provider request id is retained');
roadmap_provider_assert($directResponse->responseHash() === hash('sha256', $rawDirect), 'only deterministic response hash is retained');
roadmap_provider_assert(($captured['headers']['Authorization'] ?? '') === 'Bearer test-key-never-log', 'Bearer credential is sent');
roadmap_provider_assert(($captured['headers']['X-Model-Name'] ?? '') === 'ag/gemini-3.7-flash-low', 'low-latency model header is sent');
roadmap_provider_assert($captured['timeout'] === 3, 'configured timeout is used');
$transportBody = json_decode($captured['body'], true, 512, JSON_THROW_ON_ERROR);
roadmap_provider_assert(($transportBody['model'] ?? '') === 'ag/gemini-3.7-flash-low', 'low-latency model is included in 9Router body');
roadmap_provider_assert(isset($transportBody['messages'][0]['content'], $transportBody['messages'][1]['content']), '9Router chat envelope is used');
roadmap_provider_assert(($transportBody['response_format']['type'] ?? null) === 'json_object', '9Router JSON-object mode is required');
roadmap_provider_assert(($transportBody['temperature'] ?? null) === 0.1, 'low-variance generation is required');
roadmap_provider_assert(($transportBody['max_tokens'] ?? null) === 4096, 'roadmap output is bounded for predictable latency');
$modelInput = json_decode($transportBody['messages'][1]['content'], true, 512, JSON_THROW_ON_ERROR);
roadmap_provider_assert(($modelInput['output_schema']['additionalProperties'] ?? null) === false, 'exact output schema reaches the model');

$geminiConfig = RecommendationConfig::fromEnvironment([
    'APP_ENV' => 'test',
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => 'gemini',
    'TALENTHUB_AI_MODEL' => 'gemini-3.7-flash',
    'TALENTHUB_AI_API_URL' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.7-flash:generateContent',
    'TALENTHUB_AI_API_KEY' => 'gemini-test-key-never-log',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'generativelanguage.googleapis.com',
    'TALENTHUB_AI_TIMEOUT_SECONDS' => '3',
    'TALENTHUB_AI_MAX_ATTEMPTS' => '2',
    'TALENTHUB_AI_ROADMAP_TIMEOUT_SECONDS' => '3',
]);
$geminiCaptured = [];
$geminiProvider = new HttpRoadmapProvider($geminiConfig, static function ($url, $headers, $body, $timeout) use (&$geminiCaptured, $rawDirect): array {
    $geminiCaptured = compact('url', 'headers', 'body', 'timeout');
    return ['status' => 200, 'headers' => [], 'body' => $rawDirect];
});
$geminiResponse = $geminiProvider->generate(roadmap_provider_request(), roadmap_provider_authorizer());
roadmap_provider_assert($geminiResponse->isSuccess(), 'native Gemini roadmap request succeeds');
roadmap_provider_assert(($geminiCaptured['headers']['x-goog-api-key'] ?? '') === 'gemini-test-key-never-log', 'native Gemini roadmap uses x-goog-api-key');
roadmap_provider_assert(!isset($geminiCaptured['headers']['Authorization']), 'native Gemini roadmap does not use Bearer authorization');
$geminiBody = json_decode((string) ($geminiCaptured['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
roadmap_provider_assert(isset($geminiBody['systemInstruction']['parts'][0]['text']), 'native Gemini roadmap includes system instruction');
roadmap_provider_assert(isset($geminiBody['contents'][0]['parts'][0]['text']), 'native Gemini roadmap includes user contents');
roadmap_provider_assert(($geminiBody['generationConfig']['responseFormat']['text']['mimeType'] ?? null) === 'APPLICATION_JSON', 'native Gemini roadmap uses the official JSON response-format enum');
roadmap_provider_assert(
    is_array($geminiBody['generationConfig']['responseFormat']['text']['schema'] ?? null)
        && ($geminiBody['generationConfig']['responseFormat']['text']['schema']['type'] ?? null) === 'object',
    'native Gemini roadmap sends the canonical output schema inside responseFormat.text.schema'
);
$geminiSchema = $geminiBody['generationConfig']['responseFormat']['text']['schema'];
$encodedGeminiSchema = json_encode($geminiSchema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
foreach (['const', 'minLength', 'pattern', 'uniqueItems', 'oneOf'] as $unsupportedKeyword) {
    roadmap_provider_assert(
        !str_contains($encodedGeminiSchema, '"' . $unsupportedKeyword . '"'),
        "native Gemini schema recursively removes unsupported keyword {$unsupportedKeyword}",
    );
}
$selfTaskType = $geminiSchema['properties']['phases']['items']['properties']['tasks']['items']['properties']['action']['anyOf'][0]['properties']['type'] ?? null;
roadmap_provider_assert(
    is_array($selfTaskType) && ($selfTaskType['enum'] ?? null) === ['self_task'],
    'native Gemini schema converts oneOf/const discriminators into documented anyOf/enum constraints',
);
$largeEnum = array_map(static fn (int $index): string => sprintf('evidence-%03d', $index), range(1, 50));
$largeProperties = [];
foreach (range(1, 12) as $index) {
    $largeProperties['evidence_group_' . $index] = [
        'type' => 'array',
        'items' => ['type' => 'string', 'enum' => $largeEnum],
        'minItems' => 1,
        'uniqueItems' => true,
    ];
}
$largeRequest = new ProviderRequest('learner-roadmap-prompt-1.4.0', [
    'instructions' => ['Chỉ trả về JSON hợp lệ.'],
    'output_schema' => [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => array_keys($largeProperties),
        'properties' => $largeProperties,
    ],
], [
    'evidence-001' => new RecommendationEvidence(
        'assessment',
        'result-large-schema',
        '2026-08-20T00:00:00+00:00',
        'provider_source',
        ['test_type' => 'holland'],
    ),
]);
$largeCaptured = [];
$largeProvider = new HttpRoadmapProvider($geminiConfig, static function ($url, $headers, $body, $timeout) use (&$largeCaptured, $rawDirect): array {
    $largeCaptured = compact('url', 'headers', 'body', 'timeout');
    return ['status' => 200, 'headers' => [], 'body' => $rawDirect];
});
$largeProvider->generate($largeRequest, roadmap_provider_authorizer());
$largeBody = json_decode((string) ($largeCaptured['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
$largeGeminiSchema = $largeBody['generationConfig']['responseFormat']['text']['schema'] ?? null;
roadmap_provider_assert(
    is_array($largeGeminiSchema) && !str_contains(json_encode($largeGeminiSchema, JSON_THROW_ON_ERROR), '"enum"'),
    'native Gemini roadmap reduces oversized dynamic-enum schemas to a structural schema accepted by Gemini',
);
$largePrompt = json_decode((string) ($largeBody['contents'][0]['parts'][0]['text'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
roadmap_provider_assert(
    ($largePrompt['output_schema']['properties']['evidence_group_1']['items']['enum'] ?? null) === $largeEnum,
    'full roadmap constraints remain in the model prompt and are not weakened before server-side validation',
);
roadmap_provider_assert(($geminiBody['generationConfig']['maxOutputTokens'] ?? null) === 8192, 'native Gemini roadmap reserves enough output tokens for the full three-phase contract');
roadmap_provider_assert(($geminiBody['generationConfig']['thinkingConfig']['thinkingLevel'] ?? null) === 'low', 'native Gemini roadmap uses the documented low thinking level for bounded latency and output headroom');
roadmap_provider_assert(!isset($geminiBody['response_format'], $geminiBody['max_tokens'], $geminiBody['messages']), 'native Gemini roadmap omits OpenAI-only fields');

$thoughtEnvelope = json_encode([
    'candidates' => [[
        'content' => ['parts' => [
            ['thought' => true, 'text' => 'Internal summary that must not be parsed as the roadmap.'],
            ['text' => $rawDirect],
        ]],
        'finishReason' => 'STOP',
    ]],
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$thoughtProvider = new HttpRoadmapProvider($geminiConfig, static fn (): array => ['status' => 200, 'headers' => [], 'body' => $thoughtEnvelope]);
$thoughtResponse = $thoughtProvider->generate(roadmap_provider_request(), roadmap_provider_authorizer());
roadmap_provider_assert($thoughtResponse->isSuccess() && $thoughtResponse->payload() === $fixture, 'native Gemini parser skips thought parts and reads the final structured JSON part');

$fenced = "```json\n{$rawDirect}\n```";
$envelopeBody = json_encode(['id' => 'body_req_456', 'choices' => [['message' => ['content' => $fenced]]]], JSON_THROW_ON_ERROR);
$envelope = new HttpRoadmapProvider(roadmap_provider_config(), static fn (): array => ['status' => 200, 'headers' => [], 'body' => $envelopeBody]);
$envelopeResponse = $envelope->generate(roadmap_provider_request(), roadmap_provider_authorizer());
roadmap_provider_assert($envelopeResponse->isSuccess() && $envelopeResponse->payload() === $fixture, 'fenced OpenAI/9Router content succeeds');
roadmap_provider_assert($envelopeResponse->providerRequestId() === 'body_req_456', 'safe envelope request id is retained');

$attempt = 0;
$retrying = new HttpRoadmapProvider(roadmap_provider_config(), static function () use (&$attempt, $rawDirect): array {
    $attempt++;
    return $attempt === 1
        ? ['status' => 502, 'headers' => [], 'body' => '{}']
        : ['status' => 200, 'headers' => [], 'body' => $rawDirect];
});
$retryAuthorizer = roadmap_provider_authorizer();
roadmap_provider_assert($retrying->generate(roadmap_provider_request(), $retryAuthorizer)->isSuccess(), '502 is retried once');
roadmap_provider_assert($retryAuthorizer->calls === 2, 'consent is rechecked before retry');

$revokedAttempt = 0;
$revoked = new HttpRoadmapProvider(roadmap_provider_config(), static function () use (&$revokedAttempt): array {
    $revokedAttempt++;
    return ['status' => 503, 'headers' => [], 'body' => '{}'];
});
$revokedResponse = $revoked->generate(roadmap_provider_request(), roadmap_provider_authorizer(2));
roadmap_provider_assert(!$revokedResponse->isSuccess() && $revokedResponse->errorCode() === 'consent_revoked', 'revoked consent stops the retry');
roadmap_provider_assert($revokedAttempt === 1, 'no second HTTP call occurs after revocation');

foreach ([400 => 'provider_rejected', 401 => 'provider_rejected', 403 => 'provider_rejected', 500 => 'provider_unavailable'] as $status => $error) {
    $provider = new HttpRoadmapProvider(roadmap_provider_config(1), static fn (): array => ['status' => $status, 'headers' => [], 'body' => '{}']);
    roadmap_provider_assert($provider->generate(roadmap_provider_request(), roadmap_provider_authorizer())->errorCode() === $error, "HTTP {$status} is safely mapped");
}
$limited = new HttpRoadmapProvider(roadmap_provider_config(), static fn (): array => ['status' => 429, 'headers' => ['Retry-After' => '17'], 'body' => '{}']);
$limitedResponse = $limited->generate(roadmap_provider_request(), roadmap_provider_authorizer());
roadmap_provider_assert($limitedResponse->errorCode() === 'rate_limited' && $limitedResponse->retryAfterSeconds() === 17, '429 exposes bounded retry guidance');

$timeout = new HttpRoadmapProvider(roadmap_provider_config(), static fn (): array => throw new RuntimeException('secret transport detail'));
roadmap_provider_assert($timeout->generate(roadmap_provider_request(), roadmap_provider_authorizer())->errorCode() === 'provider_unavailable', 'transport errors are safely mapped');
$invalidUtf8 = new HttpRoadmapProvider(roadmap_provider_config(), static fn (): array => ['status' => 200, 'headers' => [], 'body' => "\xB1\x31"]);
roadmap_provider_assert($invalidUtf8->generate(roadmap_provider_request(), roadmap_provider_authorizer())->errorCode() === 'malformed_response', 'invalid UTF-8 is rejected');
$malformed = new HttpRoadmapProvider(roadmap_provider_config(), static fn (): array => ['status' => 200, 'headers' => [], 'body' => '{bad-json']);
roadmap_provider_assert($malformed->generate(roadmap_provider_request(), roadmap_provider_authorizer())->errorCode() === 'malformed_response', 'malformed JSON is rejected');

echo "learner_ai_roadmap_provider_test: OK\n";
