<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Queue;

final class AiRefreshJob
{
    public function __construct(
        public readonly string $jobKey,
        public readonly string $studentId,
        public readonly string $capability,
        public readonly string $snapshotHash,
        public readonly string $status = 'pending',
        public readonly int $attempts = 0,
        public readonly ?string $nextRetryAt = null,
        public readonly ?string $leaseUntil = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $leaseOwner = null,
        public readonly ?string $leaseToken = null,
        public readonly ?string $createdAt = null,
    ) {}
    public static function key(string $studentId, string $snapshotHash, string $capability): string { return hash('sha256', implode(':', [$studentId, $snapshotHash, $capability])); }

    public function executionIdempotencyKey(): string
    {
        return 'worker-' . $this->jobKey . '-' . $this->attempts;
    }
}
