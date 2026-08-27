<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Queue;

final class AiRefreshDispatcher
{
    public function __construct(private readonly AiRefreshJobRepository $jobs) {}
    public function dispatch(string $studentId,string $snapshotHash,array $capabilities=['recommendation','roadmap']): array { $result=[]; foreach ($capabilities as $capability) { $this->jobs->cancelSuperseded($studentId,(string)$capability,$snapshotHash); $result[]=$this->jobs->enqueue($studentId,$snapshotHash,(string)$capability); } return $result; }
}
