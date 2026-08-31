<?php
declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use TalentHub\Modules\School\Repository\DatabaseSchoolAiRefreshJobRepository;

final class SchoolAiRefreshWorker
{
    public function __construct(
        private readonly DatabaseSchoolAiRefreshJobRepository $queue,
        private readonly SchoolAiInsightService $service,
    ) {
    }

    public function runOnce(): bool
    {
        $job = $this->queue->claim();
        if ($job === null) {
            return false;
        }

        $jobId = (int) $job['id'];
        $schoolId = (string) $job['school_id'];
        $aggregateHash = (string) $job['aggregate_hash'];

        try {
            $result = $this->service->refreshForSchool($schoolId, $aggregateHash);
            $state = (string) ($result['state'] ?? '');
            if ($state === 'ready_model' || $state === 'insufficient_data') {
                $this->queue->complete($jobId);
            } elseif ($state === 'superseded') {
                $this->queue->cancel($jobId);
            } else {
                $this->queue->fail($jobId);
            }
        } catch (\Throwable) {
            $this->queue->fail($jobId);
        }

        return true;
    }
}
