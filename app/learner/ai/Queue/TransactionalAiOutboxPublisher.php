<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Queue;
use PDO;
final class TransactionalAiOutboxPublisher
{
 /** Records an AI source mutation in the caller's existing database transaction. */
 public static function publish(PDO $pdo,string $aggregateType,string $aggregateId,int $aggregateVersion,array $studentIds,string $eventType,array $safePayload=[],?string $tenantId=null):bool
 {
  if(!$pdo->inTransaction())throw new \LogicException('AI outbox writes require an active mutation transaction.');
  if(!self::tableExists($pdo,'learner_ai_data_outbox'))throw new \RuntimeException('AI outbox schema is required for synchronized mutations.');
  $tenantId=is_string($tenantId)&&trim($tenantId)!==''?trim($tenantId):null;
  $id=self::uuid();$s=$pdo->prepare("INSERT INTO learner_ai_data_outbox (id,aggregate_type,aggregate_id,tenant_id,event_type,aggregate_version,payload_hash,affected_student_ids,delivery_status,occurred_at) VALUES (:id,:type,:aggregate,:tenant,:event,:version,:hash,:students,'pending',:occurred)");
  $s->execute(['id'=>$id,'type'=>$aggregateType,'aggregate'=>$aggregateId,'tenant'=>$tenantId,'event'=>$eventType,'version'=>$aggregateVersion,'hash'=>hash('sha256',json_encode($safePayload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),'students'=>json_encode(array_values(array_unique($studentIds)),JSON_THROW_ON_ERROR),'occurred'=>gmdate('Y-m-d H:i:s')]);return true;
 }
 public static function version():int{return (int)floor(microtime(true)*1000000);}
 private static function tableExists(PDO $pdo,string $table):bool{if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$s=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");$s->execute(['name'=>$table]);return $s->fetchColumn()!==false;}$s=$pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:name');$s->execute(['name'=>$table]);return $s->fetchColumn()!==false;}
 private static function uuid():string{$h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-8'.substr($h,17,3).'-'.substr($h,20,12);}
}
