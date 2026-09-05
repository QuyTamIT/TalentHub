<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Provider;

use Closure;
use JsonException;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Consent\ProviderConsentDenied;
use TalentHub\Learner\Ai\Contracts\RoadmapProvider;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;

final class HttpRoadmapProvider implements RoadmapProvider
{
    private const GEMINI_SCHEMA_MAX_BYTES = 8000;
    private const GEMINI_SCHEMA_MAX_ENUM_VALUES = 200;

    /** @var Closure(string,array<string,string>,string,int):array<string,mixed> */
    private readonly Closure $http;

    /** @param (callable(string,array<string,string>,string,int):array<string,mixed>)|null $http */
    private readonly ?AiMetricsCollector $metrics;

    public function __construct(private readonly RecommendationConfig $config, ?callable $http = null, ?RetryPolicy $retryPolicy = null, ?CircuitBreaker $circuitBreaker = null, ?callable $sleeper = null, ?ProviderHealthStore $healthStore = null, ?AiMetricsCollector $metrics = null)
    {
        $this->http = $http !== null ? Closure::fromCallable($http) : Closure::fromCallable([$this, 'defaultHttpTransport']);
        $this->retryPolicy = $retryPolicy ?? new RetryPolicy($config->maxAttempts());
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreaker();
        $this->sleeper = $sleeper !== null ? Closure::fromCallable($sleeper) : static function (int $milliseconds): void { if ($milliseconds > 0) usleep($milliseconds * 1000); };
        $this->healthStore=$healthStore??new ProviderHealthStore();
        $this->metrics = $metrics ?? AiMetricsCollector::shared();
    }
    private readonly RetryPolicy $retryPolicy;
    private readonly CircuitBreaker $circuitBreaker;
    /** @var Closure(int):void */ private readonly Closure $sleeper;
    private readonly ProviderHealthStore $healthStore;
    private int $attemptsUsed=0;

    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): RoadmapProviderResponse
    {
        $started=microtime(true);$this->attemptsUsed=0;$response=$this->performGenerate($request,$authorizer);$latency=(int)round((microtime(true)-$started)*1000);$this->healthStore->record($response->isSuccess(),$latency,max(0,$this->attemptsUsed-1),$response->errorCode(),$this->circuitBreaker->state());
        $this->metrics->record(['provider_latency_ms'=>$latency,'provider_error'=>$response->errorCode(),'circuit_state'=>$this->circuitBreaker->state()]);
        return $response;
    }

    public function health(): array { return $this->healthStore->snapshot(); }

    private function performGenerate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): RoadmapProviderResponse
    {
        if (!$this->config->enabled() || $this->config->apiUrl() === null || $this->config->apiKey() === null) {
            return RoadmapProviderResponse::failure('provider_disabled', null, 'config');
        }
<<<<<<< HEAD
        if (!$this->circuitBreaker->allow()) return RoadmapProviderResponse::failure('provider_circuit_open', null, 'health');
=======
        if (!$this->circuitBreaker->allow()
            && !ProviderRuntimeMode::alwaysAttempt($this->config->environment())) {
            return RoadmapProviderResponse::failure('provider_circuit_open', null, 'health');
        }
>>>>>>> 05d98af655ad6632b478e8cd4a88f4058926f303
        try {
            $body = json_encode($this->transportPayload($request), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return RoadmapProviderResponse::failure('invalid_request', null, 'request');
        }
        $headers = $this->transportHeaders();

        for ($attempt = 1; $attempt <= $this->config->maxAttempts(); $attempt++) {
            $this->attemptsUsed=$attempt;
            try {
                $authorizer->beforeAttempt($attempt);
            } catch (ProviderConsentDenied $exception) {
                return RoadmapProviderResponse::failure($exception->reason(), null, 'consent');
            }
            try {
                $response = ($this->http)($this->config->apiUrl(), $headers, $body, $this->config->roadmapTimeoutSeconds());
            } catch (\Throwable) {
                if ($this->retryPolicy->shouldRetry(0,'network',$attempt)) { ($this->sleeper)($this->retryPolicy->delayMs($attempt)); continue; }
                $this->circuitBreaker->recordFailure();
                return RoadmapProviderResponse::failure('provider_unavailable', null, 'network');
            }
            $status = is_numeric($response['status'] ?? null) ? (int) $response['status'] : 0;
            if ($status === 200) { $result=$this->success($response['body'] ?? null, $response['headers'] ?? []); if ($result->isSuccess()) $this->circuitBreaker->recordSuccess(); else $this->circuitBreaker->recordFailure(); return $result; }
            if ($status === 429) { $retryAfter=$this->retryAfter($response['headers'] ?? []); return RoadmapProviderResponse::failure('rate_limited', $retryAfter, '4xx'); }
            if ($this->retryPolicy->shouldRetry($status,null,$attempt)) { ($this->sleeper)($this->retryPolicy->delayMs($attempt)); continue; }
            if ($status >= 500) $this->circuitBreaker->recordFailure();
            return RoadmapProviderResponse::failure($status >= 500 ? 'provider_unavailable' : 'provider_rejected', null, $status >= 500 ? '5xx' : '4xx');
        }
        return RoadmapProviderResponse::failure('provider_unavailable', null, '5xx');
    }

    /** @return array<string,mixed> */
    private function transportPayload(ProviderRequest $request): array
    {
        $payload = $request->payload();
        $instructions = is_array($payload['instructions'] ?? null) ? $payload['instructions'] : [];
        unset($payload['instructions']);
        if ($this->isGeminiProvider()) {
            $generationConfig = [
                'responseFormat' => [
                    'text' => [
                        'mimeType' => 'APPLICATION_JSON',
                        'schema' => $this->outputSchema($payload['output_schema'] ?? []),
                    ],
                ],
                'thinkingConfig' => ['thinkingLevel' => 'low'],
                'maxOutputTokens' => 8192,
            ];
            return [
                'systemInstruction' => [
                    'parts' => [['text' => implode("\n", array_filter($instructions, 'is_string'))]],
                ],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [[
                        'text' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]],
                ]],
                'generationConfig' => $generationConfig,
            ];
        }
        $transport = [
            'messages' => [
                ['role' => 'system', 'content' => implode("\n", array_filter($instructions, 'is_string'))],
                ['role' => 'user', 'content' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1,
            'max_tokens' => 4096,
        ];
        if ($this->config->model() !== null) $transport['model'] = $this->config->model();
        return $transport;
    }

    /**
     * Sanitize the canonical roadmap output schema for the Gemini
     * `responseFormat.text.schema` slot. Gemini rejects fields that are
     * not part of the supported subset (for example, the `const`
     * discriminator that the prompt registry uses); we keep the required
     * shape, translate safe discriminator constraints and strip unrecognised
     * keys, falling back to a closed object when the schema is missing.
     *
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private function outputSchema(array $schema): array
    {
        if ($schema === []) {
            return ['type' => 'object', 'additionalProperties' => false];
        }
        $cleaned = $this->cleanSchemaNode($schema);
        $cleaned['type'] = 'object';
<<<<<<< HEAD
        return $cleaned;
    }

=======
        if ($this->requiresStructuralSchema($cleaned)) {
            $cleaned = $this->structuralSchemaNode($cleaned);
            $cleaned['type'] = 'object';
        }
        return $cleaned;
    }

    /** @param array<string,mixed> $schema */
    private function requiresStructuralSchema(array $schema): bool
    {
        try {
            $bytes = strlen(json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (JsonException) {
            return true;
        }
        return $bytes > self::GEMINI_SCHEMA_MAX_BYTES
            || $this->enumValueCount($schema) > self::GEMINI_SCHEMA_MAX_ENUM_VALUES;
    }

    private function enumValueCount(mixed $node): int
    {
        if (!is_array($node)) return 0;
        $count = 0;
        foreach ($node as $key => $value) {
            if ($key === 'enum' && is_array($value)) {
                $count += count($value);
            }
            $count += $this->enumValueCount($value);
        }
        return $count;
    }

    /**
     * Gemini rejects deeply repeated dynamic enums even though every keyword
     * is individually supported. Keep the required object/array structure in
     * responseFormat and leave the complete constraints in the user payload;
     * RoadmapAnalysisValidator still enforces them before anything is saved.
     *
     * @return array<string,mixed>|list<mixed>|string|int|float|bool|null
     */
    private function structuralSchemaNode(mixed $node): mixed
    {
        if (!is_array($node)) return $node;
        if (array_is_list($node)) {
            return array_map(fn (mixed $value): mixed => $this->structuralSchemaNode($value), $node);
        }
        $result = [];
        foreach ($node as $key => $value) {
            if ($key === 'enum' && is_array($value)) {
                // Keep small closed vocabularies (action types, directions,
                // categories) so the model cannot invent them. Large dynamic
                // learner/catalog/evidence enums are retained in the prompt
                // and enforced by the server-side validator instead.
                if (count($value) <= 20) {
                    $result[$key] = $value;
                }
                continue;
            }
            if ($key === 'properties' && is_array($value) && !array_is_list($value)) {
                $properties = [];
                foreach ($value as $property => $propertySchema) {
                    if (is_string($property) && is_array($propertySchema)) {
                        $properties[$property] = $this->structuralSchemaNode($propertySchema);
                    }
                }
                $result[$key] = $properties;
                continue;
            }
            if (in_array($key, ['type', 'required', 'additionalProperties'], true)) {
                $result[$key] = $value;
                continue;
            }
            if (in_array($key, ['items', 'anyOf'], true)) {
                $result[$key] = $this->structuralSchemaNode($value);
            }
        }
        return $result === [] ? ['type' => 'object'] : $result;
    }

>>>>>>> 05d98af655ad6632b478e8cd4a88f4058926f303
    /** @param array<string,mixed>|list<mixed> $node @return array<string,mixed>|list<mixed>|string|int|float|bool|null */
    private function cleanSchemaNode(mixed $node): mixed
    {
        if (!is_array($node)) return $node;
        $isList = array_is_list($node);
        if ($isList) {
            $result = [];
            foreach ($node as $value) $result[] = $this->cleanSchemaNode($value);
            return $result;
        }
        $result = [];
        $supported = [
            '$ref',
            'type', 'format', 'title', 'description', 'enum',
            'items', 'prefixItems', 'minItems', 'maxItems',
            'minimum', 'maximum', 'anyOf',
            'properties', 'additionalProperties', 'required',
        ];
        foreach ($node as $key => $value) {
            if (!is_string($key)) continue;
            if ($key === 'const' && (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null)) {
                $result['enum'] = [$value];
                continue;
            }
            if ($key === 'oneOf' && is_array($value) && array_is_list($value)) {
                $result['anyOf'] = $this->cleanSchemaNode($value);
                continue;
            }
            if (!in_array($key, $supported, true)) continue;
            if ($key === 'properties' && is_array($value) && !array_is_list($value)) {
                $properties = [];
                foreach ($value as $property => $propertySchema) {
                    if (is_string($property) && is_array($propertySchema)) {
                        $properties[$property] = $this->cleanSchemaNode($propertySchema);
                    }
                }
                $result[$key] = $properties;
                continue;
            }
            $result[$key] = $this->cleanSchemaNode($value);
        }
        return $result;
    }

    /** @return array<string,string> */
    private function transportHeaders(): array
    {
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($this->isGeminiProvider()) {
            $headers['x-goog-api-key'] = (string) $this->config->apiKey();
            return $headers;
        }

        $headers['Authorization'] = 'Bearer ' . $this->config->apiKey();
        if ($this->config->model() !== null) $headers['X-Model-Name'] = $this->config->model();
        return $headers;
    }

    private function isGeminiProvider(): bool
    {
        return str_starts_with(strtolower((string) $this->config->provider()), 'gemini');
    }

    private function success(mixed $body, mixed $headers): RoadmapProviderResponse
    {
        if (!is_string($body) || preg_match('//u', $body) !== 1) {
            return RoadmapProviderResponse::failure('malformed_response', null, '2xx');
        }
        if (strlen($body) > 2097152) return RoadmapProviderResponse::failure('response_too_large', null, '2xx');
        $hash = hash('sha256', $body);
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return RoadmapProviderResponse::failure('malformed_response', null, '2xx', null, $hash);
        }
        if (!is_array($decoded)) return RoadmapProviderResponse::failure('malformed_response', null, '2xx', null, $hash);
        $requestId = $this->requestId($headers) ?? (is_string($decoded['id'] ?? null) ? $decoded['id'] : null);
        if (isset($decoded['executive_summary'])) {
            return RoadmapProviderResponse::success($decoded, $requestId, $hash);
        }
        $content = $decoded['choices'][0]['message']['content'] ?? $this->geminiContent($decoded);
        if (!is_string($content)) return RoadmapProviderResponse::failure('malformed_response', null, '2xx', $requestId, $hash);
        $raw = trim($content);
        if (str_starts_with($raw, '```')) {
            $raw = trim((string) preg_replace(['/\A```(?:json)?\s*/i', '/\s*```\z/'], '', $raw));
        }
        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return RoadmapProviderResponse::failure('malformed_response', null, '2xx', $requestId, $hash);
        }
        return is_array($payload) && $payload !== []
            ? RoadmapProviderResponse::success($payload, $requestId, $hash)
            : RoadmapProviderResponse::failure('malformed_response', null, '2xx', $requestId, $hash);
    }

    /** @param array<string,mixed> $decoded */
    private function geminiContent(array $decoded): ?string
    {
        $parts = $decoded['candidates'][0]['content']['parts'] ?? null;
        if (!is_array($parts)) return null;
        $chunks = [];
        foreach ($parts as $part) {
            if (!is_array($part) || ($part['thought'] ?? false) === true || !is_string($part['text'] ?? null)) continue;
            $chunks[] = $part['text'];
        }
        return $chunks === [] ? null : implode('', $chunks);
    }

    private function retryAfter(mixed $headers): ?int
    {
        if (!is_array($headers)) return null;
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'retry-after' && filter_var($value, FILTER_VALIDATE_INT) !== false) return max(0, min(3600, (int) $value));
        }
        return null;
    }

    private function requestId(mixed $headers): ?string
    {
        if (!is_array($headers)) return null;
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), ['x-request-id', 'request-id', 'x-provider-request-id'], true) && is_string($value)) return $value;
        }
        return null;
    }

    public static function connectTimeoutSeconds(int $requestTimeoutSeconds): int
    {
        return min(15, max(1, $requestTimeoutSeconds));
    }

    /** @param array<string,string> $headers @return array{status:int,headers:array<string,string>,body:string} */
    private function defaultHttpTransport(string $url, array $headers, string $body, int $timeout): array
    {
        if (!function_exists('curl_init')) throw new \RuntimeException('cURL extension is required for HTTP roadmap provider.');
        $ch = curl_init($url);
        if ($ch === false) throw new \RuntimeException('Failed to initialize cURL handle.');
        $formattedHeaders = [];
        foreach ($headers as $key => $value) $formattedHeaders[] = "{$key}: {$value}";
        $responseHeaders = [];
        $options = [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => self::connectTimeoutSeconds($timeout),
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header); $parts = explode(':', $header, 2);
                if (count($parts) === 2) $responseHeaders[trim($parts[0])] = trim($parts[1]);
                return $length;
            },
        ];
        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        curl_setopt_array($ch, $options);
        $responseBody = curl_exec($ch);
        if ($responseBody === false) { $error = curl_error($ch); curl_close($ch); throw new \RuntimeException('HTTP request failed: ' . $error); }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return ['status' => $status, 'headers' => $responseHeaders, 'body' => is_string($responseBody) ? $responseBody : ''];
    }
}
