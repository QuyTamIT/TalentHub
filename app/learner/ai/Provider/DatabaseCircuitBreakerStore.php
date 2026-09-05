<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Provider;
use PDO;
final class DatabaseCircuitBreakerStore implements CircuitBreakerStore
{
 public function __construct(private readonly PDO $pdo){}
 public function load(string $key):array{$s=$this->pdo->prepare('SELECT state,failure_count,opened_at FROM learner_ai_provider_health WHERE provider_key=:key');$s->execute(['key'=>$key]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?['state'=>(string)$r['state'],'failures'=>(int)$r['failure_count'],'opened_at'=>$r['opened_at']===null?null:(int)$r['opened_at']]:['state'=>'closed','failures'=>0,'opened_at'=>null];}
 public function save(string $key,string $state,int $failures,?int $openedAt):void{$sqlite=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$sql=$sqlite?'INSERT INTO learner_ai_provider_health (provider_key,state,failure_count,opened_at,updated_at) VALUES (:key,:state,:failures,:opened,:updated) ON CONFLICT(provider_key) DO UPDATE SET state=excluded.state,failure_count=excluded.failure_count,opened_at=excluded.opened_at,updated_at=excluded.updated_at':'INSERT INTO learner_ai_provider_health (provider_key,state,failure_count,opened_at,updated_at) VALUES (:key,:state,:failures,:opened,:updated) ON DUPLICATE KEY UPDATE state=VALUES(state),failure_count=VALUES(failure_count),opened_at=VALUES(opened_at),updated_at=VALUES(updated_at)';$s=$this->pdo->prepare($sql);$s->execute(['key'=>$key,'state'=>$state,'failures'=>$failures,'opened'=>$openedAt,'updated'=>gmdate('Y-m-d H:i:s')]);}
 public function recordFailure(string $key,int $threshold,int $now):array
 {
  $threshold=max(1,$threshold);$updated=gmdate('Y-m-d H:i:s');$sqlite=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
  if($sqlite){$sql="INSERT INTO learner_ai_provider_health (provider_key,state,failure_count,opened_at,updated_at) VALUES (:key,:initial_state,1,:initial_opened,:updated_insert) ON CONFLICT(provider_key) DO UPDATE SET failure_count=failure_count+1,state=CASE WHEN state='half_open' OR failure_count+1>=CAST(:threshold_state AS INTEGER) THEN 'open' ELSE 'closed' END,opened_at=CASE WHEN state='half_open' OR failure_count+1>=CAST(:threshold_opened AS INTEGER) THEN :failure_opened ELSE NULL END,updated_at=:updated_update";}
  else{$sql="INSERT INTO learner_ai_provider_health (provider_key,state,failure_count,opened_at,updated_at) VALUES (:key,:initial_state,1,:initial_opened,:updated_insert) ON DUPLICATE KEY UPDATE opened_at=IF(state='half_open' OR failure_count+1>=:threshold_opened,:failure_opened,NULL),state=IF(state='half_open' OR failure_count+1>=:threshold_state,'open','closed'),failure_count=failure_count+1,updated_at=:updated_update";}
  $parameters=['key'=>$key,'initial_state'=>$threshold<=1?'open':'closed','initial_opened'=>$threshold<=1?$now:null,'updated_insert'=>$updated,'threshold_state'=>$threshold,'threshold_opened'=>$threshold,'failure_opened'=>$now,'updated_update'=>$updated];$s=$this->pdo->prepare($sql);$s->execute($parameters);return $this->load($key);
 }
 public function transitionToHalfOpen(string $key,int $openedAt):bool{$s=$this->pdo->prepare("UPDATE learner_ai_provider_health SET state='half_open',updated_at=:updated WHERE provider_key=:key AND state='open' AND opened_at=:opened");$s->execute(['updated'=>gmdate('Y-m-d H:i:s'),'key'=>$key,'opened'=>$openedAt]);return $s->rowCount()===1;}
}
