<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use InvalidArgumentException;

/**
 * Strict allow-list, evidence, uniqueness and safety validator for the
 * Gemini Top 3 opportunity analyses. Every model-derived string is
 * validated here before reaching the learner.
 */
final class OpportunityMatchValidator
{
    private const ALLOWED_ITEM_KEYS = [
        'catalog_id', 'gemini_score', 'why_fit',
        'matched_skill_codes', 'missing_skill_codes',
        'expected_outcome_codes', 'evidence_ref_ids',
    ];

    private const LOW_FIT_KEYS = [
        'catalog_id', 'gemini_score', 'why_not_fit_yet',
        'matched_skill_codes', 'missing_skill_codes',
        'missing_conditions', 'improvement_steps', 'evidence_ref_ids',
    ];

    private const MIN_WHY_FIT_LENGTH = 12;

    private const NEAR_DUPLICATE_JACCARD = 0.85;

    private const UNSAFE_PATTERNS = [
        '/\b(chắc chắn|đảm bảo|cam kết|được tuyển|đậu đại học|nhập học|guaranteed|will definitely|sẽ được tuyển|sẽ đạt giải|se duoc tuyen|se dat giai|chac chan|dam bao|cam ket|duoc tuyen|dau dai hoc|nhap hoc|hired|admitted|will be hired|will be admitted|will be awarded|will receive an offer|admission guarantee|employment guarantee|will win|will pass)\b/iu',
        '/\b(tuyển dụng|hiring|admission|admissions|award|prize|grade|grades|employment|job offer|tuyen dung|giai thuong)\b.*\b(chắc chắn|đảm bảo|guaranteed|sẽ được|will be|chac chan|dam bao|se duoc)\b/iu',
    ];

    /**
     * @param list<array<string,mixed>> $items
     * @param list<OpportunityCandidate> $allowList
     * @return list<OpportunityMatch>
     */
    public function validate(array $items, array $allowList, LearnerOpportunityProfile $profile, string $mode = 'top3'): array
    {
        $isTop3 = $mode === 'top3';
        $isLowFit = $mode === 'low_fit';
        if (!in_array($mode, ['top3', 'recommendation', 'low_fit'], true)) {
            throw new InvalidArgumentException('Unsupported opportunity match validation mode.');
        }
        if (($isTop3 && count($items) !== 3) || (!$isTop3 && (count($items) < 1 || count($items) > 3))) {
            throw new InvalidArgumentException($isTop3
                ? 'Opportunity match validator requires exactly three items.'
                : 'Opportunity match validator requires one to three items.');
        }

        $candidateMap = self::indexCandidates($allowList);
        $profileEvidence = self::profileEvidenceAllowList($profile);

        $normalisedWhyFits = [];
        $matches = [];
        $seenIds = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Opportunity match validator expects structured items.');
            }
            $allowedKeys = $isLowFit ? self::LOW_FIT_KEYS : self::ALLOWED_ITEM_KEYS;
            $extraKeys = array_diff(array_keys($item), $allowedKeys);
            if ($extraKeys !== []) {
                throw new InvalidArgumentException('Opportunity match item carries unsupported properties: ' . implode(',', $extraKeys));
            }
            $requiredKeys = $isLowFit
                ? self::LOW_FIT_KEYS
                : self::ALLOWED_ITEM_KEYS;
            foreach ($requiredKeys as $requiredKey) {
                if (!array_key_exists($requiredKey, $item)) {
                    throw new InvalidArgumentException("Opportunity match item is missing required key {$requiredKey}.");
                }
            }

            $catalogId = self::canonicalString($item['catalog_id'] ?? null, 'catalog_id');
            if (!isset($candidateMap[$catalogId])) {
                throw new InvalidArgumentException("Opportunity match item references a catalog id that is not in the candidate allow-list: {$catalogId}.");
            }
            if (isset($seenIds[$catalogId])) {
                throw new InvalidArgumentException('Opportunity match validator detected duplicate catalog id.');
            }
            $seenIds[$catalogId] = true;
            $candidate = $candidateMap[$catalogId];

            $geminiScore = $item['gemini_score'] ?? null;
            if (!is_int($geminiScore) || $geminiScore < 0 || $geminiScore > 100) {
                throw new InvalidArgumentException('Opportunity match gemini_score must be an integer within 0..100.');
            }

            $whyField = $isLowFit ? 'why_not_fit_yet' : 'why_fit';
            $whyFit = self::canonicalString($item[$whyField] ?? null, $whyField);
            if (mb_strlen(trim($whyFit), 'UTF-8') < self::MIN_WHY_FIT_LENGTH) {
                throw new InvalidArgumentException('Opportunity match why_fit is too short to be project-specific.');
            }
            $normalised = self::normaliseWhyFit($whyFit);
            $normalisedWhyFits[] = $normalised;
            self::assertSafeClaim($whyFit);

            $matchedCodes = self::codeList($item['matched_skill_codes'] ?? null, 'matched_skill_codes');
            $missingCodes = self::codeList($item['missing_skill_codes'] ?? null, 'missing_skill_codes');
            $outcomeCodes = $isLowFit
                ? []
                : self::codeList($item['expected_outcome_codes'] ?? null, 'expected_outcome_codes');
            $evidenceRefs = self::codeList($item['evidence_ref_ids'] ?? null, 'evidence_ref_ids');

            $requiredSkills = [];
            foreach ($candidate->requiredSkills() as $skill) {
                $requiredSkills[$skill['code']] = $skill['minimum_score'];
            }
            $candidateOutcomes = [];
            foreach ($candidate->learningOutcomes() as $outcome) {
                $candidateOutcomes[$outcome['code']] = true;
            }
            $candidateEvidence = [];
            foreach ($candidate->providerPayload()['evidence_refs'] ?? [] as $ref) {
                if (is_string($ref)) {
                    $candidateEvidence[$ref] = true;
                }
            }

            foreach ($matchedCodes as $code) {
                $minimum = $requiredSkills[$code] ?? null;
                $profileScore = $profile->skillScore($code);
                if ($minimum === null || $profileScore === null || $profileScore < $minimum) {
                    throw new InvalidArgumentException("Opportunity match matched_skill_codes contains unsupported code: {$code}.");
                }
            }
            foreach ($missingCodes as $code) {
                $minimum = $requiredSkills[$code] ?? null;
                $profileScore = $profile->skillScore($code);
                if ($minimum === null || ($profileScore !== null && $profileScore >= $minimum)) {
                    throw new InvalidArgumentException("Opportunity match missing_skill_codes contains unsupported code: {$code}.");
                }
                if (in_array($code, $matchedCodes, true)) {
                    throw new InvalidArgumentException("Opportunity match item lists {$code} as both matched and missing.");
                }
            }
            foreach ($outcomeCodes as $code) {
                if (!isset($candidateOutcomes[$code])) {
                    throw new InvalidArgumentException("Opportunity match expected_outcome_codes contains unsupported code: {$code}.");
                }
            }
            foreach ($evidenceRefs as $ref) {
                $isCatalogEvidence = str_starts_with($ref, 'opportunity:') || str_starts_with($ref, 'catalog:');
                $isAllowed = isset($candidateEvidence[$ref])
                    || (!$isCatalogEvidence && isset($profileEvidence[$ref]));
                if (!$isAllowed) {
                    throw new InvalidArgumentException("Opportunity match evidence_ref_ids contains unsupported reference: {$ref}.");
                }
            }
            if ($evidenceRefs === []) {
                throw new InvalidArgumentException('Opportunity match item must cite at least one evidence reference.');
            }

            $missingConditions = [];
            $improvementSteps = [];
            if ($isLowFit) {
                $missingConditions = self::textList($item['missing_conditions'] ?? null, 'missing_conditions');
                $improvementSteps = self::textList($item['improvement_steps'] ?? null, 'improvement_steps', true);
                foreach ($improvementSteps as $step) {
                    self::assertSafeClaim($step);
                }
            }

            $matches[] = new OpportunityMatch(
                $candidate,
                $geminiScore,
                $whyFit,
                $matchedCodes,
                $missingCodes,
                $outcomeCodes,
                $evidenceRefs,
                null,
                $isLowFit ? 'low_fit' : 'recommendation',
                $isLowFit ? $whyFit : '',
                $missingConditions,
                $improvementSteps,
            );
        }

        self::assertNoDuplicateWhyFits($normalisedWhyFits);

        return $matches;
    }

    /** @param array<string,mixed> $analysis @param list<string> $evidenceAllowList @return array<string,mixed> */
    public function validateSummary(array $analysis, LearnerOpportunityProfile $profile, array $evidenceAllowList): array
    {
        $required = ['headline', 'explanation', 'learner_strengths', 'catalog_demands', 'main_gaps', 'next_steps', 'evidence_ref_ids'];
        $extra = array_diff(array_keys($analysis), $required);
        if ($extra !== []) {
            throw new InvalidArgumentException('No-fit summary carries unsupported properties: ' . implode(',', $extra));
        }
        foreach ($required as $key) {
            if (!array_key_exists($key, $analysis)) {
                throw new InvalidArgumentException("No-fit summary is missing required key {$key}.");
            }
        }
        $headline = self::canonicalString($analysis['headline'], 'headline');
        $explanation = self::canonicalString($analysis['explanation'], 'explanation');
        if (mb_strlen($headline, 'UTF-8') < 12 || mb_strlen($explanation, 'UTF-8') < 24) {
            throw new InvalidArgumentException('No-fit summary text is too short.');
        }
        self::assertSafeClaim($headline);
        self::assertSafeClaim($explanation);
        $strengths = self::textList($analysis['learner_strengths'], 'learner_strengths');
        $demands = self::textList($analysis['catalog_demands'], 'catalog_demands');
        $gaps = self::textList($analysis['main_gaps'], 'main_gaps');
        $steps = self::textList($analysis['next_steps'], 'next_steps', true);
        foreach (array_merge($strengths, $demands, $gaps, $steps) as $text) {
            self::assertSafeClaim($text);
        }
        $refs = self::codeList($analysis['evidence_ref_ids'], 'evidence_ref_ids');
        $allowed = array_fill_keys(array_map('strval', $evidenceAllowList), true);
        if ($refs === []) {
            throw new InvalidArgumentException('No-fit summary must cite at least one evidence reference.');
        }
        foreach ($refs as $ref) {
            if (!isset($allowed[$ref])) {
                throw new InvalidArgumentException("No-fit summary evidence_ref_ids contains unsupported reference: {$ref}.");
            }
        }
        return [
            'headline' => $headline,
            'explanation' => $explanation,
            'learner_strengths' => $strengths,
            'catalog_demands' => $demands,
            'main_gaps' => $gaps,
            'next_steps' => $steps,
            'evidence_ref_ids' => $refs,
            'state' => 'no_fit_model',
        ];
    }

    /** @param list<OpportunityCandidate> $allowList @return array<string,OpportunityCandidate> */
    private static function indexCandidates(array $allowList): array
    {
        $map = [];
        foreach ($allowList as $candidate) {
            $map[$candidate->catalogId()] = $candidate;
        }
        return $map;
    }

    /** @return array<string,true> */
    private static function profileEvidenceAllowList(LearnerOpportunityProfile $profile): array
    {
        $refs = [];
        foreach ($profile->evidenceRefs() as $ref) {
            if (!str_starts_with($ref, 'opportunity:') && !str_starts_with($ref, 'catalog:')) {
                $refs[$ref] = true;
            }
        }
        return $refs;
    }

    private static function canonicalString(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException("Opportunity match {$field} must be a string.");
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException("Opportunity match {$field} must not be empty.");
        }
        return $trimmed;
    }

    /** @return list<string> */
    private static function codeList(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("Opportunity match {$field} must be an array.");
        }
        $codes = [];
        foreach ($value as $entry) {
            if (!is_string($entry)) {
                throw new InvalidArgumentException("Opportunity match {$field} entries must be strings.");
            }
            $trimmed = trim($entry);
            if ($trimmed !== '') {
                $codes[$trimmed] = true;
            }
        }
        return array_values(array_keys($codes));
    }

    /** @return list<string> */
    private static function textList(mixed $value, string $field, bool $requireOne = false): array
    {
        $items = self::codeList($value, $field);
        if ($requireOne && $items === []) {
            throw new InvalidArgumentException("Opportunity match {$field} must contain at least one item.");
        }
        foreach ($items as $item) {
            if (mb_strlen($item, 'UTF-8') < 2) {
                throw new InvalidArgumentException("Opportunity match {$field} entries are too short.");
            }
        }
        return $items;
    }

    private static function normaliseWhyFit(string $whyFit): string
    {
        $lower = mb_strtolower($whyFit, 'UTF-8');
        $collapsed = (string) preg_replace('/\s+/u', ' ', $lower);
        return trim($collapsed);
    }

    /** @param list<string> $normalised */
    private static function assertNoDuplicateWhyFits(array $normalised): void
    {
        for ($i = 0; $i < count($normalised); $i++) {
            for ($j = $i + 1; $j < count($normalised); $j++) {
                if ($normalised[$i] === $normalised[$j]) {
                    throw new InvalidArgumentException('Opportunity match why_fit strings must be project-specific across the three items.');
                }
                if (self::jaccard($normalised[$i], $normalised[$j]) >= self::NEAR_DUPLICATE_JACCARD) {
                    throw new InvalidArgumentException('Opportunity match why_fit strings are too similar across the three items.');
                }
            }
        }
    }

    private static function jaccard(string $a, string $b): float
    {
        $tokensA = self::tokens($a);
        $tokensB = self::tokens($b);
        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }
        $intersection = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));
        return $union === 0 ? 0.0 : $intersection / $union;
    }

    /** @return list<string> */
    private static function tokens(string $value): array
    {
        $parts = (array) preg_split('/[\s\p{P}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($parts, static fn (string $token): bool => mb_strlen($token, 'UTF-8') > 0));
    }

    private static function assertSafeClaim(string $whyFit): void
    {
        foreach (self::UNSAFE_PATTERNS as $pattern) {
            if (preg_match($pattern, $whyFit) === 1) {
                throw new InvalidArgumentException('Opportunity match why_fit contains an unsupported hiring or outcome promise.');
            }
        }
    }
}
