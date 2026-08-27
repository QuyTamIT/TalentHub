<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Queue;

interface AiRefreshJobRepository
{
    public function enqueue(string $studentId, string $snapshotHash, string $capability): AiRefreshJob;
    public function claimNext(string $workerId, int $leaseSeconds = 60): ?AiRefreshJob;
    public function renewLease(string $jobKey, string $leaseToken, int $leaseSeconds = 60): bool;
    public function ownsLease(string $jobKey, string $leaseToken): bool;
    public function complete(string $jobKey, ?string $leaseToken = null): void;
    public function fail(string $jobKey, string $errorCode, bool $deadLetter = false, ?string $leaseToken = null, ?int $retryAfterSeconds = null): void;
    public function cancelSuperseded(string $studentId, string $capability, string $currentSnapshotHash): void;
}
