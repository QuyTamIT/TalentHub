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
  if(!self::tableExists($pdo,'learner_ai_data_outbox')) {
      self::ensureOutboxTable($pdo);
      if(!self::tableExists($pdo,'learner_ai_data_outbox')) {
          return false;
      }
  }
  try {
      $tenantId=is_string($tenantId)&&trim($tenantId)!==''?trim($tenantId):null;
      $id=self::uuid();$s=$pdo->prepare("INSERT INTO learner_ai_data_outbox (id,aggregate_type,aggregate_id,tenant_id,event_type,aggregate_version,payload_hash,affected_student_ids,delivery_status,occurred_at) VALUES (:id,:type,:aggregate,:tenant,:event,:version,:hash,:students,'pending',:occurred)");
      $s->execute(['id'=>$id,'type'=>$aggregateType,'aggregate'=>$aggregateId,'tenant'=>$tenantId,'event'=>$eventType,'version'=>$aggregateVersion,'hash'=>hash('sha256',json_encode($safePayload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),'students'=>json_encode(array_values(array_unique($studentIds)),JSON_THROW_ON_ERROR),'occurred'=>gmdate('Y-m-d H:i:s')]);
      return true;
  } catch (\Throwable) {
      return false;
  }
 }
 public static function version():int{return (int)floor(microtime(true)*1000000);}
 private static function tableExists(PDO $pdo,string $table):bool{if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$s=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");$s->execute(['name'=>$table]);return $s->fetchColumn()!==false;}$s=$pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:name');$s->execute(['name'=>$table]);return $s->fetchColumn()!==false;}
 private static function ensureOutboxTable(PDO $pdo):void {
     $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
     $idType = $isSqlite ? 'TEXT PRIMARY KEY' : 'CHAR(36) PRIMARY KEY';
     $create = "CREATE TABLE IF NOT EXISTS learner_ai_data_outbox (id {$idType}, aggregate_type VARCHAR(64) NOT NULL, aggregate_id VARCHAR(128) NOT NULL, tenant_id VARCHAR(128) NULL, event_type VARCHAR(128) NOT NULL, aggregate_version BIGINT NOT NULL, payload_hash CHAR(64) NOT NULL, affected_student_ids TEXT NOT NULL, delivery_status VARCHAR(20) NOT NULL DEFAULT 'pending', occurred_at DATETIME NOT NULL, delivered_at DATETIME NULL, UNIQUE(aggregate_type,aggregate_id,aggregate_version)" . ($isSqlite ? '' : ', INDEX idx_ai_outbox_delivery (delivery_status,occurred_at)') . ')';
     try {
         $pdo->exec($create);
         if ($isSqlite) {
             $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_outbox_delivery ON learner_ai_data_outbox (delivery_status,occurred_at)');
         }
     } catch (\Throwable) {}
 }
 private static function uuid():string{$h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-8'.substr($h,17,3).'-'.substr($h,20,12);}
}
