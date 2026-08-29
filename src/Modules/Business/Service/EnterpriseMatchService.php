<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Service;

use DateTimeImmutable;
use DateTimeZone;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;

/**
 * Privacy-first enterprise matching pipeline.
 * A provider is deliberately injected as a callable so the service remains
 * deterministic and testable when the AI provider is unavailable.
 */
final class EnterpriseMatchService
{
    /** @var callable|null */
    private $provider;

    public function __construct(
        private readonly EnterpriseTalentRepository $repository,
        ?callable $provider = null,
        private readonly ?string $modelVersion = 'gemini-1.5-pro'
    ) {
        $this->provider = $provider;
    }

    /**
     * @param array<string,mixed>|string $job
     * @param callable|null $provider function(array $job, array $candidates): array
     * @return array{state:string,job:array<string,mixed>,items:list<array<string,mixed>>,generated_at:string,analysis_origin?:string,freshness_status?:string,model_version?:string,last_known_good?:bool}
     */
    public function match(string $enterpriseId, array|string $job, ?callable $provider = null): array
    {
        $normalized = $this->normalizeJobRequirements($job);
        $jobHash = hash('sha256', json_encode([
            'job_id' => $normalized['id'],
            'required_skills' => $normalized['required_skills'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $candidates = $this->repository->matchCandidates($enterpriseId, $normalized['required_skills']);
        $generatedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');

        if ($candidates === []) {
            return [
                'state' => 'no_candidates',
                'job' => $normalized,
                'items' => [],
                'generated_at' => $generatedAt,
            ];
        }

        // Build opaque candidate projections for provider
        $candidateProjections = [];
        $refMap = [];
        $i = 1;
        foreach ($candidates as $cand) {
            $ref = 'candidate_' . $i++;
            $refMap[$ref] = $cand;
            $verifiedSkills = [];
            foreach ((array) ($cand['skills'] ?? []) as $sk) {
                $verifiedSkills[] = [
                    'name' => (string) ($sk['name'] ?? ''),
                    'level_score' => (float) ($sk['level_score'] ?? 0.0),
                ];
            }
            $candidateProjections[] = [
                'candidate_ref' => $ref,
                'verified_skills' => $verifiedSkills,
            ];
        }

        $callback = $provider ?? $this->provider;
        if ($callback !== null) {
            try {
                $modelOutput = $callback($normalized, $candidateProjections);
                $modelVersion = trim((string) ($modelOutput['model_version'] ?? $this->modelVersion ?? ''));
                if ($modelVersion === '') {
                    throw new \RuntimeException('Enterprise AI model version is missing.');
                }
                $items = $this->rank($normalized, $modelOutput, $refMap);
                $rankingPayload = [
                    'schema_version' => 'enterprise-match-2.0.0',
                    'analysis_origin' => 'model',
                    'model_version' => $modelVersion,
                    'generated_at' => $generatedAt,
                    'items' => $items,
                ];
                $this->repository->storeMatchRanking($enterpriseId, $jobHash, $rankingPayload);
                return [
                    'state' => 'ready_model',
                    'analysis_origin' => 'model',
                    'freshness_status' => 'current',
                    'model_version' => $modelVersion,
                    'job' => $normalized,
                    'items' => $items,
                    'generated_at' => $generatedAt,
                ];
            } catch (\Throwable) {
                // The model, cache, and persistence paths must not fall back to deterministic ranking.
            }
        }

        // Check durable LKG cache in DB
        $cached = $this->repository->cachedMatchRanking($enterpriseId, $jobHash);
        if ($cached !== null && $this->isCurrentLkg($cached)) {
            $cachedItems = $this->validatedCachedItems($cached['items'] ?? null, $candidates);
            if ($cachedItems !== null) {
                return [
                    'state' => 'stale_model',
                    'analysis_origin' => 'model',
                    'freshness_status' => 'stale',
                    'last_known_good' => true,
                    'model_version' => (string) ($cached['model_version'] ?? $this->modelVersion ?? 'gemini-1.5-pro'),
                    'job' => $normalized,
                    'items' => $cachedItems,
                    'generated_at' => (string) ($cached['generated_at'] ?? $generatedAt),
                ];
            }
        }

        return [
            'state' => 'provider_unavailable',
            'analysis_origin' => null,
            'freshness_status' => 'unavailable',
            'model_version' => $this->modelVersion,
            'error_code' => 'provider_unavailable',
            'job' => $normalized,
            'items' => [],
            'generated_at' => $generatedAt,
        ];
    }

    /**
     * @param array<string,mixed>|string $job
     * @return array<string,mixed>
     */
    public function normalizeJobRequirements(array|string $job): array
    {
        if (is_string($job)) {
            $job = ['description' => $job];
        }
        $title = trim((string) ($job['title'] ?? $job['name'] ?? ''));
        $description = trim((string) ($job['description'] ?? $job['summary'] ?? ''));
        $rawSkills = $job['required_skills'] ?? $job['requiredSkills'] ?? $job['skills'] ?? $job['requirements'] ?? [];
        if (is_string($rawSkills)) {
            $rawSkills = preg_split('/[,;|\n]+/', $rawSkills) ?: [];
        }

        if ($this->containsProtectedTrait($title) || $this->containsProtectedTrait($description)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Yêu cầu tuyển dụng chứa thuộc tính được bảo vệ (protected traits).');
        }

        $skills = [];
        foreach ((array) $rawSkills as $key => $skill) {
            if (!is_int($key) && is_numeric($skill)) {
                $skill = $key;
            }
            $value = trim((string) $skill);
            if ($value === '') {
                continue;
            }
            if ($this->containsProtectedTrait($value)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Kỹ năng yêu cầu chứa thuộc tính được bảo vệ (protected traits).');
            }
            $skills[mb_strtolower($value)] = $value;
        }
        ksort($skills, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'id' => trim((string) ($job['id'] ?? $job['job_id'] ?? '')),
            'title' => $title,
            'description' => $description,
            'required_skills' => array_values($skills),
            'schema_version' => 'enterprise-match-2.0.0',
        ];
    }

    /**
     * @param array<string,mixed>|string $job
     * @return array<string,mixed>
     */
    public function normalizeJob(array|string $job): array
    {
        return $this->normalizeJobRequirements($job);
    }

    private function containsProtectedTrait(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $pattern = '/\b(?:age|gender|sex|male|female|women|men|race|ethnicity|religion|disability|health|marital|pregnant|pregnancy|nationality|dob|birthdate|ngày[ -]?sinh|giới[ -]?tính|dân[ -]?tộc|tôn[ -]?giáo|khuyết[ -]?tật|nam|nữ|bệnh|sức[ -]?khỏe)\b/iu';
        return preg_match($pattern, $value) === 1;
    }

    /**
     * The model may choose which eligible opaque candidates to rank, but it never
     * supplies identity, evidence, matched skills, gaps, or computed fallback scores.
     *
     * @param array<string,mixed> $job
     * @param array<string,mixed> $modelOutput
     * @param array<string,array<string,mixed>> $refMap
     * @return list<array<string,mixed>>
     */
    private function rank(array $job, array $modelOutput, array $refMap): array
    {
        $rawItems = $modelOutput['items'] ?? null;
        if (!is_array($rawItems) || !array_is_list($rawItems)) {
            throw new \RuntimeException('Enterprise AI response items are invalid.');
        }

        $requiredMap = [];
        foreach ((array) ($job['required_skills'] ?? []) as $requiredSkill) {
            $skill = trim((string) $requiredSkill);
            if ($skill !== '') {
                $requiredMap[mb_strtolower($skill)] = $skill;
            }
        }

        $allowedReasonCodes = array_fill_keys([
            'verified_skill_match',
            'partial_skill_match',
            'skill_gap',
            'strong_verified_level',
        ], true);
        $seenRefs = [];
        $items = [];

        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                throw new \RuntimeException('Enterprise AI response item is invalid.');
            }
            $ref = trim((string) ($rawItem['candidate_ref'] ?? ''));
            if ($ref === '' || !isset($refMap[$ref]) || isset($seenRefs[$ref])) {
                throw new \RuntimeException('Enterprise AI candidate reference is invalid.');
            }
            if (!array_key_exists('match_score', $rawItem) || !is_numeric($rawItem['match_score'])) {
                throw new \RuntimeException('Enterprise AI match score is invalid.');
            }
            $score = (float) $rawItem['match_score'];
            if ($score < 0.0 || $score > 100.0) {
                throw new \RuntimeException('Enterprise AI match score is out of range.');
            }
            $reasons = $rawItem['reason_codes'] ?? null;
            if (!is_array($reasons) || !array_is_list($reasons) || array_filter($reasons, static fn(mixed $reason): bool => !is_string($reason) || !isset($allowedReasonCodes[$reason])) !== []) {
                throw new \RuntimeException('Enterprise AI reason codes are invalid.');
            }
            $seenRefs[$ref] = true;
            $candidate = $refMap[$ref];
            $matchedSkills = [];
            $matchedLowers = [];
            $evidence = [];
            $nowIso = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
            foreach ((array) ($candidate['skills'] ?? []) as $skill) {
                $name = trim((string) ($skill['name'] ?? ''));
                $lower = mb_strtolower($name);
                if ($name === '' || !isset($requiredMap[$lower])) {
                    continue;
                }
                $matchedSkills[] = $requiredMap[$lower];
                $matchedLowers[$lower] = true;
                $evidence[] = [
                    'source_type' => 'verified_skill',
                    'source_id' => (string) ($skill['skill_id'] ?? ''),
                    'observed_at' => $nowIso,
                    'safe_value' => [
                        'skill' => $name,
                        'level_score' => (float) ($skill['level_score'] ?? 0.0),
                    ],
                ];
            }
            $skillGaps = [];
            foreach ($requiredMap as $lower => $skill) {
                if (!isset($matchedLowers[$lower])) {
                    $skillGaps[] = $skill;
                }
            }
            $items[] = [
                'student_id' => (string) ($candidate['student_id'] ?? ''),
                'display_name' => (string) ($candidate['display_name'] ?? 'Ứng viên'),
                'match_score' => round($score, 2),
                'matched_skills' => array_values(array_unique($matchedSkills)),
                'skill_gaps' => $skillGaps,
                'reason_codes' => array_values(array_unique($reasons)),
                'evidence' => $evidence,
            ];
        }

        usort($items, static function (array $left, array $right): int {
            $scoreOrder = ((float) $right['match_score']) <=> ((float) $left['match_score']);
            return $scoreOrder !== 0 ? $scoreOrder : strcmp((string) $left['student_id'], (string) $right['student_id']);
        });
        return $items;
    }

    /** @param array<string,mixed> $cached */
    private function isCurrentLkg(array $cached): bool
    {
        if (($cached['analysis_origin'] ?? null) !== 'model' || !is_array($cached['items'] ?? null)) {
            return false;
        }
        $generatedAt = strtotime((string) ($cached['generated_at'] ?? $cached['updated_at'] ?? ''));
        return $generatedAt !== false && (time() - $generatedAt) <= 604800;
    }

    /**
     * @param mixed $rawItems
     * @param list<array<string,mixed>> $candidates
     * @return list<array<string,mixed>>|null
     */
    private function validatedCachedItems(mixed $rawItems, array $candidates): ?array
    {
        if (!is_array($rawItems) || !array_is_list($rawItems)) {
            return null;
        }
        $eligible = [];
        foreach ($candidates as $candidate) {
            $eligible[(string) ($candidate['student_id'] ?? '')] = true;
        }
        $allowedReasonCodes = array_fill_keys(['verified_skill_match', 'partial_skill_match', 'skill_gap', 'strong_verified_level'], true);
        $seenStudents = [];
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                return null;
            }
            $studentId = (string) ($item['student_id'] ?? '');
            $score = $item['match_score'] ?? null;
            $reasons = $item['reason_codes'] ?? null;
            if ($studentId === '' || !isset($eligible[$studentId]) || isset($seenStudents[$studentId]) || !is_numeric($score) || (float) $score < 0.0 || (float) $score > 100.0 || !is_array($reasons) || !array_is_list($reasons) || array_filter($reasons, static fn(mixed $reason): bool => !is_string($reason) || !isset($allowedReasonCodes[$reason])) !== []) {
                return null;
            }
            $seenStudents[$studentId] = true;
        }
        return array_values($rawItems);
    }
}
