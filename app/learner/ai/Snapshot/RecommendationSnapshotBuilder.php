<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Snapshot;

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Sources\ActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\AssessmentSource;
use TalentHub\Learner\Ai\Sources\OpportunitySource;
use TalentHub\Learner\Ai\Sources\PublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\SkillSource;
use TalentHub\Learner\Ai\Sources\StudentProfileSource;

final class RecommendationSnapshotBuilder
{
    private const CONSENTED_SCOPES = ['assessment', 'skills', 'activity', 'evaluation'];

    public function __construct(
        private readonly StudentProfileSource $studentProfileSource,
        private readonly SkillSource $skillSource,
        private readonly AssessmentSource $assessmentSource,
        private readonly ActivityExperienceSource $activityExperienceSource,
        private readonly PublishedEvaluationSource $publishedEvaluationSource,
        private readonly OpportunitySource $opportunitySource,
    ) {
    }

    /** @param list<string> $allowedScopes */
    public function build(string $studentId, array $allowedScopes): RecommendationInput
    {
        $allowedScopes = $this->normalizeScopes($allowedScopes);
        $has = static fn (string $scope): bool => in_array($scope, $allowedScopes, true);
        $profile = $this->profile($this->studentProfileSource->forStudent($studentId));
        $skills = $has('skills') ? $this->skills($this->skillSource->forStudent($studentId)) : [];
        $assessments = $has('assessment') ? $this->assessments($this->assessmentSource->forStudent($studentId)) : [];
        $activities = $has('activity') ? $this->activities($this->activityExperienceSource->forStudent($studentId)) : [];
        $evaluations = $has('evaluation') ? $this->evaluations($this->publishedEvaluationSource->forStudent($studentId)) : [];
        $opportunities = $this->opportunities($this->opportunitySource->forStudent($studentId));

        $payload = [
            'profile' => $profile,
            'skills' => $this->withoutSourceIds($skills),
            'assessments' => $this->withoutSourceIds($assessments),
            'activities' => $this->withoutSourceIds($activities),
            'evaluations' => $this->withoutSourceIds($evaluations),
            'opportunities' => $this->withoutSourceIds($opportunities),
        ];
        $missingConsent = array_values(array_diff(self::CONSENTED_SCOPES, $allowedScopes));
        sort($missingConsent, SORT_STRING);

        return new RecommendationInput(
            $payload,
            $this->sourceUpdatedAt($payload),
            [
                'allowed_scopes' => $allowedScopes,
                'missing_consent_scopes' => $missingConsent,
                'source_counts' => [
                    'skills' => count($skills),
                    'assessments' => count($assessments),
                    'activities' => count($activities),
                    'evaluations' => count($evaluations),
                    'opportunities' => count($opportunities),
                ],
            ],
            $this->evidenceReferences($skills, $assessments, $activities, $evaluations, $opportunities)
        );
    }

    /** @param list<string> $scopes @return list<string> */
    private function normalizeScopes(array $scopes): array
    {
        $normalized = [];
        foreach ($scopes as $scope) {
            if (is_string($scope) && in_array($scope, self::CONSENTED_SCOPES, true)) {
                $normalized[$scope] = true;
            }
        }
        $allowed = array_keys($normalized);
        sort($allowed, SORT_STRING);
        return $allowed;
    }

    /** @param array<string,mixed> $profile @return array<string,string> */
    private function profile(array $profile): array
    {
        $status = trim((string) ($profile['study_status'] ?? ''));
        return $status === '' ? [] : ['study_status' => $status];
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private function skills(array $records): array
    {
        $skills = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = $this->identifier($record, 'student_skill_id');
            $code = trim((string) ($record['code'] ?? ''));
            $updatedAt = $this->timestamp($record['source_updated_at'] ?? null);
            if ($id === '' || $code === '' || $updatedAt === null) {
                continue;
            }
            $skills[] = [
                '_source_id' => $id,
                'code' => $code,
                'category' => trim((string) ($record['category'] ?? '')),
                'level_score' => (float) ($record['level_score'] ?? 0),
                'source_type' => trim((string) ($record['source_type'] ?? '')),
                'verification_status' => trim((string) ($record['verification_status'] ?? '')),
                'verified_at' => $this->timestamp($record['verified_at'] ?? null),
                'source_updated_at' => $updatedAt,
            ];
        }
        return $this->sortRecords($skills);
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private function assessments(array $records): array
    {
        $assessments = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = $this->identifier($record, 'result_id');
            $submittedAt = $this->timestamp($record['submitted_at'] ?? null);
            $scores = $record['dimension_scores'] ?? null;
            if ($id === '' || $submittedAt === null || !is_array($scores)) {
                continue;
            }
            $assessments[] = [
                '_source_id' => $id,
                'test_code' => trim((string) ($record['test_code'] ?? '')),
                'test_type' => trim((string) ($record['test_type'] ?? '')),
                'assessment_version' => trim((string) ($record['assessment_version'] ?? '')),
                'scoring_version' => trim((string) ($record['scoring_version'] ?? '')),
                'result_code' => trim((string) ($record['result_code'] ?? '')),
                'dimension_scores' => $scores,
                'submitted_at' => $submittedAt,
            ];
        }
        return $this->sortRecords($assessments);
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private function activities(array $records): array
    {
        $activities = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = $this->identifier($record, 'experience_id');
            $confirmedAt = $this->timestamp($record['confirmed_at'] ?? null);
            if ($id === '' || $confirmedAt === null) {
                continue;
            }
            $activities[] = [
                '_source_id' => $id,
                'activity_category' => trim((string) ($record['activity_category'] ?? '')),
                'hours' => (float) ($record['hours'] ?? 0),
                'confirmed_at' => $confirmedAt,
            ];
        }
        return $this->sortRecords($activities);
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private function evaluations(array $records): array
    {
        $evaluations = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = $this->identifier($record, 'evaluation_id');
            $publishedAt = $this->timestamp($record['published_at'] ?? null);
            if ($id === '' || $publishedAt === null || !is_numeric($record['overall_score'] ?? null)) {
                continue;
            }
            $evaluation = [
                '_source_id' => $id,
                'overall_score' => (float) $record['overall_score'],
                'published_at' => $publishedAt,
            ];
            if (is_numeric($record['presentation_score'] ?? null)) {
                $evaluation['presentation_score'] = (float) $record['presentation_score'];
            }
            $evaluations[] = $evaluation;
        }
        return $this->sortRecords($evaluations);
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private function opportunities(array $records): array
    {
        $opportunities = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = $this->identifier($record, 'opportunity_id');
            $deadline = $this->timestamp($record['deadline_at'] ?? null);
            if ($id === '' || $deadline === null) {
                continue;
            }
            $opportunities[] = [
                '_source_id' => $id,
                'title' => trim((string) ($record['title'] ?? '')),
                'location' => trim((string) ($record['location'] ?? '')),
                'deadline_at' => $deadline,
            ];
        }
        return $this->sortRecords($opportunities);
    }

    /** @param array<string,mixed> $payload @return array<string,string> */
    private function sourceUpdatedAt(array $payload): array
    {
        $timestamps = [];
        foreach ([
            'skill' => ['skills', 'source_updated_at'],
            'assessment' => ['assessments', 'submitted_at'],
            'activity' => ['activities', 'confirmed_at'],
            'evaluation' => ['evaluations', 'published_at'],
            'opportunity' => ['opportunities', 'deadline_at'],
        ] as $source => [$payloadKey, $field]) {
            $values = [];
            foreach ($payload[$payloadKey] as $record) {
                if (is_array($record) && is_string($record[$field] ?? null)) {
                    $values[] = $record[$field];
                }
            }
            if ($values !== []) {
                rsort($values, SORT_STRING);
                $timestamps[$source] = $values[0];
            }
        }
        return $timestamps;
    }

    /**
     * @param list<array<string,mixed>> $skills
     * @param list<array<string,mixed>> $assessments
     * @param list<array<string,mixed>> $activities
     * @param list<array<string,mixed>> $evaluations
     * @param list<array<string,mixed>> $opportunities
     * @return list<array{source_type:string,source_id:string,observed_at:?string,safe_value:array<string,mixed>}>
     */
    private function evidenceReferences(array $skills, array $assessments, array $activities, array $evaluations, array $opportunities): array
    {
        $references = [];
        foreach ([
            'skill' => [$skills, 'source_updated_at'],
            'assessment' => [$assessments, 'submitted_at'],
            'activity_experience' => [$activities, 'confirmed_at'],
            'evaluation' => [$evaluations, 'published_at'],
            'opportunity' => [$opportunities, 'deadline_at'],
        ] as $type => [$records, $timestampField]) {
            foreach ($records as $record) {
                $safeValue = $record;
                unset($safeValue['_source_id']);
                $references[] = [
                    'source_type' => $type,
                    'source_id' => (string) $record['_source_id'],
                    'observed_at' => $record[$timestampField] ?? null,
                    'safe_value' => $safeValue,
                ];
            }
        }
        usort($references, static fn (array $left, array $right): int => [$left['source_type'], $left['source_id']] <=> [$right['source_type'], $right['source_id']]);
        return $references;
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private function sortRecords(array $records): array
    {
        usort($records, static fn (array $left, array $right): int => (string) $left['_source_id'] <=> (string) $right['_source_id']);
        return $records;
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private function withoutSourceIds(array $records): array
    {
        return array_map(static function (array $record): array {
            unset($record['_source_id']);
            return $record;
        }, $records);
    }

    /** @param array<string,mixed> $record */
    private function identifier(array $record, string $field): string
    {
        return trim((string) ($record[$field] ?? ''));
    }

    private function timestamp(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\\TH:i:s.uP');
        } catch (\Throwable) {
            return null;
        }
    }
}
