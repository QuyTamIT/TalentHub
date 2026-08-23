<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use InvalidArgumentException;

final class BadgeRuleEngine
{
    public const ALLOWED_FACTS = [
        'confirmed_experience_hours',
        'attended_activity_count',
        'submitted_assessment_type_count',
        'published_teacher_evaluation_count',
    ];

    public const ALLOWED_OPERATORS = [
        'gte',
    ];

    /**
     * Evaluate rule criteria against learner fact values.
     *
     * @param array<string,mixed> $criteria
     * @param array<string,int|float> $facts
     * @return array{
     *     eligible: bool,
     *     fact: string,
     *     current: int|float,
     *     target: int|float,
     *     progressPercent: int
     * }
     * @throws InvalidArgumentException
     */
    public function evaluate(array $criteria, array $facts): array
    {
        // Exact keys check: only 'fact', 'operator', 'value'
        $keys = array_keys($criteria);
        sort($keys);
        if ($keys !== ['fact', 'operator', 'value']) {
            throw new InvalidArgumentException('Rule criteria must contain exactly fact, operator, and value keys.');
        }

        $fact = $criteria['fact'];
        if (!is_string($fact) || !in_array($fact, self::ALLOWED_FACTS, true)) {
            throw new InvalidArgumentException("Unknown or disallowed rule fact: " . (is_string($fact) ? $fact : gettype($fact)));
        }

        $operator = $criteria['operator'];
        if (!is_string($operator) || !in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw new InvalidArgumentException("Unknown or disallowed rule operator: " . (is_string($operator) ? $operator : gettype($operator)));
        }

        $value = $criteria['value'];
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('Rule threshold value must be numeric.');
        }
        if (!is_finite((float) $value) || $value < 0) {
            throw new InvalidArgumentException('Rule threshold value must be non-negative and finite.');
        }

        $currentRaw = $facts[$fact] ?? 0;
        $current = is_numeric($currentRaw) ? (is_float($currentRaw) || is_int($currentRaw) ? $currentRaw : (float) $currentRaw) : 0;
        $current = max(0, $current);

        $target = $value;
        $eligible = false;

        if ($operator === 'gte') {
            $eligible = ($current >= $target);
        }

        $progressPercent = 100;
        if ($target > 0) {
            $progressPercent = (int) min(100, max(0, floor(($current / $target) * 100)));
        }

        return [
            'eligible' => $eligible,
            'fact' => $fact,
            'current' => $current,
            'target' => $target,
            'progressPercent' => $progressPercent,
        ];
    }
}
