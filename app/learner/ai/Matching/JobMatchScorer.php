<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

/**
 * Deterministic backend scorer for AI Job Matching (40% skills, 35%
 * assessment signals, 25% experience evidence).
 *
 * Formulas:
 * - skill component: weight-averaged attainment min(current/target, 1.0) over
 *   role benchmark skills refined by posting-specific requirements; skills
 *   absent from the learner profile count as current score 0.
 * - assessment component: for each role signal, similarity
 *   max(0, 1 - |actual - target| / 100) with the actual value resolved from
 *   the family-specific learner signals; a missing signal contributes zero
 *   similarity and is never replaced by invented data.
 * - experience component: share of benchmark skill weight evidenced by
 *   explicit canonical tags on confirmed activities, projects or published
 *   evaluations; skill records alone are never treated as experience.
 *
 * The scorer never calls Gemini, never invents skills, targets or evidence,
 * Candidate targets retain provenance, including explicit approximation when
 * a source supplies no usable threshold.
 */
final class JobMatchScorer
{
    public function score(LearnerOpportunityProfile $profile, OpportunityCandidate $candidate, CareerRoleBenchmark $role): JobMatchResult
    {
        $signals = $profile->assessmentSignals();
        $skillEvidenceRefs = $profile->skillEvidenceRefs();
        $experienceTags = $profile->confirmedExperienceTags();

        $requirements = $this->effectiveRequirements($candidate, $role);
        $skillWeightSum = array_sum(array_column($requirements, 'weight'));
        $skillWeighted = 0.0;
        $experienceWeighted = 0.0;
        $evaluations = [];

        $hasMandatoryGap = false;
        foreach ($requirements as $requirement) {
            $code = $requirement['code'];
            $current = $profile->skillScore($code);
            $target = $requirement['minimum_score'];
            $targetKnown = is_int($target) && $target > 0;
            $attainment = $targetKnown && $current !== null ? min($current / $target, 1.0) : 0.0;
            $isMet = $targetKnown && $current !== null && $current >= $target;
            if ($requirement['required'] && !$isMet) {
                $hasMandatoryGap = true;
            }
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
                'gap' => $targetKnown && $current !== null ? max($target - $current, 0) : null,
                'weight' => $requirement['weight'],
                'required' => $requirement['required'],
                'is_met' => $isMet,
                'evidence_refs' => $evidenceRefs,
                'target_basis' => $requirement['target_basis'],
                'target_is_approximate' => $requirement['target_is_approximate'] || !$targetKnown,
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
        foreach ($requirements as $requirement) $benchmarkCodes[$requirement['code']] = true;
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
            new JobMatchScore($skillScore, $assessmentScore, $experienceScore, $hasMandatoryGap ? 59 : 100),
            $evaluations,
            $unbenchmarked,
        );
    }

    /**
     * Candidate requirements refine the selected role benchmark. A positive
     * candidate threshold is authoritative for that posting, including a
     * lower threshold; a zero source threshold remains explicitly unknown.
     *
     * @return list<array{code:string,label:string,minimum_score:?int,weight:float,required:bool,target_basis:string,target_is_approximate:bool}>
     */
    private function effectiveRequirements(OpportunityCandidate $candidate, CareerRoleBenchmark $role): array
    {
        $effective = [];
        foreach ($role->skillRequirements() as $requirement) {
            $requirement['target_basis'] = 'role_benchmark';
            $requirement['target_is_approximate'] = false;
            $effective[$requirement['code']] = $requirement;
        }

        $defaultWeight = $effective === [] ? 100.0 : 100.0 / count($effective);
        foreach ($candidate->requiredSkills() as $candidateSkill) {
            $code = LearnerOpportunityProfile::normalizeCode($candidateSkill['code']);
            if ($code === '') continue;
            $candidateTarget = $candidateSkill['minimum_score'];
            if (isset($effective[$code])) {
                if ($candidateTarget > 0) {
                    $effective[$code]['minimum_score'] = $candidateTarget;
                    $effective[$code]['target_basis'] = 'candidate';
                    $effective[$code]['target_is_approximate'] = false;
                } else {
                    $effective[$code]['minimum_score'] = null;
                    $effective[$code]['target_basis'] = 'candidate_unspecified';
                    $effective[$code]['target_is_approximate'] = true;
                }
                $effective[$code]['required'] = true;
                continue;
            }
            $effective[$code] = [
                'code' => $code,
                'label' => $candidateSkill['label'],
                'minimum_score' => $candidateTarget > 0 ? $candidateTarget : null,
                'weight' => $defaultWeight,
                'required' => true,
                'target_basis' => $candidateTarget > 0 ? 'candidate' : 'candidate_unspecified',
                'target_is_approximate' => $candidateTarget <= 0,
            ];
        }
        return array_values($effective);
    }
}
