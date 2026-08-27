<?php
declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Provider\CircuitBreaker;
use TalentHub\Learner\Ai\Provider\RetryPolicy;
use TalentHub\Modules\School\Service\SchoolAiGeminiExplainer;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once dirname(__DIR__) . '/src/Modules/School/Service/SchoolAiGeminiExplainer.php';

function school_gemini_assert(bool $ok, string $message): void { if (!$ok) throw new RuntimeException("Assertion failed: {$message}"); }

$config = RecommendationConfig::fromEnvironment([
    'APP_ENV' => 'test', 'TALENTHUB_AI_ENABLED' => 'true', 'TALENTHUB_AI_PROVIDER' => 'gemini',
    'TALENTHUB_AI_MODEL' => 'gemini-test', 'TALENTHUB_AI_API_URL' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'generativelanguage.googleapis.com', 'TALENTHUB_AI_API_KEY' => 'server-only-key', 'TALENTHUB_AI_MAX_ATTEMPTS' => '2',
]);
$calls = 0; $sleeps = [];
$explainer = new SchoolAiGeminiExplainer($config, static function(string $url, array $headers, string $body, int $timeout) use (&$calls): array {
    $calls++;
    if ($calls === 1) return ['status' => 429, 'headers' => ['retry-after' => '3'], 'body' => 'temporarily unavailable'];
    return ['status' => 200, 'body' => json_encode(['candidates' => [['content' => ['parts' => [['text' => json_encode(['summary' => 'Ổn định', 'priorities' => ['Theo dõi xu hướng'], 'confidence' => 'medium'])]]]]]], JSON_THROW_ON_ERROR)];
}, new RetryPolicy(2, 1, 5000), new CircuitBreaker(), static function(int $milliseconds) use (&$sleeps): void { $sleeps[] = $milliseconds; });
$result = $explainer(['instructions' => ['Explain aggregate trends only.'], 'aggregate_evidence' => ['cohorts' => [['student_count' => 5, 'trend_signals' => [['label' => 'Tăng']]]]]]);
school_gemini_assert($result['summary'] === 'Ổn định' && $calls === 2 && $sleeps !== [], 'school explainer retries with backoff after provider outage');
school_gemini_assert($sleeps[0] === 3000, 'school explainer honors provider Retry-After');
school_gemini_assert(!str_contains(json_encode($result), 'student-') && !str_contains(json_encode($result), '@'), 'school explainer result contains no learner identifiers');
echo "school_ai_gemini_explainer_test: OK\n";
