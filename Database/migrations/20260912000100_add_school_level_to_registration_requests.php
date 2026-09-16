<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Add schoolLevel column to organization_registration_requests for school grade level selection';
    }

    public function isReversible(): bool
    {
        return true;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('organization_registration_requests');
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();
        
        // Add schoolLevel column (only for school type registrations)
        $pdo->exec(<<<'SQL'
        ALTER TABLE organization_registration_requests
          ADD COLUMN schoolLevel VARCHAR(50) NULL AFTER type
        SQL);
        
        // Add index for faster filtering
        $pdo->exec(<<<'SQL'
        ALTER TABLE organization_registration_requests
          ADD KEY idx_org_reg_type_school_level (type, schoolLevel)
        SQL);
    }

    public function down(MigrationContext $context): void
    {
        $pdo = $context->pdo();
        $pdo->exec(<<<'SQL'
        ALTER TABLE organization_registration_requests
          DROP COLUMN schoolLevel
        SQL);
    }
};