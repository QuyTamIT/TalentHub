<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class EvaluationCohortPolicy
{
    /** @param array<string,mixed> $canonicalFacts */
    public function educationBand(array $canonicalFacts): ?string
    {
        $value = mb_strtolower(trim((string) ($canonicalFacts['education_level'] ?? $canonicalFacts['grade_level'] ?? '')), 'UTF-8');
        if ($value === '') return null;
        if (preg_match('/(thpt|high\s*school|grade\s*(10|11|12)|lớp\s*(10|11|12))/u', $value) === 1) return 'high';
        if (preg_match('/(university|college|đại\s*học|cao\s*đẳng)/u', $value) === 1) return 'college';
        return null;
    }

    /** @param array<string,mixed> $canonicalFacts @return list<string> */
    public function approvedTags(array $canonicalFacts): array { return []; }
}
