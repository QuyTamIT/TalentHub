<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Validation;

use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Domain\RoadmapDirection;
use TalentHub\Learner\Ai\Domain\RoadmapInsight;
use TalentHub\Learner\Ai\Domain\RoadmapPhase;
use TalentHub\Learner\Ai\Domain\RoadmapTask;

final class RoadmapAnalysisValidator
{
    private const TALENT_MAP_FIELDS = [
        'Tư duy Logic & Hệ thống',
        'Kỹ năng Thực hành & Thao tác',
        'Tổ chức & Điều phối',
    ];

    private const PAYLOAD_FIELDS = [
        'alternative_directions',
        'executive_summary',
        'insights',
        'phases',
        'primary_direction',
        'recommended_activity_source_ids',
        'talent_map',
    ];
    private const EXTENDED_FIELDS = [
        'confidence',
        'evidence',
        'growth_hypotheses',
        'improvements',
        'potential_paths',
        'strengths',
        'trend_signals',
    ];

    /** @var array<string,bool> */
    private readonly array $allowedEvidence;
    /** @var array<string,bool> */
    private readonly array $allowedActivityIds;
    /** @var array<string,bool> */
    private readonly array $allowedCatalogIds;

    /** @param list<string> $allowedEvidence @param list<string> $allowedActivityIds @param list<string> $allowedCatalogIds */
    public function __construct(array $allowedEvidence, array $allowedActivityIds, array $allowedCatalogIds = [])
    {
        $this->allowedEvidence = $this->allowList($allowedEvidence, 'Roadmap evidence allow-list is invalid.');
        $this->allowedActivityIds = $this->allowList($allowedActivityIds, 'Roadmap activity allow-list is invalid.');
        $this->allowedCatalogIds = $this->allowList($allowedCatalogIds, 'Roadmap catalog allow-list is invalid.');
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $engineMetadata */
    public function fromProviderPayload(array $payload, array $engineMetadata): RoadmapAnalysis
    {
        $this->assertPayloadFields($payload);
        $metadata = $this->modelMetadata($engineMetadata);
        $summary = $this->requiredText($payload['executive_summary'], 'Roadmap executive summary is required.');
        $this->assertVietnamese($summary);

        $primary = $this->direction($payload['primary_direction'], 'primary_direction');
        $alternatives = $this->list($payload['alternative_directions'], 'Roadmap alternatives are invalid.');
        if (count($alternatives) !== 2) {
            throw new \InvalidArgumentException('Roadmap requires exactly two alternative directions.');
        }
        $alternativeDirections = array_map(
            fn (mixed $value, int $index): RoadmapDirection => $this->direction($value, 'alternative_direction_' . ($index + 1)),
            $alternatives,
            array_keys($alternatives),
        );

        $insightRecords = $this->list($payload['insights'], 'Roadmap insights are invalid.');
        if (count($insightRecords) !== 3) {
            throw new \InvalidArgumentException('Roadmap requires exactly three insights.');
        }
        $insights = array_map(fn (mixed $value): RoadmapInsight => $this->insight($value), $insightRecords);
        $categories = array_map(static fn (RoadmapInsight $insight): string => $insight->category(), $insights);
        sort($categories, SORT_STRING);
        if ($categories !== ['improvement', 'potential', 'strength']) {
            throw new \InvalidArgumentException('Roadmap insight categories are invalid.');
        }

        $phaseRecords = $this->list($payload['phases'], 'Roadmap phases are invalid.');
        if (count($phaseRecords) !== 3) {
            throw new \InvalidArgumentException('Roadmap requires exactly three phases.');
        }
        $phases = array_map(fn (mixed $value): RoadmapPhase => $this->phase($value), $phaseRecords);
        $positions = array_map(static fn (RoadmapPhase $phase): int => $phase->position(), $phases);
        if ($positions !== [1, 2, 3]) {
            throw new \InvalidArgumentException('Roadmap phase positions are invalid.');
        }
        $codes = array_map(static fn (RoadmapPhase $phase): string => $phase->code(), $phases);
        if ($codes !== ['discover', 'practice', 'breakthrough']) {
            throw new \InvalidArgumentException('Roadmap phase codes are invalid.');
        }
        for ($index = 1; $index < count($phases); $index++) {
            if ($phases[$index]->startDay() <= $phases[$index - 1]->endDay()) {
                throw new \InvalidArgumentException('Roadmap phases must not overlap.');
            }
        }
        foreach ([[0, 30], [31, 60], [61, 90]] as $index => [$start, $end]) {
            if ($phases[$index]->startDay() !== $start || $phases[$index]->endDay() !== $end) {
                throw new \InvalidArgumentException('Roadmap phase day ranges are invalid.');
            }
        }

        $recommended = $this->references($payload['recommended_activity_source_ids'], $this->allowedActivityIds, false, 'Roadmap activity source ids are invalid.');
        $extended = $this->extendedPayload($payload);

        return new RoadmapAnalysis(
            'model',
            $summary,
            $primary,
            $alternativeDirections,
            $insights,
            $phases,
            $metadata['confidence_band'],
            $recommended,
            $metadata,
            $extended['talent_map'],
            $extended['strengths'],
            $extended['improvements'],
            $extended['potential_paths'],
            $extended['trend_signals'],
            $extended['growth_hypotheses'],
            $extended['confidence'],
        );
    }

    public function validate(RoadmapAnalysis $analysis): void
    {
        if ($analysis->evidenceReferenceIds() === []) {
            throw new \RuntimeException('Roadmap analysis requires evidence references.');
        }
        foreach ($analysis->evidenceReferenceIds() as $reference) {
            if (!isset($this->allowedEvidence[$reference])) {
                throw new \RuntimeException('Roadmap analysis cited unavailable evidence.');
            }
        }
    }

    /** @param mixed $value */
    private function direction(mixed $value, string $fallbackCode): RoadmapDirection
    {
        if (!is_array($value)) throw new \InvalidArgumentException('Roadmap direction is invalid.');
        $this->assertExactFields($value, ['code', 'label', 'rationale'], 'Roadmap direction fields are invalid.');
        $label = $this->requiredText($value['label'], 'Roadmap direction label is required.');
        $rationale = $this->requiredText($value['rationale'], 'Roadmap direction rationale is required.');
        $this->assertVietnamese($label . ' ' . $rationale);
        return new RoadmapDirection(
            $this->directionCode($value['code'], $fallbackCode),
            $label,
            $rationale,
        );
    }

    private function directionCode(mixed $value, string $fallback): string
    {
        if (!is_string($value) || trim($value) === '') return $fallback;
        $normalized = strtolower(trim($value));
        $normalized = trim((string) preg_replace('/[^a-z0-9_]+/', '_', $normalized), '_');
        if ($normalized === '' || preg_match('/^[a-z]/', $normalized) !== 1) return $fallback;
        $normalized = rtrim(substr($normalized, 0, 64), '_');
        return preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $normalized) === 1 ? $normalized : $fallback;
    }

    /** @param mixed $value */
    private function insight(mixed $value): RoadmapInsight
    {
        if (!is_array($value)) throw new \InvalidArgumentException('Roadmap insight is invalid.');
        $this->assertExactFields($value, ['category', 'evidence_ref_ids', 'summary', 'title'], 'Roadmap insight fields are invalid.');
        $title = $this->requiredText($value['title'], 'Roadmap insight title is required.');
        $summary = $this->requiredText($value['summary'], 'Roadmap insight summary is required.');
        $this->assertVietnamese($title . ' ' . $summary);
        return new RoadmapInsight(
            $this->requiredText($value['category'], 'Roadmap insight category is required.'),
            $title,
            $summary,
            $this->references($value['evidence_ref_ids'], $this->allowedEvidence, true, 'Roadmap evidence references are invalid.'),
        );
    }

    /** @param mixed $value */
    private function phase(mixed $value): RoadmapPhase
    {
        if (!is_array($value)) throw new \InvalidArgumentException('Roadmap phase is invalid.');
        $this->assertExactFields($value, [
            'code', 'deliverable', 'effort_label', 'end_day', 'evidence_ref_ids', 'goal',
            'metric_label', 'position', 'skill_focus', 'start_day', 'tasks', 'title',
        ], 'Roadmap phase fields are invalid.');
        foreach (['title', 'goal', 'skill_focus', 'deliverable', 'effort_label', 'metric_label'] as $field) {
            $this->assertVietnamese($this->requiredText($value[$field], 'Roadmap phase copy is required.'));
        }
        $taskRecords = $this->list($value['tasks'], 'Roadmap phase tasks are invalid.');
        $tasks = array_map(fn (mixed $task): RoadmapTask => $this->task($task), $taskRecords);
        return new RoadmapPhase(
            $this->integer($value['position'], 'Roadmap phase position is invalid.'),
            $this->integer($value['start_day'], 'Roadmap phase start day is invalid.'),
            $this->integer($value['end_day'], 'Roadmap phase end day is invalid.'),
            $this->requiredText($value['code'], 'Roadmap phase code is required.'),
            (string) $value['title'],
            (string) $value['goal'],
            (string) $value['skill_focus'],
            (string) $value['deliverable'],
            (string) $value['effort_label'],
            (string) $value['metric_label'],
            $this->references($value['evidence_ref_ids'], $this->allowedEvidence, true, 'Roadmap evidence references are invalid.'),
            $tasks,
        );
    }

    /** @param mixed $value */
    private function task(mixed $value): RoadmapTask
    {
        if (!is_array($value)) throw new \InvalidArgumentException('Roadmap task is invalid.');
        $this->assertExactFields($value, ['action', 'description', 'estimated_minutes', 'evidence_ref_ids', 'position', 'title'], 'Roadmap task fields are invalid.');
        $title = $this->requiredText($value['title'], 'Roadmap task title is required.');
        $description = $this->requiredText($value['description'], 'Roadmap task description is required.');
        $this->assertVietnamese($title . ' ' . $description);
        if (!is_array($value['action'])) throw new \InvalidArgumentException('Roadmap action type is unsupported.');
        $action = $value['action'];
        if (($action['type'] ?? null) === 'register_activity') {
            $activityId = $action['activity_source_id'] ?? null;
            if (!is_string($activityId) || !isset($this->allowedActivityIds[$activityId])) {
                throw new \InvalidArgumentException('Roadmap activity source ids are invalid.');
            }
        }
        return new RoadmapTask(
            $this->integer($value['position'], 'Roadmap task position is invalid.'),
            $title,
            $description,
            $this->integer($value['estimated_minutes'], 'Roadmap task estimated minutes are invalid.'),
            $action,
            $this->references($value['evidence_ref_ids'], $this->allowedEvidence, true, 'Roadmap evidence references are invalid.'),
        );
    }

    /** @param array<string,mixed> $metadata @return array<string,string|null> */
    private function modelMetadata(array $metadata): array
    {
        $required = ['confidence_band', 'model_version', 'origin', 'prompt_version', 'provider'];
        $allowed = [...$required, 'provider_request_id', 'response_hash'];
        $actual = array_keys($metadata);
        sort($actual, SORT_STRING);
        sort($allowed, SORT_STRING);
        foreach ($required as $field) {
            if (!array_key_exists($field, $metadata)) throw new \InvalidArgumentException('Roadmap model metadata is invalid.');
        }
        if (array_diff($actual, $allowed) !== []) throw new \InvalidArgumentException('Roadmap model metadata is invalid.');
        if (($metadata['origin'] ?? null) !== 'model') {
            throw new \InvalidArgumentException('Roadmap model metadata is invalid.');
        }
        foreach (['provider', 'model_version', 'prompt_version'] as $field) {
            if (!is_string($metadata[$field]) || trim($metadata[$field]) === '') {
                throw new \InvalidArgumentException('Roadmap model metadata is required.');
            }
        }
        if (!is_string($metadata['confidence_band']) || !in_array($metadata['confidence_band'], ['low', 'medium', 'high'], true)) {
            throw new \InvalidArgumentException('Roadmap model metadata is invalid.');
        }
        return [
            'provider' => trim($metadata['provider']),
            'model_version' => trim($metadata['model_version']),
            'prompt_version' => trim($metadata['prompt_version']),
            'rule_version' => null,
            'fallback_reason' => null,
            'provider_request_id' => is_string($metadata['provider_request_id'] ?? null) ? trim($metadata['provider_request_id']) : null,
            'response_hash' => is_string($metadata['response_hash'] ?? null) ? trim($metadata['response_hash']) : null,
            'confidence_band' => $metadata['confidence_band'],
        ];
    }

    /** @param array<string,mixed> $record @param list<string> $expected */
    private function assertExactFields(array $record, array $expected, string $message): void
    {
        $actual = array_keys($record);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($actual !== $expected) throw new \InvalidArgumentException($message);
    }

    /** @param array<string,mixed> $payload */
    private function assertPayloadFields(array $payload): void
    {
        $actual = array_keys($payload);
        $allowed = [...self::PAYLOAD_FIELDS, ...self::EXTENDED_FIELDS];
        if (array_diff($actual, $allowed) !== [] || array_diff(self::PAYLOAD_FIELDS, $actual) !== []) {
            throw new \InvalidArgumentException('Roadmap provider payload fields are invalid.');
        }
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function extendedPayload(array $payload): array
    {
        $confidence = $payload['confidence'] ?? null;
        if ($confidence !== null && (!is_int($confidence) && !is_float($confidence) || $confidence < 0 || $confidence > 1)) {
            throw new \InvalidArgumentException('Roadmap confidence is invalid.');
        }
        $allowedEvidence = $this->allowedEvidence;
        if (array_key_exists('evidence', $payload)) {
            $this->references($payload['evidence'], $allowedEvidence, true, 'Roadmap evidence references are invalid.');
        }
        $talentMap = $this->extendedRecords($payload['talent_map'] ?? [], ['field', 'score', 'evidence_ref_ids'], 'talent map', static function (array $record): void {
            $score = $record['score'] ?? null;
            if (!is_string($record['field'] ?? null) || trim($record['field']) === '' || (!is_int($score) && !is_float($score)) || $score < 0 || $score > 1) {
                throw new \InvalidArgumentException('Roadmap talent map is invalid.');
            }
        });
        if (count($talentMap) !== 3) {
            throw new \InvalidArgumentException('Roadmap requires exactly three talent map records.');
        }
        $talentFields = array_column($talentMap, 'field');
        sort($talentFields, SORT_STRING);
        $expectedTalentFields = self::TALENT_MAP_FIELDS;
        sort($expectedTalentFields, SORT_STRING);
        if ($talentFields !== $expectedTalentFields) {
            throw new \InvalidArgumentException('Roadmap talent map fields are invalid.');
        }
        $strengths = $this->extendedRecords($payload['strengths'] ?? [], ['text', 'evidence_ref_ids'], 'strengths');
        $improvements = $this->extendedRecords($payload['improvements'] ?? [], ['text', 'evidence_ref_ids'], 'improvements');
        $potentialPaths = $this->extendedRecords($payload['potential_paths'] ?? [], ['label', 'catalog_id', 'evidence_ref_ids'], 'potential paths', function (array $record): void {
            if (array_key_exists('catalog_id', $record)) {
                $catalogId = $record['catalog_id'];
                if (!is_string($catalogId) || !isset($this->allowedCatalogIds[trim($catalogId)])) {
                    throw new \InvalidArgumentException('Roadmap catalog id is invalid.');
                }
            }
        });
        $trendSignals = $this->extendedRecords($payload['trend_signals'] ?? [], ['direction', 'label', 'evidence_ref_ids'], 'trend signals', static function (array $record): void {
            if (!is_string($record['direction'] ?? null) || !in_array($record['direction'], ['up', 'down', 'flat'], true)) {
                throw new \InvalidArgumentException('Roadmap trend signals are invalid.');
            }
        });
        $growthHypotheses = $this->extendedRecords($payload['growth_hypotheses'] ?? [], ['text', 'confidence', 'evidence_ref_ids'], 'growth hypotheses', static function (array $record): void {
            if (!is_numeric($record['confidence'] ?? null) || $record['confidence'] < 0 || $record['confidence'] > 1) {
                throw new \InvalidArgumentException('Roadmap growth hypotheses are invalid.');
            }
        });
        return [
            'talent_map' => $talentMap,
            'strengths' => $strengths,
            'improvements' => $improvements,
            'potential_paths' => $potentialPaths,
            'trend_signals' => $trendSignals,
            'growth_hypotheses' => $growthHypotheses,
            'confidence' => $confidence === null ? 0.0 : (float) $confidence,
        ];
    }

    /** @param mixed $value @param list<string> $fields @return list<array<string,mixed>> */
    private function extendedRecords(mixed $value, array $fields, string $label, ?callable $extra = null): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('Roadmap ' . $label . ' are invalid.');
        }
        $result = [];
        foreach ($value as $record) {
            if (!is_array($record)) {
                throw new \InvalidArgumentException('Roadmap ' . $label . ' are invalid.');
            }
            $actual = array_keys($record);
            if (array_diff($actual, $fields) !== [] || array_diff(['evidence_ref_ids'], $actual) !== []) {
                throw new \InvalidArgumentException('Roadmap ' . $label . ' fields are invalid.');
            }
            $text = $record['text'] ?? $record['label'] ?? $record['field'] ?? null;
            if (!is_string($text) || trim($text) === '') {
                throw new \InvalidArgumentException('Roadmap ' . $label . ' text is required.');
            }
            $record['evidence_ref_ids'] = $this->references($record['evidence_ref_ids'], $this->allowedEvidence, true, 'Roadmap ' . $label . ' evidence references are invalid.');
            if ($extra !== null) {
                $extra($record);
            }
            $result[] = $record;
        }
        return $result;
    }

    /** @param mixed $value @return list<mixed> */
    private function list(mixed $value, string $message): array
    {
        if (!is_array($value) || !array_is_list($value)) throw new \InvalidArgumentException($message);
        return $value;
    }

    /** @param mixed $value */
    private function requiredText(mixed $value, string $message): string
    {
        if (!is_string($value) || trim($value) === '') throw new \InvalidArgumentException($message);
        return trim($value);
    }

    /** @param mixed $value */
    private function integer(mixed $value, string $message): int
    {
        if (!is_int($value)) throw new \InvalidArgumentException($message);
        return $value;
    }

    private function assertVietnamese(string $value): void
    {
        if (preg_match('/[À-ỹĐđ]/u', $value) !== 1
            && preg_match('/\b(bạn|của|và|phát triển|kỹ năng|hoàn thành|phản hồi)\b/iu', $value) !== 1) {
            throw new \InvalidArgumentException('Roadmap learner text must be Vietnamese.');
        }
    }

    /** @param mixed $value @param array<string,bool> $allowList @return list<string> */
    private function references(mixed $value, array $allowList, bool $required, string $message): array
    {
        if (!is_array($value) || !array_is_list($value)) throw new \InvalidArgumentException($message);
        $normalized = [];
        foreach ($value as $reference) {
            if (!is_string($reference) || trim($reference) === '' || !isset($allowList[trim($reference)])) {
                throw new \InvalidArgumentException($message);
            }
            $normalized[trim($reference)] = true;
        }
        if ($required && $normalized === []) throw new \InvalidArgumentException($message);
        $result = array_keys($normalized);
        sort($result, SORT_STRING);
        return $result;
    }

    /** @param list<string> $values @return array<string,bool> */
    private function allowList(array $values, string $message): array
    {
        $result = [];
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') throw new \InvalidArgumentException($message);
            $result[trim($value)] = true;
        }
        return $result;
    }
}
