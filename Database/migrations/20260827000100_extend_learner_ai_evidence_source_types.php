<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    private const SOURCE_TYPES = "'profile','skill','assessment','activity_experience','activity','evaluation','opportunity','catalog','certificate','project','achievement','badge','progress','checkin','mentor_evaluation','teacher_feedback','roadmap_feedback'";

    public function description(): string
    {
        return 'Allow Phase 2 canonical AI snapshot source types in persisted evidence';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('learner_recommendation_snapshot_evidence');
        $context->assertTableExists('learner_recommendation_evidence');
        $timeZone = $context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new RuntimeException('AI evidence source migration requires MySQL session time zone +00:00.');
        }
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(
            "ALTER TABLE learner_recommendation_snapshot_evidence
             DROP CHECK chk_learner_recommendation_snapshot_evidence_source_type,
             ADD CONSTRAINT chk_learner_recommendation_snapshot_evidence_source_type
             CHECK (sourceType IN (" . self::SOURCE_TYPES . "))",
        );
        $context->execute(
            "ALTER TABLE learner_recommendation_evidence
             DROP CHECK chk_learner_recommendation_evidence_source_type,
             ADD CONSTRAINT chk_learner_recommendation_evidence_source_type
             CHECK (sourceType IN (" . self::SOURCE_TYPES . "))",
        );
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('AI evidence source type expansion is forward-only because persisted evidence must remain readable.');
    }
};
