<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;
use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\LearnerRoadmapRegistryReconciler;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Reconcile the pre-existing learner AI roadmap store with the forward migration registry';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        require_once dirname(__DIR__, 2) . '/app/learner/data/bootstrap.php';
        $database = (string) ($context->pdo()->query('SELECT DATABASE()')->fetchColumn() ?: '');
        if ($database === '') {
            throw new RuntimeException('Roadmap registry reconciliation requires a selected database.');
        }
        $schema = new SchemaInspector($context->pdo(), $database);
        LearnerRoadmapRegistryReconciler::assertExistingSchema($schema);
    }

    public function up(MigrationContext $context): void
    {
        require_once dirname(__DIR__, 2) . '/app/learner/data/bootstrap.php';
        $database = (string) ($context->pdo()->query('SELECT DATABASE()')->fetchColumn() ?: '');
        $schema = new SchemaInspector($context->pdo(), $database);
        LearnerRoadmapRegistryReconciler::reconcile(
            $context->pdo(),
            $schema,
            dirname(__DIR__) . '/migrations/learner/005_create_ai_roadmap_store.php',
        );
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Roadmap registry reconciliation is forward-only.');
    }
};
