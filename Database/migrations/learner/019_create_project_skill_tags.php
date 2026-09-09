<?php
declare(strict_types=1);
use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
return new ForwardMigrationDefinition('019_create_project_skill_tags','Store canonical skills evidenced by learner projects',__FILE__,hash_file('sha256',__FILE__),new class implements LearnerForwardMigration {
 public function version():string{return '019_create_project_skill_tags';}
 public function description():string{return 'Store canonical skills evidenced by learner projects';}
 public function statements(string $driver):array{$id=strtolower($driver)==='sqlite'?'TEXT':'CHAR(36)';return ["CREATE TABLE project_skill_tags (id {$id} NOT NULL PRIMARY KEY, projectId {$id} NOT NULL, skillId {$id} NOT NULL, verifiedAt DATETIME NULL, createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(projectId, skillId))",'CREATE INDEX idx_project_skill_tags_project ON project_skill_tags (projectId)','CREATE INDEX idx_project_skill_tags_skill ON project_skill_tags (skillId)'];}
 public function expectedSchema():array{return ['project_skill_tags'=>['columns'=>['id','projectId','skillId','verifiedAt','createdAt'],'indexes'=>['idx_project_skill_tags_project','idx_project_skill_tags_skill']]];}
});
