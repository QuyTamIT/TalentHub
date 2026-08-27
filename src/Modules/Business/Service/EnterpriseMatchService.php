<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Service;

use DateTimeImmutable;
use DateTimeZone;
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
        ?callable $provider = null
    ) {
        $this->provider = $provider;
    }

    /**
     * @param array<string,mixed>|string $job
     * @param callable|null $provider function(array $job, array $candidates): array
     * @return array{state:string,job:array<string,mixed>,items:list<array<string,mixed>>,generated_at:string}
     */
    public function match(string $enterpriseId, array|string $job, ?callable $provider = null): array
    {
        $normalized = $this->normalizeJobRequirements($job);
        $jobHash = hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $candidates = $this->repository->matchCandidates($enterpriseId, $normalized['required_skills']);
        $generatedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');

        if ($candidates === []) {
            return ['state' => 'no_candidates', 'job' => $normalized, 'items' => [], 'generated_at' => $generatedAt];
        }

        $callback = $provider ?? $this->provider;
        $providerFailed = false;
        if ($callback !== null) {
            try {
                $scores = $callback($normalized, $candidates);
                $items = $this->rank($candidates, $normalized, $this->normalizeScores($scores));
                $this->repository->storeMatchRanking($enterpriseId, $jobHash, $items);
                return ['state' => 'ready_model', 'job' => $normalized, 'items' => $items, 'generated_at' => $generatedAt];
            } catch (\Throwable) {
                // Provider errors never erase a previously computed ranking.
                $providerFailed = true;
            }
        }

        $cached = $this->repository->cachedMatchRanking($enterpriseId, $jobHash);
        if ($cached !== []) {
            $allowed = array_fill_keys(array_column($candidates, 'student_id'), true);
            $cached = array_values(array_filter($cached, static function (mixed $item) use ($allowed): bool {
                if (!is_array($item)) return false;
                $studentId = (string) ($item['student_id'] ?? '');
                return $studentId !== '' && isset($allowed[$studentId]);
            }));
            if ($cached !== []) {
                return [
                    'state' => $providerFailed ? 'provider_outage_cached' : 'cached_ranking',
                    'job' => $normalized,
                    'items' => $cached,
                    'generated_at' => $generatedAt,
                ];
            }
        }

        return [
            'state' => $providerFailed ? 'provider_outage_deterministic' : 'ready_rule',
            'job' => $normalized,
            'items' => $this->rank($candidates, $normalized),
            'generated_at' => $generatedAt,
        ];
    }

    /** @param array<string,mixed>|string $job @return array<string,mixed> */
    public function normalizeJobRequirements(array|string $job): array
    {
        if (is_string($job)) {
            $job = ['description' => $job];
        }
        $title = $this->redactProtectedText(trim((string) ($job['title'] ?? $job['name'] ?? '')));
        $description = $this->redactProtectedText(trim((string) ($job['description'] ?? $job['summary'] ?? '')));
        $rawSkills = $job['required_skills'] ?? $job['requiredSkills'] ?? $job['skills'] ?? $job['requirements'] ?? [];
        if (is_string($rawSkills)) {
            $rawSkills = preg_split('/[,;|\n]+/', $rawSkills) ?: [];
        }
        $skills = [];
        foreach ((array) $rawSkills as $key => $skill) {
            if (!is_int($key) && is_numeric($skill)) {
                $skill = $key;
            }
            $value = trim((string) $skill);
            if ($value !== '' && !$this->isProtectedTerm($value)) {
                $skills[mb_strtolower($value)] = $value;
            }
        }
        ksort($skills, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'title' => $title,
            'description' => $description,
            'required_skills' => array_values($skills),
            'schema_version' => 'enterprise-match-1.0.0',
        ];
    }

    /** @param array<string,mixed>|string $job @return array<string,mixed> */
    public function normalizeJob(array|string $job): array
    {
        return $this->normalizeJobRequirements($job);
    }

    private function redactProtectedText(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        $terms = 'age|gender|sex|male|female|women|men|race|ethnicity|religion|disability|health|marital|pregnan(?:t|cy)|nationality|date[ -]?of[ -]?birth|ngày[ -]?sinh|giới[ -]?tính|dân[ -]?tộc|tôn[ -]?giáo|khuyết[ -]?tật|nam|nữ';
        return trim((string) preg_replace('/\b(?:' . $terms . ')\b\s*[:=]?\s*[^,;|\n]*/iu', '[redacted]', $value));
    }

    private function isProtectedTerm(string $value): bool
    {
        return preg_match('/\b(?:age|gender|sex|male|female|women|men|race|ethnicity|religion|disability|health|marital|pregnant|pregnancy|nationality|dob|birthdate|ngày[ -]?sinh|giới[ -]?tính|dân[ -]?tộc|tôn[ -]?giáo|khuyết[ -]?tật|nam|nữ)\b/iu', $value) === 1;
    }

    /** @param list<array<string,mixed>> $candidates @param array<string,mixed> $job @param array<mixed,mixed> $scores @return list<array<string,mixed>> */
    private function rank(array $candidates, array $job, array $scores = []): array
    {
        $required = array_fill_keys(array_map(static fn($v): string => mb_strtolower(trim((string) $v)), (array) ($job['required_skills'] ?? [])), true);
        $items = [];
        foreach ($candidates as $candidate) {
            $matched = [];
            $evidence = [];
            foreach ((array) ($candidate['skills'] ?? []) as $skill) {
                $name = (string) ($skill['name'] ?? '');
                if (isset($required[mb_strtolower(trim($name))])) {
                    $matched[] = $name;
                    $evidence[] = [
                        'source_type' => 'verified_skill',
                        'source_id' => (string) ($skill['skill_id'] ?? ''),
                        'observed_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
                        'safe_value' => ['skill' => $name, 'level_score' => (float) ($skill['level_score'] ?? 0)],
                    ];
                }
            }
            $studentId = (string) ($candidate['student_id'] ?? '');
            $score = $this->scoreFor($scores, $studentId, $required, $matched, $candidate);
            $items[] = [
                'student_id' => $studentId,
                'display_name' => (string) ($candidate['display_name'] ?? 'Ứng viên'),
                'match_score' => $score,
                'reason_codes' => array_values(array_unique(array_merge($matched !== [] ? ['skill_match'] : ['skill_gap'], $matched !== [] ? ['verified_skill'] : []))),
                'evidence' => $evidence,
            ];
        }
        usort($items, static function (array $a, array $b): int {
            $score = ((float) $b['match_score']) <=> ((float) $a['match_score']);
            return $score !== 0 ? $score : strcmp((string) $a['student_id'], (string) $b['student_id']);
        });
        return $items;
    }

    private function scoreFor(array $scores, string $studentId, array $required, array $matched, array $candidate): float
    {
        $provided = $scores[$studentId] ?? null;
        if (is_array($provided)) {
            $provided = $provided['match_score'] ?? $provided['score'] ?? null;
        }
        if (is_numeric($provided)) {
            return round(max(0.0, min(100.0, (float) $provided)), 2);
        }
        if ($required === []) {
            return 0.0;
        }
        $levels = [];
        foreach ((array) ($candidate['skills'] ?? []) as $skill) {
            if (in_array((string) ($skill['name'] ?? ''), $matched, true)) {
                $levels[] = (float) ($skill['level_score'] ?? 0);
            }
        }
        return round(max(0.0, min(100.0, count($levels) === 0 ? 0.0 : array_sum($levels) / count($required))), 2);
    }

    /** @return array<string,mixed> */
    private function normalizeScores(mixed $scores): array
    {
        if (!is_array($scores)) {
            return [];
        }
        if (isset($scores['items']) && is_array($scores['items'])) {
            $scores = $scores['items'];
        }
        $normalized = [];
        foreach ($scores as $key => $value) {
            if (is_string($key) && !is_numeric($key)) {
                $normalized[$key] = $value;
                continue;
            }
            if (is_array($value)) {
                $id = (string) ($value['student_id'] ?? $value['studentId'] ?? '');
                if ($id !== '') {
                    $normalized[$id] = $value['match_score'] ?? $value['matchScore'] ?? $value['score'] ?? null;
                }
            }
        }
        return $normalized;
    }
}
