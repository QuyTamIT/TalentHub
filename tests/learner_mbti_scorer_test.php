<?php

declare(strict_types=1);

use TalentHub\Learner\Assessment\Scoring\AssessmentScorer;
use TalentHub\Learner\Assessment\Scoring\MbtiScorer;
use TalentHub\Learner\Assessment\Scoring\ScoringResult;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function mbti_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function mbti_expect_exception(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (\RuntimeException) {
        return;
    }

    mbti_assert(false, $message);
}

// 1. Class exists and implements AssessmentScorer
mbti_assert(class_exists(MbtiScorer::class), 'MbtiScorer class must exist');

$scorer = new MbtiScorer();
mbti_assert($scorer instanceof AssessmentScorer, 'MbtiScorer must implement AssessmentScorer');

// 2. Golden test: 2 questions per axis (1 for each pole)
// Fixture: E=5, I=1, S=1, N=5, T=5, F=1, J=5, P=1
$questions = [
    ['question_id' => 'q-ei-e', 'dimension_code' => 'EI:E', 'required' => 1],
    ['question_id' => 'q-ei-i', 'dimension_code' => 'EI:I', 'required' => 1],
    ['question_id' => 'q-sn-s', 'dimension_code' => 'SN:S', 'required' => 1],
    ['question_id' => 'q-sn-n', 'dimension_code' => 'SN:N', 'required' => 1],
    ['question_id' => 'q-tf-t', 'dimension_code' => 'TF:T', 'required' => 1],
    ['question_id' => 'q-tf-f', 'dimension_code' => 'TF:F', 'required' => 1],
    ['question_id' => 'q-jp-j', 'dimension_code' => 'JP:J', 'required' => 1],
    ['question_id' => 'q-jp-p', 'dimension_code' => 'JP:P', 'required' => 1],
];

$answers = [
    'q-ei-e' => 5,
    'q-ei-i' => 1,
    'q-sn-s' => 1,
    'q-sn-n' => 5,
    'q-tf-t' => 5,
    'q-tf-f' => 1,
    'q-jp-j' => 5,
    'q-jp-p' => 1,
];

$result = $scorer->score($questions, $answers);
mbti_assert($result instanceof ScoringResult, 'score() returns ScoringResult');

$data = $result->toArray();
mbti_assert($data['result_code'] === 'ENTJ', "Expected result_code 'ENTJ', got '{$data['result_code']}'");
mbti_assert($data['summary'] === 'Xu hướng học tập và làm việc theo bốn trục tham khảo.', 'Summary matches requirement');

$expectedScores = [
    'E' => 100,
    'I' => 0,
    'S' => 0,
    'N' => 100,
    'T' => 100,
    'F' => 0,
    'J' => 100,
    'P' => 0,
];

mbti_assert(array_keys($data['dimension_scores']) === ['E', 'I', 'S', 'N', 'T', 'F', 'J', 'P'], 'Dimension scores must contain all 8 poles in order E,I,S,N,T,F,J,P');

foreach ($expectedScores as $pole => $expectedValue) {
    mbti_assert(
        isset($data['dimension_scores'][$pole]) && is_int($data['dimension_scores'][$pole]),
        "Pole {$pole} score must exist and be an integer"
    );
    mbti_assert(
        $data['dimension_scores'][$pole] === $expectedValue,
        "Pole {$pole} expected score {$expectedValue}, got {$data['dimension_scores'][$pole]}"
    );
}

// 3. Exact tie returns ESTJ
$tiedAnswers = [];
foreach ($questions as $q) {
    $tiedAnswers[$q['question_id']] = 3;
}
$tiedResult = $scorer->score($questions, $tiedAnswers)->toArray();
mbti_assert($tiedResult['result_code'] === 'ESTJ', "Exact tie must fallback to ESTJ, got '{$tiedResult['result_code']}'");
foreach (['E', 'I', 'S', 'N', 'T', 'F', 'J', 'P'] as $pole) {
    mbti_assert($tiedResult['dimension_scores'][$pole] === 50, "Tied pole {$pole} score must be 50");
}

// 4. Missing required question throws RuntimeException
mbti_expect_exception(
    static fn () => $scorer->score($questions, ['q-ei-e' => 5]),
    'Missing required question must throw RuntimeException'
);

// 5. Unanswered optional question is skipped
$optionalQuestions = [
    ['question_id' => 'q1', 'dimension_code' => 'EI:E', 'required' => 1],
    ['question_id' => 'q2', 'dimension_code' => 'SN:S', 'required' => 0],
];
$optResult = $scorer->score($optionalQuestions, ['q1' => 5])->toArray();
mbti_assert($optResult['dimension_scores']['E'] === 100, 'E is scored');
mbti_assert($optResult['dimension_scores']['I'] === 0, 'I gets opposite value 0');
mbti_assert($optResult['dimension_scores']['S'] === 0, 'Unanswered optional pole S defaults to 0');
mbti_assert($optResult['dimension_scores']['N'] === 0, 'Unanswered optional pole N defaults to 0');

// 6. Invalid dimension codes or pole mismatch throws RuntimeException
mbti_expect_exception(
    static fn () => $scorer->score([['question_id' => 'q1', 'dimension_code' => 'EI:X', 'required' => 1]], ['q1' => 3]),
    'Invalid pole EI:X must throw RuntimeException'
);
mbti_expect_exception(
    static fn () => $scorer->score([['question_id' => 'q1', 'dimension_code' => 'INVALID', 'required' => 1]], ['q1' => 3]),
    'Invalid dimension code must throw RuntimeException'
);
mbti_expect_exception(
    static fn () => $scorer->score([['question_id' => 'q1', 'dimension_code' => 'EI:S', 'required' => 1]], ['q1' => 3]),
    'Pole S on axis EI must throw RuntimeException'
);

// 7. Invalid Likert answer throws RuntimeException
mbti_expect_exception(
    static fn () => $scorer->score($questions, array_merge($answers, ['q-ei-e' => 0])),
    'Likert answer 0 must throw RuntimeException'
);
mbti_expect_exception(
    static fn () => $scorer->score($questions, array_merge($answers, ['q-ei-e' => 6])),
    'Likert answer 6 must throw RuntimeException'
);
mbti_expect_exception(
    static fn () => $scorer->score($questions, array_merge($answers, ['q-ei-e' => 'invalid'])),
    'Non-numeric answer must throw RuntimeException'
);

echo "learner_mbti_scorer_test: OK\n";
