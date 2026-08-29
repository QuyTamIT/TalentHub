<?php
declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Provider\CircuitBreaker;
use TalentHub\Learner\Ai\Provider\DatabaseCircuitBreakerStore;
use TalentHub\Modules\Business\Service\EnterpriseAiGeminiMatcher;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once dirname(__DIR__) . '/src/Modules/Business/Service/EnterpriseAiGeminiMatcher.php';

function matcher_assert(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$config = RecommendationConfig::fromEnvironment([
    'APP_ENV' => 'test',
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => 'gemini',
    'TALENTHUB_AI_MODEL' => 'gemini-1.5-pro',
    'TALENTHUB_AI_API_URL' => 'http://127.0.0.1:20128/v1beta/models/gemini-1.5-pro:generateContent',
    'TALENTHUB_AI_API_KEY' => 'test-key-enterprise',
    'TALENTHUB_AI_ALLOWED_HOSTS' => '127.0.0.1',
    'TALENTHUB_AI_SHADOW' => 'false',
    'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '100',
    'TALENTHUB_AI_PILOT_APPROVAL_REFERENCE' => 'test-approval',
    'TALENTHUB_AI_PILOT_PAUSED' => 'false',
]);

$job = [
    'title' => 'Backend Developer',
    'required_skills' => ['PHP', 'SQL'],
];
$candidateProjections = [
    [
        'candidate_ref' => 'candidate_1',
        'verified_skills' => [
            ['name' => 'PHP', 'level_score' => 90.0],
        ],
    ],
    [
        'candidate_ref' => 'candidate_2',
        'verified_skills' => [
            ['name' => 'PHP', 'level_score' => 85.0],
            ['name' => 'SQL', 'level_score' => 95.0],
        ],
    ],
];

// Test 1: Successful structured response
$capturedBody = null;
$mockTransport = static function (string $url, array $headers, string $body) use (&$capturedBody): array {
    $capturedBody = $body;
    $responseJson = json_encode([
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        [
                            'text' => json_encode([
                                'model_version' => 'gemini-1.5-pro',
                                'items' => [
                                    [
                                        'candidate_ref' => 'candidate_2',
                                        'match_score' => 95.0,
                                        'reason_codes' => ['verified_skill_match', 'strong_verified_level'],
                                    ],
                                    [
                                        'candidate_ref' => 'candidate_1',
                                        'match_score' => 80.0,
                                        'reason_codes' => ['partial_skill_match', 'skill_gap'],
                                    ],
                                ],
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    return ['status' => 200, 'headers' => [], 'body' => $responseJson];
};

$matcher = new EnterpriseAiGeminiMatcher($config, $mockTransport);
$result = $matcher($job, $candidateProjections);

matcher_assert($result['model_version'] === 'gemini-1.5-pro', 'matcher returns model_version');
matcher_assert(count($result['items']) === 2, 'matcher returns 2 ranked items');
matcher_assert($result['items'][0]['candidate_ref'] === 'candidate_2', 'candidate_2 is first');
matcher_assert($result['items'][0]['match_score'] === 95.0, 'match score is parsed correctly');
matcher_assert(!str_contains((string) $capturedBody, 'student_id') && !str_contains((string) $capturedBody, 'Alice'), 'captured prompt contains no PII or student_id');

// Test 2: Rejection of invalid / unknown candidate refs
$mockUnknownRef = static function (): array {
    return [
        'status' => 200,
        'headers' => [],
        'body' => json_encode([
            'candidates' => [
                ['content' => ['parts' => [['text' => json_encode([
                    'model_version' => 'gemini-1.5-pro',
                    'items' => [['candidate_ref' => 'candidate_999', 'match_score' => 90.0, 'reason_codes' => ['verified_skill_match']]],
                ])]]]],
            ],
        ]),
    ];
};
$matcherUnknown = new EnterpriseAiGeminiMatcher($config, $mockUnknownRef);
$unknownCaught = false;
try {
    $matcherUnknown($job, $candidateProjections);
} catch (RuntimeException $e) {
    $unknownCaught = true;
}
matcher_assert($unknownCaught, 'unknown candidate_ref throws RuntimeException');

// Test 3: Rejection of unknown reason codes
$mockUnknownReason = static function (): array {
    return [
        'status' => 200,
        'headers' => [],
        'body' => json_encode([
            'candidates' => [
                ['content' => ['parts' => [['text' => json_encode([
                    'model_version' => 'gemini-1.5-pro',
                    'items' => [['candidate_ref' => 'candidate_1', 'match_score' => 90.0, 'reason_codes' => ['invented_reason_code']]],
                ])]]]],
            ],
        ]),
    ];
};
$matcherReason = new EnterpriseAiGeminiMatcher($config, $mockUnknownReason);
$reasonCaught = false;
try {
    $matcherReason($job, $candidateProjections);
} catch (RuntimeException $e) {
    $reasonCaught = true;
}
matcher_assert($reasonCaught, 'invented reason code throws RuntimeException');

// Test 4: Duplicate refs and invalid model score never become partial rankings.
$mockDuplicate = static function (): array {
    $modelText = json_encode([
        'items' => [
            ['candidate_ref' => 'candidate_1', 'match_score' => 70, 'reason_codes' => []],
            ['candidate_ref' => 'candidate_1', 'match_score' => 80, 'reason_codes' => []],
        ],
    ]);
    return [
        'status' => 200,
        'headers' => [],
        'body' => json_encode(['candidates' => [['content' => ['parts' => [['text' => $modelText]]]]]]),
    ];
};
$duplicateCaught = false;
try {
    (new EnterpriseAiGeminiMatcher($config, $mockDuplicate))($job, $candidateProjections);
} catch (RuntimeException) {
    $duplicateCaught = true;
}
matcher_assert($duplicateCaught, 'duplicate candidate_ref throws RuntimeException');

$mockOutOfRange = static function (): array {
    $modelText = json_encode([
        'items' => [['candidate_ref' => 'candidate_1', 'match_score' => 101, 'reason_codes' => []]],
    ]);
    return [
        'status' => 200,
        'headers' => [],
        'body' => json_encode(['candidates' => [['content' => ['parts' => [['text' => $modelText]]]]]]),
    ];
};
$outOfRangeCaught = false;
try {
    (new EnterpriseAiGeminiMatcher($config, $mockOutOfRange))($job, $candidateProjections);
} catch (RuntimeException) {
    $outOfRangeCaught = true;
}
matcher_assert($outOfRangeCaught, 'out-of-range model match_score throws RuntimeException');

// Test 5: Circuit breaker / 429 Retry-After handling
$mock429 = static function (): array {
    return ['status' => 429, 'headers' => ['retry-after' => '30'], 'body' => 'Rate limit exceeded'];
};
$matcher429 = new EnterpriseAiGeminiMatcher($config, $mock429);
$error429Caught = false;
try {
    $matcher429($job, $candidateProjections);
} catch (RuntimeException $e) {
    $error429Caught = true;
}
matcher_assert($error429Caught, '429 throws categorized exception');

echo "enterprise_ai_gemini_matcher_test: OK\n";
