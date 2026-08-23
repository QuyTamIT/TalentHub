<?php
declare(strict_types=1);
use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\LearnerMigrationBridge;
use TalentHub\Database\Migration\MigrationContext;
return new class extends AbstractMigration {
    public function description(): string { return 'Bridge canonical learner AI input foundation into the deployment migration chain'; }
    public function isReversible(): bool { return false; }
    public function preflight(MigrationContext $context): void { foreach (['student_profiles','activities','activity_registrations'] as $table) { $context->assertTableExists($table); } }
    public function up(MigrationContext $context): void { LearnerMigrationBridge::migrate($context->pdo(), '002_create_ai_input_foundation'); }
    public function down(MigrationContext $context): void {}
};
