<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Service;

use InvalidArgumentException;

final class RubricScoreCalculator
{
    public const FORMULA_VERSION = 'rubric-weighted-1.0';
    public const SCORE_METHOD = 'rubric_weighted';

    /**
     * Calculates weighted rubric score based on rubric-weighted-1.0 specification.
     *
     * @param list<array<string,mixed>> $criteria
     * @return array{
     *     score: float,
     *     formula_version: string,
     *     score_method: string,
     *     calculation: array<string,mixed>
     * }
     */
    public function calculate(array $criteria): array
    {
        if ($criteria === []) {
            throw new InvalidArgumentException('Rubric criteria list must not be empty.');
        }

        $weightSum = 0.0;
        $weightedAttainmentSum = 0.0;
        $criteriaDetails = [];

        foreach ($criteria as $index => $criterion) {
            if (!is_array($criterion)) {
                throw new InvalidArgumentException("Criterion at index {$index} must be an array.");
            }

            $id = (string) ($criterion['id'] ?? $criterion['criteria_id'] ?? $criterion['criteriaId'] ?? $index);
            $code = (string) ($criterion['code'] ?? $id);
            $label = (string) ($criterion['label'] ?? $criterion['name'] ?? $code);

            // Min check
            $rawMin = $criterion['min'] ?? 0.0;
            if (!is_numeric($rawMin) || (float) $rawMin !== 0.0) {
                throw new InvalidArgumentException("Criterion '{$code}' min must be 0.");
            }
            $min = 0.0;

            // Max check
            $rawMax = $criterion['max'] ?? 10.0;
            if (!is_numeric($rawMax) || (float) $rawMax <= 0.0) {
                throw new InvalidArgumentException("Criterion '{$code}' max must be positive.");
            }
            $max = (float) $rawMax;

            // Weight check
            $rawWeight = $criterion['weight'] ?? 1.0;
            if (!is_numeric($rawWeight) || (float) $rawWeight <= 0.0) {
                throw new InvalidArgumentException("Criterion '{$code}' weight must be positive.");
            }
            $weight = (float) $rawWeight;

            // Required check
            $required = (bool) ($criterion['required'] ?? true);
            $rawScore = $criterion['score'] ?? null;

            if ($rawScore === null || $rawScore === '') {
                if ($required) {
                    throw new InvalidArgumentException("Required criterion '{$code}' is missing a score.");
                }
                continue;
            }

            if (!is_numeric($rawScore)) {
                throw new InvalidArgumentException("Criterion '{$code}' score must be numeric.");
            }

            $score = (float) $rawScore;
            if ($score < $min || $score > $max) {
                throw new InvalidArgumentException("Criterion '{$code}' score must be within {$min}..{$max}.");
            }

            $attainment = $score / $max;
            $weightSum += $weight;
            $weightedAttainmentSum += $weight * $attainment;

            $criteriaDetails[] = [
                'id' => $id,
                'code' => $code,
                'label' => $label,
                'score' => $score,
                'min' => $min,
                'max' => $max,
                'weight' => $weight,
                'required' => $required,
                'attainment' => round($attainment, 4),
            ];
        }

        if ($weightSum <= 0.0 || $criteriaDetails === []) {
            throw new InvalidArgumentException('Rubric calculation requires at least one scored criterion with positive weight.');
        }

        $overallScore = round(100.0 * ($weightedAttainmentSum / $weightSum), 2);

        return [
            'score' => $overallScore,
            'formula_version' => self::FORMULA_VERSION,
            'score_method' => self::SCORE_METHOD,
            'calculation' => [
                'formula' => self::FORMULA_VERSION,
                'rounding' => 'half_up_2_decimals',
                'weight_sum' => round($weightSum, 4),
                'weighted_sum' => round($weightedAttainmentSum, 4),
                'total_score' => $overallScore,
                'criteria' => $criteriaDetails,
            ],
        ];
    }
}