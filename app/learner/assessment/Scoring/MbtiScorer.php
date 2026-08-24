<?php

declare(strict_types=1);

namespace TalentHub\Learner\Assessment\Scoring;

use RuntimeException;

final class MbtiScorer implements AssessmentScorer
{
    private const POLES = ['E', 'I', 'S', 'N', 'T', 'F', 'J', 'P'];
    private const AXES = [
        'EI' => ['E', 'I'],
        'SN' => ['S', 'N'],
        'TF' => ['T', 'F'],
        'JP' => ['J', 'P'],
    ];

    /**
     * @param list<array<string,mixed>> $questions
     * @param array<string,mixed> $answers
     */
    public function score(array $questions, array $answers): ScoringResult
    {
        $totals = array_fill_keys(self::POLES, 0);
        $counts = array_fill_keys(self::POLES, 0);

        foreach ($questions as $question) {
            $rawDimCode = (string) ($question['dimension_code'] ?? $question['dimension'] ?? '');
            [$axis, $pole, $opposite] = $this->parseDimension($rawDimCode);
            $questionId = (string) ($question['question_id'] ?? $question['id'] ?? '');
            $required = (int) ($question['required'] ?? 1) === 1;

            if (!array_key_exists($questionId, $answers)) {
                if ($required) {
                    throw new RuntimeException('All required assessment questions must be answered before submission.');
                }
                continue;
            }

            $rawAnswer = $answers[$questionId];
            $val = LikertScore::value($rawAnswer, false);
            $totals[$pole] += $val;
            $counts[$pole]++;
            $totals[$opposite] += (6 - $val);
            $counts[$opposite]++;
        }

        $scores = [];
        foreach (self::POLES as $p) {
            $scores[$p] = LikertScore::normalize($totals[$p], $counts[$p]);
        }

        $resultCode = '';
        foreach (self::AXES as $axis => $poles) {
            [$poleA, $poleB] = $poles;
            $scoreA = $scores[$poleA];
            $scoreB = $scores[$poleB];
            if ($scoreA >= $scoreB) {
                $resultCode .= $poleA;
            } else {
                $resultCode .= $poleB;
            }
        }

        return new ScoringResult(
            $resultCode,
            'Xu hướng học tập và làm việc theo bốn trục tham khảo.',
            $scores
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function parseDimension(string $code): array
    {
        $code = strtoupper(trim($code));
        if (preg_match('/\A(EI|SN|TF|JP):([EISNTFJP])\z/', $code, $match) !== 1) {
            throw new RuntimeException('Unsupported MBTI dimension code.');
        }

        $axis = $match[1];
        $pole = $match[2];
        $poles = self::AXES[$axis];

        if (!in_array($pole, $poles, true)) {
            throw new RuntimeException("Pole {$pole} does not belong to axis {$axis}.");
        }

        $opposite = ($poles[0] === $pole) ? $poles[1] : $poles[0];

        return [$axis, $pole, $opposite];
    }
}
