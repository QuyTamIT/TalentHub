<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Provider;

use Closure;
use JsonException;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Consent\ProviderConsentDenied;
use TalentHub\Learner\Ai\Contracts\RoadmapProvider;

final class HttpRoadmapProvider implements RoadmapProvider
{
    /** @var Closure(string,array<string,string>,string,int):array<string,mixed> */
    private readonly Closure $http;

    /** @param (callable(string,array<string,string>,string,int):array<string,mixed>)|null $http */
    public function __construct(private readonly RecommendationConfig $config, ?callable $http = null)
    {
        $this->http = $http !== null ? Closure::fromCallable($http) : Closure::fromCallable([$this, 'defaultHttpTransport']);
    }

    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): RoadmapProviderResponse
    {
        if (!$this->config->enabled() || $this->config->apiUrl() === null || $this->config->apiKey() === null) {
            return RoadmapProviderResponse::failure('provider_disabled', null, 'config');
        }
        try {
            $body = json_encode($this->transportPayload($request), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return RoadmapProviderResponse::failure('invalid_request', null, 'request');
        }
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->config->apiKey()];
        if ($this->config->model() !== null) $headers['X-Model-Name'] = $this->config->model();

        for ($attempt = 1; $attempt <= $this->config->maxAttempts(); $attempt++) {
            try {
                $authorizer->beforeAttempt($attempt);
            } catch (ProviderConsentDenied $exception) {
                return RoadmapProviderResponse::failure($exception->reason(), null, 'consent');
            }
            try {
                $response = ($this->http)($this->config->apiUrl(), $headers, $body, $this->config->roadmapTimeoutSeconds());
            } catch (\Throwable) {
                return RoadmapProviderResponse::failure('provider_unavailable', null, 'network');
            }
            $status = is_numeric($response['status'] ?? null) ? (int) $response['status'] : 0;
            if ($status === 200) return $this->success($response['body'] ?? null, $response['headers'] ?? []);
            if ($status === 429) return RoadmapProviderResponse::failure('rate_limited', $this->retryAfter($response['headers'] ?? []), '4xx');
            if (in_array($status, [502, 503], true) && $attempt < $this->config->maxAttempts()) continue;
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

    private function success(mixed $body, mixed $headers): RoadmapProviderResponse
    {
        if (!is_string($body) || preg_match('//u', $body) !== 1) {
            return RoadmapProviderResponse::failure('malformed_response', null, '2xx');
        }
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
        $content = $decoded['choices'][0]['message']['content'] ?? $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
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

    /** @param array<string,string> $headers @return array{status:int,headers:array<string,string>,body:string} */
    private function defaultHttpTransport(string $url, array $headers, string $body, int $timeout): array
    {
        if (!function_exists('curl_init')) throw new \RuntimeException('cURL extension is required for HTTP roadmap provider.');
        $ch = curl_init($url);
        if ($ch === false) throw new \RuntimeException('Failed to initialize cURL handle.');
        $formattedHeaders = [];
        foreach ($headers as $key => $value) $formattedHeaders[] = "{$key}: {$value}";
        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(2, $timeout),
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header); $parts = explode(':', $header, 2);
                if (count($parts) === 2) $responseHeaders[trim($parts[0])] = trim($parts[1]);
                return $length;
            },
        ]);
        $responseBody = curl_exec($ch);
        if ($responseBody === false) { $error = curl_error($ch); curl_close($ch); throw new \RuntimeException('HTTP request failed: ' . $error); }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return ['status' => $status, 'headers' => $responseHeaders, 'body' => is_string($responseBody) ? $responseBody : ''];
    }
}
