<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Observability;

/**
 * Collects bounded, structured AI operational metrics. No learner identifiers,
 * prompts, provider responses, credentials, or authorization material is kept.
 */
final class AiMetricsCollector
{
    public const SCHEMA = 'ai-observability-v1';

    /** @var list<array<string,mixed>> */
    private array $events = [];
    /** @var array<string,int|float|string|null> */
    private array $gauges = [];
    /** @var array<string,int> */
    private array $queueEvents = [];
    private static ?self $shared = null;
    /** @var \Closure(array<string,mixed>):void|null */
    private readonly ?\Closure $sink;

    public static function shared(): self
    {
        return self::$shared ??= new self(1000, static function (array $event): void {
            // The application logger/collector can ingest this JSON. The
            // event has already passed the allow-list sanitizer above.
            error_log(json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        });
    }

    /** @param callable(array<string,mixed>):void|null $sink */
    public function __construct(private readonly int $maxEvents = 1000, ?callable $sink = null)
    {
        if ($maxEvents < 1) {
            throw new \InvalidArgumentException('maxEvents must be positive.');
        }
        $this->sink = $sink === null ? null : \Closure::fromCallable($sink);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function record(array $input): array
    {
        $event = [
            'metric_schema' => self::SCHEMA,
            'occurred_at' => gmdate('c'),
        ];
        foreach ([
            'queue_depth', 'queue_oldest_age_seconds', 'queue_age_seconds', 'freshness_age_seconds', 'model_freshness_seconds',
            'provider_latency_ms', 'provider_quota_remaining', 'input_tokens',
            'output_tokens', 'estimated_cost',
        ] as $key) {
            if (array_key_exists($key, $input) && is_numeric($input[$key])) {
                $value = (float) $input[$key];
                $event[$key] = $key === 'estimated_cost' ? max(0.0, round($value, 8)) : max(0, (int) $value);
                if (in_array($key, ['queue_depth', 'queue_oldest_age_seconds', 'queue_age_seconds', 'freshness_age_seconds', 'model_freshness_seconds', 'provider_quota_remaining'], true)) {
                    $this->gauges[$key] = $event[$key];
                }
            }
        }
        foreach (['stale', 'fallback', 'recommendation_click'] as $key) {
            if (array_key_exists($key, $input)) $event[$key] = (bool) $input[$key];
        }
        if (isset($input['provider_error']) && is_string($input['provider_error'])) {
            $event['provider_error'] = $this->category($input['provider_error']);
        }
        if (isset($input['queue_error']) && is_string($input['queue_error'])) {
            $event['queue_error'] = $this->category($input['queue_error']);
        }
        if (isset($input['circuit_state']) && is_string($input['circuit_state'])) {
            $state = strtolower(trim($input['circuit_state']));
            if (in_array($state, ['closed', 'open', 'half_open'], true)) { $event['circuit_state'] = $state; $this->gauges['circuit_state'] = $state; }
        }
        if (isset($input['queue_event']) && is_string($input['queue_event'])) {
            $queueEvent = strtolower(trim($input['queue_event']));
            if (in_array($queueEvent, ['claimed', 'completed', 'failed', 'dead_letter', 'cancelled', 'idle'], true)) {
                $event['queue_event'] = $queueEvent;
                $this->queueEvents[$queueEvent] = ($this->queueEvents[$queueEvent] ?? 0) + 1;
            }
        }
        if (isset($input['recommendation_feedback']) && is_string($input['recommendation_feedback'])) {
            $feedback = strtolower(trim($input['recommendation_feedback']));
            if (in_array($feedback, ['helpful', 'not_helpful', 'dismissed'], true)) $event['recommendation_feedback'] = $feedback;
        }
        if (isset($input['recommendation_action']) && is_string($input['recommendation_action'])) {
            $action = strtolower(trim($input['recommendation_action']));
            if (in_array($action, ['view_activity', 'view_opportunity', 'register_activity', 'open_catalog_item', 'join_group'], true)) $event['recommendation_action'] = $action;
        }
        $this->events[] = $event;
        if (count($this->events) > $this->maxEvents) array_shift($this->events);
        if ($this->sink !== null) {
            try { ($this->sink)($event); } catch (\Throwable) { /* telemetry must never break an AI request */ }
        }
        return $event;
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        $count = count($this->events);
        $provider = array_values(array_filter($this->events, static fn(array $e): bool => isset($e['provider_latency_ms']) || isset($e['provider_error'])));
        $providerErrors = count(array_filter($provider, static fn(array $e): bool => isset($e['provider_error'])));
        $withClick = array_values(array_filter($this->events, static fn(array $e): bool => array_key_exists('recommendation_click', $e)));
        $withFeedback = array_values(array_filter($this->events, static fn(array $e): bool => isset($e['recommendation_feedback'])));
        $withStale = array_values(array_filter($this->events, static fn(array $e): bool => array_key_exists('stale', $e)));
        $withFallback = array_values(array_filter($this->events, static fn(array $e): bool => array_key_exists('fallback', $e)));
        $latest = $this->events[$count - 1] ?? [];
        $cost = array_sum(array_map(static fn(array $e): float => (float) ($e['estimated_cost'] ?? 0.0), $this->events));
        return [
            'metric_schema' => self::SCHEMA,
            'sample_count' => $count,
            'queue_depth' => (int) ($this->gauges['queue_depth'] ?? 0),
            'queue_age_seconds' => (int) ($this->gauges['queue_oldest_age_seconds'] ?? $this->gauges['queue_age_seconds'] ?? 0),
            'queue_oldest_age_seconds' => (int) ($this->gauges['queue_oldest_age_seconds'] ?? $this->gauges['queue_age_seconds'] ?? 0),
            'freshness_age_seconds' => (int) ($this->gauges['freshness_age_seconds'] ?? $this->gauges['model_freshness_seconds'] ?? 0),
            'model_freshness_seconds' => (int) ($this->gauges['model_freshness_seconds'] ?? $this->gauges['freshness_age_seconds'] ?? 0),
            'stale_ratio' => $withStale === [] ? 0.0 : round(count(array_filter($withStale, static fn(array $e): bool => $e['stale'] === true)) / count($withStale), 6),
            'provider_latency_ms' => $this->average($provider, 'provider_latency_ms'),
            'provider_latency_p95_ms' => $this->percentile($provider, 'provider_latency_ms', 0.95),
            'provider_error_rate' => $provider === [] ? 0.0 : round($providerErrors / count($provider), 6),
            'provider_quota_remaining' => isset($this->gauges['provider_quota_remaining']) ? (int) $this->gauges['provider_quota_remaining'] : null,
            'circuit_state' => $this->gauges['circuit_state'] ?? 'closed',
            'fallback_rate' => $withFallback === [] ? 0.0 : round(count(array_filter($withFallback, static fn(array $e): bool => $e['fallback'] === true)) / count($withFallback), 6),
            'recommendation_click_rate' => $withClick === [] ? 0.0 : round(count(array_filter($withClick, static fn(array $e): bool => ($e['recommendation_click'] ?? false) === true)) / count($withClick), 6),
            'recommendation_feedback_rate' => $withFeedback === [] ? 0.0 : round(count($withFeedback) / max(1, $count), 6),
            'input_tokens' => array_sum(array_map(static fn(array $e): int => (int) ($e['input_tokens'] ?? 0), $this->events)),
            'output_tokens' => array_sum(array_map(static fn(array $e): int => (int) ($e['output_tokens'] ?? 0), $this->events)),
            'token_cost' => round($cost, 8),
            'queue_events' => $this->queueEvents,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function events(): array { return $this->events; }

    /** Sanitize an event without retaining it; useful at logging boundaries. */
    public function sanitize(array $input): array
    {
        $probe = new self(1);
        return $probe->record($input);
    }

    /** @param array<string,mixed> $event */
    public function toJson(array $event): string
    {
        return json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @param list<array<string,mixed>> $events */
    private function average(array $events, string $key): ?float
    {
        $values = array_values(array_filter(array_map(static fn(array $e): ?float => isset($e[$key]) ? (float) $e[$key] : null, $events), static fn(?float $v): bool => $v !== null));
        return $values === [] ? null : round(array_sum($values) / count($values), 3);
    }

    /** @param list<array<string,mixed>> $events */
    private function percentile(array $events, string $key, float $quantile): ?float
    {
        $values = array_values(array_filter(array_map(static fn(array $e): ?float => isset($e[$key]) ? (float) $e[$key] : null, $events), static fn(?float $v): bool => $v !== null));
        if ($values === []) return null;
        sort($values, SORT_NUMERIC);
        return round($values[max(0, (int) ceil($quantile * count($values)) - 1)], 3);
    }

    private function category(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['quota_exhausted', 'rate_limited', 'rate_limit_exceeded', 'timeout', 'provider_unavailable', 'invalid_credentials', 'malformed_output', 'malformed_outbox', 'outbox_dispatch_failed', 'school_refresh_dispatch_failed', 'refresh_lease_lost', 'capability_refresh_unavailable', 'server_error'], true) ? $value : 'provider_error';
    }
}
