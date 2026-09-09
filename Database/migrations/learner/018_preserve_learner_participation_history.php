<?php
declare(strict_types=1);

use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;
use TalentHub\Learner\Data\Migrations\LearnerMigrationChecksum;

return new ForwardMigrationDefinition(
    '018_preserve_learner_participation_history',
    'Prevent learner deletion from cascading into immutable participation history',
    __FILE__,
    LearnerMigrationChecksum::canonical(__FILE__),
    new class implements LearnerForwardMigration {
        public function version(): string { return '018_preserve_learner_participation_history'; }
        public function description(): string { return 'Preserve participation audit events when deleting learner profiles'; }
        public function statements(string $driver): array
        {
            if (strtolower($driver) !== 'mysql') throw new RuntimeException('Learner history repair requires MySQL.');
            // Preserve the applied migration and its constraints. The additive
            // parent guard runs before FK cascades and rejects history loss.
            return [<<<'SQL'
CREATE TRIGGER learner_participation_history_restrict_delete
BEFORE DELETE ON student_profiles
FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM learner_participation_events WHERE studentId = OLD.id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Learner participation history must be retained';
    END IF;
END
SQL];
        }
        public function expectedSchema(): array
        {
            return ['learner_participation_events'=>['columns'=>['id','studentId','eventKey','occurredAt'],'indexes'=>['PRIMARY','uq_learner_part_events_event_key']]];
        }
    }
);
