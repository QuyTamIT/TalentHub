<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\LearnerMigrationBridge;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Bridge learner AI capability profiles into the deployment migration chain'; }
    public function isReversible(): bool { return false; }
    public function up(MigrationContext $context): void { LearnerMigrationBridge::migrate($context->pdo(), '009_create_ai_capability_profiles'); }
    public function down(MigrationContext $context): void {}
};
