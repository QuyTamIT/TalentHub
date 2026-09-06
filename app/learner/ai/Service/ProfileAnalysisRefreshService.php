<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Service;
final class ProfileAnalysisRefreshService
{
 /** @var \Closure */ private readonly \Closure $generator;
 /** @var \Closure */ private readonly \Closure $publisher;
 public function __construct(callable $generator,callable $publisher){$this->generator=\Closure::fromCallable($generator);$this->publisher=\Closure::fromCallable($publisher);}
 public function refresh(string $studentId,string $snapshotHash,string $jobKey,callable $leaseGuard):array
 {
  if(!$leaseGuard())throw new \RuntimeException('refresh_lease_lost');
  $result=($this->generator)($studentId,'profile-worker-'.$jobKey,'profile-'.$jobKey,false,$leaseGuard,true,$snapshotHash);
  if(($result['state']??null)!=='ready_model')throw new \RuntimeException('profile_analysis_unavailable');
  if(!is_string($result['input_hash']??null)||!hash_equals($snapshotHash,$result['input_hash']))throw new \RuntimeException('profile_analysis_snapshot_mismatch');
  if(!$leaseGuard())throw new \RuntimeException('refresh_lease_lost');
  $profile=[];foreach(['talent_map','strengths','improvements','potential_paths','trend_signals','evidence'] as $field)$profile[$field]=is_array($result[$field]??null)?$result[$field]:[];
  ($this->publisher)($studentId,$profile,$snapshotHash,(string)($result['model_version']??($result['engine']['model_version']??'unknown')),(string)($result['generated_at']??gmdate('c')));
  return $result;
 }
}
