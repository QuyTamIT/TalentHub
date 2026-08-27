<?php
declare(strict_types=1);
use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
return new ForwardMigrationDefinition('007_create_ai_data_outbox','Create transactional AI data outbox',__FILE__,hash_file('sha256',__FILE__),new class implements LearnerForwardMigration {
 public function version():string{return '007_create_ai_data_outbox';} public function description():string{return 'Create transactional AI data outbox';}
 public function statements(string $driver):array { $sqlite=strtolower($driver)==='sqlite'; $id=$sqlite?'TEXT PRIMARY KEY':'CHAR(36) PRIMARY KEY'; $create="CREATE TABLE learner_ai_data_outbox (id {$id}, aggregate_type VARCHAR(64) NOT NULL, aggregate_id VARCHAR(128) NOT NULL, tenant_id VARCHAR(128) NULL, event_type VARCHAR(128) NOT NULL, aggregate_version BIGINT NOT NULL, payload_hash CHAR(64) NOT NULL, affected_student_ids TEXT NOT NULL, delivery_status VARCHAR(20) NOT NULL DEFAULT 'pending', occurred_at DATETIME NOT NULL, delivered_at DATETIME NULL, UNIQUE(aggregate_type,aggregate_id,aggregate_version)".($sqlite?'':', INDEX idx_ai_outbox_delivery (delivery_status,occurred_at)').')'; return $sqlite?[$create,'CREATE INDEX idx_ai_outbox_delivery ON learner_ai_data_outbox (delivery_status,occurred_at)']:[$create]; }
 public function expectedSchema():array{return ['learner_ai_data_outbox'=>['columns'=>['id','aggregate_type','aggregate_id','tenant_id','event_type','aggregate_version','payload_hash','affected_student_ids','delivery_status','occurred_at','delivered_at'],'indexes'=>['idx_ai_outbox_delivery']]];}
});
