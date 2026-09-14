<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Widen AI snapshot evidence sourceId for composite skill identifiers';
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('learner_recommendation_snapshot_evidence');
        $context->assertTableExists('learner_recommendation_evidence');
    }

    public function up(MigrationContext $context): void
    {
        if ($context->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return;
        }

        foreach (['learner_recommendation_snapshot_evidence', 'learner_recommendation_evidence'] as $table) {
            $length = $context->pdo()->query(
                "SELECT character_maximum_length
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = " . $context->pdo()->quote($table) . "
                   AND column_name = 'sourceId'
                 LIMIT 1"
            )?->fetchColumn();
            if ((int) $length >= 128) {
                continue;
            }
            $context->execute("ALTER TABLE {$table} MODIFY COLUMN sourceId VARCHAR(191) NOT NULL");
        }
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('AI evidence source IDs may exceed 36 characters after this migration.');
    }
};
