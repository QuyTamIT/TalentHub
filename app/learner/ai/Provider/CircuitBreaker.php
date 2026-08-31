<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Provider;

final class CircuitBreaker
{
 /** @var callable():int */ private $clock;
 private readonly CircuitBreakerStore $store;
 public function __construct(private readonly int $failureThreshold=3,private readonly int $cooldownSeconds=30,?callable $clock=null,?CircuitBreakerStore $store=null,private readonly string $key='default'){$this->clock=$clock??static fn():int=>time();$this->store=$store??new InMemoryCircuitBreakerStore();}
 public function allow():bool{$current=$this->store->load($this->key);if($current['state']==='closed')return true;if($current['state']==='half_open')return false;$opened=$current['opened_at'];if($opened===null||($this->clock)()-$opened<$this->cooldownSeconds)return false;return $this->store->transitionToHalfOpen($this->key,$opened);}
 public function recordSuccess():void{$this->store->save($this->key,'closed',0,null);}
 public function recordFailure():void{$this->store->recordFailure($this->key,$this->failureThreshold,($this->clock)());}
 public function state():string{$current=$this->store->load($this->key);if($current['state']==='open'&&$current['opened_at']!==null&&($this->clock)()-$current['opened_at']>=$this->cooldownSeconds)return 'half_open';return $current['state'];}
}
