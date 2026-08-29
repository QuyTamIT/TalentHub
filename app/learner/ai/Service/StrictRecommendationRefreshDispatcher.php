<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

use TalentHub\Learner\Ai\Persistence\DatabaseAiRefreshStateRepository;
use TalentHub\Learner\Ai\Queue\AiRefreshDispatcher;
use TalentHub\Learner\Ai\Queue\AiRefreshJob;

final class StrictRecommendationRefreshDispatcher
{
    public function __construct(
        private readonly AiRefreshDispatcher $dispatcher,
        private readonly DatabaseAiRefreshStateRepository $refreshState,
    ) {}

    /** @return list<AiRefreshJob> */
    public function dispatch(string $studentId, string $snapshotHash): array
    {
        $jobs = $this->dispatcher->dispatch($studentId, $snapshotHash, ['recommendation']);
        foreach ($jobs as $job) {
            if ($job instanceof AiRefreshJob && $job->capability === 'recommendation') {
                $this->refreshState->pending($studentId, 'recommendation', $snapshotHash, $job->jobKey);
            }
        }
        return $jobs;
    }
}
