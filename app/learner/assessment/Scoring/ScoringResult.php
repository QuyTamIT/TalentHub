<?php

declare(strict_types=1);

namespace TalentHub\Learner\Assessment\Scoring;

use InvalidArgumentException;

final class ScoringResult
{
    /**
     * @param array<string, int> $dimensionScores
     */
    public function __construct(
        private readonly string $resultCode,
        private readonly string $summary,
        private readonly array $dimensionScores
    ) {
        if (trim($this->resultCode) === '') {
            throw new InvalidArgumentException('Assessment result code cannot be empty.');
        }

        if (trim($this->summary) === '') {
            throw new InvalidArgumentException('Assessment result summary cannot be empty.');
        }

        foreach ($this->dimensionScores as $key => $score) {
            if (!is_string($key) || trim($key) === '') {
                throw new InvalidArgumentException('Assessment dimension key cannot be empty.');
            }
            if (!is_int($score) || $score < 0 || $score > 100) {
                throw new InvalidArgumentException("Assessment dimension score for '{$key}' must be an integer between 0 and 100.");
            }
        }
    }

    /**
     * @return array{result_code:string,summary:string,dimension_scores:array<string,int>}
     */
    public function toArray(): array
    {
        return [
            'result_code' => $this->resultCode,
            'summary' => $this->summary,
            'dimension_scores' => $this->dimensionScores,
        ];
    }
}
