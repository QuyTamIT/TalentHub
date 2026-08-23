<?php

declare(strict_types=1);

namespace TalentHub\Learner\Assessment\Scoring;

interface AssessmentScorer
{
    /**
     * @param list<array<string,mixed>> $questions
     * @param array<string,mixed> $answers
     */
    public function score(array $questions, array $answers): ScoringResult;
}
