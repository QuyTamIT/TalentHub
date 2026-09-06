<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Enforce scorer-compatible career role assessment dimensions';
    }

    public function isReversible(): bool
    {
        return true;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('career_role_assessment_signals');
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
            ALTER TABLE career_role_assessment_signals
                ADD CONSTRAINT chk_career_role_assessment_signals_dimension CHECK (
                    (assessmentFamily = 'holland' AND dimensionCode IN ('R','I','A','S','E','C'))
                    OR (assessmentFamily = 'mbti' AND dimensionCode IN ('E','I','S','N','T','F','J','P'))
                    OR (assessmentFamily = 'disc' AND dimensionCode IN ('D','I','S','C'))
                    OR (assessmentFamily = 'multiple_intelligence' AND dimensionCode IN ('LOGI','LING','SPAT','MUSIC','BODY','INTER','INTRA','NAT'))
                )
        SQL);
    }

    public function down(MigrationContext $context): void
    {
        $context->execute(
            'ALTER TABLE career_role_assessment_signals '
            . 'DROP CHECK chk_career_role_assessment_signals_dimension'
        );
    }
};
