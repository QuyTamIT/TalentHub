<?php
declare(strict_types=1);
use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
return new ForwardMigrationDefinition('006_create_ai_refresh_jobs','Create idempotent AI refresh queue',__FILE__,hash_file('sha256',__FILE__),new class implements LearnerForwardMigration {
 public function version():string{return '006_create_ai_refresh_jobs';} public function description():string{return 'Create idempotent AI refresh queue';}
 public function statements(string $driver):array { $sqlite=strtolower($driver)==='sqlite'; $auto=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'; $create="CREATE TABLE learner_ai_refresh_jobs (id {$auto}, job_key VARCHAR(128) NOT NULL, student_id CHAR(36) NOT NULL, capability VARCHAR(64) NOT NULL, snapshot_hash CHAR(64) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', attempts INT NOT NULL DEFAULT 0, next_retry_at DATETIME NULL, lease_until DATETIME NULL, lease_owner VARCHAR(128) NULL, lease_token CHAR(64) NULL, error_code VARCHAR(100) NULL, dead_lettered_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE(job_key)".($sqlite?'':', INDEX idx_ai_refresh_jobs_claim (status,next_retry_at,lease_until)').')'; return $sqlite?[$create,'CREATE INDEX idx_ai_refresh_jobs_claim ON learner_ai_refresh_jobs (status,next_retry_at,lease_until)']:[$create]; }
 public function expectedSchema():array{return ['learner_ai_refresh_jobs'=>['columns'=>['id','job_key','student_id','capability','snapshot_hash','status','attempts','next_retry_at','lease_until','lease_owner','lease_token','error_code','dead_lettered_at','created_at','updated_at'],'indexes'=>['idx_ai_refresh_jobs_claim']]];}
});
