<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;

/** @param list<array<string,mixed>> $skills
 * @param list<array<string,mixed>> $assessments
 * @param list<array<string,mixed>> $activities
 * @param list<array<string,mixed>> $evaluations
 * @param list<array<string,mixed>> $opportunities
 */
function learner_rule_input(array $skills, array $assessments, array $activities, array $evaluations, array $scopes = ['assessment', 'skills', 'activity', 'evaluation'], array $opportunities = []): RecommendationInput
{
    $evidence = [];
    foreach ([
        'skill' => [$skills, 'source_updated_at'],
        'assessment' => [$assessments, 'submitted_at'],
        'activity_experience' => [$activities, 'confirmed_at'],
        'evaluation' => [$evaluations, 'published_at'],
        'opportunity' => [$opportunities, 'deadline_at'],
    ] as $sourceType => [$records, $timestampField]) {
        foreach ($records as $record) {
            $sourceId = (string) ($record['_source_id'] ?? '');
            $safeValue = $record;
            unset($safeValue['_source_id']);
            $evidence[] = [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'observed_at' => $record[$timestampField] ?? null,
                'safe_value' => $safeValue,
            ];
        }
    }

    return new RecommendationInput(
        [
            'profile' => ['study_status' => 'active'],
            'skills' => array_map(static function (array $record): array {
                unset($record['_source_id']);
                return $record;
            }, $skills),
            'assessments' => array_map(static function (array $record): array {
                unset($record['_source_id']);
                return $record;
            }, $assessments),
            'activities' => array_map(static function (array $record): array {
                unset($record['_source_id']);
                return $record;
            }, $activities),
            'evaluations' => array_map(static function (array $record): array {
                unset($record['_source_id']);
                return $record;
            }, $evaluations),
            'opportunities' => array_map(static function (array $record): array {
                unset($record['_source_id']);
                return $record;
            }, $opportunities),
        ],
        [],
        [
            'allowed_scopes' => $scopes,
            'missing_consent_scopes' => array_values(array_diff(['assessment', 'skills', 'activity', 'evaluation'], $scopes)),
        ],
        $evidence,
    );
}

/** @return array<string,mixed> */
function learner_rule_holland(string $sourceId = 'assessment-1', string $testCode = 'holland', ?array $dimensionScores = null): array
{
    return [
        '_source_id' => $sourceId,
        'test_code' => $testCode,
        'assessment_version' => '1.0',
        'scoring_version' => 'holland-riasec-1.0',
        'dimension_scores' => $dimensionScores ?? ['R' => 82, 'I' => 78, 'A' => 55, 'S' => 45, 'E' => 60, 'C' => 50],
        'submitted_at' => '2026-06-15T09:00:00.000000+00:00',
    ];
}

/** @return array<string,mixed> */
function learner_rule_opportunity(string $sourceId, string $category, string $status = 'published'): array
{
    return [
        '_source_id' => $sourceId,
        'title' => 'Opportunity ' . $sourceId,
        'category' => $category,
        'status' => $status,
        'deadline_at' => '2026-09-30T17:00:00.000000+00:00',
    ];
}

/** @return array<string,mixed> */
function learner_rule_iot_skill(string $sourceId = 'skill-iot'): array
{
    return [
        '_source_id' => $sourceId,
        'code' => 'iot',
        'level_score' => 84,
        'verification_status' => 'verified',
        'source_updated_at' => '2026-06-16T09:00:00.000000+00:00',
    ];
}

/** @return array<string,mixed> */
function learner_rule_technical_activity(string $sourceId = 'activity-1', string $status = 'active'): array
{
    return [
        '_source_id' => $sourceId,
        'activity_category' => 'technical_workshop',
        'status' => $status,
        'hours' => 8,
        'confirmed_at' => '2026-06-17T09:00:00.000000+00:00',
    ];
}

/** @return array<string,mixed> */
function learner_rule_evaluation(string $sourceId = 'evaluation-1', float $presentationScore = 80): array
{
    return [
        '_source_id' => $sourceId,
        'overall_score' => 80,
        'presentation_score' => $presentationScore,
        'published_at' => '2026-06-18T09:00:00.000000+00:00',
    ];
}

function learner_rule_context(array $scopes = ['assessment', 'skills', 'activity', 'evaluation']): RecommendationContext
{
    return new RecommendationContext($scopes, 'request-rule-1', 'idempotency-rule-1');
}
