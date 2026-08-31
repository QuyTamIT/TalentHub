<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Extend student-enterprise consent grants to application profile sharing'; }
    public function isReversible(): bool { return false; }

    public function preflight(MigrationContext $context): void
    {
        foreach (['student_profiles', 'enterprises', 'privacy_consents', 'enterprise_talent_access_grants', 'audit_logs'] as $table) $context->assertTableExists($table);
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();
        if ($this->hasCheck($pdo, 'enterprise_talent_access_grants', 'chk_enterprise_talent_grant_scope')) {
            $pdo->exec('ALTER TABLE enterprise_talent_access_grants DROP CHECK chk_enterprise_talent_grant_scope');
        }
        $pdo->exec("ALTER TABLE enterprise_talent_access_grants ADD CONSTRAINT chk_enterprise_talent_grant_scope CHECK (scope IN ('enterprise_talent_discovery','enterprise_talent_contact','application_profile_share'))");
    }

    public function down(MigrationContext $context): void { throw new RuntimeException('Enterprise consent history is forward-only.'); }

    private function hasCheck(PDO $pdo, string $table, string $constraint): bool
    {
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name=:table AND constraint_name=:constraint AND constraint_type=\'CHECK\' LIMIT 1');
        $statement->execute(['table'=>$table,'constraint'=>$constraint]);return (bool)$statement->fetchColumn();
    }
};
