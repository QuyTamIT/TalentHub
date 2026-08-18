<?php

declare(strict_types=1);

namespace TalentHub\Learner\Assessment\Scoring;

use RuntimeException;

final class ScorerRegistry
{
    /**
     * @param array<string, AssessmentScorer> $scorers
     */
    public function __construct(private readonly array $scorers)
    {
    }

    public function forVersion(string $scoringVersion): AssessmentScorer
    {
        $scorer = $this->scorers[trim($scoringVersion)] ?? null;
        if (!$scorer instanceof AssessmentScorer) {
            throw new RuntimeException('Assessment scoring version is not approved.');
        }

        return $scorer;
    }
}
