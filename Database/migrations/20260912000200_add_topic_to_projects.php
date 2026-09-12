<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Add topic column to projects table to support project categorization and enterprise matching';
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('projects');
    }

    public function up(MigrationContext $context): void
    {
        if (!$this->columnExists($context, 'projects', 'topic')) {
            $context->execute(<<<'SQL'
                ALTER TABLE projects
                    ADD COLUMN topic VARCHAR(255) NULL AFTER category
            SQL);
        }
    }

    public function isReversible(): bool
    {
        return true;
    }

    public function down(MigrationContext $context): void
    {
        if ($this->columnExists($context, 'projects', 'topic')) {
            $context->execute(<<<'SQL'
                ALTER TABLE projects
                    DROP COLUMN topic
            SQL);
        }
    }

    private function columnExists(MigrationContext $context, string $table, string $column): bool
    {
        $statement = $context->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }
};
