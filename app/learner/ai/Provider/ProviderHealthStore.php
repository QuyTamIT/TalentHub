<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Provider;
final class ProviderHealthStore
{
 /** @var list<array{at:string,success:bool,latency_ms:int,retry_count:int,error_category:?string,circuit_state:string}> */ private array $samples=[];
 public function record(bool $success,int $latencyMs,int $retryCount,?string $errorCategory,string $circuitState):void{$this->samples[]=['at'=>gmdate('c'),'success'=>$success,'latency_ms'=>max(0,$latencyMs),'retry_count'=>max(0,$retryCount),'error_category'=>$errorCategory===null?null:substr($errorCategory,0,64),'circuit_state'=>$circuitState];if(count($this->samples)>1000)array_shift($this->samples);}
 public function snapshot():array{$total=count($this->samples);$failures=count(array_filter($this->samples,static fn(array $s):bool=>!$s['success']));return ['sample_count'=>$total,'failure_count'=>$failures,'failure_rate'=>$total===0?0.0:$failures/$total,'latest'=>$this->samples[$total-1]??null];}
}
