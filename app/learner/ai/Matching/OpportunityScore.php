<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use InvalidArgumentException;
use LogicException;

/**
 * Immutable breakdown of a learner opportunity fit score. The structured
 * score is the deterministic sum of five canonical dimensions; the final
 * match score is composed exactly as
 *   round(0.70 * structured_score + 0.30 * gemini_score).
 * The final match score can only be produced after a Gemini score has
 * been attached via withGeminiScore() to keep the composer deterministic.
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

    private const STRUCTURED_WEIGHT = 0.70;
    private const GEMINI_WEIGHT = 0.30;

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
        if ($this->geminiScore === null) {
            throw new LogicException('Opportunity score cannot compute finalScore before a Gemini score is attached.');
        }
        return (int) round(self::STRUCTURED_WEIGHT * $this->structuredScore + self::GEMINI_WEIGHT * $this->geminiScore);
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
