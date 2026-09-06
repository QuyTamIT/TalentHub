<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Provider;
interface CircuitBreakerStore
{
 /** @return array{state:string,failures:int,opened_at:?int} */ public function load(string $key):array;
 public function save(string $key,string $state,int $failures,?int $openedAt):void;
 /** @return array{state:string,failures:int,opened_at:?int} */ public function recordFailure(string $key,int $threshold,int $now):array;
 public function transitionToHalfOpen(string $key,int $openedAt):bool;
}
