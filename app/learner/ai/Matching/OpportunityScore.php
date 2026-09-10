<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use InvalidArgumentException;

/**
 * Immutable breakdown of a learner opportunity fit score. The structured
 * score is the deterministic sum of five canonical dimensions; the final
 * match score is the structured score. Gemini's legacy score is retained as
 * diagnostic metadata only and never changes learner-facing ranking.
 */
final class OpportunityScore
{
    public const MAX = [
        'skill_match' => 35,
        'assessment_alignment' => 25,
        'experience_relevance' => 15,
        'growth_potential' => 15,
        'feasibility' => 10,
    ];

    /** @var array{skill_match:int,assessment_alignment:int,experience_relevance:int,growth_potential:int,feasibility:int} */
    private readonly array $breakdown;

    private readonly int $structuredScore;

    private readonly ?int $geminiScore;

    /** @param array<string,int> $breakdown */
    public function __construct(array $breakdown, ?int $geminiScore = null)
    {
        $normalised = [];
        foreach (self::MAX as $dimension => $maximum) {
            if (!array_key_exists($dimension, $breakdown)) {
                throw new InvalidArgumentException("Opportunity score is missing dimension {$dimension}.");
            }
            $value = $breakdown[$dimension];
            if (!is_int($value)) {
                throw new InvalidArgumentException("Opportunity score dimension {$dimension} must be an integer.");
            }
            if ($value < 0 || $value > $maximum) {
                throw new InvalidArgumentException("Opportunity score dimension {$dimension} must be within 0..{$maximum}.");
            }
            $normalised[$dimension] = $value;
        }
        foreach ($breakdown as $dimension => $_) {
            if (!isset(self::MAX[$dimension])) {
                throw new InvalidArgumentException("Opportunity score does not accept dimension {$dimension}.");
            }
        }

        $this->breakdown = $normalised;
        $this->structuredScore = array_sum($normalised);
        $this->geminiScore = self::normaliseGeminiScore($geminiScore);
    }

    public function structuredScore(): int
    {
        return $this->structuredScore;
    }

    public function geminiScore(): ?int
    {
        return $this->geminiScore;
    }

    public function withGeminiScore(int $score): self
    {
        return new self($this->breakdown, $score);
    }

    public function finalScore(): int
    {
        return $this->structuredScore;
    }

    /** @return array{skill_match:int,assessment_alignment:int,experience_relevance:int,growth_potential:int,feasibility:int} */
    public function breakdown(): array
    {
        return $this->breakdown;
    }

    private static function normaliseGeminiScore(?int $score): ?int
    {
        if ($score === null) {
            return null;
        }
        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException('Opportunity score Gemini component must be within 0..100.');
        }
        return $score;
    }
}
