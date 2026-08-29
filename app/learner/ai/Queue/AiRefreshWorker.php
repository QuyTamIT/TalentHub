<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Queue;

use ReflectionClass;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;
use TalentHub\Learner\Ai\Provider\ProviderRetryAfterException;
use Throwable;

final class AiRefreshWorker
{
    /** @var \Closure(AiRefreshJob, callable): void */
    private readonly \Closure $handler;
    private readonly AiMetricsCollector $metrics;

    public function __construct(
        private readonly AiRefreshJobRepository $jobs,
        callable $handler,
        private readonly int $maxAttempts = 5,
        private readonly ?AiDataOutboxConsumer $outbox = null,
        ?AiMetricsCollector $metrics = null
    ) {
        $this->handler = \Closure::fromCallable($handler);
        $this->metrics = $metrics ?? AiMetricsCollector::shared();
    }

    public function runOnce(string $workerId): bool
    {
        if ($this->outbox !== null) {
            $this->outbox->consume(100);
        }
        $this->recordQueueGauge();

        $job = $this->jobs->claimNext($workerId, 240);
        if ($job === null) {
            $this->metrics->record(['queue_event' => 'idle']);
            return false;
        }

        $this->metrics->record(['queue_event' => 'claimed']);

        $guard = function () use ($job): bool {
            return $job->leaseToken !== null && $this->jobs->renewLease($job->jobKey, $job->leaseToken, 240);
        };

        try {
            if (!$guard()) {
                throw new \RuntimeException('refresh_lease_lost');
            }

            ($this->handler)($job, $guard);

            if (!$this->jobs->ownsLease($job->jobKey, (string) $job->leaseToken)) {
                throw new \RuntimeException('refresh_lease_lost');
            }

            $this->jobs->complete($job->jobKey, $job->leaseToken);
            $this->metrics->record(['queue_event' => 'completed']);
        } catch (Throwable $e) {
            $retry = $e instanceof ProviderRetryAfterException ? $e->retryAfterSeconds() : null;
            $error = $e instanceof ProviderRetryAfterException ? $e->safeCategory() : self::safeError($e);

            if ($error === 'superseded_snapshot') {
                $this->jobs->cancelSuperseded($job->studentId, $job->capability, $job->snapshotHash);
                $this->metrics->record(['queue_event' => 'cancelled', 'reason' => 'superseded_snapshot']);
            } else {
                $isDead = $job->attempts >= $this->maxAttempts;
                $this->jobs->fail($job->jobKey, $error, $isDead, $job->leaseToken, $retry);
                $this->metrics->record([
                    'queue_event' => $isDead ? 'dead_letter' : 'failed',
                    'provider_error' => $error,
                ]);
            }
        } finally {
            $this->recordQueueGauge();
        }

        return true;
    }

    private function recordQueueGauge(): void
    {
        if (method_exists($this->jobs, 'pendingStats')) {
            $stats = $this->jobs->pendingStats();
            $this->metrics->record([
                'queue_depth' => $stats['depth'] ?? 0,
                'queue_oldest_age_seconds' => $stats['oldest_age_seconds'] ?? 0,
            ]);
        }
    }

    private static function safeError(Throwable $e): string
    {
        if ($e instanceof ProviderRetryAfterException) {
            return $e->safeCategory();
        }

        $msg = strtolower(trim($e->getMessage()));
        if (in_array($msg, [
            'refresh_lease_lost',
            'provider_unavailable',
            'superseded_snapshot',
            'capability_refresh_unavailable',
            'rate_limit_exceeded',
            'data_insufficient',
            'consent_required',
        ], true)) {
            return $msg;
        }

        $code = strtolower((new ReflectionClass($e))->getShortName());
        return preg_replace('/[^a-z0-9_]/', '_', substr($code, 0, 80)) ?: 'refresh_failed';
    }
}
