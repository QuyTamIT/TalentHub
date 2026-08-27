<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Queue;
use PDO;
final class DatabaseAiDataOutboxRepository implements AiDataOutboxRepository
{
 public function __construct(private readonly PDO $pdo){}
 public function append(AiDataOutbox $e,?string $tenantId=null):void{$s=$this->pdo->prepare("INSERT INTO learner_ai_data_outbox (id,aggregate_type,aggregate_id,tenant_id,event_type,aggregate_version,payload_hash,affected_student_ids,delivery_status,occurred_at) VALUES (:id,:type,:aggregate,:tenant,:event,:version,:hash,:students,'pending',:occurred)");$s->execute(['id'=>$e->eventId,'type'=>$e->aggregateType,'aggregate'=>$e->aggregateId,'tenant'=>$tenantId,'event'=>$e->eventType,'version'=>$e->aggregateVersion,'hash'=>$e->payloadHash,'students'=>json_encode($e->studentIds,JSON_THROW_ON_ERROR),'occurred'=>gmdate('Y-m-d H:i:s')]);}
 public function pending(int $limit=100):array{$limit=max(1,min(500,$limit));$s=$this->pdo->query("SELECT * FROM learner_ai_data_outbox WHERE delivery_status='pending' ORDER BY occurred_at LIMIT {$limit}");return $s?($s->fetchAll(PDO::FETCH_ASSOC)?:[]):[];}
 public function delivered(string $eventId):void{$s=$this->pdo->prepare("UPDATE learner_ai_data_outbox SET delivery_status='delivered',delivered_at=:at WHERE id=:id");$s->execute(['at'=>gmdate('Y-m-d H:i:s'),'id'=>$eventId]);}
}
