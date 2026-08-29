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
        $jobHash = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
        $providerFailed = false;
        if ($callback !== null) {
            try {
                $modelOutput = $callback($normalized, $candidateProjections);
                $modelVersion = (string) ($modelOutput['model_version'] ?? $this->modelVersion ?? 'gemini-1.5-pro');
                $items = $this->rank($candidates, $normalized, $modelOutput, $refMap);
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
                $providerFailed = true;
            }
        }

        // Check durable LKG cache in DB
        $cached = $this->repository->cachedMatchRanking($enterpriseId, $jobHash);
        if ($cached !== null && is_array($cached['items'] ?? null)) {
            $allowed = array_fill_keys(array_column($candidates, 'student_id'), true);
            $cachedItems = array_values(array_filter($cached['items'], static function (mixed $item) use ($allowed): bool {
                if (!is_array($item)) {
                    return false;
                }
                $studentId = (string) ($item['student_id'] ?? '');
                return $studentId !== '' && isset($allowed[$studentId]);
            }));
            if ($cachedItems !== []) {
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
     * @param list<array<string,mixed>> $candidates
     * @param array<string,mixed> $job
     * @param array<mixed,mixed> $scores
     * @param array<string,array<string,mixed>> $refMap
     * @return list<array<string,mixed>>
     */
    private function rank(array $candidates, array $job, array $scores = [], array $refMap = []): array
    {
        $requiredList = (array) ($job['required_skills'] ?? []);
        $requiredMap = [];
        foreach ($requiredList as $rSkill) {
            $requiredMap[mb_strtolower(trim((string) $rSkill))] = (string) $rSkill;
        }

        $scoreMap = [];
        $rawItems = $scores['items'] ?? (isset($scores[0]) ? $scores : []);
        if (is_array($rawItems)) {
            foreach ($rawItems as $rawItem) {
                if (!is_array($rawItem)) {
                    continue;
                }
                $ref = (string) ($rawItem['candidate_ref'] ?? '');
                $sId = (string) ($rawItem['student_id'] ?? $rawItem['studentId'] ?? '');
                if ($ref !== '' && isset($refMap[$ref])) {
                    $sId = (string) ($refMap[$ref]['student_id'] ?? '');
                }
                if ($sId !== '') {
                    $scoreVal = $rawItem['match_score'] ?? $rawItem['matchScore'] ?? $rawItem['score'] ?? null;
                    $reasons = (array) ($rawItem['reason_codes'] ?? $rawItem['reasonCodes'] ?? []);
                    $scoreMap[$sId] = [
                        'score' => is_numeric($scoreVal) ? round(max(0.0, min(100.0, (float) $scoreVal)), 2) : null,
                        'reason_codes' => array_values(array_filter($reasons, 'is_string')),
                    ];
                }
            }
        }
        foreach ($scores as $k => $v) {
            if (is_string($k) && !is_numeric($k) && $k !== 'items' && $k !== 'model_version') {
                $sId = $k;
                if (isset($refMap[$k])) {
                    $sId = (string) ($refMap[$k]['student_id'] ?? '');
                }
                if ($sId !== '' && !isset($scoreMap[$sId])) {
                    $scoreVal = is_numeric($v) ? (float) $v : (is_array($v) ? ($v['match_score'] ?? $v['score'] ?? null) : null);
                    $reasons = is_array($v) && isset($v['reason_codes']) ? (array) $v['reason_codes'] : [];
                    $scoreMap[$sId] = [
                        'score' => is_numeric($scoreVal) ? round(max(0.0, min(100.0, (float) $scoreVal)), 2) : null,
                        'reason_codes' => array_values(array_filter($reasons, 'is_string')),
                    ];
                }
            }
        }

        $nowIso = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
        $allowedReasonCodes = [
            'verified_skill_match',
            'partial_skill_match',
            'skill_gap',
            'strong_verified_level',
        ];

        $items = [];
        foreach ($candidates as $candidate) {
            $studentId = (string) ($candidate['student_id'] ?? '');
            $matchedSkills = [];
            $matchedLowers = [];
            $evidence = [];
            $hasStrongLevel = false;

            foreach ((array) ($candidate['skills'] ?? []) as $skill) {
                $name = (string) ($skill['name'] ?? '');
                $low = mb_strtolower(trim($name));
                if (isset($requiredMap[$low])) {
                    $matchedSkills[] = $requiredMap[$low];
                    $matchedLowers[$low] = true;
                    $levelScore = (float) ($skill['level_score'] ?? 0.0);
                    if ($levelScore >= 85.0) {
                        $hasStrongLevel = true;
                    }
                    $evidence[] = [
                        'source_type' => 'verified_skill',
                        'source_id' => (string) ($skill['skill_id'] ?? ''),
                        'observed_at' => $nowIso,
                        'safe_value' => ['skill' => $name, 'level_score' => $levelScore],
                    ];
                }
            }

            $skillGaps = [];
            foreach ($requiredMap as $low => $orig) {
                if (!isset($matchedLowers[$low])) {
                    $skillGaps[] = $orig;
                }
            }

            $derivedReasons = [];
            if ($matchedSkills !== []) {
                $derivedReasons[] = 'verified_skill_match';
            }
            if ($skillGaps !== []) {
                $derivedReasons[] = 'skill_gap';
            }
            if ($hasStrongLevel) {
                $derivedReasons[] = 'strong_verified_level';
            }
            if ($matchedSkills !== [] && $skillGaps !== []) {
                $derivedReasons[] = 'partial_skill_match';
            }

            $modelReasons = $scoreMap[$studentId]['reason_codes'] ?? [];
            $filteredModelReasons = array_values(array_filter($modelReasons, static fn($r) => in_array($r, $allowedReasonCodes, true)));

            $combinedReasons = array_values(array_unique(array_merge($filteredModelReasons, $derivedReasons)));

            $score = $scoreMap[$studentId]['score'] ?? null;
            if ($score === null) {
                // If model did not rank this candidate, compute level score average
                $score = count($requiredMap) === 0 ? 0.0 : round(max(0.0, min(100.0, (count($matchedSkills) / count($requiredMap)) * 100.0)), 2);
            }

            $items[] = [
                'student_id' => $studentId,
                'display_name' => (string) ($candidate['display_name'] ?? 'Ứng viên'),
                'match_score' => $score,
                'matched_skills' => $matchedSkills,
                'skill_gaps' => $skillGaps,
                'reason_codes' => $combinedReasons,
                'evidence' => $evidence,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $scoreDiff = ((float) $b['match_score']) <=> ((float) $a['match_score']);
            return $scoreDiff !== 0 ? $scoreDiff : strcmp((string) $a['student_id'], (string) $b['student_id']);
        });

        return $items;
    }
}
