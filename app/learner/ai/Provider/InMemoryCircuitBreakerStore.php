<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Provider;
final class InMemoryCircuitBreakerStore implements CircuitBreakerStore
{
 /** @var array<string,array{state:string,failures:int,opened_at:?int}> */ private array $states=[];
 public function load(string $key):array{return $this->states[$key]??['state'=>'closed','failures'=>0,'opened_at'=>null];}
 public function save(string $key,string $state,int $failures,?int $openedAt):void{$this->states[$key]=['state'=>$state,'failures'=>$failures,'opened_at'=>$openedAt];}
 public function recordFailure(string $key,int $threshold,int $now):array{$current=$this->load($key);$failures=$current['failures']+1;$open=$current['state']==='half_open'||$failures>=max(1,$threshold);return $this->states[$key]=['state'=>$open?'open':'closed','failures'=>$failures,'opened_at'=>$open?$now:null];}
 public function transitionToHalfOpen(string $key,int $openedAt):bool{$state=$this->load($key);if($state['state']!=='open'||$state['opened_at']!==$openedAt)return false;$this->save($key,'half_open',$state['failures'],$openedAt);return true;}
}
