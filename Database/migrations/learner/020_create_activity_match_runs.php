<?php
declare(strict_types=1);
use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
return new ForwardMigrationDefinition('020_create_activity_match_runs','Persist learner activity match results',__FILE__,hash_file('sha256',__FILE__),new class implements LearnerForwardMigration {
    public function version(): string { return '020_create_activity_match_runs'; }
    public function description(): string { return 'Persist learner activity match results'; }
    public function statements(string $driver): array {
        $text = $driver === 'mysql' ? 'LONGTEXT' : 'TEXT';
        return ["CREATE TABLE learner_activity_match_runs (id VARCHAR(32) PRIMARY KEY,studentId CHAR(36) NOT NULL,inputHash CHAR(64) NOT NULL,payloadJson {$text} NOT NULL,createdAt DATETIME NOT NULL)", 'CREATE INDEX idx_activity_match_owner_date ON learner_activity_match_runs (studentId,createdAt,id)'];
    }
    public function expectedSchema(): array { return ['learner_activity_match_runs'=>['columns'=>['id','studentId','inputHash','payloadJson','createdAt'],'indexes'=>['idx_activity_match_owner_date']]]; }
});
