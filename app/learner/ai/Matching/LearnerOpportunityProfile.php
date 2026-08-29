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

    /** @var list<string> */
    private readonly array $experienceTags;

    /** @var list<string> */
    private readonly array $evidenceRefs;

    private function __construct(
        ?string $educationBand,
        array $skills,
        array $assessmentDimensions,
        array $experienceTags,
        array $evidenceRefs,
    ) {
        $this->educationBand = $educationBand;
        $this->skills = $skills;
        $this->assessmentDimensions = $assessmentDimensions;
        $this->experienceTags = $experienceTags;
        $this->evidenceRefs = $evidenceRefs;
    }

    public static function fromInput(RecommendationInput $input): self
    {
        $payload = $input->payload();

        return new self(
            self::resolveEducationBand($payload),
            self::collectSkills($payload),
            self::collectAssessmentDimensions($payload),
            self::collectExperienceTags($payload),
            self::collectEvidenceRefs($input),
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

    /** @return list<string> */
    public function experienceTags(): array
    {
        return $this->experienceTags;
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

    public static function normalizeCode(string $raw): string
    {
        $lower = mb_strtolower(trim($raw), 'UTF-8');
        $ascii = strtr($lower, self::DIACRITICS);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '_', $ascii);
        return trim($slug, '_');
    }
}
