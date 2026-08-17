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

    /** @param callable(string,array<string,string>,string,int):array<string,mixed> $http */
    public function __construct(private readonly RecommendationConfig $config, callable $http)
    {
        $this->http = Closure::fromCallable($http);
    }

    public function generate(ProviderRequest $request): ProviderResponse
    {
        if (!$this->config->enabled() || $this->config->apiUrl() === null || $this->config->apiKey() === null) {
            return ProviderResponse::failure('provider_disabled');
        }
        try {
            $body = json_encode($request->payload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return ProviderResponse::failure('invalid_request');
        }
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->config->apiKey(),
        ];
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
        if (!is_array($decoded) || !isset($decoded['items']) || !is_array($decoded['items'])) {
            return ProviderResponse::failure('malformed_response');
        }
        try {
            return ProviderResponse::success($decoded['items']);
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
}
