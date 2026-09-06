<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

use DateTimeImmutable;
use InvalidArgumentException;
use TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource;

/**
 * Validated canonical opportunity candidate built from database evidence.
 * Titles, providers, URLs, deadlines and capacity always originate from
 * server-owned records; the value object rejects protected traits and
 * prose-as-skill-codes before anything can reach a model prompt.
 */
final class OpportunityCandidate
{
    private const EDUCATION_BANDS = ['middle', 'high', 'college'];

    private const MAX_CODE_LENGTH = 64;

    private const SENTENCE_PUNCTUATION = ['.', ',', ';', ':', '!', '?', '(', ')', '"', "'"];

    private readonly string $catalogId;

    private readonly string $catalogType;

    private readonly string $title;

    private readonly string $providerName;

    /**
     * Canonical enterprise identity for internship job candidates. Empty for
     * legacy catalog candidates that do not carry an enterprise record.
     */
    private readonly string $enterpriseId;

    private readonly string $canonicalUrl;

    private readonly string $summary;

    private readonly string $category;

    private readonly string $location;

    private readonly string $difficulty;

    /** @var list<array{code:string,minimum_score:int,label:string}> */
    private readonly array $requiredSkills;

    /**
     * Sanitized, structured recruitment requirement text for internship job
     * candidates. Always database-owned content; never inferred.
     *
     * @var list<string>
     */
    private readonly array $requirements;

    /** @var list<array{code:string,label:string}> */
    private readonly array $learningOutcomes;

    /** @var list<string> */
    private readonly array $educationBands;

    private readonly ?DateTimeImmutable $deadline;

    /** @var array{capacity:?int,enrolled:?int,remaining:?int} */
    private readonly array $availability;

    private readonly ?string $status;

    private readonly ?string $publishStatus;

    /** @var list<string> */
    private readonly array $evidenceRefs;

    private function __construct(
        string $catalogId,
        string $catalogType,
        string $title,
        string $providerName,
        string $enterpriseId,
        string $canonicalUrl,
        string $summary,
        string $category,
        string $location,
        string $difficulty,
        array $requiredSkills,
        array $requirements,
        array $learningOutcomes,
        array $educationBands,
        ?DateTimeImmutable $deadline,
        array $availability,
        ?string $status,
        ?string $publishStatus,
        array $evidenceRefs,
    ) {
        $this->catalogId = $catalogId;
        $this->catalogType = $catalogType;
        $this->title = $title;
        $this->providerName = $providerName;
        $this->enterpriseId = $enterpriseId;
        $this->canonicalUrl = $canonicalUrl;
        $this->summary = $summary;
        $this->category = $category;
        $this->location = $location;
        $this->difficulty = $difficulty;
        $this->requiredSkills = $requiredSkills;
        $this->requirements = $requirements;
        $this->learningOutcomes = $learningOutcomes;
        $this->educationBands = $educationBands;
        $this->deadline = $deadline;
        $this->availability = $availability;
        $this->status = $status;
        $this->publishStatus = $publishStatus;
        $this->evidenceRefs = $evidenceRefs;
    }

    public static function fromEvidence(array $evidence): self
    {
        $safeValue = is_array($evidence['safe_value'] ?? null) ? $evidence['safe_value'] : $evidence;
        if (DatabaseCatalogSource::containsProtectedTraits($safeValue)) {
            throw new InvalidArgumentException('Opportunity candidate rejected protected trait data.');
        }

        $rawCatalogId = $safeValue['catalog_id'] ?? $evidence['source_id'] ?? $evidence['catalog_id'] ?? null;
        $catalogId = is_scalar($rawCatalogId) ? trim((string) $rawCatalogId) : '';
        if ($catalogId === '' || strlen($catalogId) > 128) {
            throw new InvalidArgumentException('Opportunity candidate requires a catalog id of at most 128 characters.');
        }

        $rawType = $safeValue['item_type'] ?? $safeValue['opportunity_type'] ?? $evidence['source_type'] ?? null;
        $catalogType = is_string($rawType) ? LearnerOpportunityProfile::normalizeCode($rawType) : '';
        if ($catalogType === '') {
            throw new InvalidArgumentException('Opportunity candidate requires a canonical catalog type.');
        }
        if (strlen($catalogType) > 64) {
            throw new InvalidArgumentException('Opportunity candidate requires a canonical catalog type.');
        }

        $rawTitle = $safeValue['title'] ?? null;
        $title = is_scalar($rawTitle) ? trim((string) $rawTitle) : '';
        if ($title === '' || mb_strlen($title, 'UTF-8') > 255) {
            throw new InvalidArgumentException('Opportunity candidate requires a non-empty title of at most 255 characters.');
        }

        $providerName = self::displayString($safeValue['provider_name'] ?? null, 255);
        $canonicalUrl = self::normalizeUrl($safeValue['url'] ?? null);
        $summary = self::displayString($safeValue['summary'] ?? $safeValue['description'] ?? null, 1000);
        $category = self::displayString($safeValue['category'] ?? null, 64);
        $location = self::displayString($safeValue['location'] ?? null, 255);
        $difficulty = self::displayString($safeValue['difficulty'] ?? null, 32);

        return new self(
            $catalogId,
            $catalogType,
            $title,
            $providerName,
            self::optionalEnterpriseId($safeValue['enterprise_id'] ?? null),
            $canonicalUrl,
            $summary,
            $category,
            $location,
            $difficulty,
            self::collectRequiredSkills($safeValue['required_skills'] ?? []),
            self::collectRequirements($safeValue['requirements'] ?? []),
            self::collectLearningOutcomes($safeValue['learning_outcomes'] ?? []),
            self::collectEducationBands($safeValue['education_bands'] ?? []),
            self::parseDeadline($safeValue['deadline_at'] ?? null),
            self::collectAvailability($safeValue['availability'] ?? null),
            self::optionalCode($safeValue['status'] ?? null),
            self::optionalCode($safeValue['publish_status'] ?? null),
            self::collectEvidenceRefs($evidence, $catalogId),
        );
    }

    public function catalogId(): string
    {
        return $this->catalogId;
    }

    public function catalogType(): string
    {
        return $this->catalogType;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function providerName(): string
    {
        return $this->providerName;
    }

    public function enterpriseId(): string
    {
        return $this->enterpriseId;
    }

    /** @return list<string> */
    public function requirements(): array
    {
        return $this->requirements;
    }

    public function canonicalUrl(): string
    {
        return $this->canonicalUrl;
    }

    /** @return list<array{code:string,minimum_score:int,label:string}> */
    public function requiredSkills(): array
    {
        return $this->requiredSkills;
    }

    /** @return list<array{code:string,label:string}> */
    public function learningOutcomes(): array
    {
        return $this->learningOutcomes;
    }

    /** @return list<string> */
    public function educationBands(): array
    {
        return $this->educationBands;
    }

    public function deadline(): ?DateTimeImmutable
    {
        return $this->deadline;
    }

    /** @return array{capacity:?int,enrolled:?int,remaining:?int} */
    public function availability(): array
    {
        return $this->availability;
    }

    public function isEligibleFor(LearnerOpportunityProfile $profile, DateTimeImmutable $now): bool
    {
        if ($this->status === null && $this->publishStatus === null) {
            return false;
        }
        if ($this->publishStatus !== null && $this->publishStatus !== 'published') {
            return false;
        }
        if ($this->status !== null && $this->status !== 'active') {
            return false;
        }
        if ($this->deadline !== null && $this->deadline <= $now) {
            return false;
        }
        if (($this->availability['remaining'] ?? null) !== null && $this->availability['remaining'] <= 0) {
            return false;
        }
        if ($this->educationBands !== []) {
            $band = $profile->educationBand();
            if ($band === null || !in_array($band, $this->educationBands, true)) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string,mixed> */
    public function providerPayload(): array
    {
        return [
            'catalog_id' => $this->catalogId,
            'catalog_type' => $this->catalogType,
            'title' => $this->title,
            'provider_name' => $this->providerName,
            'enterprise_id' => $this->enterpriseId,
            'summary' => $this->summary,
            'category' => $this->category,
            'location' => $this->location,
            'difficulty' => $this->difficulty,
            'required_skills' => $this->requiredSkills,
            'requirements' => $this->requirements,
            'learning_outcomes' => $this->learningOutcomes,
            'education_bands' => $this->educationBands,
            'deadline_at' => $this->deadline?->format('Y-m-d\\TH:i:s.uP'),
            'availability' => $this->availability,
            'status' => $this->status,
            'url' => $this->canonicalUrl,
            'evidence_refs' => $this->evidenceRefs,
        ];
    }

    private static function displayString(mixed $raw, int $maxLength): string
    {
        if (!is_scalar($raw)) {
            return '';
        }
        $value = trim((string) $raw);
        return mb_strlen($value, 'UTF-8') > $maxLength ? mb_substr($value, 0, $maxLength, 'UTF-8') : $value;
    }

    /**
     * Validates an optional canonical enterprise identifier. Legacy evidence
     * rows without an enterprise record stay valid; a malformed identifier is
     * rejected so a half-owned candidate can never reach a scorer.
     */
    private static function optionalEnterpriseId(mixed $raw): string
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return '';
        }
        if (!is_string($raw)) {
            throw new InvalidArgumentException('Opportunity candidate requires a string enterprise id.');
        }
        $enterpriseId = trim($raw);
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/', $enterpriseId) !== 1) {
            throw new InvalidArgumentException('Opportunity candidate rejected a malformed enterprise id.');
        }
        return $enterpriseId;
    }

    /**
     * Collects sanitized recruitment requirement text. Entries must already
     * be plain strings (the internship source sanitizes requirementsJson);
     * anything else is rejected rather than coerced.
     *
     * @return list<string>
     */
    private static function collectRequirements(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new InvalidArgumentException('Opportunity candidate requires requirements to be a list of strings.');
        }
        $requirements = [];
        foreach ($raw as $entry) {
            if (!is_string($entry)) {
                throw new InvalidArgumentException('Opportunity candidate rejected a non-string requirement entry.');
            }
            $value = trim(preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $entry) ?? '');
            $value = (string) preg_replace('/\s+/u', ' ', $value);
            if ($value === '') {
                continue;
            }
            if (mb_strlen($value, 'UTF-8') > 500) {
                $value = mb_substr($value, 0, 500, 'UTF-8');
            }
            $requirements[] = $value;
            if (count($requirements) >= 20) {
                break;
            }
        }
        return $requirements;
    }

    private static function normalizeUrl(mixed $raw): string
    {
        if (!is_string($raw) || trim($raw) === '') {
            throw new InvalidArgumentException('Opportunity candidate requires a canonical URL.');
        }
        $url = trim($raw);
        if (preg_match('/[\s\x00-\x1F]/', $url) === 1) {
            throw new InvalidArgumentException('Opportunity candidate rejected a URL with whitespace or control characters.');
        }
        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//') || str_contains($url, '\\')) {
                throw new InvalidArgumentException('Opportunity candidate rejected a protocol-relative or malformed internal URL.');
            }
            return $url;
        }
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?: ''));
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        if ($scheme === '' || $host === '') {
            throw new InvalidArgumentException('Opportunity candidate rejected a URL without scheme and host.');
        }
        if ($scheme !== 'https') {
            throw new InvalidArgumentException('Opportunity candidate rejected a non-https external URL.');
        }
        return $url;
    }

    /** @return list<array{code:string,minimum_score:int,label:string}> */
    private static function collectRequiredSkills(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $skills = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $code = self::canonicalCode($entry['code'] ?? null, 'required skill');
            $minimum = $entry['minimum_score'] ?? 0;
            if (!is_numeric($minimum)) {
                throw new InvalidArgumentException('Opportunity candidate requires a numeric required-skill minimum score.');
            }
            $minimumScore = (int) round((float) $minimum);
            if ($minimumScore < 0 || $minimumScore > 100) {
                throw new InvalidArgumentException('Opportunity candidate rejected an out of range required-skill minimum score.');
            }
            $label = self::displayString($entry['label'] ?? $entry['code'] ?? null, 255);
            if (isset($skills[$code])) {
                throw new InvalidArgumentException('Opportunity candidate rejected a duplicate required-skill code.');
            }
            $skills[$code] = ['code' => $code, 'minimum_score' => $minimumScore, 'label' => $label];
        }
        return array_values($skills);
    }

    /** @return list<array{code:string,label:string}> */
    private static function collectLearningOutcomes(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $outcomes = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $code = self::canonicalCode($entry['code'] ?? null, 'learning outcome');
            $label = self::displayString($entry['label'] ?? $entry['code'] ?? null, 255);
            if (isset($outcomes[$code])) {
                throw new InvalidArgumentException('Opportunity candidate rejected a duplicate learning-outcome code.');
            }
            $outcomes[$code] = ['code' => $code, 'label' => $label];
        }
        return array_values($outcomes);
    }

    /** @return list<string> */
    private static function collectEducationBands(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $bands = [];
        foreach ($raw as $band) {
            if (!is_string($band) || trim($band) === '') {
                continue;
            }
            $canonical = strtolower(trim($band));
            if (!in_array($canonical, self::EDUCATION_BANDS, true)) {
                throw new InvalidArgumentException('Opportunity candidate rejected an unknown education band.');
            }
            if (!in_array($canonical, $bands, true)) {
                $bands[] = $canonical;
            }
        }
        return $bands;
    }

    private static function parseDeadline(mixed $raw): ?DateTimeImmutable
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($raw);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Opportunity candidate rejected a malformed deadline.');
        }
    }

    /** @return array{capacity:?int,enrolled:?int,remaining:?int} */
    private static function collectAvailability(mixed $raw): array
    {
        if (!is_array($raw)) {
            return ['capacity' => null, 'enrolled' => null, 'remaining' => null];
        }
        return [
            'capacity' => isset($raw['capacity']) && is_numeric($raw['capacity']) ? (int) $raw['capacity'] : null,
            'enrolled' => isset($raw['enrolled']) && is_numeric($raw['enrolled']) ? (int) $raw['enrolled'] : null,
            'remaining' => isset($raw['remaining']) && is_numeric($raw['remaining']) ? (int) $raw['remaining'] : null,
        ];
    }

    private static function optionalCode(mixed $raw): ?string
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        return strtolower(trim($raw));
    }

    private static function canonicalCode(mixed $raw, string $context): string
    {
        if (!is_string($raw) || trim($raw) === '') {
            throw new InvalidArgumentException("Opportunity candidate requires a non-empty {$context} code.");
        }
        $trimmed = trim($raw);
        if (mb_strlen($trimmed, 'UTF-8') > self::MAX_CODE_LENGTH) {
            throw new InvalidArgumentException("Opportunity candidate rejected {$context} prose longer than " . self::MAX_CODE_LENGTH . ' characters.');
        }
        if (array_reduce(
            self::SENTENCE_PUNCTUATION,
            static fn (bool $found, string $needle): bool => $found || str_contains($trimmed, $needle),
            false,
        )) {
            throw new InvalidArgumentException("Opportunity candidate rejected {$context} prose with sentence punctuation.");
        }
        $code = LearnerOpportunityProfile::normalizeCode($trimmed);
        if ($code === '' || preg_match('/^[a-z0-9]+(_[a-z0-9]+)*$/', $code) !== 1) {
            throw new InvalidArgumentException("Opportunity candidate rejected a non-canonical {$context} code.");
        }
        return $code;
    }

    /** @return list<string> */
    private static function collectEvidenceRefs(array $evidence, string $catalogId): array
    {
        $refs = [];
        $type = is_string($evidence['source_type'] ?? null) ? trim((string) $evidence['source_type']) : '';
        $id = is_string($evidence['source_id'] ?? null) ? trim((string) $evidence['source_id']) : '';
        if ($type !== '' && $id !== '') {
            $refs[] = $type . ':' . $id;
        }
        $selfRef = 'catalog:' . $catalogId;
        if (!in_array($selfRef, $refs, true)) {
            $refs[] = $selfRef;
        }
        return $refs;
    }
}
