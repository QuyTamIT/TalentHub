<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use DateTimeImmutable;
use DomainException;

/**
 * Deterministic structured opportunity fit scorer. Compares canonical
 * codes only (never display labels), never calls Gemini, and produces the
 * same breakdown for the same inputs. Hard-gate failures short-circuit
 * with a DomainException so ineligible candidates never receive a low
 * score by mistake.
 */
final class StructuredOpportunityScorer
{
    private const MANDATORY_MINIMUM_SCORE = 50;

    private const DIFFICULTY_READINESS = [
        '' => 0,
        'introductory' => 0,
        'beginner' => 0,
        'intermediate' => 50,
        'advanced' => 80,
    ];

    private readonly DateTimeImmutable $clock;

    public function __construct(?DateTimeImmutable $clock = null)
    {
        $this->clock = ($clock ?? new DateTimeImmutable('now'))
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    public function score(LearnerOpportunityProfile $profile, OpportunityCandidate $candidate): OpportunityScore
    {
        $this->assertHardGates($profile, $candidate);

        $breakdown = [
            'skill_match' => $this->skillMatch($profile, $candidate),
            'assessment_alignment' => $this->assessmentAlignment($profile, $candidate),
            'experience_relevance' => $this->experienceRelevance($profile, $candidate),
            'growth_potential' => $this->growthPotential($profile, $candidate),
            'feasibility' => $this->feasibility($profile, $candidate),
        ];

        return new OpportunityScore($breakdown);
    }

    private function assertHardGates(LearnerOpportunityProfile $profile, OpportunityCandidate $candidate): void
    {
        if (!$candidate->isEligibleFor($profile, $this->clock)) {
            throw new DomainException('candidate_ineligible');
        }
    }

    private function skillMatch(LearnerOpportunityProfile $profile, OpportunityCandidate $candidate): int
    {
        $required = $candidate->requiredSkills();
        if ($required === []) {
            return 0;
        }
        $profileSkills = $profile->skills();
        $met = 0;
        foreach ($required as $skill) {
            $code = $skill['code'];
            $minimum = $skill['minimum_score'];
            $score = $profileSkills[$code] ?? null;
            if ($score !== null && $score >= $minimum) {
                $met += 1;
            }
        }
        return self::capProportion($met / count($required), OpportunityScore::MAX['skill_match']);
    }

    private function assessmentAlignment(LearnerOpportunityProfile $profile, OpportunityCandidate $candidate): int
    {
        $dimensions = $profile->assessmentDimensions();
        if ($dimensions === []) {
            return 0;
        }
        $candidateTags = self::candidateAssessmentTags($candidate);
        if ($candidateTags === []) {
            return 0;
        }
        $overlap = count(array_intersect($candidateTags, array_keys($dimensions)));
        return self::capProportion($overlap / count($candidateTags), OpportunityScore::MAX['assessment_alignment']);
    }

    private function experienceRelevance(LearnerOpportunityProfile $profile, OpportunityCandidate $candidate): int
    {
        $experienceTags = $profile->experienceTags();
        if ($experienceTags === []) {
            return 0;
        }
        $requiredCodes = [];
        foreach ($candidate->requiredSkills() as $skill) {
            $requiredCodes[] = $skill['code'];
        }
        if ($requiredCodes === []) {
            return 0;
        }
        $overlap = count(array_intersect($requiredCodes, $experienceTags));
        return self::capProportion($overlap / count($requiredCodes), OpportunityScore::MAX['experience_relevance']);
    }

    private function growthPotential(LearnerOpportunityProfile $profile, OpportunityCandidate $candidate): int
    {
        $required = $candidate->requiredSkills();
        if ($required === []) {
            return 0;
        }
        $profileSkills = $profile->skills();
        $outcomeCodes = [];
        foreach ($candidate->learningOutcomes() as $outcome) {
            $outcomeCodes[] = $outcome['code'];
        }

        $missing = [];
        $hasMandatoryMissing = false;
        foreach ($required as $skill) {
            $score = $profileSkills[$skill['code']] ?? null;
            if ($score === null || $score < $skill['minimum_score']) {
                $missing[] = $skill;
                if ($skill['minimum_score'] >= self::MANDATORY_MINIMUM_SCORE
                    && !in_array($skill['code'], $outcomeCodes, true)) {
                    $hasMandatoryMissing = true;
                }
            }
        }

        if ($missing === []) {
            return OpportunityScore::MAX['growth_potential'];
        }
        if ($hasMandatoryMissing) {
            return 0;
        }
        $allCovered = true;
        foreach ($missing as $skill) {
            if (!in_array($skill['code'], $outcomeCodes, true)) {
                $allCovered = false;
                break;
            }
        }
        return $allCovered ? OpportunityScore::MAX['growth_potential'] : 0;
    }

    private function feasibility(LearnerOpportunityProfile $profile, OpportunityCandidate $candidate): int
    {
        $payload = $candidate->providerPayload();
        $difficulty = LearnerOpportunityProfile::normalizeCode((string) ($payload['difficulty'] ?? ''));
        if (!array_key_exists($difficulty, self::DIFFICULTY_READINESS)) {
            throw new DomainException('candidate_ineligible');
        }

        $requiredReadiness = self::DIFFICULTY_READINESS[$difficulty];
        if ($requiredReadiness === 0) {
            return OpportunityScore::MAX['feasibility'];
        }

        $requiredSkills = $candidate->requiredSkills();
        if ($requiredSkills === []) {
            return 0;
        }

        $total = 0;
        foreach ($requiredSkills as $skill) {
            $total += $profile->skillScore($skill['code']) ?? 0;
        }
        $readiness = (int) round($total / count($requiredSkills));

        return $readiness >= $requiredReadiness ? OpportunityScore::MAX['feasibility'] : 0;
    }

    /** @return list<string> */
    private static function candidateAssessmentTags(OpportunityCandidate $candidate): array
    {
        $payload = $candidate->providerPayload();
        $tags = [];
        $category = isset($payload['category']) && is_string($payload['category']) ? trim($payload['category']) : '';
        if ($category !== '') {
            $code = LearnerOpportunityProfile::normalizeCode($category);
            if ($code !== '') {
                $tags[] = $code;
            }
        }
        return $tags;
    }

    private static function capProportion(float $proportion, int $maximum): int
    {
        if ($proportion <= 0.0) {
            return 0;
        }
        if ($proportion >= 1.0) {
            return $maximum;
        }
        $value = (int) round($proportion * $maximum);
        if ($value < 0) {
            return 0;
        }
        return $value > $maximum ? $maximum : $value;
    }
}
