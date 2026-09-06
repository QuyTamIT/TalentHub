<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    private const CONSTRAINT = 'chk_learner_ai_roadmap_tasks_position';

    public function description(): string
    {
        return 'Expand learner roadmap task positions from five to ten per phase';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('learner_ai_roadmap_tasks');
        $range = $this->positionRange($context);
        if (!in_array($range, ['positionbetween1and5', 'positionbetween1and10'], true)) {
            throw new RuntimeException('Learner roadmap task position constraint is not a supported 1-5 or 1-10 range.');
        }
    }

    public function up(MigrationContext $context): void
    {
        if ($this->positionRange($context) === 'positionbetween1and10') {
            return;
        }
        $context->execute(
            'ALTER TABLE learner_ai_roadmap_tasks '
            . 'DROP CHECK chk_learner_ai_roadmap_tasks_position, '
            . 'ADD CONSTRAINT chk_learner_ai_roadmap_tasks_position CHECK (position BETWEEN 1 AND 10)'
        );
        if ($this->positionRange($context) !== 'positionbetween1and10') {
            throw new RuntimeException('Learner roadmap task position constraint was not expanded to 1-10.');
        }
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('The learner roadmap task limit expansion is forward-only.');
    }

    private function positionRange(MigrationContext $context): ?string
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT check_clause
            FROM information_schema.check_constraints
            WHERE constraint_schema = DATABASE()
              AND constraint_name = :constraint
            LIMIT 1
            SQL);
        $statement->execute(['constraint' => self::CONSTRAINT]);
        $clause = $statement->fetchColumn();
        return is_string($clause)
            ? preg_replace('/[^a-z0-9]+/', '', strtolower($clause))
            : null;
    }
};
