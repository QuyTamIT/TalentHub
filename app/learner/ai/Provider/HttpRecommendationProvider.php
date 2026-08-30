<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Provider;

use Closure;
use JsonException;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Consent\ProviderConsentDenied;
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;

final class HttpRecommendationProvider implements RecommendationProvider
{
    /** @var Closure(string,array<string,string>,string,int):array<string,mixed> */
    private readonly Closure $http;

    /** @param (callable(string,array<string,string>,string,int):array<string,mixed>)|null $http */
    private readonly ?AiMetricsCollector $metrics;

    public function __construct(private readonly RecommendationConfig $config, ?callable $http = null, ?RetryPolicy $retryPolicy = null, ?CircuitBreaker $circuitBreaker = null, ?callable $sleeper = null, ?ProviderHealthStore $healthStore = null, ?AiMetricsCollector $metrics = null)
    {
        $this->http = $http !== null
            ? Closure::fromCallable($http)
            : Closure::fromCallable([$this, 'defaultHttpTransport']);
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

    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse
    {
        $started=microtime(true);$this->attemptsUsed=0;$response=$this->performGenerate($request,$authorizer);$latency=(int)round((microtime(true)-$started)*1000);$this->healthStore->record($response->isSuccess(),$latency,max(0,$this->attemptsUsed-1),$response->errorCode(),$this->circuitBreaker->state());
        $usage=$response->usage() ?? []; $this->metrics->record(['provider_latency_ms'=>$latency,'provider_error'=>$response->errorCode(),'circuit_state'=>$this->circuitBreaker->state(),'input_tokens'=>$usage['input_tokens']??null,'output_tokens'=>$usage['output_tokens']??null,'estimated_cost'=>$usage['estimated_cost']??null]);
        return $response;
    }

    public function health(): array { return $this->healthStore->snapshot(); }

    private function performGenerate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse
    {
        if (!$this->config->enabled() || $this->config->apiUrl() === null || $this->config->apiKey() === null) {
            return ProviderResponse::failure('provider_disabled', null, 'config');
        }
        if (!$this->circuitBreaker->allow()) return ProviderResponse::failure('provider_circuit_open', null, 'health');
        try {
            $payload = $this->transportPayload($request);
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return ProviderResponse::failure('invalid_request', null, 'request');
        }
        $headers = $this->transportHeaders();
        for ($attempt = 1; $attempt <= $this->config->maxAttempts(); $attempt++) {
            $this->attemptsUsed=$attempt;
            try {
                $authorizer->beforeAttempt($attempt);
            } catch (ProviderConsentDenied $exception) {
                throw $exception;
            }
            try {
                $response = ($this->http)($this->config->apiUrl(), $headers, $body, $this->config->timeoutSeconds());
            } catch (\Throwable) {
                if ($this->retryPolicy->shouldRetry(0,'network',$attempt)) { ($this->sleeper)($this->retryPolicy->delayMs($attempt)); continue; }
                $this->circuitBreaker->recordFailure();
                return ProviderResponse::failure('provider_unavailable', null, 'network');
            }
            $status = is_numeric($response['status'] ?? null) ? (int) $response['status'] : 0;
            if ($status === 200) { $result=$this->success($response['body'] ?? null); if ($result->isSuccess()) $this->circuitBreaker->recordSuccess(); else $this->circuitBreaker->recordFailure(); return $result; }
            if ($status === 429) { $retryAfter=$this->retryAfter($response['headers'] ?? []); return ProviderResponse::failure('rate_limited', $retryAfter, '4xx'); }
            if ($this->retryPolicy->shouldRetry($status,null,$attempt)) { ($this->sleeper)($this->retryPolicy->delayMs($attempt)); continue; }
            if ($status >= 500) $this->circuitBreaker->recordFailure();
            return ProviderResponse::failure($status >= 500 ? 'provider_unavailable' : 'provider_rejected', null, $status >= 500 ? '5xx' : '4xx');
        }
        return ProviderResponse::failure('provider_unavailable', null, '5xx');
    }

    /** @return array<string,mixed> */
    private function transportPayload(ProviderRequest $request): array
    {
        $payload = $request->payload();
        if ($this->isGeminiProvider()) {
            return $this->geminiPayload($payload);
        }
        if (str_starts_with(strtolower((string) $this->config->provider()), '9router')) {
            $payload = [
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => implode(' ', [
                            'Return one JSON object only: {"items":[...]}. Do not use Markdown.',
                            'Every item must contain exactly item_type, title, summary, priority, confidence_band, action, and evidence_ref_ids.',
                            'item_type must be one of "strength", "improvement", "development", "activity", or "roadmap".',
                            'priority must be a JSON integer from 1 to 100. confidence_band must be "low", "medium", or "high".',
                            'action must be exactly one supported object: {"type":"develop_skill","skill_code":"..."}; {"type":"continue_technical_activity","activity_source_id":"..."}; {"type":"practice_presentation","weeks":4}; {"type":"explore_career_group","career_group":"arts"}; or {"type":"register_activity","career_group":"arts","activity_source_id":"UUID"}.',
                            'career_group must be one of "technical", "business", "arts", or "sports_academic". Do not add unsupported action keys.',
                            'evidence_ref_ids must be a non-empty JSON array containing only supplied evidence reference IDs.',
                            'title and summary must be non-empty and must not promise admissions, employment, or guaranteed outcomes.',
                        ]),
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode(
                            $payload,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                        ),
                    ],
                ],
            ];
        }
        if ($this->config->model() !== null && !isset($payload['model'])) {
            $payload['model'] = $this->config->model();
        }

        return $payload;
    }

    /** @return array<string,string> */
    private function transportHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if ($this->isGeminiProvider()) {
            $headers['x-goog-api-key'] = (string) $this->config->apiKey();
            return $headers;
        }

        $headers['Authorization'] = 'Bearer ' . $this->config->apiKey();
        if ($this->config->model() !== null) {
            $headers['X-Model-Name'] = $this->config->model();
        }
        return $headers;
    }

    private function isGeminiProvider(): bool
    {
        return str_starts_with(strtolower((string) $this->config->provider()), 'gemini');
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function geminiPayload(array $payload): array
    {
        $instructions = is_array($payload['instructions'] ?? null) ? $payload['instructions'] : [];
        $schema = is_array($payload['output_schema'] ?? null) ? $payload['output_schema'] : null;
        unset($payload['instructions']);

        $responseTextFormat = ['mimeType' => 'APPLICATION_JSON'];
        if ($schema !== null) {
            $responseTextFormat['schema'] = $schema;
        }

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
            'generationConfig' => [
                'responseFormat' => ['text' => $responseTextFormat],
                // Gemini can consume a substantial part of the response budget
                // before emitting the structured candidate. 2048 caused a
                // valid recommendation response to end with MAX_TOKENS and
                // truncated JSON, which strict mode correctly rejected.
                'maxOutputTokens' => 8192,
            ],
        ];
    }

    private function success(mixed $body): ProviderResponse
    {
        if (!is_string($body)) {
            return ProviderResponse::failure('malformed_response', null, '2xx');
        }
        if (strlen($body) > 1048576) return ProviderResponse::failure('response_too_large', null, '2xx');
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ProviderResponse::failure('malformed_response', null, '2xx');
        }
        if (!is_array($decoded)) {
            return ProviderResponse::failure('malformed_response', null, '2xx');
        }

        // Direct structured payload: {"items": [...]}
        if (isset($decoded['items']) && is_array($decoded['items'])) {
            try {
                return ProviderResponse::success($decoded['items'], is_array($decoded['usage'] ?? null) ? $decoded['usage'] : null);
            } catch (\InvalidArgumentException) {
                return ProviderResponse::failure('malformed_response', null, '2xx');
            }
        }


        // OpenAI / 9Router chat completion envelope: {"choices": [{"message": {"content": "..."}}]}
        if (isset($decoded['choices'][0]['message']['content']) && is_string($decoded['choices'][0]['message']['content'])) {
            return $this->parseContentString($decoded['choices'][0]['message']['content']);
        }

        // Gemini native response envelope: {"candidates": [{"content": {"parts": [{"text": "..."}]}}]}
        if (isset($decoded['candidates'][0]['content']['parts'][0]['text']) && is_string($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            return $this->parseContentString($decoded['candidates'][0]['content']['parts'][0]['text']);
        }

        return ProviderResponse::failure('malformed_response', null, '2xx');
    }

    private function parseContentString(string $content): ProviderResponse
    {
        $raw = trim($content);
        if (str_starts_with($raw, '```')) {
            $raw = (string) preg_replace('/\A```(?:json)?\s*/i', '', $raw);
            $raw = (string) preg_replace('/\s*```\z/', '', $raw);
            $raw = trim($raw);
        }
        try {
            $parsed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ProviderResponse::failure('malformed_response');
        }
        if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
            return ProviderResponse::failure('malformed_response');
        }
        try {
            return ProviderResponse::success($parsed['items']);
        } catch (\InvalidArgumentException) {
            return ProviderResponse::failure('malformed_response');
        }
    }

    private function retryAfter(mixed $headers): ?int
    {
        if (!is_array($headers)) {
            return null;
        }
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'retry-after' && filter_var($value, FILTER_VALIDATE_INT) !== false) {
                return max(0, (int) $value);
            }
        }
        return null;
    }

    /**
     * Default HTTP transport via cURL.
     *
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    private function defaultHttpTransport(string $url, array $headers, string $body, int $timeout): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL extension is required for HTTP recommendation provider.');
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL handle.');
        }
        $formattedHeaders = [];
        foreach ($headers as $k => $v) {
            $formattedHeaders[] = "{$k}: {$v}";
        }
        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(60, $timeout),
            CURLOPT_CONNECTTIMEOUT => min(15, max(5, $timeout)),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }
                return $len;
            },
        ]);
        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("HTTP request failed: {$err}");
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => is_string($responseBody) ? $responseBody : '',
        ];
    }
}
