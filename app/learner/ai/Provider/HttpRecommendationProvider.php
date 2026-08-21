<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Provider;

use Closure;
use JsonException;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;

final class HttpRecommendationProvider implements RecommendationProvider
{
    /** @var Closure(string,array<string,string>,string,int):array<string,mixed> */
    private readonly Closure $http;

    /** @param (callable(string,array<string,string>,string,int):array<string,mixed>)|null $http */
    public function __construct(private readonly RecommendationConfig $config, ?callable $http = null)
    {
        $this->http = $http !== null
            ? Closure::fromCallable($http)
            : Closure::fromCallable([$this, 'defaultHttpTransport']);
    }

    public function generate(ProviderRequest $request): ProviderResponse
    {
        if (!$this->config->enabled() || $this->config->apiUrl() === null || $this->config->apiKey() === null) {
            return ProviderResponse::failure('provider_disabled');
        }
        try {
            $payload = $this->transportPayload($request);
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return ProviderResponse::failure('invalid_request');
        }
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->config->apiKey(),
        ];
        if ($this->config->model() !== null) {
            $headers['X-Model-Name'] = $this->config->model();
        }
        for ($attempt = 1; $attempt <= $this->config->maxAttempts(); $attempt++) {
            try {
                $response = ($this->http)($this->config->apiUrl(), $headers, $body, $this->config->timeoutSeconds());
            } catch (\Throwable) {
                return ProviderResponse::failure('provider_unavailable');
            }
            $status = is_numeric($response['status'] ?? null) ? (int) $response['status'] : 0;
            if ($status === 200) {
                return $this->success($response['body'] ?? null);
            }
            if ($status === 429) {
                return ProviderResponse::failure('rate_limited', $this->retryAfter($response['headers'] ?? []));
            }
            if (in_array($status, [502, 503], true) && $attempt < $this->config->maxAttempts()) {
                continue;
            }
            return ProviderResponse::failure($status >= 500 ? 'provider_unavailable' : 'provider_rejected');
        }
        return ProviderResponse::failure('provider_unavailable');
    }

    /** @return array<string,mixed> */
    private function transportPayload(ProviderRequest $request): array
    {
        $payload = $request->payload();
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

    private function success(mixed $body): ProviderResponse
    {
        if (!is_string($body)) {
            return ProviderResponse::failure('malformed_response');
        }
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ProviderResponse::failure('malformed_response');
        }
        if (!is_array($decoded)) {
            return ProviderResponse::failure('malformed_response');
        }

        // Direct structured payload: {"items": [...]}
        if (isset($decoded['items']) && is_array($decoded['items'])) {
            try {
                return ProviderResponse::success($decoded['items']);
            } catch (\InvalidArgumentException) {
                return ProviderResponse::failure('malformed_response');
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

        return ProviderResponse::failure('malformed_response');
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
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(2, $timeout),
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
