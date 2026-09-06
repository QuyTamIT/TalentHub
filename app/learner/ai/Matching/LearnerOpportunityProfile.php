<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use InvalidArgumentException;
use TalentHub\Learner\Ai\Domain\RecommendationInput;

/**
 * Consent-safe matching profile extracted from a recommendation snapshot.
 * Only allow-listed canonical fields are ever read from the payload, so
 * emails, phone numbers, precise addresses, health data and protected
 * traits can never reach the matching domain.
 */
final class LearnerOpportunityProfile
{
    private const EDUCATION_BANDS = ['middle', 'high', 'college'];

    private const DIACRITICS = [
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

    /** @var ?string */
    private readonly ?string $educationBand;

    /** @var array<string,int> */
    private readonly array $skills;

    /** @var array<string,float> */
    private readonly array $assessmentDimensions;

    /**
     * Family-specific assessment signals keyed "family:DIMENSION" (for
     * example holland:I, mbti:I, multiple_intelligence:LOGI) so identical
     * dimension codes from different assessment families never collide.
     *
     * @var array<string,float>
     */
    private readonly array $assessmentSignals;

    /** @var list<string> */
    private readonly array $experienceTags;

    /** @var list<string> */
    private readonly array $confirmedExperienceTags;

    /** @var list<string> */
    private readonly array $evidenceRefs;

    /** @var array<string,list<string>> skill code => evidence references */
    private readonly array $skillEvidenceRefs;

    private function __construct(
        ?string $educationBand,
        array $skills,
        array $assessmentDimensions,
        array $assessmentSignals,
        array $experienceTags,
        array $confirmedExperienceTags,
        array $evidenceRefs,
        array $skillEvidenceRefs,
    ) {
        $this->educationBand = $educationBand;
        $this->skills = $skills;
        $this->assessmentDimensions = $assessmentDimensions;
        $this->assessmentSignals = $assessmentSignals;
        $this->experienceTags = $experienceTags;
        $this->confirmedExperienceTags = $confirmedExperienceTags;
        $this->evidenceRefs = $evidenceRefs;
        $this->skillEvidenceRefs = $skillEvidenceRefs;
    }

    public static function fromInput(RecommendationInput $input): self
    {
        $payload = $input->payload();

        return new self(
            self::resolveEducationBand($payload),
            self::collectSkills($payload),
            self::collectAssessmentDimensions($payload),
            self::collectAssessmentSignals($payload),
            self::collectExperienceTags($payload),
            self::collectConfirmedExperienceTags($payload, $input),
            self::collectEvidenceRefs($input),
            self::collectSkillEvidenceRefs($input),
        );
    }

    public function educationBand(): ?string
    {
        return $this->educationBand;
    }

    public function skillScore(string $code): ?int
    {
        return $this->skills[self::normalizeCode($code)] ?? null;
    }

    /** @return array<string,int> */
    public function skills(): array
    {
        return $this->skills;
    }

    /** @return array<string,float> */
    public function assessmentDimensions(): array
    {
        return $this->assessmentDimensions;
    }

    /**
     * Family-specific assessment signals keyed "family:DIMENSION". The first
     * occurrence of a signal wins; the assessment source feeds attempts in
     * newest-submitted-first order, so the newest attempt per signal is kept.
     *
     * @return array<string,float>
     */
    public function assessmentSignals(): array
    {
        return $this->assessmentSignals;
    }

    /**
     * Evidence references per canonical skill code, resolved from snapshot
     * evidence whose safe_value carries the skill code.
     *
     * @return array<string,list<string>>
     */
    public function skillEvidenceRefs(): array
    {
        return $this->skillEvidenceRefs;
    }

    /** @return list<string> */
    public function experienceTags(): array
    {
        return $this->experienceTags;
    }

    /**
     * Explicit skill tags attached to confirmed activities or to project /
     * published-evaluation records that expose such tags. This intentionally
     * stays separate from experienceTags(), whose legacy broad category/tag
     * contract is still consumed by the project matching pipeline.
     *
     * @return list<string>
     */
    public function confirmedExperienceTags(): array
    {
        return $this->confirmedExperienceTags;
    }

    /** @return list<string> */
    public function evidenceRefs(): array
    {
        return $this->evidenceRefs;
    }

    /** @param array<string,mixed> $payload */
    private static function resolveEducationBand(array $payload): ?string
    {
        $declared = $payload['education_band'] ?? null;
        if (is_string($declared) && trim($declared) !== '') {
            $declared = strtolower(trim($declared));
            if (!in_array($declared, self::EDUCATION_BANDS, true)) {
                throw new InvalidArgumentException('Learner opportunity profile rejected an unknown education band.');
            }
            return $declared;
        }

        $gradeLevel = $payload['profile']['grade_level'] ?? null;
        if (is_numeric($gradeLevel)) {
            $grade = (int) $gradeLevel;
            // Secondary classes use grades 6–12; college classes use 1-based year levels.
            if ($grade >= 1 && $grade <= 5) return 'college';
            if ($grade >= 6 && $grade <= 9) return 'middle';
            if ($grade >= 10 && $grade <= 12) return 'high';
            if ($grade >= 13) return 'college';
        }

        return null;
    }

    /** @param array<string,mixed> $payload @return array<string,int> */
    private static function collectSkills(array $payload): array
    {
        $skills = [];
        $entries = $payload['skills'] ?? [];
        if (!is_array($entries)) {
            return $skills;
        }
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $rawCode = $entry['code'] ?? null;
            if (!is_string($rawCode) || trim($rawCode) === '') {
                throw new InvalidArgumentException('Learner opportunity profile requires a non-empty skill code.');
            }
            $rawScore = $entry['score'] ?? $entry['level_score'] ?? null;
            if (!is_numeric($rawScore)) {
                throw new InvalidArgumentException('Learner opportunity profile requires a numeric skill score.');
            }
            $score = (int) round((float) $rawScore);
            if ($score < 0 || $score > 100) {
                throw new InvalidArgumentException('Learner opportunity profile rejected an out of range skill score.');
            }
            $code = self::normalizeCode($rawCode);
            if (isset($skills[$code])) {
                throw new InvalidArgumentException('Learner opportunity profile rejected a duplicate skill code.');
            }
            $skills[$code] = $score;
        }
        return $skills;
    }

    /** @param array<string,mixed> $payload @return array<string,float> */
    private static function collectAssessmentDimensions(array $payload): array
    {
        $dimensions = [];
        $assessments = $payload['assessments'] ?? [];
        if (!is_array($assessments)) {
            return $dimensions;
        }
        foreach ($assessments as $assessment) {
            if (!is_array($assessment)) {
                continue;
            }
            $scores = $assessment['dimension_scores'] ?? $assessment['dimensionScores'] ?? null;
            if (!is_array($scores)) {
                continue;
            }
            foreach ($scores as $rawDimension => $rawScore) {
                if (!is_string($rawDimension) || trim($rawDimension) === '' || !is_numeric($rawScore)) {
                    continue;
                }
                $score = (float) $rawScore;
                if ($score < 0 || $score > 100) {
                    throw new InvalidArgumentException('Learner opportunity profile rejected an out of range assessment dimension score.');
                }
                $dimensions[self::normalizeCode($rawDimension)] = $score;
            }
        }
        return $dimensions;
    }

    /** @param array<string,mixed> $payload @return list<string> */
    private static function collectExperienceTags(array $payload): array
    {
        $tags = [];
        $activities = $payload['activities'] ?? [];
        if (!is_array($activities)) {
            return $tags;
        }
        // Preserve the broad legacy contract consumed by the project scorer.
        // JobMatchScorer uses confirmedExperienceTags() below for the stricter
        // applied-experience component.
        foreach ($activities as $activity) {
            if (!is_array($activity)) {
                continue;
            }
            $candidates = [];
            if (isset($activity['experience_id']) || isset($activity['activity_category'])) {
                $candidates[] = $activity['activity_category'] ?? null;
                foreach ((array) ($activity['tags'] ?? []) as $tag) {
                    $candidates[] = is_string($tag) ? $tag : null;
                }
            }
            foreach ($candidates as $candidate) {
                if (!is_string($candidate) || trim($candidate) === '') {
                    continue;
                }
                $code = self::normalizeCode($candidate);
                if ($code !== '' && !in_array($code, $tags, true)) {
                    $tags[] = $code;
                }
            }
        }
        return $tags;
    }

    /**
     * Collect only applied-experience tags that are explicitly supplied by a
     * trusted source. This is deliberately separate from experienceTags() so
     * the existing project-matching pipeline keeps its historical contract.
     *
     * @param array<string,mixed> $payload
     * @return list<string>
     */
    private static function collectConfirmedExperienceTags(array $payload, RecommendationInput $input): array
    {
        $tags = [];
        $activities = $payload['activities'] ?? [];
        if (is_array($activities)) {
            foreach ($activities as $activity) {
                if (!is_array($activity) || !self::isConfirmedActivity($activity, $input)) {
                    continue;
                }
                foreach (['skill_tags', 'skill_codes', 'skills'] as $field) {
                    self::appendCanonicalSkillTags($tags, $activity[$field] ?? null);
                }
            }
        }

        $projects = $payload['projects'] ?? [];
        if (is_array($projects)) {
            foreach ($projects as $project) {
                if (!is_array($project) || !self::isEligibleProject($project)) {
                    continue;
                }
                foreach (['skill_tags', 'skill_codes', 'skills'] as $field) {
                    self::appendCanonicalSkillTags($tags, $project[$field] ?? null);
                }
            }
        }

        $evaluations = $payload['evaluations'] ?? [];
        if (is_array($evaluations)) {
            foreach ($evaluations as $evaluation) {
                if (!is_array($evaluation) || !self::isPublishedEvaluation($evaluation)) {
                    continue;
                }
                foreach (['skill_tags', 'skill_codes', 'skills'] as $field) {
                    self::appendCanonicalSkillTags($tags, $evaluation[$field] ?? null);
                }
            }
        }

        return $tags;
    }

    /** @param array<string,mixed> $activity */
    private static function isConfirmedActivity(array $activity, RecommendationInput $input): bool
    {
        if (!self::nonEmptyString($activity['confirmed_at'] ?? null)
            || (isset($activity['status']) && !self::allowedStatus($activity['status'], ['confirmed', 'completed', 'attended']))) {
            return false;
        }
        if (self::nonEmptyString($activity['experience_id'] ?? null)) {
            return true;
        }

        // The consent-safe snapshot intentionally keeps the experience ID in
        // the evidence reference rather than duplicating it inside safe_value.
        // Require the exact activity payload to be backed by such a reference
        // before accepting its tags as confirmed experience.
        foreach ($input->evidenceReferences() as $reference) {
            if (($reference['source_type'] ?? null) !== 'activity_experience'
                || !self::nonEmptyString($reference['source_id'] ?? null)
                || !is_array($reference['safe_value'] ?? null)) {
                continue;
            }
            if ($reference['safe_value'] === $activity) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $project */
    private static function isEligibleProject(array $project): bool
    {
        if (isset($project['member_status']) && !self::allowedStatus($project['member_status'], ['active', 'completed', 'in_progress'])) {
            return false;
        }
        if (isset($project['membership_status']) && !self::allowedStatus($project['membership_status'], ['active', 'completed', 'in_progress'])) {
            return false;
        }
        return isset($project['status'])
            && self::allowedStatus($project['status'], ['active', 'in_progress', 'completed', 'published']);
    }

    /** @param array<string,mixed> $evaluation */
    private static function isPublishedEvaluation(array $evaluation): bool
    {
        if (isset($evaluation['status']) && !self::allowedStatus($evaluation['status'], ['published'])) {
            return false;
        }
        return self::nonEmptyString($evaluation['published_at'] ?? $evaluation['publishedAt'] ?? null);
    }

    private static function nonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /** @param list<string> $allowed */
    private static function allowedStatus(mixed $status, array $allowed): bool
    {
        return is_string($status) && in_array(strtolower(trim($status)), $allowed, true);
    }

    /** @param list<string> $tags */
    private static function appendCanonicalSkillTags(array &$tags, mixed $raw): void
    {
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return;
            }
            if (str_starts_with($trimmed, '[')) {
                try {
                    $decoded = json_decode($trimmed, true, 64, JSON_THROW_ON_ERROR);
                    self::appendCanonicalSkillTags($tags, $decoded);
                } catch (\Throwable) {
                    // Invalid JSON is untrusted input; fail closed.
                }
                return;
            }
            // A plain string is accepted only when it already has the
            // canonical registry shape. Display labels must be normalized by
            // the source adapter before they reach this profile.
            $normalized = strtolower($trimmed);
            if (preg_match('/\A[a-z0-9]+(?:_[a-z0-9]+)*\z/', $normalized) !== 1) {
                return;
            }
            if (!in_array($normalized, $tags, true)) {
                $tags[] = $normalized;
            }
            return;
        }
        if (!is_array($raw)) {
            return;
        }
        foreach ($raw as $entry) {
            if (is_string($entry)) {
                self::appendCanonicalSkillTags($tags, $entry);
                continue;
            }
            if (!is_array($entry)) {
                continue;
            }
            $code = $entry['code'] ?? $entry['skill_code'] ?? $entry['skillCode'] ?? null;
            if (is_string($code)) {
                self::appendCanonicalSkillTags($tags, $code);
            }
        }
    }

    /** @return list<string> */
    private static function collectEvidenceRefs(RecommendationInput $input): array
    {
        $refs = [];
        foreach ($input->evidenceReferences() as $reference) {
            $type = is_string($reference['source_type'] ?? null) ? trim((string) $reference['source_type']) : '';
            $id = is_string($reference['source_id'] ?? null) ? trim((string) $reference['source_id']) : '';
            if ($type === '' || $id === '') {
                continue;
            }
            $ref = $type . ':' . $id;
            if (!in_array($ref, $refs, true)) {
                $refs[] = $ref;
            }
        }
        return $refs;
    }

    /** @return array<string,list<string>> */
    private static function collectSkillEvidenceRefs(RecommendationInput $input): array
    {
        $refs = [];
        foreach ($input->evidenceReferences() as $reference) {
            $type = is_string($reference['source_type'] ?? null) ? trim((string) $reference['source_type']) : '';
            $id = is_string($reference['source_id'] ?? null) ? trim((string) $reference['source_id']) : '';
            $safeValue = is_array($reference['safe_value'] ?? null) ? $reference['safe_value'] : [];
            $code = $safeValue['code'] ?? null;
            if ($type !== 'skill' || $id === '' || !is_string($code) || trim($code) === '') {
                continue;
            }
            $code = self::normalizeCode($code);
            if ($code === '') {
                continue;
            }
            $ref = $type . ':' . $id;
            if (!in_array($ref, $refs[$code] ?? [], true)) {
                $refs[$code][] = $ref;
            }
        }
        return $refs;
    }

    private const ASSESSMENT_FAMILIES = ['holland', 'mbti', 'disc', 'multiple_intelligence'];

    private const FAMILY_DIMENSIONS = [
        'holland' => ['R', 'I', 'A', 'S', 'E', 'C'],
        'mbti' => ['E', 'I', 'S', 'N', 'T', 'F', 'J', 'P'],
        'disc' => ['D', 'I', 'S', 'C'],
        'multiple_intelligence' => ['LOGI', 'LING', 'SPAT', 'MUSIC', 'BODY', 'INTER', 'INTRA', 'NAT'],
    ];

    private const MI_DIMENSION_ALIASES = [
        'logical' => 'LOGI',
        'linguistic' => 'LING',
        'spatial' => 'SPAT',
        'musical' => 'MUSIC',
        'bodily' => 'BODY',
        'kinesthetic' => 'BODY',
        'interpersonal' => 'INTER',
        'intrapersonal' => 'INTRA',
        'naturalist' => 'NAT',
        'nature' => 'NAT',
    ];

    /**
     * Collects family-specific assessment signals. Family resolution uses the
     * allow-listed test type, falling back to the allow-listed test code;
     * unknown tests, unknown dimensions and out-of-range scores never become
     * signals (out-of-range scores are rejected outright, mirroring the flat
     * dimension collector). When several attempts produce the same signal the
     * attempt with the newest submitted_at wins; entries without a timestamp
     * keep the first occurrence.
     *
     * @return array<string,float>
     */
    private static function collectAssessmentSignals(array $payload): array
    {
        $signals = [];
        $timestamps = [];
        $assessments = $payload['assessments'] ?? [];
        if (!is_array($assessments)) {
            return $signals;
        }
        foreach ($assessments as $assessment) {
            if (!is_array($assessment)) {
                continue;
            }
            $family = self::resolveAssessmentFamily(
                $assessment['test_type'] ?? null,
                $assessment['test_code'] ?? null,
            );
            if ($family === null) {
                continue;
            }
            $scores = $assessment['dimension_scores'] ?? $assessment['dimensionScores'] ?? null;
            if (!is_array($scores)) {
                continue;
            }
            $submittedAt = $assessment['submitted_at'] ?? null;
            $submittedAt = is_string($submittedAt) ? trim($submittedAt) : '';
            foreach ($scores as $rawDimension => $rawScore) {
                if (!is_string($rawDimension) || trim($rawDimension) === '' || !is_numeric($rawScore)) {
                    continue;
                }
                $score = (float) $rawScore;
                if ($score < 0.0 || $score > 100.0) {
                    throw new InvalidArgumentException('Learner opportunity profile rejected an out of range assessment signal.');
                }
                $dimension = self::canonicalAssessmentDimension($family, $rawDimension);
                if ($dimension === null) {
                    continue;
                }
                $key = $family . ':' . $dimension;
                $existingTimestamp = $timestamps[$key] ?? null;
                if ($existingTimestamp === null) {
                    $signals[$key] = $score;
                    $timestamps[$key] = $submittedAt;
                    continue;
                }
                if ($submittedAt !== '' && ($existingTimestamp === '' || $submittedAt > $existingTimestamp)) {
                    $signals[$key] = $score;
                    $timestamps[$key] = $submittedAt;
                }
            }
        }
        return $signals;
    }

    private static function resolveAssessmentFamily(mixed $testType, mixed $testCode): ?string
    {
        if (is_string($testType)) {
            $normalized = strtolower(trim($testType));
            if (in_array($normalized, self::ASSESSMENT_FAMILIES, true)) {
                return $normalized;
            }
        }
        if (is_string($testCode)) {
            $normalized = strtolower(trim($testCode));
            foreach (self::ASSESSMENT_FAMILIES as $family) {
                if (str_contains($normalized, $family)) {
                    return $family;
                }
            }
        }
        return null;
    }

    private static function canonicalAssessmentDimension(string $family, string $rawDimension): ?string
    {
        if ($family === 'multiple_intelligence') {
            $normalized = self::normalizeCode($rawDimension);
            $mapped = self::MI_DIMENSION_ALIASES[$normalized] ?? null;
            if ($mapped !== null) {
                return $mapped;
            }
            $upper = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $normalized));
            return in_array($upper, self::FAMILY_DIMENSIONS[$family], true) ? $upper : null;
        }

        $upper = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $rawDimension));
        return in_array($upper, self::FAMILY_DIMENSIONS[$family], true) ? $upper : null;
    }

    public static function normalizeCode(string $raw): string
    {
        $lower = mb_strtolower(trim($raw), 'UTF-8');
        $ascii = strtr($lower, self::DIACRITICS);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '_', $ascii);
        return trim($slug, '_');
    }
}
