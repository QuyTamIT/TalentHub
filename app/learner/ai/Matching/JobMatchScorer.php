<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

/**
 * Deterministic backend scorer for AI Job Matching (40% skills, 35%
 * assessment signals, 25% experience evidence).
 *
 * Formulas:
 * - skill component: weight-averaged attainment min(current/target, 1.0) over
 *   the role benchmark skills; skills absent from the learner profile count
 *   as current score 0.
 * - assessment component: for each role signal, similarity
 *   max(0, 1 - |actual - target| / 100) with the actual value resolved from
 *   the family-specific learner signals; a missing signal contributes zero
 *   similarity and is never replaced by invented data.
 * - experience component: share of benchmark skill weight evidenced by
 *   explicit canonical tags on confirmed activities, projects or published
 *   evaluations; skill records alone are never treated as experience.
 *
 * The scorer never calls Gemini, never invents skills, targets or evidence,
 * and leaves the legacy StructuredOpportunityScorer/OpportunityScore contract
 * untouched.
 */
final class JobMatchScorer
{
    public function score(LearnerOpportunityProfile $profile, OpportunityCandidate $candidate, CareerRoleBenchmark $role): JobMatchResult
    {
        $signals = $profile->assessmentSignals();
        $skillEvidenceRefs = $profile->skillEvidenceRefs();
        $experienceTags = $profile->confirmedExperienceTags();

        $skillWeightSum = $role->skillWeightSum();
        $skillWeighted = 0.0;
        $experienceWeighted = 0.0;
        $evaluations = [];

        foreach ($role->skillRequirements() as $requirement) {
            $code = $requirement['code'];
            $current = $profile->skillScore($code) ?? 0;
            $target = $requirement['minimum_score'];
            $attainment = $target > 0 ? min($current / $target, 1.0) : 1.0;
            $isMet = $current >= $target;
            $evidenceRefs = $skillEvidenceRefs[$code] ?? [];
            // Skill evidence proves that a skill record exists, but it is not
            // evidence of applied experience. Only explicit, canonical skill
            // tags attached to confirmed activities/projects/evaluations may
            // contribute to the 25% experience component.
            $evidenced = in_array($code, $experienceTags, true);

            if ($skillWeightSum > 0.0) {
                $skillWeighted += $requirement['weight'] * $attainment;
                $experienceWeighted += $requirement['weight'] * ($evidenced ? 1.0 : 0.0);
            }

            $evaluations[] = [
                'code' => $code,
                'label' => $requirement['label'],
                'current_score' => $current,
                'target_score' => $target,
                'gap' => max($target - $current, 0),
                'weight' => $requirement['weight'],
                'required' => $requirement['required'],
                'is_met' => $isMet,
                'evidence_refs' => $evidenceRefs,
            ];
        }

        $signalWeightSum = 0.0;
        $assessmentWeighted = 0.0;
        foreach ($role->assessmentSignals() as $signal) {
            $key = CareerRoleBenchmark::signalKey($signal['family'], $signal['dimension']);
            // Absence is not an observed score. Keep it distinct from an
            // explicit score of 0 so missing assessment data cannot earn
            // compatibility points from the similarity formula.
            $similarity = array_key_exists($key, $signals)
                ? max(0.0, 1.0 - abs($signals[$key] - $signal['target']) / 100.0)
                : 0.0;
            $assessmentWeighted += $signal['weight'] * $similarity;
            $signalWeightSum += $signal['weight'];
        }

        $skillScore = $skillWeightSum > 0.0 ? (int) round($skillWeighted / $skillWeightSum * 100.0) : 0;
        $assessmentScore = $signalWeightSum > 0.0 ? (int) round($assessmentWeighted / $signalWeightSum * 100.0) : 0;
        $experienceScore = $skillWeightSum > 0.0 ? (int) round($experienceWeighted / $skillWeightSum * 100.0) : 0;

        usort($evaluations, static fn (array $a, array $b): int => strcmp($a['code'], $b['code']));

        $benchmarkCodes = [];
        foreach ($role->skillRequirements() as $requirement) {
            $benchmarkCodes[$requirement['code']] = true;
        }
        $unbenchmarked = [];
        foreach ($candidate->requiredSkills() as $skill) {
            $code = LearnerOpportunityProfile::normalizeCode((string) $skill['code']);
            if ($code === '' || isset($benchmarkCodes[$code])) {
                continue;
            }
            $unbenchmarked[] = [
                'code' => $code,
                'label' => (string) ($skill['label'] ?? $code),
            ];
        }

        return new JobMatchResult(
            $role,
            new JobMatchScore($skillScore, $assessmentScore, $experienceScore),
            $evaluations,
            $unbenchmarked,
        );
    }
}
