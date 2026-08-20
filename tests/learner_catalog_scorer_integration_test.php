<?php

declare(strict_types=1);

use TalentHub\Learner\Assessment\Scoring\AssessmentScorer;
use TalentHub\Learner\Assessment\Scoring\DiscScorer;
use TalentHub\Learner\Assessment\Scoring\HollandScorer;
use TalentHub\Learner\Assessment\Scoring\MbtiScorer;
use TalentHub\Learner\Assessment\Scoring\MultipleIntelligenceScorer;
use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use TalentHub\Learner\Assessment\Scoring\ScoringResult;
use TalentHub\Support\Uuid;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/src/Support/Uuid.php';

// =============================================================================
// TEST HARNESS ASSERTION HELPERS
// =============================================================================

$assertionsCount = 0;

function integration_assert(bool $condition, string $message): void
{
    global $assertionsCount;
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
    $assertionsCount++;
}

/**
 * @param callable(): mixed $callback
 * @param class-string<\Throwable> $expectedExceptionClass
 */
function integration_expect_exception(callable $callback, string $expectedExceptionClass, string $message): void
{
    global $assertionsCount;
    try {
        $callback();
    } catch (\Throwable $e) {
        if ($e instanceof $expectedExceptionClass) {
            $assertionsCount++;
            return;
        }
        $actualClass = get_class($e);
        fwrite(STDERR, "Assertion failed (expected {$expectedExceptionClass}, got {$actualClass} with message: {$e->getMessage()}): {$message}\n");
        exit(1);
    }

    fwrite(STDERR, "Assertion failed (no exception thrown, expected {$expectedExceptionClass}): {$message}\n");
    exit(1);
}

/**
 * Executes a scorer twice with identical questions and answers,
 * asserting that the result is 100% deterministic (identical result_code,
 * summary, dimension_scores, and toArray representation).
 *
 * @param AssessmentScorer $scorer
 * @param list<array<string,mixed>> $questions
 * @param array<string,mixed> $answers
 * @param string $contextMessage
 * @return ScoringResult
 */
function assert_deterministic_scoring(
    AssessmentScorer $scorer,
    array $questions,
    array $answers,
    string $contextMessage
): ScoringResult {
    $run1 = $scorer->score($questions, $answers);
    $run2 = $scorer->score($questions, $answers);

    integration_assert($run1 instanceof ScoringResult, "{$contextMessage}: Run 1 must return a ScoringResult instance");
    integration_assert($run2 instanceof ScoringResult, "{$contextMessage}: Run 2 must return a ScoringResult instance");

    $data1 = $run1->toArray();
    $data2 = $run2->toArray();

    integration_assert(
        $data1['result_code'] === $data2['result_code'],
        "{$contextMessage}: Deterministic check failed on result_code ('{$data1['result_code']}' vs '{$data2['result_code']}')"
    );
    integration_assert(
        $data1['summary'] === $data2['summary'],
        "{$contextMessage}: Deterministic check failed on summary ('{$data1['summary']}' vs '{$data2['summary']}')"
    );
    integration_assert(
        $data1['dimension_scores'] === $data2['dimension_scores'],
        "{$contextMessage}: Deterministic check failed on dimension_scores"
    );
    integration_assert(
        $data1 === $data2,
        "{$contextMessage}: Deterministic check failed on toArray() representation"
    );

    return $run1;
}

// =============================================================================
// SYNTHETIC CATALOG FIXTURE GENERATORS
// =============================================================================

function synthetic_test_uuid(int $catalogIdx, int $questionIdx): string
{
    return sprintf(
        'c%03x0000-%04x-4000-8000-%012x',
        $catalogIdx,
        $questionIdx,
        ($catalogIdx * 1000) + $questionIdx
    );
}

/**
 * Standard 5-point Likert options.
 */
const SYNTHETIC_LIKERT_OPTIONS = [
    ['value' => 1, 'label' => 'Hoàn toàn không đồng ý'],
    ['value' => 2, 'label' => 'Không đồng ý'],
    ['value' => 3, 'label' => 'Bình thường'],
    ['value' => 4, 'label' => 'Đồng ý'],
    ['value' => 5, 'label' => 'Hoàn toàn đồng ý'],
];

/**
 * Build a canonical synthetic catalog fixture compliant with catalog schema and scorer contract.
 *
 * @param 'holland'|'mbti'|'disc'|'multiple_intelligence' $framework
 * @param 'middle'|'high'|'college' $band
 * @param int $catalogIdx
 * @param int $itemsPerDimension (default 2 for minimal test, e.g. 5 for Holland 30-item, 4 for MBTI 32-item)
 * @return array{
 *   metadata: array{
 *     framework: string,
 *     education_band: string,
 *     scoring_version: string,
 *     question_count: int,
 *     stable_code_namespace: string,
 *     review_state: string,
 *     review_events: list<array<string,string>>,
 *     schema_hash: ?string,
 *     advisory_disclaimer: string
 *   },
 *   questions: list<array{
 *     id: string,
 *     code: string,
 *     position: int,
 *     dimension_code: string,
 *     required: bool,
 *     content: string,
 *     options: list<array{value:int,label:string}>
 *   }>
 * }
 */
function build_synthetic_catalog(
    string $framework,
    string $band,
    int $catalogIdx,
    int $itemsPerDimension = 2
): array {
    $namespace = "{$framework}_{$band}_";
    $questions = [];
    $pos = 1;

    $scoringVersion = match ($framework) {
        'holland' => 'holland-riasec-1.0',
        'mbti' => 'mbti-education-1.0',
        'disc' => 'disc-education-1.0',
        'multiple_intelligence' => 'multiple-intelligence-1.0',
    };

    $disclaimer = match ($framework) {
        'holland' => 'Kết quả Holland chỉ mang tính định hướng nghề nghiệp, không phải chẩn đoán tâm lý hay xác nhận nghề nghiệp.',
        'mbti' => 'Đây là bộ câu hỏi định hướng học tập nội bộ, không phải công cụ MBTI chính thức hay đánh giá tâm lý.',
        'disc' => 'Kết quả DISC chỉ mang tính tham khảo cho giao tiếp và làm việc nhóm, không phải công cụ đánh giá nhân sự.',
        'multiple_intelligence' => 'Định hướng đa trí thông minh giúp chọn trải nghiệm học tập, không phải chỉ số năng lực hay chẩn đoán.',
    };

    if ($framework === 'holland') {
        $dimensions = ['R', 'I', 'A', 'S', 'E', 'C'];
        foreach ($dimensions as $dim) {
            for ($i = 1; $i <= $itemsPerDimension; $i++) {
                $isReversed = ($i % 2 === 0);
                $dimSuffix = $isReversed ? ':-' : ':+';
                $qId = synthetic_test_uuid($catalogIdx, $pos);
                $qCode = sprintf('%s%s_%03d', $namespace, strtolower($dim), $pos);
                $content = "Holland prompt for {$dim} item {$i} ({$band})";
                $questions[] = [
                    'id' => $qId,
                    'code' => $qCode,
                    'position' => $pos,
                    'dimension_code' => "{$dim}{$dimSuffix}",
                    'required' => true,
                    'content' => $content,
                    'options' => SYNTHETIC_LIKERT_OPTIONS,
                ];
                $pos++;
            }
        }
    } elseif ($framework === 'disc') {
        $dimensions = ['D', 'I', 'S', 'C'];
        foreach ($dimensions as $dim) {
            for ($i = 1; $i <= $itemsPerDimension; $i++) {
                $isReversed = ($i % 2 === 0);
                $dimSuffix = $isReversed ? ':-' : ':+';
                $qId = synthetic_test_uuid($catalogIdx, $pos);
                $qCode = sprintf('%s%s_%03d', $namespace, strtolower($dim), $pos);
                $content = "DISC prompt for {$dim} item {$i} ({$band})";
                $questions[] = [
                    'id' => $qId,
                    'code' => $qCode,
                    'position' => $pos,
                    'dimension_code' => "{$dim}{$dimSuffix}",
                    'required' => true,
                    'content' => $content,
                    'options' => SYNTHETIC_LIKERT_OPTIONS,
                ];
                $pos++;
            }
        }
    } elseif ($framework === 'multiple_intelligence') {
        $dimensions = ['LING', 'LOGI', 'SPAT', 'BODY', 'MUSIC', 'INTER', 'INTRA', 'NAT'];
        foreach ($dimensions as $dim) {
            for ($i = 1; $i <= $itemsPerDimension; $i++) {
                $isReversed = ($i % 2 === 0);
                $dimSuffix = $isReversed ? ':-' : ':+';
                $qId = synthetic_test_uuid($catalogIdx, $pos);
                $qCode = sprintf('%s%s_%03d', $namespace, strtolower($dim), $pos);
                $content = "MI prompt for {$dim} item {$i} ({$band})";
                $questions[] = [
                    'id' => $qId,
                    'code' => $qCode,
                    'position' => $pos,
                    'dimension_code' => "{$dim}{$dimSuffix}",
                    'required' => true,
                    'content' => $content,
                    'options' => SYNTHETIC_LIKERT_OPTIONS,
                ];
                $pos++;
            }
        }
    } elseif ($framework === 'mbti') {
        $axes = [
            'EI' => ['E', 'I'],
            'SN' => ['S', 'N'],
            'TF' => ['T', 'F'],
            'JP' => ['J', 'P'],
        ];
        foreach ($axes as $axis => $poles) {
            foreach ($poles as $pole) {
                for ($i = 1; $i <= $itemsPerDimension; $i++) {
                    $qId = synthetic_test_uuid($catalogIdx, $pos);
                    $qCode = sprintf('%s%s_%03d', $namespace, strtolower($pole), $pos);
                    $content = "MBTI prompt for {$axis}:{$pole} item {$i} ({$band})";
                    $questions[] = [
                        'id' => $qId,
                        'code' => $qCode,
                        'position' => $pos,
                        'dimension_code' => "{$axis}:{$pole}",
                        'required' => true,
                        'content' => $content,
                        'options' => SYNTHETIC_LIKERT_OPTIONS,
                    ];
                    $pos++;
                }
            }
        }
    }

    return [
        'metadata' => [
            'framework' => $framework,
            'education_band' => $band,
            'scoring_version' => $scoringVersion,
            'question_count' => count($questions),
            'stable_code_namespace' => $namespace,
            'review_state' => 'draft',
            'review_events' => [],
            'schema_hash' => null,
            'advisory_disclaimer' => $disclaimer,
        ],
        'questions' => $questions,
    ];
}

// =============================================================================
// GLOBAL SETUP
// =============================================================================

echo "=== STARTING LEARNER CATALOG SCORER INTEGRATION TEST SUITE ===\n";

// Verify ScorerRegistry and real scorer classes exist
integration_assert(class_exists(HollandScorer::class), 'HollandScorer class must exist');
integration_assert(class_exists(MbtiScorer::class), 'MbtiScorer class must exist');
integration_assert(class_exists(DiscScorer::class), 'DiscScorer class must exist');
integration_assert(class_exists(MultipleIntelligenceScorer::class), 'MultipleIntelligenceScorer class must exist');
integration_assert(class_exists(ScorerRegistry::class), 'ScorerRegistry class must exist');

$hollandScorer = new HollandScorer();
$mbtiScorer = new MbtiScorer();
$discScorer = new DiscScorer();
$miScorer = new MultipleIntelligenceScorer();

integration_assert($hollandScorer instanceof AssessmentScorer, 'HollandScorer implements AssessmentScorer');
integration_assert($mbtiScorer instanceof AssessmentScorer, 'MbtiScorer implements AssessmentScorer');
integration_assert($discScorer instanceof AssessmentScorer, 'DiscScorer implements AssessmentScorer');
integration_assert($miScorer instanceof AssessmentScorer, 'MultipleIntelligenceScorer implements AssessmentScorer');

$scorerRegistry = new ScorerRegistry([
    'holland-riasec-1.0' => $hollandScorer,
    'mbti-education-1.0' => $mbtiScorer,
    'disc-education-1.0' => $discScorer,
    'multiple-intelligence-1.0' => $miScorer,
]);

// =============================================================================
// SECTION 1: HOLLAND SCORER INTEGRATION TESTS
// =============================================================================

echo "\n--- Section 1: Holland Scorer Integration ---\n";

// 1.1 Minimal 12-question synthetic Holland catalog (2 questions per RIASEC dimension)
$holland12 = build_synthetic_catalog('holland', 'middle', 1, 2);
integration_assert(count($holland12['questions']) === 12, 'Holland synthetic catalog has exactly 12 questions');
integration_assert($holland12['metadata']['question_count'] === 12, 'Holland metadata question_count is 12');

// Build answers with graded values for RIASEC:
// R (q1=5 pos, q2=1 rev->5) => norm(10, 2) = 100
// I (q3=5 pos, q4=2 rev->4) => norm(9, 2) = 88
// A (q5=4 pos, q6=2 rev->4) => norm(8, 2) = 75
// S (q7=3 pos, q8=3 rev->3) => norm(6, 2) = 50
// E (q9=2 pos, q10=4 rev->2) => norm(4, 2) = 25
// C (q11=1 pos, q12=5 rev->1) => norm(2, 2) = 0
$hollandAnswersGraded = [
    $holland12['questions'][0]['id'] => 5, // R:+
    $holland12['questions'][1]['id'] => 1, // R:- (6-1=5)
    $holland12['questions'][2]['id'] => 5, // I:+
    $holland12['questions'][3]['id'] => 2, // I:- (6-2=4)
    $holland12['questions'][4]['id'] => 4, // A:+
    $holland12['questions'][5]['id'] => 2, // A:- (6-2=4)
    $holland12['questions'][6]['id'] => 3, // S:+
    $holland12['questions'][7]['id'] => 3, // S:- (6-3=3)
    $holland12['questions'][8]['id'] => 2, // E:+
    $holland12['questions'][9]['id'] => 4, // E:- (6-4=2)
    $holland12['questions'][10]['id'] => 1, // C:+
    $holland12['questions'][11]['id'] => 5, // C:- (6-5=1)
];

// Run directly and through registry with determinism assertions
$hollandResult1 = assert_deterministic_scoring(
    $hollandScorer,
    $holland12['questions'],
    $hollandAnswersGraded,
    'Holland direct scorer graded profile'
);
$hollandResultReg = assert_deterministic_scoring(
    $scorerRegistry->forVersion('holland-riasec-1.0'),
    $holland12['questions'],
    $hollandAnswersGraded,
    'Holland ScorerRegistry graded profile'
);
integration_assert(
    $hollandResult1->toArray() === $hollandResultReg->toArray(),
    'Holland direct scorer and ScorerRegistry results must match exactly'
);

// Verify Holland dimensions and score bounds
$hollandData = $hollandResult1->toArray();
$expectedHollandDims = ['R', 'I', 'A', 'S', 'E', 'C'];
integration_assert(count($hollandData['dimension_scores']) === 6, 'Holland must contain exactly 6 dimensions');
foreach ($expectedHollandDims as $dim) {
    integration_assert(array_key_exists($dim, $hollandData['dimension_scores']), "Holland dimension {$dim} must be present");
    $score = $hollandData['dimension_scores'][$dim];
    integration_assert(is_int($score), "Holland {$dim} score must be integer");
    integration_assert($score >= 0 && $score <= 100, "Holland {$dim} score {$score} must be between 0 and 100");
}
integration_assert($hollandData['dimension_scores']['R'] === 100, 'Holland R score is 100');
integration_assert($hollandData['dimension_scores']['I'] === 88, 'Holland I score is 88');
integration_assert($hollandData['dimension_scores']['A'] === 75, 'Holland A score is 75');
integration_assert($hollandData['dimension_scores']['S'] === 50, 'Holland S score is 50');
integration_assert($hollandData['dimension_scores']['E'] === 25, 'Holland E score is 25');
integration_assert($hollandData['dimension_scores']['C'] === 0, 'Holland C score is 0');

// Verify Holland result_code format (3 uppercase RIASEC letters, distinct)
integration_assert(
    preg_match('/\A[RIASEC]{3}\z/', $hollandData['result_code']) === 1,
    "Holland result_code '{$hollandData['result_code']}' must match RIASEC 3-character format"
);
integration_assert(count(array_unique(str_split($hollandData['result_code']))) === 3, 'Holland result_code must contain 3 unique letters');
integration_assert($hollandData['result_code'] === 'RIA', "Holland expected result_code 'RIA', got '{$hollandData['result_code']}'");
integration_assert(
    $hollandData['summary'] === 'Định hướng nghề nghiệp theo mô hình Holland RIASEC.',
    'Holland summary must match contract'
);

// 1.2 Holland Inverted Profile: C > E > S > A > I > R
$hollandAnswersInverted = [
    $holland12['questions'][0]['id'] => 1, // R:+
    $holland12['questions'][1]['id'] => 5, // R:- (1) => R = 0
    $holland12['questions'][2]['id'] => 2, // I:+
    $holland12['questions'][3]['id'] => 4, // I:- (2) => I = 25
    $holland12['questions'][4]['id'] => 3, // A:+
    $holland12['questions'][5]['id'] => 3, // A:- (3) => A = 50
    $holland12['questions'][6]['id'] => 4, // S:+
    $holland12['questions'][7]['id'] => 2, // S:- (4) => S = 75
    $holland12['questions'][8]['id'] => 5, // E:+
    $holland12['questions'][9]['id'] => 2, // E:- (4) => E = 88
    $holland12['questions'][10]['id'] => 5, // C:+
    $holland12['questions'][11]['id'] => 1, // C:- (5) => C = 100
];
$invResult = assert_deterministic_scoring(
    $hollandScorer,
    $holland12['questions'],
    $hollandAnswersInverted,
    'Holland inverted profile'
)->toArray();
integration_assert($invResult['result_code'] === 'CES', "Holland inverted expected 'CES', got '{$invResult['result_code']}'");
integration_assert($invResult['dimension_scores']['C'] === 100, 'C is 100');
integration_assert($invResult['dimension_scores']['R'] === 0, 'R is 0');

// 1.3 Holland Stable Tie-break (all answers = 3 -> all scores = 50 -> stable order R, I, A, S, E, C -> 'RIA')
$hollandTiedAnswers = [];
foreach ($holland12['questions'] as $q) {
    $hollandTiedAnswers[$q['id']] = 3;
}
$tiedResult = assert_deterministic_scoring(
    $hollandScorer,
    $holland12['questions'],
    $hollandTiedAnswers,
    'Holland stable tie-break'
)->toArray();
integration_assert($tiedResult['result_code'] === 'RIA', "Holland tie-break must produce 'RIA', got '{$tiedResult['result_code']}'");
foreach ($expectedHollandDims as $dim) {
    integration_assert($tiedResult['dimension_scores'][$dim] === 50, "Holland tied dimension {$dim} score must be 50");
}

// 1.4 Holland Multi-Band 30-item catalogs (Middle, High, College)
foreach (['middle', 'high', 'college'] as $band) {
    $cat = build_synthetic_catalog('holland', $band, 10, 5); // 6 dims * 5 items = 30 questions
    integration_assert(count($cat['questions']) === 30, "Holland {$band} synthetic catalog has 30 questions");
    $answers = [];
    foreach ($cat['questions'] as $idx => $q) {
        $answers[$q['id']] = (($idx % 5) + 1);
    }
    $bandRes = assert_deterministic_scoring(
        $hollandScorer,
        $cat['questions'],
        $answers,
        "Holland {$band} 30-item catalog"
    )->toArray();
    integration_assert(
        preg_match('/\A[RIASEC]{3}\z/', $bandRes['result_code']) === 1,
        "Holland {$band} result_code valid"
    );
    foreach ($expectedHollandDims as $dim) {
        integration_assert(
            $bandRes['dimension_scores'][$dim] >= 0 && $bandRes['dimension_scores'][$dim] <= 100,
            "Holland {$band} dimension {$dim} in [0, 100]"
        );
    }
}

// 1.5 Holland Error & Edge cases
integration_expect_exception(
    static fn () => $hollandScorer->score($holland12['questions'], [$holland12['questions'][0]['id'] => 5]),
    RuntimeException::class,
    'Holland missing required question must throw RuntimeException'
);

$invalidDimQ = [
    ['id' => 'q-invalid', 'dimension_code' => 'X:+', 'required' => true],
];
integration_expect_exception(
    static fn () => $hollandScorer->score($invalidDimQ, ['q-invalid' => 4]),
    RuntimeException::class,
    'Holland invalid dimension code must throw RuntimeException'
);

// Optional questions behavior
$optHollandQuestions = [
    ['id' => 'q1', 'dimension_code' => 'R:+', 'required' => true],
    ['id' => 'q2', 'dimension_code' => 'I:+', 'required' => false],
];
$optHollandRes = $hollandScorer->score($optHollandQuestions, ['q1' => 5])->toArray();
integration_assert($optHollandRes['dimension_scores']['R'] === 100, 'Holland optional test: R scored');
integration_assert($optHollandRes['dimension_scores']['I'] === 0, 'Holland optional test: unanswered I defaults to 0');

echo "Holland Scorer Integration: PASS\n";

// =============================================================================
// SECTION 2: MBTI SCORER INTEGRATION TESTS
// =============================================================================

echo "\n--- Section 2: MBTI Scorer Integration ---\n";

// 2.1 Minimal 8-question synthetic MBTI catalog (1 question per pole, 2 per axis)
$mbti8 = build_synthetic_catalog('mbti', 'middle', 2, 1);
integration_assert(count($mbti8['questions']) === 8, 'MBTI 8-question catalog has 8 questions');

// Fixture 1: Target ENTJ
// Axis EI: q1 (EI:E) = 5 -> E+=5, I+=1; q2 (EI:I) = 1 -> I+=1, E+=5. Totals: E=10(c=2)->100, I=2(c=2)->0
// Axis SN: q3 (SN:S) = 1 -> S+=1, N+=5; q4 (SN:N) = 5 -> N+=5, S+=1. Totals: S=2(c=2)->0, N=10(c=2)->100
// Axis TF: q5 (TF:T) = 5 -> T+=5, F+=1; q6 (TF:F) = 1 -> F+=1, T+=5. Totals: T=10(c=2)->100, F=2(c=2)->0
// Axis JP: q7 (JP:J) = 5 -> J+=5, P+=1; q8 (JP:P) = 1 -> P+=1, J+=5. Totals: J=10(c=2)->100, P=2(c=2)->0
$mbtiAnswersENTJ = [
    $mbti8['questions'][0]['id'] => 5, // EI:E
    $mbti8['questions'][1]['id'] => 1, // EI:I
    $mbti8['questions'][2]['id'] => 1, // SN:S
    $mbti8['questions'][3]['id'] => 5, // SN:N
    $mbti8['questions'][4]['id'] => 5, // TF:T
    $mbti8['questions'][5]['id'] => 1, // TF:F
    $mbti8['questions'][6]['id'] => 5, // JP:J
    $mbti8['questions'][7]['id'] => 1, // JP:P
];

$mbtiResult1 = assert_deterministic_scoring(
    $mbtiScorer,
    $mbti8['questions'],
    $mbtiAnswersENTJ,
    'MBTI direct scorer ENTJ profile'
);
$mbtiResultReg = assert_deterministic_scoring(
    $scorerRegistry->forVersion('mbti-education-1.0'),
    $mbti8['questions'],
    $mbtiAnswersENTJ,
    'MBTI ScorerRegistry ENTJ profile'
);
integration_assert(
    $mbtiResult1->toArray() === $mbtiResultReg->toArray(),
    'MBTI direct scorer and ScorerRegistry results must match exactly'
);

$mbtiData = $mbtiResult1->toArray();
$expectedMbtiPoles = ['E', 'I', 'S', 'N', 'T', 'F', 'J', 'P'];
integration_assert(
    array_keys($mbtiData['dimension_scores']) === $expectedMbtiPoles,
    'MBTI dimension_scores must contain all 8 poles in exact order: E, I, S, N, T, F, J, P'
);
foreach ($expectedMbtiPoles as $pole) {
    $score = $mbtiData['dimension_scores'][$pole];
    integration_assert(is_int($score), "MBTI pole {$pole} score must be integer");
    integration_assert($score >= 0 && $score <= 100, "MBTI pole {$pole} score {$score} must be between 0 and 100");
}
integration_assert($mbtiData['dimension_scores']['E'] === 100, 'MBTI E score is 100');
integration_assert($mbtiData['dimension_scores']['I'] === 0, 'MBTI I score is 0');
integration_assert($mbtiData['dimension_scores']['S'] === 0, 'MBTI S score is 0');
integration_assert($mbtiData['dimension_scores']['N'] === 100, 'MBTI N score is 100');
integration_assert($mbtiData['dimension_scores']['T'] === 100, 'MBTI T score is 100');
integration_assert($mbtiData['dimension_scores']['F'] === 0, 'MBTI F score is 0');
integration_assert($mbtiData['dimension_scores']['J'] === 100, 'MBTI J score is 100');
integration_assert($mbtiData['dimension_scores']['P'] === 0, 'MBTI P score is 0');

integration_assert(
    preg_match('/\A[EI][SN][TF][JP]\z/', $mbtiData['result_code']) === 1,
    "MBTI result_code '{$mbtiData['result_code']}' must match 4-letter type regex"
);
integration_assert($mbtiData['result_code'] === 'ENTJ', "MBTI expected 'ENTJ', got '{$mbtiData['result_code']}'");
integration_assert(
    $mbtiData['summary'] === 'Xu hướng học tập và làm việc theo bốn trục tham khảo.',
    'MBTI summary must match contract'
);

// 2.2 MBTI Inverted Profile: Target ISFP
$mbtiAnswersISFP = [
    $mbti8['questions'][0]['id'] => 1, // EI:E -> E=0, I=100
    $mbti8['questions'][1]['id'] => 5, // EI:I
    $mbti8['questions'][2]['id'] => 5, // SN:S -> S=100, N=0
    $mbti8['questions'][3]['id'] => 1, // SN:N
    $mbti8['questions'][4]['id'] => 1, // TF:T -> T=0, F=100
    $mbti8['questions'][5]['id'] => 5, // TF:F
    $mbti8['questions'][6]['id'] => 1, // JP:J -> J=0, P=100
    $mbti8['questions'][7]['id'] => 5, // JP:P
];
$isfpResult = assert_deterministic_scoring(
    $mbtiScorer,
    $mbti8['questions'],
    $mbtiAnswersISFP,
    'MBTI ISFP profile'
)->toArray();
integration_assert($isfpResult['result_code'] === 'ISFP', "MBTI expected 'ISFP', got '{$isfpResult['result_code']}'");
integration_assert($isfpResult['dimension_scores']['I'] === 100, 'I score is 100');
integration_assert($isfpResult['dimension_scores']['S'] === 100, 'S score is 100');
integration_assert($isfpResult['dimension_scores']['F'] === 100, 'F score is 100');
integration_assert($isfpResult['dimension_scores']['P'] === 100, 'P score is 100');

// 2.3 MBTI Exact Tie on All Axes -> Defaults to 'ESTJ'
$mbtiTiedAnswers = [];
foreach ($mbti8['questions'] as $q) {
    $mbtiTiedAnswers[$q['id']] = 3;
}
$mbtiTiedResult = assert_deterministic_scoring(
    $mbtiScorer,
    $mbti8['questions'],
    $mbtiTiedAnswers,
    'MBTI exact tie on all axes'
)->toArray();
integration_assert(
    $mbtiTiedResult['result_code'] === 'ESTJ',
    "MBTI exact tie must default to 'ESTJ', got '{$mbtiTiedResult['result_code']}'"
);
foreach ($expectedMbtiPoles as $pole) {
    integration_assert($mbtiTiedResult['dimension_scores'][$pole] === 50, "MBTI tied pole {$pole} score must be 50");
}

// 2.4 MBTI Multi-Band 32-item Catalogs (Middle, High, College: 4 questions per pole × 8 poles)
foreach (['middle', 'high', 'college'] as $band) {
    $cat = build_synthetic_catalog('mbti', $band, 20, 4); // 8 poles * 4 = 32 questions
    integration_assert(count($cat['questions']) === 32, "MBTI {$band} catalog has 32 questions");
    $answers = [];
    foreach ($cat['questions'] as $idx => $q) {
        $answers[$q['id']] = (($idx % 5) + 1);
    }
    $bandRes = assert_deterministic_scoring(
        $mbtiScorer,
        $cat['questions'],
        $answers,
        "MBTI {$band} 32-item catalog"
    )->toArray();
    integration_assert(
        preg_match('/\A[EI][SN][TF][JP]\z/', $bandRes['result_code']) === 1,
        "MBTI {$band} result_code valid"
    );
    foreach ($expectedMbtiPoles as $pole) {
        integration_assert(
            $bandRes['dimension_scores'][$pole] >= 0 && $bandRes['dimension_scores'][$pole] <= 100,
            "MBTI {$band} pole {$pole} in [0, 100]"
        );
    }
}

// 2.5 MBTI Error & Edge cases
integration_expect_exception(
    static fn () => $mbtiScorer->score($mbti8['questions'], [$mbti8['questions'][0]['id'] => 5]),
    RuntimeException::class,
    'MBTI missing required question must throw RuntimeException'
);

$invalidMbtiDimQ = [
    ['id' => 'q-invalid', 'dimension_code' => 'EI:X', 'required' => true],
];
integration_expect_exception(
    static fn () => $mbtiScorer->score($invalidMbtiDimQ, ['q-invalid' => 4]),
    RuntimeException::class,
    'MBTI invalid pole must throw RuntimeException'
);

$invalidMbtiSuffixQ = [
    ['id' => 'q-suffix', 'dimension_code' => 'EI:E:+', 'required' => true],
];
integration_expect_exception(
    static fn () => $mbtiScorer->score($invalidMbtiSuffixQ, ['q-suffix' => 4]),
    RuntimeException::class,
    'MBTI with reverse suffix must throw RuntimeException'
);

echo "MBTI Scorer Integration: PASS\n";

// =============================================================================
// SECTION 3: DISC SCORER INTEGRATION TESTS
// =============================================================================

echo "\n--- Section 3: DISC Scorer Integration ---\n";

// 3.1 Minimal 8-question synthetic DISC catalog (2 per dimension D, I, S, C: 1 pos + 1 rev)
$disc8 = build_synthetic_catalog('disc', 'middle', 3, 2);
integration_assert(count($disc8['questions']) === 8, 'DISC 8-question catalog has 8 questions');

// Build answers with graded ranking:
// I (q3=5 pos, q4=1 rev->5) => norm(10, 2) = 100
// S (q5=4 pos, q6=2 rev->4) => norm(8, 2) = 75
// C (q7=3 pos, q8=3 rev->3) => norm(6, 2) = 50
// D (q1=1 pos, q2=4 rev->2) => norm(3, 2) = 13 (rounded)
// Expected ranking: I (100) > S (75) > C (50) > D (13) => 'ISCD'
$discAnswersISCD = [
    $disc8['questions'][0]['id'] => 1, // D:+
    $disc8['questions'][1]['id'] => 4, // D:- (6-4=2)
    $disc8['questions'][2]['id'] => 5, // I:+
    $disc8['questions'][3]['id'] => 1, // I:- (6-1=5)
    $disc8['questions'][4]['id'] => 4, // S:+
    $disc8['questions'][5]['id'] => 2, // S:- (6-2=4)
    $disc8['questions'][6]['id'] => 3, // C:+
    $disc8['questions'][7]['id'] => 3, // C:- (6-3=3)
];

$discResult1 = assert_deterministic_scoring(
    $discScorer,
    $disc8['questions'],
    $discAnswersISCD,
    'DISC direct scorer ISCD profile'
);
$discResultReg = assert_deterministic_scoring(
    $scorerRegistry->forVersion('disc-education-1.0'),
    $disc8['questions'],
    $discAnswersISCD,
    'DISC ScorerRegistry ISCD profile'
);
integration_assert(
    $discResult1->toArray() === $discResultReg->toArray(),
    'DISC direct scorer and ScorerRegistry results must match exactly'
);

$discData = $discResult1->toArray();
$expectedDiscDims = ['D', 'I', 'S', 'C'];
integration_assert(count($discData['dimension_scores']) === 4, 'DISC must contain exactly 4 dimensions');
foreach ($expectedDiscDims as $dim) {
    integration_assert(array_key_exists($dim, $discData['dimension_scores']), "DISC dimension {$dim} must exist");
    $score = $discData['dimension_scores'][$dim];
    integration_assert(is_int($score), "DISC {$dim} score must be integer");
    integration_assert($score >= 0 && $score <= 100, "DISC {$dim} score {$score} must be between 0 and 100");
}
integration_assert($discData['dimension_scores']['I'] === 100, 'DISC I score is 100');
integration_assert($discData['dimension_scores']['S'] === 75, 'DISC S score is 75');
integration_assert($discData['dimension_scores']['C'] === 50, 'DISC C score is 50');
integration_assert($discData['dimension_scores']['D'] === 13, 'DISC D score is 13');

// DISC result_code format (full 4-letter permutation of D, I, S, C)
integration_assert(
    preg_match('/\A[DISC]{4}\z/', $discData['result_code']) === 1,
    "DISC result_code '{$discData['result_code']}' must match 4-character format"
);
integration_assert(
    count(array_unique(str_split($discData['result_code']))) === 4,
    'DISC result_code must contain all 4 unique letters'
);
integration_assert($discData['result_code'] === 'ISCD', "DISC expected result_code 'ISCD', got '{$discData['result_code']}'");
integration_assert(
    $discData['summary'] === 'Xu hướng hành vi học tập và làm việc nhóm theo DISC.',
    'DISC summary must match contract'
);

// 3.2 DISC Inverted Ranking: Target DCIS
$discAnswersDCIS = [
    $disc8['questions'][0]['id'] => 5, // D:+
    $disc8['questions'][1]['id'] => 1, // D:- (5) => D = 100
    $disc8['questions'][2]['id'] => 3, // I:+
    $disc8['questions'][3]['id'] => 3, // I:- (3) => I = 50
    $disc8['questions'][4]['id'] => 1, // S:+
    $disc8['questions'][5]['id'] => 5, // S:- (1) => S = 0
    $disc8['questions'][6]['id'] => 4, // C:+
    $disc8['questions'][7]['id'] => 2, // C:- (4) => C = 75
];
$dcisResult = assert_deterministic_scoring(
    $discScorer,
    $disc8['questions'],
    $discAnswersDCIS,
    'DISC DCIS profile'
)->toArray();
integration_assert($dcisResult['result_code'] === 'DCIS', "DISC expected 'DCIS', got '{$dcisResult['result_code']}'");

// 3.3 DISC Stable Tie-break (all answers = 3 -> all scores = 50 -> stable order D, I, S, C -> 'DISC')
$discTiedAnswers = [];
foreach ($disc8['questions'] as $q) {
    $discTiedAnswers[$q['id']] = 3;
}
$discTiedResult = assert_deterministic_scoring(
    $discScorer,
    $disc8['questions'],
    $discTiedAnswers,
    'DISC stable tie-break'
)->toArray();
integration_assert($discTiedResult['result_code'] === 'DISC', "DISC tie-break must produce 'DISC', got '{$discTiedResult['result_code']}'");
foreach ($expectedDiscDims as $dim) {
    integration_assert($discTiedResult['dimension_scores'][$dim] === 50, "DISC tied {$dim} score must be 50");
}

// 3.4 DISC Multi-Band Catalogs (Middle, High, College: 7 questions per dimension = 28 questions)
foreach (['middle', 'high', 'college'] as $band) {
    $cat = build_synthetic_catalog('disc', $band, 30, 7);
    integration_assert(count($cat['questions']) === 28, "DISC {$band} catalog has 28 questions");
    $answers = [];
    foreach ($cat['questions'] as $idx => $q) {
        $answers[$q['id']] = (($idx % 5) + 1);
    }
    $bandRes = assert_deterministic_scoring(
        $discScorer,
        $cat['questions'],
        $answers,
        "DISC {$band} 28-item catalog"
    )->toArray();
    integration_assert(
        preg_match('/\A[DISC]{4}\z/', $bandRes['result_code']) === 1,
        "DISC {$band} result_code valid"
    );
    integration_assert(
        count(array_unique(str_split($bandRes['result_code']))) === 4,
        "DISC {$band} result_code contains 4 unique letters"
    );
}

// 3.5 DISC Error & Edge cases
integration_expect_exception(
    static fn () => $discScorer->score($disc8['questions'], [$disc8['questions'][0]['id'] => 5]),
    RuntimeException::class,
    'DISC missing required question must throw RuntimeException'
);

$invalidDiscDimQ = [
    ['id' => 'q-invalid', 'dimension_code' => 'Z:+', 'required' => true],
];
integration_expect_exception(
    static fn () => $discScorer->score($invalidDiscDimQ, ['q-invalid' => 4]),
    RuntimeException::class,
    'DISC invalid dimension code must throw RuntimeException'
);

echo "DISC Scorer Integration: PASS\n";

// =============================================================================
// SECTION 4: MULTIPLE INTELLIGENCE (MI) SCORER INTEGRATION TESTS
// =============================================================================

echo "\n--- Section 4: Multiple Intelligence Scorer Integration ---\n";

// 4.1 Minimal 16-question synthetic MI catalog (2 per dimension across 8 dimensions: 1 pos + 1 rev)
$mi16 = build_synthetic_catalog('multiple_intelligence', 'middle', 4, 2);
integration_assert(count($mi16['questions']) === 16, 'MI 16-question catalog has 16 questions');

// Build answers with graded ranking for 8 dimensions:
// LOGI: q3=5 pos, q4=1 rev->5 => norm(10, 2) = 100
// INTER: q11=5 pos, q12=2 rev->4 => norm(9, 2) = 88
// SPAT: q5=4 pos, q6=2 rev->4 => norm(8, 2) = 75
// LING: q1=3 pos, q2=3 rev->3 => norm(6, 2) = 50
// BODY: q7=3 pos, q8=4 rev->2 => norm(5, 2) = 38
// MUSIC: q9=2 pos, q10=4 rev->2 => norm(4, 2) = 25
// INTRA: q13=2 pos, q14=5 rev->1 => norm(3, 2) = 13
// NAT: q15=1 pos, q16=5 rev->1 => norm(2, 2) = 0
// Order of questions in build_synthetic_catalog: LING(0,1), LOGI(2,3), SPAT(4,5), BODY(6,7), MUSIC(8,9), INTER(10,11), INTRA(12,13), NAT(14,15)
$miAnswersGraded = [
    $mi16['questions'][0]['id'] => 3,  // LING:+
    $mi16['questions'][1]['id'] => 3,  // LING:- (3) -> 50
    $mi16['questions'][2]['id'] => 5,  // LOGI:+
    $mi16['questions'][3]['id'] => 1,  // LOGI:- (5) -> 100
    $mi16['questions'][4]['id'] => 4,  // SPAT:+
    $mi16['questions'][5]['id'] => 2,  // SPAT:- (4) -> 75
    $mi16['questions'][6]['id'] => 3,  // BODY:+
    $mi16['questions'][7]['id'] => 4,  // BODY:- (2) -> 38
    $mi16['questions'][8]['id'] => 2,  // MUSIC:+
    $mi16['questions'][9]['id'] => 4,  // MUSIC:- (2) -> 25
    $mi16['questions'][10]['id'] => 5, // INTER:+
    $mi16['questions'][11]['id'] => 2, // INTER:- (4) -> 88
    $mi16['questions'][12]['id'] => 2, // INTRA:+
    $mi16['questions'][13]['id'] => 5, // INTRA:- (1) -> 13
    $mi16['questions'][14]['id'] => 1, // NAT:+
    $mi16['questions'][15]['id'] => 5, // NAT:- (1) -> 0
];

$miResult1 = assert_deterministic_scoring(
    $miScorer,
    $mi16['questions'],
    $miAnswersGraded,
    'MI direct scorer graded profile'
);
$miResultReg = assert_deterministic_scoring(
    $scorerRegistry->forVersion('multiple-intelligence-1.0'),
    $mi16['questions'],
    $miAnswersGraded,
    'MI ScorerRegistry graded profile'
);
integration_assert(
    $miResult1->toArray() === $miResultReg->toArray(),
    'MI direct scorer and ScorerRegistry results must match exactly'
);

$miData = $miResult1->toArray();
$expectedMiDims = ['LING', 'LOGI', 'SPAT', 'BODY', 'MUSIC', 'INTER', 'INTRA', 'NAT'];
integration_assert(count($miData['dimension_scores']) === 8, 'MI must contain exactly 8 dimensions');
foreach ($expectedMiDims as $dim) {
    integration_assert(array_key_exists($dim, $miData['dimension_scores']), "MI dimension {$dim} must exist");
    $score = $miData['dimension_scores'][$dim];
    integration_assert(is_int($score), "MI {$dim} score must be integer");
    integration_assert($score >= 0 && $score <= 100, "MI {$dim} score {$score} must be between 0 and 100");
}
integration_assert($miData['dimension_scores']['LOGI'] === 100, 'MI LOGI score is 100');
integration_assert($miData['dimension_scores']['INTER'] === 88, 'MI INTER score is 88');
integration_assert($miData['dimension_scores']['SPAT'] === 75, 'MI SPAT score is 75');
integration_assert($miData['dimension_scores']['LING'] === 50, 'MI LING score is 50');
integration_assert($miData['dimension_scores']['BODY'] === 38, 'MI BODY score is 38');
integration_assert($miData['dimension_scores']['MUSIC'] === 25, 'MI MUSIC score is 25');
integration_assert($miData['dimension_scores']['INTRA'] === 13, 'MI INTRA score is 13');
integration_assert($miData['dimension_scores']['NAT'] === 0, 'MI NAT score is 0');

// MI result_code format (3 dash-separated dimensions from the 8 recognized)
$validMiDimPattern = '(?:LING|LOGI|SPAT|BODY|MUSIC|INTER|INTRA|NAT)';
integration_assert(
    preg_match("/\A{$validMiDimPattern}-{$validMiDimPattern}-{$validMiDimPattern}\z/", $miData['result_code']) === 1,
    "MI result_code '{$miData['result_code']}' must match 3-part dash-separated format"
);
integration_assert(
    $miData['result_code'] === 'LOGI-INTER-SPAT',
    "MI expected result_code 'LOGI-INTER-SPAT', got '{$miData['result_code']}'"
);
integration_assert(
    $miData['summary'] === 'Định hướng đa trí thông minh phục vụ lựa chọn trải nghiệm học tập.',
    'MI summary must match contract'
);

// 4.2 MI Inverted Profile: Target NAT-MUSIC-BODY
$miAnswersInverted = [
    $mi16['questions'][0]['id'] => 1,  // LING:+
    $mi16['questions'][1]['id'] => 5,  // LING:- (1) -> 0
    $mi16['questions'][2]['id'] => 1,  // LOGI:+
    $mi16['questions'][3]['id'] => 5,  // LOGI:- (1) -> 0
    $mi16['questions'][4]['id'] => 2,  // SPAT:+
    $mi16['questions'][5]['id'] => 4,  // SPAT:- (2) -> 25
    $mi16['questions'][6]['id'] => 4,  // BODY:+
    $mi16['questions'][7]['id'] => 2,  // BODY:- (4) -> 75
    $mi16['questions'][8]['id'] => 5,  // MUSIC:+
    $mi16['questions'][9]['id'] => 2,  // MUSIC:- (4) -> 88
    $mi16['questions'][10]['id'] => 2, // INTER:+
    $mi16['questions'][11]['id'] => 4, // INTER:- (2) -> 25
    $mi16['questions'][12]['id'] => 3, // INTRA:+
    $mi16['questions'][13]['id'] => 3, // INTRA:- (3) -> 50
    $mi16['questions'][14]['id'] => 5, // NAT:+
    $mi16['questions'][15]['id'] => 1, // NAT:- (5) -> 100
];
$miInvResult = assert_deterministic_scoring(
    $miScorer,
    $mi16['questions'],
    $miAnswersInverted,
    'MI inverted profile'
)->toArray();
integration_assert(
    $miInvResult['result_code'] === 'NAT-MUSIC-BODY',
    "MI expected 'NAT-MUSIC-BODY', got '{$miInvResult['result_code']}'"
);

// 4.3 MI Stable Tie-break (all answers = 3 -> all scores = 50 -> stable order LING, LOGI, SPAT, ... -> 'LING-LOGI-SPAT')
$miTiedAnswers = [];
foreach ($mi16['questions'] as $q) {
    $miTiedAnswers[$q['id']] = 3;
}
$miTiedResult = assert_deterministic_scoring(
    $miScorer,
    $mi16['questions'],
    $miTiedAnswers,
    'MI stable tie-break'
)->toArray();
integration_assert(
    $miTiedResult['result_code'] === 'LING-LOGI-SPAT',
    "MI tie-break must produce 'LING-LOGI-SPAT', got '{$miTiedResult['result_code']}'"
);
foreach ($expectedMiDims as $dim) {
    integration_assert($miTiedResult['dimension_scores'][$dim] === 50, "MI tied {$dim} score must be 50");
}

// 4.4 MI Multi-Band Catalogs (Middle, High, College: 4 questions per dimension = 32 questions)
foreach (['middle', 'high', 'college'] as $band) {
    $cat = build_synthetic_catalog('multiple_intelligence', $band, 40, 4);
    integration_assert(count($cat['questions']) === 32, "MI {$band} catalog has 32 questions");
    $answers = [];
    foreach ($cat['questions'] as $idx => $q) {
        $answers[$q['id']] = (($idx % 5) + 1);
    }
    $bandRes = assert_deterministic_scoring(
        $miScorer,
        $cat['questions'],
        $answers,
        "MI {$band} 32-item catalog"
    )->toArray();
    integration_assert(
        preg_match("/\A{$validMiDimPattern}-{$validMiDimPattern}-{$validMiDimPattern}\z/", $bandRes['result_code']) === 1,
        "MI {$band} result_code valid"
    );
    foreach ($expectedMiDims as $dim) {
        integration_assert(
            $bandRes['dimension_scores'][$dim] >= 0 && $bandRes['dimension_scores'][$dim] <= 100,
            "MI {$band} dimension {$dim} in [0, 100]"
        );
    }
}

// 4.5 MI Error & Edge cases
integration_expect_exception(
    static fn () => $miScorer->score($mi16['questions'], [$mi16['questions'][0]['id'] => 5]),
    RuntimeException::class,
    'MI missing required question must throw RuntimeException'
);

$invalidMiDimQ = [
    ['id' => 'q-invalid', 'dimension_code' => 'UNKNOWN:+', 'required' => true],
];
integration_expect_exception(
    static fn () => $miScorer->score($invalidMiDimQ, ['q-invalid' => 4]),
    RuntimeException::class,
    'MI invalid dimension code must throw RuntimeException'
);

echo "Multiple Intelligence Scorer Integration: PASS\n";

// =============================================================================
// SECTION 5: SCORER REGISTRY & COMPREHENSIVE CROSS-MATRIX VALIDATION
// =============================================================================

echo "\n--- Section 5: ScorerRegistry & Cross-Matrix Validation ---\n";

// 5.1 Test ScorerRegistry rejection on unknown version
integration_expect_exception(
    static fn () => $scorerRegistry->forVersion('unknown-version-1.0'),
    RuntimeException::class,
    'ScorerRegistry must reject unapproved scoring version'
);

integration_expect_exception(
    static fn () => $scorerRegistry->forVersion(''),
    RuntimeException::class,
    'ScorerRegistry must reject empty scoring version'
);

// 5.2 Validate all 12 framework × band combinations against ScorerRegistry
$frameworks = ['holland', 'mbti', 'disc', 'multiple_intelligence'];
$bands = ['middle', 'high', 'college'];
$matrixCatalogIdx = 100;

foreach ($frameworks as $fw) {
    foreach ($bands as $b) {
        $catalog = build_synthetic_catalog($fw, $b, $matrixCatalogIdx++);
        $scoringVer = $catalog['metadata']['scoring_version'];

        $resolvedScorer = $scorerRegistry->forVersion($scoringVer);
        integration_assert(
            $resolvedScorer instanceof AssessmentScorer,
            "ScorerRegistry resolves approved scorer for {$fw} ({$scoringVer})"
        );

        // Generate full deterministic answers across Likert range 1..5
        $fullAnswers = [];
        foreach ($catalog['questions'] as $qIdx => $q) {
            $fullAnswers[$q['id']] = (($qIdx % 5) + 1);
        }

        // Run twice for determinism
        $result = assert_deterministic_scoring(
            $resolvedScorer,
            $catalog['questions'],
            $fullAnswers,
            "Matrix test {$fw}_{$b}"
        );

        $resArray = $result->toArray();
        integration_assert(is_string($resArray['result_code']) && trim($resArray['result_code']) !== '', "Result code not empty for {$fw}_{$b}");
        integration_assert(is_string($resArray['summary']) && trim($resArray['summary']) !== '', "Summary not empty for {$fw}_{$b}");
        integration_assert(is_array($resArray['dimension_scores']) && count($resArray['dimension_scores']) > 0, "Dimension scores present for {$fw}_{$b}");

        // Validate dimension score bounds for every dimension
        foreach ($resArray['dimension_scores'] as $dKey => $dScore) {
            integration_assert(
                is_int($dScore) && $dScore >= 0 && $dScore <= 100,
                "Dimension {$dKey} score {$dScore} must be integer 0..100 in {$fw}_{$b}"
            );
        }
    }
}

echo "ScorerRegistry & Cross-Matrix Validation: PASS\n";

// =============================================================================
// SUMMARY & COMPLETION
// =============================================================================

echo "\n=== ALL CHECKS PASSED: {$assertionsCount} ASSERTIONS COMPLETED SUCCESSFULLY ===\n";
echo "learner_catalog_scorer_integration_test: OK\n";
