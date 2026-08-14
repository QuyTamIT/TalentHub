<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\ReadModel;

use TalentHub\Learner\Data\Contracts\AssessmentRepository;
use TalentHub\Learner\Data\Support\Uuid;

final class AssessmentReadModel
{
    private const HOLLAND_DIMENSIONS = ['R', 'I', 'A', 'S', 'E', 'C'];

    public static function definition(array $record): array
    {
        $record['short_name'] ??= $record['name'] ?? null;
        $record['route_id'] ??= $record['id'] ?? null;

        return ReadModelDefaults::apply($record, [
            'id' => '',
            'route_id' => '',
            'code' => '',
            'version' => 'Không có trong schema',
            'name' => 'Bài đánh giá',
            'short_name' => 'Bài đánh giá',
            'description' => 'Nội dung mô tả chưa có trong schema hiện tại.',
            'source' => 'database',
            'source_role' => 'unknown',
            'status' => 'unknown',
            'question_count' => 0,
            'duration_minutes' => 0,
            'retake_days' => 0,
            'disclaimer' => 'Kết quả chỉ mang tính định hướng; schema hiện tại chưa cung cấp tuyên bố chi tiết.',
        ], 'assessment');
    }

    public static function question(array $record): array
    {
        $record['prompt'] ??= $record['content'] ?? null;
        $record['assessment_version'] ??= 'Không có trong schema';
        if (isset($record['dimension']) && trim((string) $record['dimension']) !== '') {
            $record['dimension'] = strtoupper(trim((string) $record['dimension']));
        }
        $record['options'] = self::normalizeQuestionOptions(
            is_array($record['options'] ?? null) ? $record['options'] : []
        );

        return ReadModelDefaults::apply($record, [
            'id' => '',
            'assessment_id' => '',
            'assessment_version' => 'Không có trong schema',
            'position' => 0,
            'dimension' => 'unknown',
            'dimension_name' => 'Chưa phân loại',
            'question_type' => 'single_choice',
            'prompt' => '',
            'required' => false,
            'options' => [],
        ], 'assessment_question');
    }

    public static function attempt(array $record): array
    {
        $record['assessment_version'] ??= 'Không có trong schema';
        $record['submitted_at'] ??= $record['completed_at'] ?? null;
        if (is_array($record['result'] ?? null)) {
            $result = $record['result'];
            $scores = $result['scores'] ?? $result['dimension_scores'] ?? [];
            $primary = $result['primary_dimension'] ?? self::primaryDimension($scores);
            $record['result'] = array_replace([
                'code' => $result['result_code'] ?? '',
                'scores' => $scores,
                'primary_dimension' => $primary,
            ], $result);
        }

        return ReadModelDefaults::apply($record, [
            'id' => '',
            'student_id' => '',
            'assessment_id' => '',
            'assessment_version' => 'Không có trong schema',
            'status' => 'unknown',
            'started_at' => null,
            'updated_at' => null,
            'expires_at' => null,
            'submitted_at' => null,
            'answers' => [],
            'result' => null,
        ], 'assessment_attempt');
    }

    public static function normalizeQuestionOptions(array $options): array
    {
        $normalized = [];
        foreach ($options as $option) {
            if (is_int($option) || is_float($option) || (is_string($option) && is_numeric($option))) {
                $value = is_string($option) ? $option + 0 : $option;
                $normalized[] = ['value' => $value, 'label' => (string) $option];
                continue;
            }
            if (!is_array($option) || !array_key_exists('value', $option)) {
                continue;
            }
            $value = $option['value'];
            if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
                continue;
            }
            $value = is_string($value) ? $value + 0 : $value;
            $label = trim((string) ($option['label'] ?? $value));
            if ($label === '') {
                continue;
            }
            $normalized[] = ['value' => $value, 'label' => $label];
        }

        return $normalized;
    }

    public static function isHollandReady(array $questions): bool
    {
        if (count($questions) !== 24) {
            return false;
        }

        $questionIds = [];
        $dimensionCounts = array_fill_keys(self::HOLLAND_DIMENSIONS, 0);
        foreach ($questions as $record) {
            $question = self::question($record);
            $id = trim((string) ($question['id'] ?? ''));
            $prompt = trim((string) ($question['prompt'] ?? ''));
            $dimension = (string) ($question['dimension'] ?? '');
            if ($id === '' || isset($questionIds[$id]) || $prompt === ''
                || !in_array($dimension, self::HOLLAND_DIMENSIONS, true)
                || !self::hasValidHollandOptions($question['options'])) {
                return false;
            }
            $questionIds[$id] = true;
            $dimensionCounts[$dimension]++;
        }

        foreach ($dimensionCounts as $count) {
            if ($count !== 4) {
                return false;
            }
        }

        return true;
    }

    public static function definitions(array $records): array
    {
        return array_map([self::class, 'definition'], $records);
    }

    public static function questions(array $records): array
    {
        return array_map([self::class, 'question'], $records);
    }

    public static function attempts(array $records): array
    {
        return array_map([self::class, 'attempt'], $records);
    }

    public static function completedAttempts(array $records): array
    {
        return array_values(array_filter(
            self::attempts($records),
            static fn (array $attempt): bool => in_array(
                (string) ($attempt['status'] ?? ''),
                ['submitted', 'completed'],
                true
            ) && self::hasValidResult($attempt['result'] ?? null)
        ));
    }

    public static function resolve(AssessmentRepository $repository, string $routeId): ?array
    {
        if (Uuid::isValid($routeId)) {
            $record = $repository->findById($routeId);
            return $record === null ? null : self::definition($record);
        }

        $needle = strtolower(trim($routeId));
        foreach ($repository->all() as $record) {
            $view = self::definition($record);
            $name = strtolower((string) ($view['name'] ?? ''));
            $type = strtolower((string) ($view['type'] ?? ''));
            $legacyId = strtolower((string) ($view['id'] ?? ''));
            if ($legacyId === $needle
                || ($needle === 'holland' && (str_contains($name, 'holland') || $type === 'riasec'))) {
                return $view;
            }
        }

        return null;
    }

    private static function primaryDimension(array $scores): string
    {
        if ($scores === []) {
            return '';
        }
        arsort($scores);
        return (string) array_key_first($scores);
    }

    private static function hasValidHollandOptions(array $options): bool
    {
        if (count($options) !== 5) {
            return false;
        }
        $values = [];
        foreach ($options as $option) {
            $value = $option['value'] ?? null;
            $label = trim((string) ($option['label'] ?? ''));
            if (!is_numeric($value) || $label === '') {
                return false;
            }
            $values[] = (float) $value;
        }

        sort($values);
        return $values === [1.0, 2.0, 3.0, 4.0, 5.0];
    }

    private static function hasValidResult(mixed $result): bool
    {
        if (!is_array($result)) {
            return false;
        }
        $primary = strtoupper(trim((string) ($result['primary_dimension'] ?? '')));
        $code = strtoupper(trim((string) ($result['code'] ?? '')));
        $scores = $result['scores'] ?? null;

        if (preg_match('/^[RIASEC]{1,6}$/', $code) !== 1
            || !in_array($primary, self::HOLLAND_DIMENSIONS, true)
            || !is_array($scores)
            || $scores === []
            || !array_key_exists($primary, $scores)) {
            return false;
        }
        $scoreDimensions = array_map('strval', array_keys($scores));
        sort($scoreDimensions);
        $requiredDimensions = self::HOLLAND_DIMENSIONS;
        sort($requiredDimensions);
        if ($scoreDimensions !== $requiredDimensions) {
            return false;
        }
        foreach ($scores as $dimension => $score) {
            if (!in_array((string) $dimension, self::HOLLAND_DIMENSIONS, true) || !is_numeric($score)) {
                return false;
            }
        }

        return true;
    }
}
