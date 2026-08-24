<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Rules;

final class CareerGroupClassifier
{
    private const HOLLAND_DIMENSIONS = ['R', 'I', 'A', 'S', 'E', 'C'];

    private const ALLOWED_TEST_CODES = [
        'holland',
        'holland_middle',
        'holland_high',
        'holland_college',
    ];

    /**
     * Group mapping definitions:
     * code => ['label' => Vietnamese label, 'dimensions' => contributing Holland dimensions]
     */
    public const GROUPS = [
        'technical' => [
            'label' => 'Kỹ thuật',
            'dimensions' => ['R', 'I'],
        ],
        'business' => [
            'label' => 'Kinh doanh',
            'dimensions' => ['E'],
        ],
        'arts' => [
            'label' => 'Nghệ thuật',
            'dimensions' => ['A'],
        ],
        'sports_academic' => [
            'label' => 'Thể thao & Học thuật',
            'dimensions' => ['S', 'C'],
        ],
    ];

    /**
     * Classify Holland dimension scores into 4 deterministic career groups.
     *
     * @param array<string, mixed> $dimensionScores Map of dimension code (R, I, A, S, E, C) => numeric score [0, 100]
     * @param string $testCode Must be 'holland', 'holland_middle', 'holland_high', or 'holland_college' (case-insensitive)
     * @return list<array{code: string, label: string, score: float, contributing_dimensions: list<string>}>
     */
    public function classify(array $dimensionScores, string $testCode = 'holland'): array
    {
        $normalizedTestCode = strtolower(trim($testCode));
        if (!in_array($normalizedTestCode, self::ALLOWED_TEST_CODES, true) || $dimensionScores === []) {
            return [];
        }

        // Validate all dimension scores are numeric and in range [0, 100]
        $normalizedScores = [];
        foreach ($dimensionScores as $dim => $score) {
            if (!is_string($dim) || !is_numeric($score)) {
                return [];
            }
            $normalizedDimension = strtoupper(trim($dim));
            if (!in_array($normalizedDimension, self::HOLLAND_DIMENSIONS, true)
                || array_key_exists($normalizedDimension, $normalizedScores)) {
                return [];
            }
            $floatScore = (float) $score;
            if ($floatScore < 0.0 || $floatScore > 100.0) {
                return [];
            }
            $normalizedScores[$normalizedDimension] = $floatScore;
        }

        if (count($normalizedScores) !== count(self::HOLLAND_DIMENSIONS) || max($normalizedScores) <= 0.0) {
            return [];
        }

        $ranked = [];
        foreach (self::GROUPS as $code => $definition) {
            $maxScore = 0.0;
            foreach ($definition['dimensions'] as $dim) {
                if (isset($normalizedScores[$dim])) {
                    $maxScore = max($maxScore, $normalizedScores[$dim]);
                }
            }

            $ranked[] = [
                'code' => $code,
                'label' => $definition['label'],
                'score' => $maxScore,
                'contributing_dimensions' => $definition['dimensions'],
            ];
        }

        // Sort descending by score, tie-break ascending by group code
        usort($ranked, static function (array $left, array $right): int {
            if ($left['score'] !== $right['score']) {
                return $right['score'] <=> $left['score'];
            }
            return strcmp($left['code'], $right['code']);
        });

        return $ranked;
    }

    /**
     * Return the top-ranked career group, or null if classification is invalid.
     *
     * @param array<string, mixed> $dimensionScores
     * @param string $testCode
     * @return array{code: string, label: string, score: float, contributing_dimensions: list<string>}|null
     */
    public function topGroup(array $dimensionScores, string $testCode = 'holland'): ?array
    {
        $classified = $this->classify($dimensionScores, $testCode);
        return $classified[0] ?? null;
    }
}
