<?php
declare(strict_types=1);
use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
return new ForwardMigrationDefinition('011_create_ai_catalog_items','Create canonical AI opportunity catalog',__FILE__,hash_file('sha256',__FILE__),new class implements LearnerForwardMigration {
 public function version():string{return '011_create_ai_catalog_items';} public function description():string{return 'Create canonical AI opportunity catalog';}
 public function statements(string $driver):array{$id=strtolower($driver)==='sqlite'?'TEXT PRIMARY KEY':'VARCHAR(128) PRIMARY KEY';return ["CREATE TABLE learner_ai_catalog_items (catalog_id {$id}, item_type VARCHAR(32) NOT NULL, category VARCHAR(64) NOT NULL, title VARCHAR(255) NOT NULL, summary TEXT NOT NULL, publish_status VARCHAR(20) NOT NULL, deadline_at DATETIME NULL, eligibility_json TEXT NOT NULL, capacity INT NOT NULL, enrolled_count INT NOT NULL DEFAULT 0, url VARCHAR(2048) NOT NULL, action_json TEXT NOT NULL, school_id VARCHAR(128) NULL, tenant_id VARCHAR(128) NULL, updated_at DATETIME NOT NULL)"];}
 public function expectedSchema():array{return ['learner_ai_catalog_items'=>['columns'=>['catalog_id','item_type','category','title','summary','publish_status','deadline_at','eligibility_json','capacity','enrolled_count','url','action_json','school_id','tenant_id','updated_at'],'indexes'=>[]]];}
});
