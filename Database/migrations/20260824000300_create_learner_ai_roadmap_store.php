<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Create versioned learner AI roadmap store'; }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('student_profiles');
        $context->assertTableExists('learner_recommendation_runs');
        foreach (['learner_ai_roadmaps','learner_ai_roadmap_phases','learner_ai_roadmap_tasks','learner_ai_roadmap_task_events'] as $table) $context->assertTableAbsent($table);
    }

    public function up(MigrationContext $context): void
    {
        require_once dirname(__DIR__, 2) . '/app/learner/data/bootstrap.php';
        $definition = require __DIR__ . '/learner/005_create_ai_roadmap_store.php';
        foreach ($definition->migration->statements('mysql') as $statement) $context->execute($statement);
    }

    public function isReversible(): bool { return false; }
    public function down(MigrationContext $context): void { throw new RuntimeException('Learner AI roadmap store migration is irreversible.'); }
};
