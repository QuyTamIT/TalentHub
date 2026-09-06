<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

/**
 * Deterministic resolver that picks the career role benchmark best matching
 * an internship candidate.
 *
 * Algorithm (no Gemini, no free-form inference):
 * 1. Candidate skill coverage per role = weight share of benchmark skills
 *    present in the candidate's canonical required skill codes.
 * 2. The highest coverage wins. Roles with zero coverage are never selected.
 * 3. Equal coverage ties are broken by allow-listed title/field keywords.
 * 4. Remaining ties fall back to the stable ascending role code.
 * 5. Without enough data the resolver reports unresolved_role and never
 *    assigns targets, missing skills or evidence.
 */
final class JobRoleResolver
{
    /**
     * Allow-listed keywords per role. Matching happens on an ASCII-folded
     * word-boundary key of the candidate title and field.
     */
    private const ROLE_KEYWORDS = [
        'ai_engineer' => ['ai', 'ml', 'llm', 'machine learning', 'generative ai', 'nlp', 'deep learning', 'computer vision'],
        'data_analyst' => ['data', 'analytics', 'bi', 'business intelligence', 'statistics', 'dashboard'],
        'backend_developer' => ['backend', 'back end', 'api', 'server'],
        'frontend_developer' => ['frontend', 'front end', 'ui', 'react'],
        'fullstack_developer' => ['fullstack', 'full stack'],
        'digital_marketing' => ['marketing', 'content', 'ads', 'seo', 'social media'],
    ];

    private const DIACRITIC_ASCII = [
        'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
        'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
        'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
        'đ' => 'd',
        'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
        'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
        'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
        'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
        'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
        'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
    ];

    /**
     * @param list<CareerRoleBenchmark> $roles
     * @return array{status:string, role:?CareerRoleBenchmark, reason:string}
     */
    public function resolve(OpportunityCandidate $candidate, array $roles): array
    {
        $unresolved = static fn (string $reason): array => ['status' => 'unresolved_role', 'role' => null, 'reason' => $reason];
        if ($roles === []) {
            return $unresolved('no_roles');
        }

        $candidateCodes = [];
        foreach ($candidate->requiredSkills() as $skill) {
            $code = LearnerOpportunityProfile::normalizeCode((string) $skill['code']);
            if ($code !== '') {
                $candidateCodes[$code] = true;
            }
        }
        if ($candidateCodes === []) {
            return $unresolved('no_candidate_skills');
        }

        $coverage = [];
        foreach ($roles as $role) {
            $matched = 0.0;
            $total = $role->skillWeightSum();
            if ($total <= 0.0) {
                $coverage[$role->code()] = 0.0;
                continue;
            }
            foreach ($role->skillRequirements() as $requirement) {
                if (isset($candidateCodes[$requirement['code']])) {
                    $matched += $requirement['weight'];
                }
            }
            $coverage[$role->code()] = $matched / $total;
        }

        $maxCoverage = max($coverage);
        if ($maxCoverage <= 0.0) {
            return $unresolved('no_benchmark_skill_overlap');
        }

        $tied = array_values(array_filter(
            $roles,
            static fn (CareerRoleBenchmark $role): bool => $coverage[$role->code()] === $maxCoverage,
        ));
        if (count($tied) === 1) {
            return ['status' => 'resolved', 'role' => $tied[0], 'reason' => 'skill_overlap'];
        }

        $text = self::normalizeText($candidate->title() . ' ' . $this->candidateField($candidate));
        $keywordHits = array_values(array_filter(
            $tied,
            static fn (CareerRoleBenchmark $role): bool => self::matchesKeywords($text, self::ROLE_KEYWORDS[$role->code()] ?? []),
        ));
        if (count($keywordHits) === 1) {
            return ['status' => 'resolved', 'role' => $keywordHits[0], 'reason' => 'keyword_tiebreak'];
        }

        usort($tied, static fn (CareerRoleBenchmark $a, CareerRoleBenchmark $b): int => strcmp($a->code(), $b->code()));
        return ['status' => 'resolved', 'role' => $tied[0], 'reason' => 'code_tiebreak'];
    }

    private function candidateField(OpportunityCandidate $candidate): string
    {
        $payload = $candidate->providerPayload();
        return is_string($payload['field'] ?? null) ? (string) $payload['field'] : '';
    }

    /** @param list<string> $keywords */
    private static function matchesKeywords(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains(' ' . $text . ' ', ' ' . $keyword . ' ')) {
                return true;
            }
        }
        return false;
    }

    private static function normalizeText(string $raw): string
    {
        $value = mb_strtolower(trim($raw), 'UTF-8');
        $value = strtr($value, self::DIACRITIC_ASCII);
        $value = (string) preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
