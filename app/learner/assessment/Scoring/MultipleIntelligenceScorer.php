<?php

declare(strict_types=1);

namespace TalentHub\Learner\Assessment\Scoring;

use RuntimeException;

final class MultipleIntelligenceScorer implements AssessmentScorer
{
    private const DIMENSIONS = ['LING', 'LOGI', 'SPAT', 'BODY', 'MUSIC', 'INTER', 'INTRA', 'NAT'];

    /**
     * @param list<array<string,mixed>> $questions
     * @param array<string,mixed> $answers
     */
    public function score(array $questions, array $answers): ScoringResult
    {
        $totals = array_fill_keys(self::DIMENSIONS, 0);
        $counts = array_fill_keys(self::DIMENSIONS, 0);

        foreach ($questions as $question) {
            $rawDimCode = (string) ($question['dimension_code'] ?? $question['dimension'] ?? '');
            [$dimension, $reversed] = $this->dimension($rawDimCode);
            $questionId = (string) ($question['question_id'] ?? $question['id'] ?? '');
            $required = (int) ($question['required'] ?? 1) === 1;

            if (!array_key_exists($questionId, $answers)) {
                if ($required) {
                    throw new RuntimeException('All required assessment questions must be answered before submission.');
                }
                continue;
            }

            $rawAnswer = $answers[$questionId];
            $val = LikertScore::value($rawAnswer, $reversed);
            $totals[$dimension] += $val;
            $counts[$dimension]++;
        }

        $scores = [];
        foreach (self::DIMENSIONS as $dim) {
            $scores[$dim] = LikertScore::normalize($totals[$dim], $counts[$dim]);
        }

        $ranked = self::DIMENSIONS;
        usort($ranked, static function (string $left, string $right) use ($scores): int {
            return $scores[$right] <=> $scores[$left]
                ?: array_search($left, self::DIMENSIONS, true) <=> array_search($right, self::DIMENSIONS, true);
        });

        $topThree = implode('-', array_slice($ranked, 0, 3));

        return new ScoringResult(
            $topThree,
            'Định hướng đa trí thông minh phục vụ lựa chọn trải nghiệm học tập.',
            $scores
        );
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function dimension(string $code): array
    {
        if (preg_match('/\A(LING|LOGI|SPAT|BODY|MUSIC|INTER|INTRA|NAT)(?::([+-]))?\z/', strtoupper(trim($code)), $match) !== 1) {
            throw new RuntimeException('Unsupported Multiple Intelligence dimension code.');
        }

        return [$match[1], ($match[2] ?? '+') === '-'];
    }
}
