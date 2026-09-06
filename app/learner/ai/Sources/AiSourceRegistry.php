<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources;

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Sources\Database\DatabaseLearnerAiExtendedSource;
use TalentHub\Learner\Data\Contracts\TalentPassportRepository;

final class AiSourceRegistry
{
    /** @var array<string,LearnerAiExtendedSource> */
    private array $sources = [];
    /** @var (callable(string):array<string,mixed>)|null */
    private $availabilityReader = null;
    /** @var (callable():void)|null */
    private $resetReaderCache = null;
    private ?\PDO $transactionPdo = null;

    /** @param list<LearnerAiExtendedSource> $sources */
    public function __construct(array $sources = [])
    {
        foreach ($sources as $source) {
            $this->register($source);
        }
    }

    public function register(LearnerAiExtendedSource $source): void
    {
        $type = trim($source->sourceType());
        if ($type === '' || isset($this->sources[$type])) {
            throw new \InvalidArgumentException('AI source type must be unique and non-empty.');
        }
        $this->sources[$type] = $source;
        ksort($this->sources, SORT_STRING);
    }

    /**
     * Inject an optional PDO handle that supports transactional snapshot
     * reads. When the registry detects a transactional source it wraps
     * the per-student read inside a single transaction so the snapshot
     * payload is consistent across every consent-scoped source.
     */
    public function setTransactionPdo(?\PDO $pdo): void
    {
        $this->transactionPdo = $pdo;
    }

    /** @return list<LearnerAiExtendedSource> */
    public function adapters(): array
    {
        return array_values($this->sources);
    }

    /** @param list<string> $allowedScopes @return list<array<string,mixed>> */
    public function readForStudent(string $studentId, array $allowedScopes): array
    {
        if ($this->resetReaderCache !== null) {
            ($this->resetReaderCache)();
        }
        $allowed = array_fill_keys($this->normalizeScopes($allowedScopes), true);
        $records = [];
        $pdo = $this->transactionPdo;
        $usesTransaction = $pdo !== null
            && $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite'
            && !$pdo->inTransaction();
        if ($usesTransaction) {
            $pdo->beginTransaction();
        }
        try {
            foreach ($this->sources as $source) {
                if (!isset($allowed[$source->consentScope()])) {
                    continue;
                }
                foreach ($source->readForStudent($studentId) as $record) {
                    $normalized = $this->normalizeRecord($source, $record);
                    if ($normalized !== null) {
                        $records[] = $normalized;
                    }
                }
            }
            if ($usesTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($usesTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        usort($records, static fn (array $left, array $right): int => [
            $left['source_type'], $left['source_id'], (string) $left['observed_at'],
        ] <=> [
            $right['source_type'], $right['source_id'], (string) $right['observed_at'],
        ]);
        return $records;
    }

    public function changedSince(string $studentId, ?string $versionOrTimestamp, array $allowedScopes): bool
    {
        $allowed = array_fill_keys($this->normalizeScopes($allowedScopes), true);
        foreach ($this->sources as $source) {
            if (isset($allowed[$source->consentScope()]) && $source->changedSince($studentId, $versionOrTimestamp)) {
                return true;
            }
        }
        return false;
    }

    public function registerTalentPassportSources(TalentPassportRepository $repository): void
    {
        $aggregateCache = [];
        $aggregateReader = static function (string $studentId) use ($repository, &$aggregateCache): array {
            if (array_key_exists($studentId, $aggregateCache)) {
                return $aggregateCache[$studentId];
            }
            try {
                $aggregate = $repository->aggregateForStudent($studentId);
                return $aggregateCache[$studentId] = is_array($aggregate) ? $aggregate : [];
            } catch (\Throwable) {
                return $aggregateCache[$studentId] = [];
            }
        };
        $this->resetReaderCache = static function () use (&$aggregateCache): void {
            $aggregateCache = [];
        };
        $this->availabilityReader = static function (string $studentId) use ($aggregateReader): array {
            $aggregate = $aggregateReader($studentId);
            return is_array($aggregate['source_availability'] ?? null) ? $aggregate['source_availability'] : [];
        };
        $this->register(new DatabaseLearnerAiExtendedSource(
            'certificate', 'certificate-1.0.0', 'skills',
            ['title', 'issuingOrganization', 'issuing_organization', 'issueDate', 'issue_date', 'expiryDate', 'expiry_date', 'credentialId', 'credential_id', 'verificationStatus', 'verification_status', 'verifiedAt', 'verified_at', 'updatedAt', 'updated_at'],
            'certificate_changed',
            static function (string $studentId) use ($aggregateReader): array {
                $aggregate = $aggregateReader($studentId);
                return is_array($aggregate['certificates'] ?? null) ? array_map(static fn (array $row): array => [
                        ...$row,
                        'source_id' => $row['id'] ?? null,
                        'updated_at' => $row['updatedAt'] ?? $row['issueDate'] ?? null,
                    ], $aggregate['certificates']) : [];
            },
        ));
        $this->register(new DatabaseLearnerAiExtendedSource(
            'project', 'project-1.0.0', 'activity',
            ['title', 'category', 'description', 'projectUrl', 'project_url', 'startAt', 'start_at', 'endAt', 'end_at', 'status', 'role', 'contribution', 'skill_tags', 'skill_codes', 'skills', 'updatedAt', 'updated_at'],
            'project_changed',
            static function (string $studentId) use ($aggregateReader): array {
                $aggregate = $aggregateReader($studentId);
                return is_array($aggregate['projects'] ?? null) ? array_map(static fn (array $row): array => [
                        ...$row,
                        'source_id' => $row['id'] ?? null,
                        'updated_at' => $row['updatedAt'] ?? $row['endAt'] ?? $row['startAt'] ?? null,
                    ], $aggregate['projects']) : [];
            },
        ));
        $this->register(new DatabaseLearnerAiExtendedSource(
            'badge', 'badge-1.0.0', 'skills',
            ['code', 'name', 'category', 'description', 'level', 'status', 'awardedAt', 'awarded_at', 'updatedAt', 'updated_at'],
            'badge_changed',
            static function (string $studentId) use ($aggregateReader): array {
                $aggregate = $aggregateReader($studentId);
                return is_array($aggregate['badges'] ?? null) ? array_map(static fn (array $row): array => [
                        ...$row,
                        'source_id' => $row['id'] ?? $row['code'] ?? null,
                        'awardedAt' => $row['awardedAt'] ?? $row['awarded_at'] ?? null,
                        'updated_at' => $row['awardedAt'] ?? $row['awarded_at'] ?? null,
                    ], $aggregate['badges']) : [];
            },
        ));

        // These adapters deliberately read optional aggregate sections when the
        // source repository exposes them. An absent section means the current
        // schema has no canonical source yet; it is never replaced by mocked or
        // model-generated data.
        $this->registerAggregateSource($aggregateReader, 'achievement', 'achievement-1.0.0', 'skills', 'achievement_changed', 'achievements', [
            'code', 'title', 'category', 'description', 'level', 'status', 'awardedAt', 'awarded_at', 'updatedAt', 'updated_at',
        ]);
        $this->registerAggregateSource($aggregateReader, 'progress', 'progress-1.0.0', 'activity', 'progress_changed', 'progress', [
            'code', 'label', 'current', 'target', 'percent', 'progressPercent', 'progress_percent', 'status', 'updatedAt', 'updated_at',
        ]);
        $this->registerAggregateSource($aggregateReader, 'checkin', 'checkin-1.0.0', 'activity', 'checkin_changed', 'checkins', [
            'activityId', 'activity_id', 'activityCategory', 'activity_category', 'displayCategory', 'display_category', 'filterCategory', 'filter_category', 'hours', 'status', 'checkedInAt', 'checked_in_at', 'confirmedAt', 'confirmed_at', 'updatedAt', 'updated_at',
        ], static function (array $aggregate): array {
            $rows = $aggregate['checkins'] ?? [];
            if ($rows === [] && is_array($aggregate['experience']['confirmed_entries'] ?? null)) {
                $rows = $aggregate['experience']['confirmed_entries'];
            }
            return is_array($rows) ? $rows : [];
        });
        $this->registerAggregateSource($aggregateReader, 'mentor_evaluation', 'mentor-evaluation-1.0.0', 'evaluation', 'mentor_evaluation_changed', 'mentor_evaluations', [
            'activityId', 'activity_id', 'overallScore', 'overall_score', 'comment', 'status', 'publishedAt', 'published_at', 'version', 'skill_tags', 'skill_codes', 'skills', 'updatedAt', 'updated_at',
        ]);
        $this->registerAggregateSource($aggregateReader, 'teacher_feedback', 'teacher-feedback-1.0.0', 'evaluation', 'teacher_feedback_changed', 'teacher_evaluations', [
            'activityId', 'activity_id', 'overallScore', 'overall_score', 'comment', 'status', 'publishedAt', 'published_at', 'version', 'skill_tags', 'skill_codes', 'skills', 'updatedAt', 'updated_at',
        ], static function (array $aggregate): array {
            $rows = $aggregate['teacher_feedback'] ?? $aggregate['teacher_evaluations'] ?? [];
            return is_array($rows) ? $rows : [];
        });
        $this->registerAggregateSource($aggregateReader, 'roadmap_feedback', 'roadmap-feedback-1.0.0', 'evaluation', 'roadmap_feedback_changed', 'roadmap_feedback', [
            'runId', 'run_id', 'verdict', 'reasonCode', 'reason_code', 'updatedAt', 'updated_at',
        ]);
    }

    /** @param list<string> $fields @param (callable(array<string,mixed>):list<array<string,mixed>>)|null $sectionReader */
    private function registerAggregateSource(
        callable $aggregateReader,
        string $type,
        string $version,
        string $scope,
        string $trigger,
        string $section,
        array $fields = ['id', 'updatedAt'],
        ?callable $sectionReader = null,
    ): void {
        $this->register(new DatabaseLearnerAiExtendedSource(
            $type,
            $version,
            $scope,
            $fields,
            $trigger,
            static function (string $studentId) use ($aggregateReader, $section, $sectionReader): array {
                    $aggregate = $aggregateReader($studentId);
                    $rows = $sectionReader !== null
                        ? $sectionReader($aggregate)
                        : ($aggregate[$section] ?? []);
                    if (!is_array($rows)) return [];
                    $normalized = [];
                    foreach ($rows as $row) {
                        if (!is_array($row)) continue;
                        $normalized[] = [
                            ...$row,
                            'source_id' => $row['source_id'] ?? $row['id'] ?? $row['checkinId'] ?? $row['badgeId'] ?? $row['badge_id'] ?? $row['code'] ?? null,
                            'updated_at' => $row['updated_at'] ?? $row['updatedAt'] ?? $row['publishedAt']
                                ?? $row['confirmedAt'] ?? $row['confirmed_at'] ?? $row['awardedAt'] ?? null,
                        ];
                    }
                    return $normalized;
            },
        ));
    }

    /** @param list<string> $allowedScopes */
    public function buildInput(string $studentId, array $allowedScopes, bool $roadmapOnly = false): RecommendationInput
    {
        $allowedScopes = $this->normalizeScopes($allowedScopes);
        $records = $this->readForStudent($studentId, $allowedScopes);
        if ($roadmapOnly) {
            $records = $this->latestAssessmentFamilies($records);
        }
        $payload = ['sources' => array_map(static function (array $record): array {
            $copy = $record;
            unset($copy['source_id']);
            return $copy;
        }, $records)];
        foreach ($records as $record) {
            $key = $this->payloadKey($record['source_type']);
            if ($key === 'profile') {
                $payload[$key] = $record['data'];
            } else {
                $payload[$key][] = $record['data'];
            }
        }
        foreach (['profile' => [], 'skills' => [], 'assessments' => [], 'activities' => [], 'evaluations' => [], 'opportunities' => []] as $key => $default) {
            $payload[$key] ??= $default;
        }

        $sourceUpdatedAt = [];
        $sourceCounts = [];
        $evidence = [];
        foreach ($records as $record) {
            $type = $record['source_type'];
            $sourceCounts[$type] = ($sourceCounts[$type] ?? 0) + 1;
            $timestamp = $record['observed_at'];
            if (is_string($timestamp) && (!isset($sourceUpdatedAt[$type]) || strcmp($timestamp, $sourceUpdatedAt[$type]) > 0)) {
                $sourceUpdatedAt[$type] = $timestamp;
            }
            if ($record['observed_at'] !== null) {
                $evidence[] = [
                    'source_type' => $type,
                    'source_id' => $record['source_id'],
                    'observed_at' => $record['observed_at'],
                    'safe_value' => $record['data'],
                ];
            }
        }
        ksort($sourceCounts, SORT_STRING);
        ksort($sourceUpdatedAt, SORT_STRING);
        $registeredScopes = [];
        foreach ($this->sources as $source) {
            $registeredScopes[$source->consentScope()] = true;
        }
        $missing = array_values(array_diff(array_keys($registeredScopes), $allowedScopes));
        sort($missing, SORT_STRING);
        $catalogTypes = ['community', 'contest', 'group', 'project', 'skill_resource', 'workshop'];
        $blockedCatalogTypes = isset($this->sources['catalog']) ? [] : $catalogTypes;
        sort($blockedCatalogTypes, SORT_STRING);
        $sourceAvailability = $this->availabilityReader !== null
            ? ($this->availabilityReader)($studentId)
            : [];
        $missingSourceTypes = [];
        foreach ($sourceAvailability as $type => $availability) {
            if (!is_array($availability) || ($availability['status'] ?? null) !== 'available') {
                $missingSourceTypes[] = (string) $type;
            }
        }
        sort($missingSourceTypes, SORT_STRING);

        return new RecommendationInput($payload, $sourceUpdatedAt, [
            'allowed_scopes' => $allowedScopes,
            'missing_consent_scopes' => $missing,
            'source_counts' => $sourceCounts,
            'blocked_catalog_types' => $blockedCatalogTypes,
            'source_availability' => $sourceAvailability,
            'missing_source_types' => $missingSourceTypes,
        ], $evidence);
    }

    /** @param list<object> $legacySources */
    public static function fromLegacySources(array $legacySources): self
    {
        $registry = new self();
        foreach ($legacySources as $source) {
            if ($source instanceof LearnerAiExtendedSource) {
                $registry->register($source);
                continue;
            }
            $adapter = self::legacyAdapter($source);
            if ($adapter !== null) {
                $registry->register($adapter);
            }
        }
        return $registry;
    }

    private static function legacyAdapter(object $source): ?LearnerAiExtendedSource
    {
        if ($source instanceof StudentProfileSource) {
            return new DatabaseLearnerAiExtendedSource('profile', 'profile-1.0.0', 'assessment', [
                'academic_year', 'class_name', 'grade_level', 'school_name', 'study_status', 'updated_at',
            ], 'profile_changed', static function (string $studentId) use ($source): array {
                $record = $source->forStudent($studentId);
                if ($record === []) return [];
                $record['source_id'] = 'profile';
                $record['observed_at'] = $record['updated_at'] ?? null;
                return [$record];
            });
        }
        if ($source instanceof SkillSource) {
            return self::legacyListAdapter($source, 'skill', 'skills', [
                'category', 'code', 'level_score', 'source_type', 'source_updated_at', 'verification_status', 'verified_at',
            ], 'student_skill_id', 'source_updated_at');
        }
        if ($source instanceof AssessmentSource) {
            return self::legacyListAdapter($source, 'assessment', 'assessment', [
                'assessment_version', 'dimension_scores', 'result_code', 'scoring_version', 'submitted_at', 'test_code', 'test_type',
            ], 'result_id', 'submitted_at');
        }
        if ($source instanceof ActivityExperienceSource) {
            return self::legacyListAdapter($source, 'activity_experience', 'activity', [
                'activity_category', 'confirmed_at', 'hours', 'skill_tags', 'skill_codes', 'skills',
            ], 'experience_id', 'confirmed_at');
        }
        if ($source instanceof PublishedEvaluationSource) {
            return self::legacyListAdapter($source, 'evaluation', 'evaluation', [
                'overall_score', 'presentation_score', 'published_at', 'skill_tags', 'skill_codes', 'skills',
            ], 'evaluation_id', 'published_at');
        }
        if ($source instanceof OpportunitySource) {
            return self::legacyListAdapter($source, 'opportunity', 'activity', [
                'action', 'availability', 'catalog_id', 'category', 'deadline_at', 'location', 'opportunity_type', 'status', 'title', 'url',
            ], 'opportunity_id', 'deadline_at');
        }
        return null;
    }

    /** @param object{forStudent:callable} $source @param list<string> $fields */
    private static function legacyListAdapter(object $source, string $type, string $scope, array $fields, string $idField, string $timestampField): LearnerAiExtendedSource
    {
        return new DatabaseLearnerAiExtendedSource(
            $type,
            $type . '-1.0.0',
            $scope,
            $fields,
            $type . '_changed',
            static function (string $studentId) use ($source, $idField, $timestampField): array {
                $records = $source->forStudent($studentId);
                foreach ($records as &$record) {
                    if (!is_array($record)) continue;
                    $record['source_id'] = $record[$idField] ?? null;
                    $record['observed_at'] = $record[$timestampField] ?? null;
                }
                unset($record);
                return $records;
            },
        );
    }

    /** @param array<string,mixed> $record @return array<string,mixed>|null */
    private function normalizeRecord(LearnerAiExtendedSource $source, array $record): ?array
    {
        $sourceId = trim((string) ($record['source_id'] ?? $record['id'] ?? ''));
        if ($sourceId === '') {
            return null;
        }
        $timestamp = $this->normalizeTimestamp($record['observed_at'] ?? $record['updated_at'] ?? null);
        $data = [];
        foreach ($source->allowedFields() as $field) {
            if (array_key_exists($field, $record)) {
                $value = $record[$field];
                if (in_array($field, ['level_score', 'level', 'hours', 'overall_score', 'overallScore', 'presentation_score', 'progressPercent', 'progress_percent', 'percent', 'current', 'target'], true) && is_numeric($value)) {
                    $value = (float) $value;
                }
                $data[$field] = $this->redact($this->isTimestampField($field) ? $this->normalizeTimestamp($value) ?? $value : $value);
            }
        }
        ksort($data, SORT_STRING);
        return [
            'source_type' => $source->sourceType(),
            'source_id' => $sourceId,
            'observed_at' => $timestamp,
            'schema_version' => $source->schemaVersion(),
            'consent_scope' => $source->consentScope(),
            'evidence_ref' => $source->sourceType() . ':' . $sourceId,
            'data' => $data,
        ];
    }

    private function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            if (is_string($value) && preg_match('/(?:authorization\s*:|bearer\s+[A-Za-z0-9._-]+|api[_-]?key\s*[:=])/i', $value) === 1) {
                return '[REDACTED]';
            }
            return $value;
        }
        $safe = [];
        foreach ($value as $key => $child) {
            $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $key));
            if (preg_match('/(?:email|phone|fullname|studentid|userid|teacherid|password|token|authorization|apikey)/', $normalized) === 1) {
                continue;
            }
            $safe[$key] = $this->redact($child);
        }
        return $safe;
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\\TH:i:s.uP');
        } catch (\Throwable) {
            return null;
        }
    }

    private function isTimestampField(string $field): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $field));
        return str_ends_with($normalized, 'at') || str_ends_with($normalized, 'date')
            || in_array($normalized, ['submitted', 'published', 'confirmed', 'verified', 'awarded', 'created', 'updated', 'checked', 'start', 'end', 'issue', 'expiry'], true);
    }

    /** @param list<string> $scopes @return list<string> */
    private function normalizeScopes(array $scopes): array
    {
        $normalized = [];
        foreach ($scopes as $scope) {
            if (is_string($scope) && trim($scope) !== '') {
                $normalized[trim($scope)] = true;
            }
        }
        $result = array_keys($normalized);
        sort($result, SORT_STRING);
        return $result;
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private function latestAssessmentFamilies(array $records): array
    {
        $latest = [];
        $other = [];
        foreach ($records as $record) {
            if ($record['source_type'] !== 'assessment') {
                $other[] = $record;
                continue;
            }
            $type = strtolower((string) ($record['data']['test_type'] ?? ''));
            $testCode = strtolower((string) ($record['data']['test_code'] ?? ''));
            if (!in_array($type, ['holland', 'mbti', 'disc', 'multiple_intelligence'], true)) {
                $type = $testCode;
            }
            $family = null;
            foreach (['holland', 'mbti', 'disc', 'multiple_intelligence'] as $candidate) {
                if ($type === $candidate || str_starts_with($type, $candidate . '_')) {
                    $family = $candidate;
                    break;
                }
            }
            if ($family === null) continue;
            if (!isset($latest[$family]) || [(string) $record['observed_at'], $record['source_id']] > [(string) $latest[$family]['observed_at'], $latest[$family]['source_id']]) {
                $latest[$family] = $record;
            }
        }
        $result = [...$other, ...array_values($latest)];
        usort($result, static fn (array $left, array $right): int => [$left['source_type'], $left['source_id']] <=> [$right['source_type'], $right['source_id']]);
        return $result;
    }

    private function payloadKey(string $sourceType): string
    {
        return match ($sourceType) {
            'profile' => 'profile',
            'skill' => 'skills',
            'assessment' => 'assessments',
            'activity', 'activity_experience', 'checkin' => 'activities',
            'evaluation', 'mentor_evaluation', 'teacher_feedback' => 'evaluations',
            'opportunity', 'catalog' => 'opportunities',
            default => str_ends_with($sourceType, 's') ? $sourceType : $sourceType . 's',
        };
    }
}
