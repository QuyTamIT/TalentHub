<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Service;

use Closure;
use RuntimeException;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Provider\CircuitBreaker;
use TalentHub\Learner\Ai\Provider\RetryPolicy;

final class EnterpriseAiGeminiMatcher
{
    /** @var Closure(string,array<string,string>,string,int):array<string,mixed> */
    private readonly Closure $transport;
    /** @var Closure(int):void */
    private readonly Closure $sleeper;

    public function __construct(
        private readonly RecommendationConfig $config,
        ?callable $transport = null,
        private readonly ?RetryPolicy $retry = null,
        private readonly ?CircuitBreaker $circuit = null,
        ?callable $sleeper = null
    ) {
        $this->transport = Closure::fromCallable($transport ?? [$this, 'http']);
        $this->sleeper = Closure::fromCallable($sleeper ?? static function (int $milliseconds): void {
            if ($milliseconds > 0) {
                usleep($milliseconds * 1000);
            }
        });
    }

    /**
     * @param array<string,mixed> $job
     * @param list<array{candidate_ref:string,verified_skills:list<array{name:string,level_score:float}>}> $candidateProjections
     * @return array{model_version:string,items:list<array{candidate_ref:string,match_score:float,reason_codes:list<string>}>}
     */
    public function __invoke(array $job, array $candidateProjections): array
    {
        if (!$this->config->enabled() || $this->config->apiUrl() === null || $this->config->apiKey() === null) {
            throw new RuntimeException('Enterprise AI provider unavailable.');
        }

        $circuit = $this->circuit ?? new CircuitBreaker();
        if (!$circuit->allow()) {
            throw new RuntimeException('Enterprise AI circuit open.');
        }

        $validRefs = [];
        foreach ($candidateProjections as $cp) {
            $ref = is_string($cp['candidate_ref'] ?? null) ? trim($cp['candidate_ref']) : '';
            if ($ref === '' || isset($validRefs[$ref])) {
                throw new RuntimeException('invalid_candidate_ref');
            }
            $validRefs[$ref] = true;
        }

        $safeJob = [
            'title' => (string) ($job['title'] ?? ''),
            'required_skills' => array_values((array) ($job['required_skills'] ?? [])),
        ];

        $systemInstruction = 'You are TalentHub Enterprise AI Matcher. Evaluate anonymous candidate projections against job requirements. Respond strictly in JSON format matching the schema without markdown formatting.';
        $userPayload = [
            'prompt_version' => 'enterprise-match-2.0.0',
            'job' => $safeJob,
            'candidates' => $candidateProjections,
            'response_schema' => [
                'model_version' => 'string',
                'items' => [
                    [
                        'candidate_ref' => 'string',
                        'match_score' => 'float between 0.0 and 100.0',
                        'reason_codes' => ['verified_skill_match', 'partial_skill_match', 'skill_gap', 'strong_verified_level'],
                    ],
                ],
            ],
        ];

        $body = json_encode([
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => json_encode($userPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]]]],
            'generationConfig' => ['responseMimeType' => 'application/json'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        if (strlen($body) > 100000) {
            throw new RuntimeException('Enterprise AI payload too large.');
        }

        $retry = $this->retry ?? new RetryPolicy($this->config->maxAttempts());
        $allowedReasons = [
            'verified_skill_match',
            'partial_skill_match',
            'skill_gap',
            'strong_verified_level',
        ];

        for ($attempt = 1; $attempt <= $this->config->maxAttempts(); $attempt++) {
            try {
                $response = ($this->transport)(
                    (string) $this->config->apiUrl(),
                    ['Content-Type' => 'application/json', 'x-goog-api-key' => (string) $this->config->apiKey()],
                    $body,
                    $this->config->timeoutSeconds()
                );
                $status = (int) ($response['status'] ?? 0);
                if (strlen((string) ($response['body'] ?? '')) > 200000) {
                    throw new RuntimeException('Enterprise AI response too large.');
                }
                if ($status === 200) {
                    $decoded = json_decode((string) ($response['body'] ?? ''), true, 64, JSON_THROW_ON_ERROR);
                    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    $result = is_string($text) ? json_decode($text, true, 32, JSON_THROW_ON_ERROR) : null;
                    if (is_array($result)) {
                        if (!isset($result['items']) || !is_array($result['items'])) {
                            throw new RuntimeException('invalid_items');
                        }
                        $seenRefs = [];
                        $items = [];
                        foreach ($result['items'] as $item) {
                            if (!is_array($item)) {
                                throw new RuntimeException('invalid_items');
                            }
                            $ref = (string) ($item['candidate_ref'] ?? '');
                            if (!isset($validRefs[$ref])) {
                                throw new RuntimeException('invalid_candidate_ref');
                            }
                            if (isset($seenRefs[$ref])) {
                                throw new RuntimeException('invalid_candidate_ref');
                            }
                            $seenRefs[$ref] = true;
                            if (!array_key_exists('match_score', $item) || !is_numeric($item['match_score'])) {
                                throw new RuntimeException('invalid_match_score');
                            }
                            $score = (float) $item['match_score'];
                            if ($score < 0.0 || $score > 100.0) {
                                throw new RuntimeException('invalid_match_score');
                            }
                            $reasons = $item['reason_codes'] ?? null;
                            if (!is_array($reasons) || !array_is_list($reasons)) {
                                throw new RuntimeException('invalid_reason_code');
                            }
                            foreach ($reasons as $reason) {
                                if (!is_string($reason) || !in_array($reason, $allowedReasons, true)) {
                                    throw new RuntimeException('invalid_reason_code');
                                }
                            }
                            $items[] = [
                                'candidate_ref' => $ref,
                                'match_score' => $score,
                                'reason_codes' => array_values($reasons),
                            ];
                        }
                        $circuit->recordSuccess();
                        return [
                            'model_version' => (string) ($result['model_version'] ?? $this->config->model() ?? 'gemini-1.5-pro'),
                            'items' => $items,
                        ];
                    }
                    break;
                }
                if (!$retry->shouldRetry($status, null, $attempt)) {
                    break;
                }
                $retryAfter = (int) ($response['headers']['retry-after'] ?? 0);
                ($this->sleeper)($retry->delayMs($attempt, $retryAfter > 0 ? $retryAfter : null));
            } catch (\JsonException) {
                break;
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'invalid_candidate_ref') || str_contains($e->getMessage(), 'invalid_reason_code') || str_contains($e->getMessage(), 'invalid_match_score') || str_contains($e->getMessage(), 'invalid_items')) {
                    $circuit->recordFailure();
                    throw $e;
                }
                if (!$retry->shouldRetry(0, 'network', $attempt)) {
                    break;
                }
                ($this->sleeper)($retry->delayMs($attempt));
            }
        }

        $circuit->recordFailure();
        throw new RuntimeException('Enterprise AI provider unavailable.');
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    private function http(string $url, array $headers, string $body, int $timeout): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl init failed');
        }
        $formatted = [];
        foreach ($headers as $k => $v) {
            $formatted[] = "{$k}: {$v}";
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $formatted,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $retryAfter = defined('CURLINFO_RETRY_AFTER') ? (int) curl_getinfo($ch, CURLINFO_RETRY_AFTER) : 0;
        curl_close($ch);
        return [
            'status' => $status,
            'headers' => $retryAfter > 0 ? ['retry-after' => (string) $retryAfter] : [],
            'body' => is_string($response) ? $response : '',
        ];
    }
}
