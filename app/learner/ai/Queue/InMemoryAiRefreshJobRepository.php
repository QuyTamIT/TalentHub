<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Queue;

final class InMemoryAiRefreshJobRepository implements ScopedAiRefreshJobRepository
{
    /** @var array<string,AiRefreshJob> */
    private array $jobs = [];
    private int $sequence = 0;

    public function enqueue(string $studentId, string $snapshotHash, string $capability): AiRefreshJob
    {
        $key = AiRefreshJob::key($studentId, $snapshotHash, $capability);
        if (!isset($this->jobs[$key])) {
            // Keep a deterministic creation marker so ordering matches the database
            // repository even when several jobs are enqueued within one second.
            $createdAt = sprintf('%.6F-%08d', microtime(true), $this->sequence++);
            $this->jobs[$key] = new AiRefreshJob($key, $studentId, $capability, $snapshotHash, 'pending', 0, null, null, null, null, null, $createdAt);
        }
        return $this->jobs[$key];
    }

    public function claimNext(string $workerId, int $leaseSeconds = 60): ?AiRefreshJob
    {
        return $this->claimNextInternal($workerId, null, $leaseSeconds);
    }

    public function claimNextForStudent(string $workerId, string $studentId, int $leaseSeconds = 60): ?AiRefreshJob
    {
        $studentId = trim($studentId);
        if ($studentId === '') {
            throw new \InvalidArgumentException('student_id_required');
        }
        return $this->claimNextInternal($workerId, $studentId, $leaseSeconds);
    }

    private function claimNextInternal(string $workerId, ?string $studentId, int $leaseSeconds): ?AiRefreshJob
    {
        $now = time();
        $candidates = [];

        foreach ($this->jobs as $key => $job) {
            if ($studentId !== null && $job->studentId !== $studentId) {
                continue;
            }
            $isPendingReady = ($job->status === 'pending' && ($job->nextRetryAt === null || strtotime($job->nextRetryAt) <= $now));
            $isLeaseExpired = ($job->status === 'processing' && $job->leaseUntil !== null && strtotime($job->leaseUntil) < $now);

            if ($isPendingReady || $isLeaseExpired) {
                $candidates[$key] = $job;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        $priorityMap = ['roadmap' => 1, 'recommendation' => 2, 'profile_analysis' => 3];
        uasort($candidates, static function (AiRefreshJob $a, AiRefreshJob $b) use ($priorityMap): int {
            $pA = $priorityMap[$a->capability] ?? 4;
            $pB = $priorityMap[$b->capability] ?? 4;
            if ($pA !== $pB) {
                return $pA <=> $pB;
            }
            $created = strcmp((string) $a->createdAt, (string) $b->createdAt);
            return $created !== 0 ? $created : strcmp($a->jobKey, $b->jobKey);
        });

        $key = array_key_first($candidates);
        $job = $candidates[$key];

        $token = hash('sha256', $workerId . random_bytes(16));
        $claimed = new AiRefreshJob(
            $job->jobKey,
            $job->studentId,
            $job->capability,
            $job->snapshotHash,
            'processing',
            $job->attempts + 1,
            null,
            gmdate('Y-m-d H:i:s', $now + max(1, $leaseSeconds)),
            null,
            $workerId,
            $token,
            $job->createdAt,
        );

        return $this->jobs[$key] = $claimed;
    }

    public function renewLease(string $jobKey, string $leaseToken, int $leaseSeconds = 60): bool
    {
        $j = $this->jobs[$jobKey] ?? null;
        if (!$j instanceof AiRefreshJob || $j->status !== 'processing' || $j->leaseToken === null || !hash_equals($j->leaseToken, $leaseToken) || $j->leaseUntil === null || strtotime($j->leaseUntil) < time()) {
            return false;
        }
        $this->jobs[$jobKey] = new AiRefreshJob(
            $j->jobKey,
            $j->studentId,
            $j->capability,
            $j->snapshotHash,
            $j->status,
            $j->attempts,
            $j->nextRetryAt,
            gmdate('Y-m-d H:i:s', time() + max(1, $leaseSeconds)),
            $j->errorCode,
            $j->leaseOwner,
            $j->leaseToken,
            $j->createdAt,
        );
        return true;
    }

    public function ownsLease(string $jobKey, string $leaseToken): bool
    {
        $j = $this->jobs[$jobKey] ?? null;
        return $j instanceof AiRefreshJob && $j->status === 'processing' && $j->leaseToken !== null && hash_equals($j->leaseToken, $leaseToken) && $j->leaseUntil !== null && strtotime($j->leaseUntil) >= time();
    }

    public function complete(string $jobKey, ?string $leaseToken = null): bool
    {
        $j = $this->jobs[$jobKey] ?? null;
        if (!$j instanceof AiRefreshJob || $j->status !== 'processing') {
            return false;
        }
        if ($leaseToken !== null && (!hash_equals((string) $j->leaseToken, $leaseToken) || $j->leaseUntil === null || strtotime($j->leaseUntil) < time())) {
            return false;
        }
        $this->jobs[$jobKey] = new AiRefreshJob($j->jobKey, $j->studentId, $j->capability, $j->snapshotHash, 'completed', $j->attempts, null, null, null, null, null, $j->createdAt);
        return true;
    }

    public function fail(string $jobKey, string $errorCode, bool $deadLetter = false, ?string $leaseToken = null, ?int $retryAfterSeconds = null): void
    {
        if (isset($this->jobs[$jobKey])) {
            $j = $this->jobs[$jobKey];
            if ($leaseToken !== null && !hash_equals((string) $j->leaseToken, $leaseToken)) {
                return;
            }
            $delay = $retryAfterSeconds === null ? min(3600, 2 ** min(10, max(1, $j->attempts))) : max(0, min(86400, $retryAfterSeconds));
            $retry = $deadLetter ? null : gmdate('Y-m-d H:i:s', time() + $delay);
            $this->jobs[$jobKey] = new AiRefreshJob(
                $j->jobKey,
                $j->studentId,
                $j->capability,
                $j->snapshotHash,
                $deadLetter ? 'dead_letter' : 'pending',
                $j->attempts,
                $retry,
                null,
                $errorCode,
                null,
                null,
                $j->createdAt,
            );
        }
    }

    public function cancelSuperseded(string $studentId, string $capability, string $currentSnapshotHash): void
    {
        foreach ($this->jobs as $key => $j) {
            if ($j->studentId === $studentId && $j->capability === $capability && $j->snapshotHash !== $currentSnapshotHash && $j->status === 'pending') {
                $this->jobs[$key] = new AiRefreshJob($j->jobKey, $j->studentId, $j->capability, $j->snapshotHash, 'cancelled', $j->attempts, null, null, null, null, null, $j->createdAt);
            }
        }
    }

    public function cancel(string $jobKey, ?string $leaseToken = null): void
    {
        $j = $this->jobs[$jobKey] ?? null;
        if (!$j instanceof AiRefreshJob || ($leaseToken !== null && !hash_equals((string) $j->leaseToken, $leaseToken))) {
            return;
        }
        $this->jobs[$jobKey] = new AiRefreshJob($j->jobKey, $j->studentId, $j->capability, $j->snapshotHash, 'cancelled', $j->attempts, null, null, 'superseded_snapshot', null, null, $j->createdAt);
    }

    public function all(): array
    {
        return $this->jobs;
    }

    /** @return array{depth:int,oldest_age_seconds:int} */
    public function pendingStats(): array
    {
        $pending = array_filter($this->jobs, static fn(AiRefreshJob $job): bool => $job->status === 'pending');
        $oldest = 0.0;
        foreach ($pending as $job) {
            $created = $job->createdAt === null ? 0.0 : (float) (explode('-', $job->createdAt, 2)[0] ?? 0.0);
            if ($created > 0.0 && ($oldest === 0.0 || $created < $oldest)) $oldest = $created;
        }
        return [
            'depth' => count($pending),
            'oldest_age_seconds' => $oldest > 0.0 ? max(0, (int) floor(microtime(true) - $oldest)) : 0,
        ];
    }
}
