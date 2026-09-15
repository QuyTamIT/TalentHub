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
     * @param list<array<string,mixed>> $candidateProjections
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

        $systemInstruction = "You are TalentHub Enterprise AI Matcher. Evaluate anonymous candidate projections against internship job requirements based on verified skills, real practical projects, domain/major, achievements, and teacher competency assessment scores.\n"
            . "CRITICAL MATCHING & RANKING RULES:\n"
            . "1. DIRECT PROFESSIONAL SKILLS & REAL PRACTICAL PROJECTS FIRST: Technical and specialized domain skills directly required by the job position—demonstrated either via verified skills or real practical projects (e.g. project title, topic, description, technologies)—MUST have the highest weight and priority.\n"
            . "2. HIERARCHY OF RELEVANCE:\n"
            . "   - Candidates with multiple directly matching professional skills or relevant practical project experience MUST be ranked at the very top and receive highest scores (>= 75 for strong alignment, 45-74 for solid partial alignment).\n"
            . "   - Candidates with real practical projects directly utilizing required technologies (e.g. PHP, MySQL/SQL, Backend Development) are highly relevant candidates.\n"
            . "   - Soft skills (Teamwork, Communication, etc.), badges, and teacher assessment scores are strictly supplementary.\n"
            . "   - Candidates who lack both direct professional skills and relevant practical projects MUST NOT be ranked high or classified as 'Rất phù hợp' or 'Phù hợp'. If included, they must receive low scores (<= 35) and 'Có liên quan'.\n"
            . "   - Do not include candidates who have 0 skills, no projects, and no relevance to the job.\n"
            . "3. FACTUAL REASONING FROM REAL DATA ONLY: In 'recommendation_reason', provide a concise, natural Vietnamese explanation strictly referencing ONLY the skills, projects, and metrics that the candidate ACTUALLY possesses. NEVER hallucinate.\n"
            . "4. CLASSIFICATION:\n"
            . "   - 'Rất phù hợp': Score >= 75.0 (possesses multiple directly matching professional skills or strong relevant practical project experience).\n"
            . "   - 'Phù hợp': Score 45.0 - 74.9 (possesses at least 1 core matching professional skill or relevant practical project).\n"
            . "   - 'Có liên quan': Score < 45.0 (related field or supporting skills without core professional skills or projects).\n"
            . "Respond strictly in JSON format matching the schema without markdown formatting.";

        $userPayload = [
            'prompt_version' => 'enterprise-match-3.0.0',
            'job' => $safeJob,
            'candidates' => $candidateProjections,
            'response_schema' => [
                'model_version' => 'string',
                'items' => [
                    [
                        'candidate_ref' => 'string',
                        'match_score' => 'float between 0.0 and 100.0',
                        'match_level' => 'Rất phù hợp | Phù hợp | Có liên quan',
                        'recommendation_reason' => 'string in Vietnamese',
                        'reason_codes' => ['verified_skill_match', 'partial_skill_match', 'skill_gap', 'strong_verified_level', 'domain_match', 'teacher_recommended', 'project_experience'],
                    ],
                ],
            ],
        ];

        $body = json_encode([
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => json_encode($userPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.0,
            ],
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
            'domain_match',
            'teacher_recommended',
            'project_experience',
        ];

        $timeout = max(15, (int) $this->config->timeoutSeconds());

        for ($attempt = 1; $attempt <= $this->config->maxAttempts(); $attempt++) {
            try {
                $response = ($this->transport)(
                    (string) $this->config->apiUrl(),
                    ['Content-Type' => 'application/json', 'x-goog-api-key' => (string) $this->config->apiKey()],
                    $body,
                    $timeout
                );
                $status = (int) ($response['status'] ?? 0);
                $this->logGeminiInteraction('Enterprise AI Matching / Tìm nhân tài bằng AI', $body, $response['body'] ?? '', $status);
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
                            $matchLevel = trim((string) ($item['match_level'] ?? ''));
                            if (!in_array($matchLevel, ['Rất phù hợp', 'Phù hợp', 'Có liên quan'], true)) {
                                $matchLevel = $score >= 75.0 ? 'Rất phù hợp' : ($score >= 45.0 ? 'Phù hợp' : 'Có liên quan');
                            }
                            $recReason = trim((string) ($item['recommendation_reason'] ?? ''));

                            $items[] = [
                                'candidate_ref' => $ref,
                                'match_score' => $score,
                                'match_level' => $matchLevel,
                                'recommendation_reason' => $recReason,
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

    private function logGeminiInteraction(string $action, mixed $requestBody, mixed $responseBody, int $status = 200): void
    {
        try {
            $root = dirname(__DIR__, 4);
            $logDir = $root . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $timestamp = date('Y-m-d H:i:s');
            $reqText = is_string($requestBody) ? $requestBody : json_encode($requestBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $resText = is_string($responseBody) ? $responseBody : json_encode($responseBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $logEntry = "======================================================================\n"
                      . "[{$timestamp}] HÀNH ĐỘNG: {$action} | HTTP STATUS: {$status}\n"
                      . "======================================================================\n"
                      . "PROMPT TRUYỀN LÊN GEMINI:\n"
                      . $reqText . "\n\n"
                      . "TOÀN BỘ RESPONSE TỪ GEMINI:\n"
                      . $resText . "\n\n";

            @file_put_contents($logDir . '/gemini_response.log', $logEntry, FILE_APPEND | LOCK_EX);
            @file_put_contents($root . '/response.log', $logEntry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Không làm gián đoạn luồng chính nếu lỗi ghi log
        }
    }
}
