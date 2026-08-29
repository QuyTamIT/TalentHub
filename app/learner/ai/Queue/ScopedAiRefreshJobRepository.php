<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Queue;

/**
 * Optional capability for a worker that is explicitly restricted to one
 * learner. The global queue interface remains unchanged for normal workers.
 */
interface ScopedAiRefreshJobRepository extends AiRefreshJobRepository
{
    public function claimNextForStudent(string $workerId, string $studentId, int $leaseSeconds = 60): ?AiRefreshJob;
}
