<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

/**
 * Immutable value object representing a single learner opportunity match
 * produced by the Gemini analysis. The Gemini analysis fields are
 * attached at construction; the deterministic 70/30 match score is
 * composed separately by the service layer via withScore() so the
 * provider mapper never computes the final match score.
 */
final class OpportunityMatch
{
    /** @var list<string> */
    private readonly array $matchedSkillCodes;

    /** @var list<string> */
    private readonly array $missingSkillCodes;

    /** @var list<string> */
    private readonly array $expectedOutcomeCodes;

    /** @var list<string> */
    private readonly array $evidenceRefs;

    /** @var list<string> */
    private readonly array $missingConditions;

    /** @var list<string> */
    private readonly array $improvementSteps;

    private readonly ?OpportunityScore $score;

    public function __construct(
        private readonly OpportunityCandidate $candidate,
        private readonly int $geminiScore,
        private readonly string $whyFit,
        array $matchedSkillCodes,
        array $missingSkillCodes,
        array $expectedOutcomeCodes,
        array $evidenceRefs,
        ?OpportunityScore $score = null,
        private readonly string $analysisKind = 'recommendation',
        private readonly string $whyNotFitYet = '',
        array $missingConditions = [],
        array $improvementSteps = [],
    ) {
        $this->matchedSkillCodes = array_values(array_map('strval', $matchedSkillCodes));
        $this->missingSkillCodes = array_values(array_map('strval', $missingSkillCodes));
        $this->expectedOutcomeCodes = array_values(array_map('strval', $expectedOutcomeCodes));
        $this->evidenceRefs = array_values(array_map('strval', $evidenceRefs));
        $this->missingConditions = array_values(array_map('strval', $missingConditions));
        $this->improvementSteps = array_values(array_map('strval', $improvementSteps));
        $this->score = $score;
    }

    public function candidate(): OpportunityCandidate
    {
        return $this->candidate;
    }

    public function geminiScore(): int
    {
        return $this->geminiScore;
    }

    public function whyFit(): string
    {
        return $this->whyFit;
    }

    /** @return list<string> */
    public function matchedSkillCodes(): array
    {
        return $this->matchedSkillCodes;
    }

    /** @return list<string> */
    public function missingSkillCodes(): array
    {
        return $this->missingSkillCodes;
    }

    /** @return list<string> */
    public function expectedOutcomeCodes(): array
    {
        return $this->expectedOutcomeCodes;
    }

    /** @return list<string> */
    public function evidenceRefs(): array
    {
        return $this->evidenceRefs;
    }

    public function analysisKind(): string
    {
        return $this->analysisKind;
    }

    public function whyNotFitYet(): string
    {
        return $this->whyNotFitYet;
    }

    /** @return list<string> */
    public function missingConditions(): array
    {
        return $this->missingConditions;
    }

    /** @return list<string> */
    public function improvementSteps(): array
    {
        return $this->improvementSteps;
    }

    public function score(): ?OpportunityScore
    {
        return $this->score;
    }

    public function withScore(OpportunityScore $score): self
    {
        return new self(
            $this->candidate,
            $this->geminiScore,
            $this->whyFit,
            $this->matchedSkillCodes,
            $this->missingSkillCodes,
            $this->expectedOutcomeCodes,
            $this->evidenceRefs,
            $score,
            $this->analysisKind,
            $this->whyNotFitYet,
            $this->missingConditions,
            $this->improvementSteps,
        );
    }
}
