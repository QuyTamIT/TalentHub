<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use InvalidArgumentException;

final class JobMatchAnalysisValidator
{
    private const KEYS = [
        'catalog_id', 'analysis', 'strength_skill_codes', 'gap_skill_codes',
        'gap_explanations', 'evidence_ref_ids',
    ];
    private const ANALYSIS_KEYS = ['catalog_id', 'analysis', 'evidence_ref_ids'];

    /**
     * @param list<array<string,mixed>> $items
     * @param list<OpportunityCandidate> $candidates
     * @param array<string,JobMatchResult> $matches
     * @param array<string,array<string,mixed>> $gaps
     * @return list<JobMatchAnalysis>
     */
    public function validate(array $items, array $candidates, array $matches, array $gaps, LearnerOpportunityProfile $profile): array
    {
        if ($items === [] || count($items) > 10) {
            throw new InvalidArgumentException('Job match analysis requires one to ten items.');
        }
        $candidateMap = [];
        $evidence = array_fill_keys($profile->evidenceRefs(), true);
        foreach ($candidates as $candidate) {
            if (!$candidate instanceof OpportunityCandidate) {
                throw new InvalidArgumentException('Job match candidate allow-list is invalid.');
            }
            $candidateMap[$candidate->catalogId()] = $candidate;
            foreach ($candidate->providerPayload()['evidence_refs'] ?? [] as $ref) {
                if (is_string($ref)) {
                    $evidence[$ref] = true;
                }
            }
        }

        $seen = [];
        $validated = [];
        foreach ($items as $item) {
            $keys = is_array($item) ? array_keys($item) : [];
            $analysisOnly = is_array($item)
                && array_diff($keys, self::ANALYSIS_KEYS) === []
                && array_diff(self::ANALYSIS_KEYS, $keys) === [];
            $legacy = is_array($item)
                && array_diff($keys, self::KEYS) === []
                && array_diff(self::KEYS, $keys) === [];
            if (!$analysisOnly && !$legacy) {
                throw new InvalidArgumentException('Job match analysis contains missing or unsupported fields.');
            }
            $id = self::string($item['catalog_id'], 'catalog_id', 128);
            if (!isset($candidateMap[$id], $matches[$id], $gaps[$id]) || isset($seen[$id])) {
                throw new InvalidArgumentException('Job match analysis references an unknown or duplicate job.');
            }
            $seen[$id] = true;
            $analysis = self::string($item['analysis'], 'analysis', 1200);
            self::validateVietnameseAnalysis($analysis);
            if ($matches[$id]->score()->totalScore() < 40
                && preg_match('/(chưa phù hợp|chưa đáp ứng|chưa đạt|thấp hơn|còn thiếu)/iu', $analysis) !== 1) {
                throw new InvalidArgumentException('A below-threshold analysis must explicitly explain why the position is not yet suitable.');
            }

            $metCodes = array_values(array_column($matches[$id]->metSkills(), 'code'));
            $missingCodes = array_values(array_column($matches[$id]->missingSkills(), 'code'));
            $met = array_fill_keys($metCodes, true);
            $missing = array_fill_keys($missingCodes, true);
            if ($analysisOnly) {
                $strengths = array_values(array_column($gaps[$id]['skills_met'] ?? [], 'code')) ?: $metCodes;
                $gapEntries = is_array($gaps[$id]['skills_missing'] ?? null) ? $gaps[$id]['skills_missing'] : [];
                $gapCodes = array_values(array_column($gapEntries, 'code')) ?: $missingCodes;
                $gapExplanations = array_map(static fn (array $gap): array => [
                    'skill_code' => (string) $gap['code'],
                    'explanation' => (string) $gap['impact'],
                ], array_values(array_filter($gapEntries, static fn (mixed $gap): bool =>
                    is_array($gap) && is_string($gap['code'] ?? null) && is_string($gap['impact'] ?? null)
                )));
            } else {
                $strengths = self::codeList($item['strength_skill_codes'], 'strength_skill_codes', $met);
                $gapCodes = self::codeList($item['gap_skill_codes'], 'gap_skill_codes', $missing);
                $gapExplanations = self::gapExplanations($item['gap_explanations'], $gapCodes);
            }
            $refs = self::codeList($item['evidence_ref_ids'], 'evidence_ref_ids', $evidence, false);
            if ($refs === []) {
                throw new InvalidArgumentException('Job match analysis requires evidence references.');
            }
            $validated[] = new JobMatchAnalysis($id, $analysis, $strengths, $gapCodes, $gapExplanations, $refs);
        }
        return $validated;
    }

    private static function validateVietnameseAnalysis(string $analysis): void
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($analysis), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($sentences) < 3 || count($sentences) > 4 || preg_match('/[ăâđêôơưáàảãạéèẻẽẹíìỉĩịóòỏõọúùủũụýỳỷỹỵ]/iu', $analysis) !== 1) {
            throw new InvalidArgumentException('Job analysis must contain three to four natural Vietnamese sentences.');
        }
        if (preg_match('/\b(chắc chắn|đảm bảo|cam kết|sẽ được tuyển|được tuyển|guaranteed|will be hired)\b/iu', $analysis) === 1) {
            throw new InvalidArgumentException('Job analysis must not promise a hiring outcome.');
        }
    }

    /** @param array<string,bool> $allow @return list<string> */
    private static function codeList(mixed $raw, string $field, array $allow, bool $canonical = true): array
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            throw new InvalidArgumentException("{$field} must be a list.");
        }
        $values = [];
        foreach ($raw as $value) {
            $value = self::string($value, $field, 160);
            if (($canonical && preg_match('/\A[a-z0-9]+(?:_[a-z0-9]+)*\z/', $value) !== 1) || !isset($allow[$value]) || isset($values[$value])) {
                throw new InvalidArgumentException("{$field} contains an unknown or duplicate value.");
            }
            $values[$value] = true;
        }
        return array_keys($values);
    }

    /** @param list<string> $gapCodes @return list<array{skill_code:string,explanation:string}> */
    private static function gapExplanations(mixed $raw, array $gapCodes): array
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            throw new InvalidArgumentException('gap_explanations must be a list.');
        }
        $allow = array_fill_keys($gapCodes, true);
        $seen = [];
        $out = [];
        foreach ($raw as $entry) {
            if (!is_array($entry) || array_keys($entry) !== ['skill_code', 'explanation']) {
                throw new InvalidArgumentException('A gap explanation is malformed.');
            }
            $code = self::string($entry['skill_code'], 'skill_code', 64);
            $explanation = self::string($entry['explanation'], 'explanation', 500);
            if (!isset($allow[$code]) || isset($seen[$code]) || mb_strlen($explanation, 'UTF-8') < 20) {
                throw new InvalidArgumentException('A gap explanation is unknown, duplicate or too short.');
            }
            $seen[$code] = true;
            $out[] = ['skill_code' => $code, 'explanation' => $explanation];
        }
        if (array_keys($seen) !== $gapCodes) {
            sort($gapCodes);
            $seenCodes = array_keys($seen);
            sort($seenCodes);
            if ($seenCodes !== $gapCodes) {
                throw new InvalidArgumentException('Every returned gap skill requires exactly one explanation.');
            }
        }
        return $out;
    }

    private static function string(mixed $raw, string $field, int $max): string
    {
        if (!is_string($raw) || trim($raw) === '' || mb_strlen(trim($raw), 'UTF-8') > $max) {
            throw new InvalidArgumentException("{$field} must be a non-empty bounded string.");
        }
        return trim($raw);
    }
}
