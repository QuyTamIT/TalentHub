<?php
declare(strict_types=1);
use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
return new ForwardMigrationDefinition('010_create_ai_provider_health','Create shared AI provider circuit health',__FILE__,hash_file('sha256',__FILE__),new class implements LearnerForwardMigration {
 public function version():string{return '010_create_ai_provider_health';}public function description():string{return 'Create shared AI provider circuit health';}
 public function statements(string $driver):array{return ["CREATE TABLE learner_ai_provider_health (provider_key VARCHAR(191) PRIMARY KEY,state VARCHAR(20) NOT NULL,failure_count INT NOT NULL DEFAULT 0,opened_at BIGINT NULL,updated_at DATETIME NOT NULL)"];}
 public function expectedSchema():array{return ['learner_ai_provider_health'=>['columns'=>['provider_key','state','failure_count','opened_at','updated_at'],'indexes'=>[]]];}
});
