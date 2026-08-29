<?php
declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use Closure;
use TalentHub\Modules\School\Repository\SchoolAiAggregateRepository;

final class SchoolAiInsightService
{
    /** @var Closure(string):array<string,mixed> */
    private readonly Closure $schoolResolver;
    /** @var Closure(array<string,mixed>):array<string,mixed>|null */
    private readonly ?Closure $modelExplainer;
    /** @var Closure(string):?array<string,mixed>|null */
    private readonly ?Closure $lastKnownGood;
    /** @var Closure(string,array<string,mixed>):void|null */
    private readonly ?Closure $saveInsight;
    /** @var Closure(string):bool|null */
    private readonly ?Closure $modelAllowed;
    /** @var Closure(string,string):void|null */
    private readonly ?Closure $enqueueRefresh;

    public function __construct(
        private readonly SchoolAiAggregateRepository $aggregates,
        callable $schoolResolver,
        ?callable $modelExplainer = null,
        ?callable $lastKnownGood = null,
        private readonly int $minimumCohort = 5,
        ?callable $saveInsight = null,
        ?callable $modelAllowed = null,
        private readonly ?string $modelVersion = null,
        private readonly bool $synchronousModel = true,
        private readonly int $maxStaleSeconds = 604800,
        ?callable $enqueueRefresh = null,
    ) {
        $this->schoolResolver = Closure::fromCallable($schoolResolver);
        $this->modelExplainer = $modelExplainer ? Closure::fromCallable($modelExplainer) : null;
        $this->lastKnownGood = $lastKnownGood ? Closure::fromCallable($lastKnownGood) : null;
        $this->saveInsight = $saveInsight ? Closure::fromCallable($saveInsight) : null;
        $this->modelAllowed = $modelAllowed ? Closure::fromCallable($modelAllowed) : null;
        $this->enqueueRefresh = $enqueueRefresh ? Closure::fromCallable($enqueueRefresh) : null;
    }

    /** @return array<string,mixed> */
    public function insight(string $userId): array
    {
        $readiness = $this->aggregates->readiness();
        if (!$readiness['ready']) {
            return [
                'capability' => 'school_insight',
                'state' => 'provider_unavailable',
                'status' => 'provider_unavailable',
                'error_code' => $readiness['error_code'] ?? 'ai_schema_unavailable',
                'analysis_origin' => null,
                'explanation' => null,
            ];
        }

        $school = ($this->schoolResolver)($userId);
        $schoolId = trim((string) ($school['id'] ?? ''));
        if ($schoolId === '') {
            throw new \RuntimeException('School tenant is unavailable.');
        }

        $aggregate = $this->aggregates->aggregate($schoolId, $this->minimumCohort);
        $aggregateHash = $this->aggregates->aggregateHash($aggregate);
        $cohortVersion = substr($aggregateHash, 0, 16);

        if ($aggregate['cohorts'] === []) {
            return [
                'capability' => 'school_insight',
                'state' => 'insufficient_data',
                'status' => 'insufficient_data',
                'freshness_status' => 'current',
                'analysis_origin' => null,
                'cohort_version' => $cohortVersion,
                'aggregate' => $aggregate,
                'explanation' => null,
                'evidence' => [],
            ];
        }

        if ($this->modelExplainer === null || ($this->modelAllowed !== null && !($this->modelAllowed)($schoolId))) {
            return [
                'capability' => 'school_insight',
                'state' => 'provider_unavailable',
                'status' => 'provider_unavailable',
                'error_code' => 'provider_unavailable',
                'analysis_origin' => null,
                'cohort_version' => $cohortVersion,
                'aggregate' => $aggregate,
                'explanation' => null,
                'evidence' => $this->evidence($aggregate),
                'model_version' => $this->modelVersion,
            ];
        }

        if (!$this->synchronousModel) {
            if ($this->enqueueRefresh !== null) {
                try {
                    ($this->enqueueRefresh)($schoolId, $aggregateHash);
                } catch (\Throwable) {
                }
            }
            $cached = $this->lastKnownGood ? ($this->lastKnownGood)($schoolId) : null;
            if (is_array($cached)) {
                $generatedAt = strtotime((string) ($cached['generated_at'] ?? ''));
                if ($generatedAt !== false && (time() - $generatedAt) > $this->maxStaleSeconds) {
                    return [
                        'capability' => 'school_insight',
                        'state' => 'provider_unavailable',
                        'status' => 'provider_unavailable',
                        'freshness_status' => 'expired',
                        'error_code' => 'stale_sla_expired',
                        'analysis_origin' => null,
                        'cohort_version' => $cohortVersion,
                        'aggregate' => $aggregate,
                        'explanation' => null,
                        'evidence' => $this->evidence($aggregate),
                        'model_version' => $this->modelVersion,
                    ];
                }
                return array_replace($cached, [
                    'capability' => 'school_insight',
                    'state' => 'stale_model',
                    'status' => 'stale_model',
                    'freshness_status' => 'stale',
                    'analysis_origin' => 'model',
                    'cohort_version' => $cohortVersion,
                    'aggregate' => $aggregate,
                    'evidence' => $this->evidence($aggregate),
                    'last_known_good' => true,
                    'stale_since' => $cached['stale_since'] ?? gmdate('c'),
                ]);
            }
            return [
                'capability' => 'school_insight',
                'state' => 'pending',
                'status' => 'pending',
                'freshness_status' => 'refreshing',
                'analysis_origin' => null,
                'cohort_version' => $cohortVersion,
                'aggregate' => $aggregate,
                'explanation' => null,
                'evidence' => $this->evidence($aggregate),
                'model_version' => $this->modelVersion,
            ];
        }

        try {
            $explanation = ($this->modelExplainer)($this->providerPayload($aggregate));
            $result = [
                'capability' => 'school_insight',
                'state' => 'ready_model',
                'status' => 'ready_model',
                'freshness_status' => 'current',
                'analysis_origin' => 'model',
                'provider' => 'gemini',
                'prompt_version' => 'school-insight-1.0.0',
                'response_hash' => hash('sha256', json_encode($explanation, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'cohort_version' => $cohortVersion,
                'aggregate' => $aggregate,
                'explanation' => $this->safeExplanation($explanation),
                'evidence' => $this->evidence($aggregate),
                'model_version' => $this->modelVersion,
                'generated_at' => gmdate('c'),
            ];
            if ($this->saveInsight !== null) {
                ($this->saveInsight)($schoolId, $result);
            }
            return $result;
        } catch (\Throwable) {
            $cached = $this->lastKnownGood ? ($this->lastKnownGood)($schoolId) : null;
            if (is_array($cached)) {
                $generatedAt = strtotime((string) ($cached['generated_at'] ?? ''));
                if ($generatedAt !== false && (time() - $generatedAt) > $this->maxStaleSeconds) {
                    return [
                        'capability' => 'school_insight',
                        'state' => 'provider_unavailable',
                        'status' => 'provider_unavailable',
                        'freshness_status' => 'expired',
                        'error_code' => 'provider_unavailable',
                        'analysis_origin' => null,
                        'cohort_version' => $cohortVersion,
                        'aggregate' => $aggregate,
                        'evidence' => $this->evidence($aggregate),
                        'model_version' => $this->modelVersion,
                        'explanation' => null,
                    ];
                }
                return array_replace($cached, [
                    'capability' => 'school_insight',
                    'state' => 'stale_model',
                    'status' => 'stale_model',
                    'freshness_status' => 'stale',
                    'analysis_origin' => 'model',
                    'cohort_version' => $cohortVersion,
                    'aggregate' => $aggregate,
                    'evidence' => $this->evidence($aggregate),
                    'last_known_good' => true,
                    'stale_since' => $cached['stale_since'] ?? gmdate('c'),
                ]);
            }
            return [
                'capability' => 'school_insight',
                'state' => 'provider_unavailable',
                'status' => 'provider_unavailable',
                'error_code' => 'provider_unavailable',
                'analysis_origin' => null,
                'cohort_version' => $cohortVersion,
                'aggregate' => $aggregate,
                'evidence' => $this->evidence($aggregate),
                'model_version' => $this->modelVersion,
                'explanation' => null,
            ];
        }
    }

    /** Called only by the asynchronous school refresh worker. */
    public function refreshForSchool(string $schoolId, ?string $expectedAggregateHash = null): array
    {
        $readiness = $this->aggregates->readiness();
        if (!$readiness['ready']) {
            throw new \RuntimeException('School AI schema unavailable.');
        }
        if ($this->modelExplainer === null || ($this->modelAllowed !== null && !($this->modelAllowed)($schoolId))) {
            throw new \RuntimeException('School AI provider unavailable.');
        }
        $aggregate = $this->aggregates->aggregate($schoolId, $this->minimumCohort);
        $currentHash = $this->aggregates->aggregateHash($aggregate);
        if ($expectedAggregateHash !== null && !hash_equals($currentHash, $expectedAggregateHash)) {
            return ['capability' => 'school_insight', 'state' => 'superseded', 'aggregate' => $aggregate];
        }
        if ($aggregate['cohorts'] === []) {
            return ['capability' => 'school_insight', 'state' => 'insufficient_data', 'aggregate' => $aggregate];
        }
        $explanation = ($this->modelExplainer)($this->providerPayload($aggregate));
        $cohortVersion = substr($currentHash, 0, 16);
        $result = [
            'capability' => 'school_insight',
            'state' => 'ready_model',
            'status' => 'ready_model',
            'freshness_status' => 'current',
            'analysis_origin' => 'model',
            'provider' => 'gemini',
            'prompt_version' => 'school-insight-1.0.0',
            'response_hash' => hash('sha256', json_encode($explanation, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'cohort_version' => $cohortVersion,
            'aggregate' => $aggregate,
            'explanation' => $this->safeExplanation($explanation),
            'evidence' => $this->evidence($aggregate),
            'model_version' => $this->modelVersion,
            'generated_at' => gmdate('c'),
        ];
        if ($this->saveInsight !== null) {
            ($this->saveInsight)($schoolId, $result);
        }
        return $result;
    }

    /** @param array<string,mixed> $aggregate @return array<string,mixed> */
    private function providerPayload(array $aggregate): array
    {
        $cohorts = [];
        foreach (array_values((array) ($aggregate['cohorts'] ?? [])) as $index => $cohort) {
            $safe = [
                'cohort_ref' => 'cohort_' . ($index + 1),
                'level' => (string) ($cohort['level'] ?? 'aggregate'),
                'student_count' => (int) ($cohort['student_count'] ?? 0),
                'stale_count' => (int) ($cohort['stale_count'] ?? 0),
                'talent_distribution' => [],
                'trend_signals' => [],
            ];
            foreach ((array) ($cohort['talent_distribution'] ?? []) as $talent) {
                $field = $this->safeText((string) ($talent['field'] ?? ''));
                if ($field !== '') {
                    $safe['talent_distribution'][] = [
                        'field' => $field,
                        'average_score' => (float) ($talent['average_score'] ?? 0),
                    ];
                }
            }
            foreach ((array) ($cohort['trend_signals'] ?? []) as $trend) {
                $label = $this->safeText((string) ($trend['label'] ?? ''));
                if ($label !== '') {
                    $safe['trend_signals'][] = [
                        'label' => $label,
                        'count' => (int) ($trend['count'] ?? 0),
                        'confidence' => (float) ($trend['confidence'] ?? 0),
                    ];
                }
            }
            $cohorts[] = $safe;
        }

        return [
            'prompt_version' => 'school-insight-1.0.0',
            'instructions' => [
                'Explain aggregate trends only. Never infer or identify an individual learner.',
                'Do not output names, student IDs, emails, phones or protected traits.',
            ],
            'aggregate_evidence' => [
                'minimum_cohort' => $aggregate['minimum_cohort'],
                'cohorts' => $cohorts,
                'suppressed_cohort_count' => $aggregate['suppressed_cohort_count'],
            ],
        ];
    }

    /** @param array<string,mixed> $aggregate @return list<array<string,mixed>> */
    private function evidence(array $aggregate): array
    {
        $out = [];
        foreach ((array) ($aggregate['cohorts'] ?? []) as $cohort) {
            $out[] = [
                'cohort_key' => (string) ($cohort['cohort_key'] ?? ''),
                'student_count' => (int) ($cohort['student_count'] ?? 0),
            ];
        }
        return $out;
    }

    /** @param array<string,mixed> $value @return array<string,mixed> */
    private function safeExplanation(array $value): array
    {
        $summary = $this->safeText((string) ($value['summary'] ?? ''));
        $priorities = [];
        foreach ((array) ($value['priorities'] ?? []) as $priority) {
            if (!is_string($priority)) {
                continue;
            }
            $text = $this->safeText($priority);
            if ($text !== '') {
                $priorities[] = $text;
            }
        }
        return [
            'summary' => $summary,
            'priorities' => array_slice($priorities, 0, 5),
            'confidence' => in_array($value['confidence'] ?? null, ['low', 'medium', 'high'], true) ? $value['confidence'] : 'low',
        ];
    }

    private function safeText(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '' || mb_strlen($value) > 2000 || preg_match('/@|\b(?:student|học sinh|email|phone|điện thoại|sdt|cccd|giới tính|tôn giáo|dân tộc|khuyết tật)\b/i', $value)) {
            return '';
        }
        return $value;
    }
}
