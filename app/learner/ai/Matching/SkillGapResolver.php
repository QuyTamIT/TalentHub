<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

/**
 * Builds the canonical skill gap contract from a deterministic job match
 * result.
 *
 * Guarantees:
 * - every entry comes from real learner scores and role benchmark targets;
 * - missing skills sort required first, then weight descending, then code
 *   ascending;
 * - impact labels are fixed Vietnamese rule templates chosen by
 *   required/weight/gap — never model-generated text;
 * - unknown candidate targets remain null and require clarification;
 * - a learner meeting every benchmark yields the no_skill_gap state.
 */
final class SkillGapResolver
{
    /**
     * @return array<string,mixed>
     */
    public function resolve(JobMatchResult $match): array
    {
        $missing = $match->missingSkills();
        $met = $match->metSkills();

        usort($missing, static function (array $a, array $b): int {
            // required first, weight descending, code ascending
            return [(int) $b['required'], (float) $b['weight'], $b['code']]
                <=> [(int) $a['required'], (float) $a['weight'], $a['code']];
        });

        $role = $match->role();
        $missingEntries = [];
        foreach ($missing as $skill) {
            $missingEntries[] = [
                'code' => $skill['code'],
                'label' => $skill['label'],
                'current_score' => $skill['current_score'],
                'target_score' => $skill['target_score'],
                'gap_score' => $skill['gap'],
                'weight' => $skill['weight'],
                'is_required' => $skill['required'],
                'impact' => self::impactLabel($skill),
                'evidence_refs' => $skill['evidence_refs'],
                'target_basis' => $skill['target_basis'],
                'target_is_approximate' => $skill['target_is_approximate'],
            ];
        }

        $metEntries = [];
        foreach ($met as $skill) {
            $metEntries[] = [
                'code' => $skill['code'],
                'label' => $skill['label'],
                'current_score' => $skill['current_score'],
                'target_score' => $skill['target_score'],
                'gap_score' => $skill['gap'],
                'weight' => $skill['weight'],
                'is_required' => $skill['required'],
                'evidence_refs' => $skill['evidence_refs'],
                'target_basis' => $skill['target_basis'],
                'target_is_approximate' => $skill['target_is_approximate'],
            ];
        }

        return [
            'state' => $missingEntries === [] ? 'no_skill_gap' : 'ok',
            'role' => ['code' => $role->code(), 'title' => $role->title()],
            'match_score' => $match->score()->totalScore(),
            'skill_readiness_score' => $match->score()->skillScore(),
            'skills_met' => $metEntries,
            'skills_missing' => $missingEntries,
            'unbenchmarked_skills' => $match->unbenchmarkedSkills(),
        ];
    }

    /** @param array{current_score:?int,target_score:?int,gap:?int,weight:float,required:bool,label?:string,code?:string} $skill */
    private static function impactLabel(array $skill): string
    {
        $gap = $skill['gap'];
        $label = $skill['label'] ?? ($skill['code'] ?? 'kỹ năng này');
        if ($skill['current_score'] === null) {
            return 'Bạn chưa có kỹ năng này trong hồ sơ.';
        }
        if ($skill['target_score'] === null) {
            return 'Vị trí chưa công bố mức yêu cầu.';
        }
        if ($gap !== null && $gap > 0) {
            return "Còn thiếu {$gap} điểm về {$label} so với chuẩn vị trí.";
        }
        return 'Đã đạt yêu cầu';
    }
}
