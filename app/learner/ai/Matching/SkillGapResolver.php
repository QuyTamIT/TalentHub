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

    /** @param array{current_score:?int,target_score:?int,gap:?int,weight:float,required:bool} $skill */
    private static function impactLabel(array $skill): string
    {
        $gap = $skill['gap'];
        if ($skill['target_score'] === null) {
            return 'Ngưỡng yêu cầu chưa được nguồn công bố; cần đối chiếu thêm trước khi kết luận mức độ đáp ứng.';
        }
        if ($skill['current_score'] === null) {
            return 'Chưa có điểm quan sát cho kỹ năng này; cần đánh giá thêm trước khi kết luận mức độ đáp ứng.';
        }
        if ($skill['required'] && $gap >= 40) {
            return 'Kỹ năng bắt buộc còn thiếu lớn, ảnh hưởng trực tiếp đến khả năng đảm nhận vị trí.';
        }
        if ($skill['required']) {
            return ($skill['target_basis'] ?? '') === 'candidate'
                ? 'Kỹ năng bắt buộc chưa đạt ngưỡng được công bố cho vị trí này.'
                : 'Kỹ năng bắt buộc chưa đạt ngưỡng tham chiếu của nghề; cần đối chiếu yêu cầu thực tế của vị trí.';
        }
        if ($gap >= 40) {
            return 'Khoảng thiếu lớn ảnh hưởng rõ rệt đến mức độ phù hợp.';
        }
        if ($skill['weight'] >= 15.0) {
            return 'Khoảng thiếu nằm ở kỹ năng có trọng số cao trong phép đối chiếu hiện tại.';
        }
        return 'Khoảng thiếu nhỏ, có thể bù đắp qua luyện tập có mục tiêu.';
    }
}
