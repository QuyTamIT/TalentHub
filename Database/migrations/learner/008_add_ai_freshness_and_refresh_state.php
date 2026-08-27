<?php
declare(strict_types=1);
use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
return new ForwardMigrationDefinition('008_add_ai_freshness_and_refresh_state','Add AI freshness and last-known-good state',__FILE__,hash_file('sha256',__FILE__),new class implements LearnerForwardMigration {
 public function version():string{return '008_add_ai_freshness_and_refresh_state';} public function description():string{return 'Add AI freshness and last-known-good state';}
 public function statements(string $driver):array { $tables=['learner_recommendation_runs','learner_ai_roadmaps']; $out=[]; foreach($tables as $t){$out[]="ALTER TABLE {$t} ADD COLUMN freshness_status VARCHAR(20) NOT NULL DEFAULT 'pending'";$out[]="ALTER TABLE {$t} ADD COLUMN stale_since DATETIME NULL";$out[]="ALTER TABLE {$t} ADD COLUMN last_refresh_error VARCHAR(100) NULL";$out[]="ALTER TABLE {$t} ADD COLUMN next_retry_at DATETIME NULL";$out[]="ALTER TABLE {$t} ADD COLUMN model_version VARCHAR(128) NULL";$out[]="ALTER TABLE {$t} ADD COLUMN snapshot_hash CHAR(64) NULL";$out[]="ALTER TABLE {$t} ADD COLUMN refresh_job_id VARCHAR(128) NULL";} return $out; }
 public function expectedSchema():array{$columns=['freshness_status','stale_since','last_refresh_error','next_retry_at','model_version','snapshot_hash','refresh_job_id'];return ['learner_recommendation_runs'=>['columns'=>$columns,'indexes'=>[]],'learner_ai_roadmaps'=>['columns'=>$columns,'indexes'=>[]]];}
});
