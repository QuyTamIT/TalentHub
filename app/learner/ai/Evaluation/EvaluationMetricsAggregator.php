<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class EvaluationMetricsAggregator
{
    /** @param list<array<string,mixed>> $records @return array<string,mixed> */
    public function aggregate(array $records, ApprovedEvaluationThresholds $thresholds): array
    {
        if (!$thresholds->isApproved()) return ['status' => 'blocked', 'reason' => 'thresholds_missing'];
        $provider = array_values(array_filter($records, static fn(array $r): bool => ($r['resultType'] ?? '') !== 'blocked_before_call'));
        $latencies = array_values(array_map('floatval', array_column(array_filter($provider, static fn(array $r): bool => $r['latencyMs'] !== null), 'latencyMs')));
        sort($latencies, SORT_NUMERIC);
        $errors = count(array_filter($provider, static fn(array $r): bool => ($r['providerErrorCategory'] ?? null) !== null));
        $usage = count(array_filter($provider, static fn(array $r): bool => ($r['inputTokens'] ?? null) !== null && ($r['outputTokens'] ?? null) !== null));
        $costRows = array_values(array_filter($provider, static fn(array $r): bool => $r['estimatedCost'] !== null));
        $currencies = array_values(array_unique(array_filter(array_column($costRows, 'costCurrency'), 'is_string')));
        if (count($currencies) > 1) throw new \InvalidArgumentException('Mixed evaluation currencies cannot be aggregated.');
        $cost = $costRows === [] ? null : round(array_sum(array_map(static fn(array $r): float => (float) $r['estimatedCost'], $costRows)) / count($costRows), 8);
        $count = count($provider);
        return [
            'status' => 'measured', 'sample_size' => count($records), 'provider_run_count' => $count,
            'latency_p50_ms' => $this->percentile($latencies, 0.50), 'latency_p95_ms' => $this->percentile($latencies, 0.95),
            'provider_error_rate' => $count === 0 ? null : round($errors / $count, 6),
            'usage_coverage' => $count === 0 ? null : round($usage / $count, 6),
            'cost_per_provider_run' => $cost, 'cost_currency' => $currencies[0] ?? null,
            'thresholds' => $thresholds->toArray(),
        ];
    }
    /** @param list<float> $values */
    private function percentile(array $values, float $quantile): ?float
    {
        if ($values === []) return null;
        $index = max(0, (int) ceil($quantile * count($values)) - 1);
        return round((float) $values[$index], 3);
    }
}
