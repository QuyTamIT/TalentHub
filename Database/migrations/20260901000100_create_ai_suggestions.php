<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create AI suggestions';
    }

    public function preflight(MigrationContext $context): void
    {
        // The original migration intentionally permits an existing table.
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `ai_suggestions` (
    `id` char(36) NOT NULL,
    `user_id` char(36) NOT NULL,
    `prompt` text NOT NULL,
    `result` json NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ai_suggestions_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function isReversible(): bool
    {
        return true;
    }

    public function down(MigrationContext $context): void
    {
        $context->execute('DROP TABLE IF EXISTS `ai_suggestions`');
    }
};
