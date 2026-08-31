<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use InvalidArgumentException;

/**
 * Deterministic backend job match score for AI Job Matching.
 *
 * Composed exactly as
 *   total = round(skill * 0.40 + assessment * 0.35 + experience * 0.25)
 * with every component an integer within 0..100. This is intentionally a
 * separate value object from OpportunityScore, which serves the project
 * matching pipeline with a different five-dimension formula and must not be
 * altered.
 */
final class JobMatchScore
{
    public const WEIGHTS = ['skills' => 0.40, 'assessment' => 0.35, 'experience' => 0.25];

    private readonly int $skillScore;

    private readonly int $assessmentScore;

    private readonly int $experienceScore;

    public function __construct(int $skillScore, int $assessmentScore, int $experienceScore)
    {
        foreach (['skill' => $skillScore, 'assessment' => $assessmentScore, 'experience' => $experienceScore] as $name => $value) {
            if ($value < 0 || $value > 100) {
                throw new InvalidArgumentException("Job match score component {$name} must be within 0..100.");
            }
        }
        $this->skillScore = $skillScore;
        $this->assessmentScore = $assessmentScore;
        $this->experienceScore = $experienceScore;
    }

    public function skillScore(): int
    {
        return $this->skillScore;
    }

    public function assessmentScore(): int
    {
        return $this->assessmentScore;
    }

    public function experienceScore(): int
    {
        return $this->experienceScore;
    }

    public function totalScore(): int
    {
        return (int) round(
            self::WEIGHTS['skills'] * $this->skillScore
            + self::WEIGHTS['assessment'] * $this->assessmentScore
            + self::WEIGHTS['experience'] * $this->experienceScore,
        );
    }

    /** strong_fit >= 80, good_fit >= 60, developing_fit >= 40, otherwise low_fit */
    public function tier(): string
    {
        $total = $this->totalScore();
        return match (true) {
            $total >= 80 => 'strong_fit',
            $total >= 60 => 'good_fit',
            $total >= 40 => 'developing_fit',
            default => 'low_fit',
        };
    }

    /** @return array{skill_score:int,assessment_score:int,experience_score:int,total_score:int,tier:string} */
    public function breakdown(): array
    {
        return [
            'skill_score' => $this->skillScore,
            'assessment_score' => $this->assessmentScore,
            'experience_score' => $this->experienceScore,
            'total_score' => $this->totalScore(),
            'tier' => $this->tier(),
        ];
    }
}
