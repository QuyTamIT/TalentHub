<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Queue;

final class InMemoryAiRefreshJobRepository implements AiRefreshJobRepository
{
    /** @var array<string,AiRefreshJob> */ private array $jobs = [];
    public function enqueue(string $studentId, string $snapshotHash, string $capability): AiRefreshJob
    { $key = AiRefreshJob::key($studentId, $snapshotHash, $capability); return $this->jobs[$key] ??= new AiRefreshJob($key, $studentId, $capability, $snapshotHash); }
    public function claimNext(string $workerId, int $leaseSeconds = 60): ?AiRefreshJob
    { foreach ($this->jobs as $key => $job) if ($job->status === 'pending' || ($job->status === 'processing' && $job->leaseUntil !== null && strtotime($job->leaseUntil) < time())) { $token=hash('sha256',$workerId.random_bytes(16));$claimed = new AiRefreshJob($job->jobKey,$job->studentId,$job->capability,$job->snapshotHash,'processing',$job->attempts+1,null,gmdate('c',time()+$leaseSeconds),null,$workerId,$token); return $this->jobs[$key] = $claimed; } return null; }
    public function renewLease(string $jobKey,string $leaseToken,int $leaseSeconds=60):bool { $j=$this->jobs[$jobKey]??null;if(!$j instanceof AiRefreshJob||$j->status!=='processing'||$j->leaseToken===null||!hash_equals($j->leaseToken,$leaseToken)||$j->leaseUntil===null||strtotime($j->leaseUntil)<time())return false;$this->jobs[$jobKey]=new AiRefreshJob($j->jobKey,$j->studentId,$j->capability,$j->snapshotHash,$j->status,$j->attempts,$j->nextRetryAt,gmdate('c',time()+max(1,$leaseSeconds)),$j->errorCode,$j->leaseOwner,$j->leaseToken);return true; }
    public function ownsLease(string $jobKey,string $leaseToken):bool { $j=$this->jobs[$jobKey]??null;return $j instanceof AiRefreshJob&&$j->status==='processing'&&$j->leaseToken!==null&&hash_equals($j->leaseToken,$leaseToken)&&$j->leaseUntil!==null&&strtotime($j->leaseUntil)>=time(); }
    public function complete(string $jobKey,?string $leaseToken=null): void { if (isset($this->jobs[$jobKey])) { $j=$this->jobs[$jobKey];if($leaseToken!==null&&!hash_equals((string)$j->leaseToken,$leaseToken))return;$this->jobs[$jobKey]=new AiRefreshJob($j->jobKey,$j->studentId,$j->capability,$j->snapshotHash,'completed',$j->attempts); } }
    public function fail(string $jobKey, string $errorCode, bool $deadLetter = false,?string $leaseToken=null,?int $retryAfterSeconds=null): void { if (isset($this->jobs[$jobKey])) { $j=$this->jobs[$jobKey];if($leaseToken!==null&&!hash_equals((string)$j->leaseToken,$leaseToken))return;$delay=$retryAfterSeconds===null?min(3600,2**min(10,max(1,$j->attempts))):max(0,min(86400,$retryAfterSeconds));$retry=$deadLetter?null:gmdate('c',time()+$delay);$this->jobs[$jobKey]=new AiRefreshJob($j->jobKey,$j->studentId,$j->capability,$j->snapshotHash,$deadLetter?'dead_letter':'pending',$j->attempts,$retry,null,$errorCode); } }
    public function cancelSuperseded(string $studentId,string $capability,string $currentSnapshotHash):void { foreach($this->jobs as $key=>$j) if($j->studentId===$studentId&&$j->capability===$capability&&$j->snapshotHash!==$currentSnapshotHash&&$j->status==='pending')$this->jobs[$key]=new AiRefreshJob($j->jobKey,$j->studentId,$j->capability,$j->snapshotHash,'cancelled',$j->attempts); }
    public function all(): array { return $this->jobs; }
    /** @return array{depth:int,oldest_age_seconds:int} */
    public function pendingStats(): array
    {
        return ['depth' => count(array_filter($this->jobs, static fn(AiRefreshJob $job): bool => $job->status === 'pending')), 'oldest_age_seconds' => 0];
    }
}
