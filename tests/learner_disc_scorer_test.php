<?php

declare(strict_types=1);

use TalentHub\Learner\Assessment\Scoring\AssessmentScorer;
use TalentHub\Learner\Assessment\Scoring\DiscScorer;
use TalentHub\Learner\Assessment\Scoring\ScoringResult;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function disc_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function disc_expect_exception(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (\RuntimeException) {
        return;
    }

    disc_assert(false, $message);
}

// 1. Class exists and implements AssessmentScorer
disc_assert(class_exists(DiscScorer::class), 'DiscScorer class must exist');

$scorer = new DiscScorer();
disc_assert($scorer instanceof AssessmentScorer, 'DiscScorer must implement AssessmentScorer');

// 2. Golden ranking test with reverse-item:
// 2 questions per dimension D, I, S, C (8 total)
// D: 2 positive questions with low scores -> D is lowest
// I: 1 positive (5) + 1 reverse I:- (1 -> 5) -> I is highest (100)
// S: 2 positive (4, 4) -> S = 75
// C: 2 positive (3, 3) -> C = 50
// Result ranking: I (100) > S (75) > C (50) > D (13) -> ISCD
$questions = [
    ['question_id' => 'q-d-1', 'dimension_code' => 'D:+', 'required' => 1],
    ['question_id' => 'q-d-2', 'dimension_code' => 'D', 'required' => 1],
    ['question_id' => 'q-i-1', 'dimension_code' => 'I:+', 'required' => 1],
    ['question_id' => 'q-i-2', 'dimension_code' => 'I:-', 'required' => 1],
    ['question_id' => 'q-s-1', 'dimension_code' => 'S:+', 'required' => 1],
    ['question_id' => 'q-s-2', 'dimension_code' => 'S', 'required' => 1],
    ['question_id' => 'q-c-1', 'dimension_code' => 'C:+', 'required' => 1],
    ['question_id' => 'q-c-2', 'dimension_code' => 'C', 'required' => 1],
];

$answers = [
    'q-d-1' => 1,
    'q-d-2' => 2,
    'q-i-1' => 5,
    'q-i-2' => 1, // reversed: 6 - 1 = 5
    'q-s-1' => 4,
    'q-s-2' => 4,
    'q-c-1' => 3,
    'q-c-2' => 3,
];

$result = $scorer->score($questions, $answers);
disc_assert($result instanceof ScoringResult, 'score() returns ScoringResult');

$data = $result->toArray();
disc_assert(str_starts_with($data['result_code'], 'IS'), "Result code must start with 'IS', got '{$data['result_code']}'");
disc_assert($data['result_code'] === 'ISCD', "Expected result code 'ISCD', got '{$data['result_code']}'");
disc_assert($data['dimension_scores']['I'] > $data['dimension_scores']['S'], 'I > S');
disc_assert($data['dimension_scores']['S'] > $data['dimension_scores']['C'], 'S > C');
disc_assert($data['dimension_scores']['C'] > $data['dimension_scores']['D'], 'C > D');
disc_assert($data['dimension_scores']['I'] > $data['dimension_scores']['D'], 'I > D');
disc_assert($data['dimension_scores']['I'] === 100, 'I score is 100');
disc_assert($data['dimension_scores']['S'] === 75, 'S score is 75');
disc_assert($data['dimension_scores']['C'] === 50, 'C score is 50');
disc_assert($data['dimension_scores']['D'] === 13, 'D score is 13');

// 3. Summary check
disc_assert(
    $data['summary'] === 'Xu hướng hành vi học tập và làm việc nhóm theo DISC.',
    'Summary matches exact requirement'
);

// 4. Stable tie-break: all dimensions have equal scores -> result code is DISC
$tiedAnswers = [];
foreach ($questions as $q) {
    $tiedAnswers[$q['question_id']] = 3;
}
$tiedResult = $scorer->score($questions, $tiedAnswers)->toArray();
disc_assert($tiedResult['result_code'] === 'DISC', "Tie-break must follow D,I,S,C order, got '{$tiedResult['result_code']}'");

// 5. Missing required answer throws RuntimeException
disc_expect_exception(
    static fn () => $scorer->score($questions, ['q-d-1' => 1]),
    'Missing required questions must throw RuntimeException'
);

// 6. Optional question without answer is skipped, unattempted dimension has score 0
$optionalQuestions = [
    ['question_id' => 'q1', 'dimension_code' => 'D', 'required' => 1],
    ['question_id' => 'q2', 'dimension_code' => 'I', 'required' => 0],
];
$optResult = $scorer->score($optionalQuestions, ['q1' => 5])->toArray();
disc_assert($optResult['dimension_scores']['D'] === 100, 'D is scored from answered question');
disc_assert($optResult['dimension_scores']['I'] === 0, 'Unanswered optional dimension I defaults to 0');
disc_assert($optResult['dimension_scores']['S'] === 0, 'Unanswered dimension S defaults to 0');
disc_assert($optResult['dimension_scores']['C'] === 0, 'Unanswered dimension C defaults to 0');

// 7. Invalid dimension codes throw RuntimeException
disc_expect_exception(
    static fn () => $scorer->score([['question_id' => 'q1', 'dimension_code' => 'X', 'required' => 1]], ['q1' => 3]),
    'Invalid dimension X must throw RuntimeException'
);
disc_expect_exception(
    static fn () => $scorer->score([['question_id' => 'q1', 'dimension_code' => 'D:*', 'required' => 1]], ['q1' => 3]),
    'Invalid suffix D:* must throw RuntimeException'
);
disc_expect_exception(
    static fn () => $scorer->score([['question_id' => 'q1', 'dimension_code' => 'I:?', 'required' => 1]], ['q1' => 3]),
    'Invalid suffix I:? must throw RuntimeException'
);
disc_expect_exception(
    static fn () => $scorer->score([['question_id' => 'q1', 'dimension_code' => 'D:Z', 'required' => 1]], ['q1' => 3]),
    'Invalid suffix D:Z must throw RuntimeException'
);

// 8. Invalid Likert values throw RuntimeException via LikertScore
disc_expect_exception(
    static fn () => $scorer->score($questions, array_merge($answers, ['q-d-1' => 0])),
    'Likert value 0 must throw RuntimeException'
);
disc_expect_exception(
    static fn () => $scorer->score($questions, array_merge($answers, ['q-d-1' => 6])),
    'Likert value 6 must throw RuntimeException'
);
disc_expect_exception(
    static fn () => $scorer->score($questions, array_merge($answers, ['q-d-1' => 'invalid'])),
    'Non-numeric value must throw RuntimeException'
);

echo "learner_disc_scorer_test: OK\n";
