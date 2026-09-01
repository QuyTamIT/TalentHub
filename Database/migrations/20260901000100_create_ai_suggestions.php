<?php
declare(strict_types=1);

function up_20260901000100_create_ai_suggestions(\PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS `ai_suggestions` (
        `id` char(36) NOT NULL,
        `user_id` char(36) NOT NULL,
        `prompt` text NOT NULL,
        `result` json NOT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_ai_suggestions_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
}

function down_20260901000100_create_ai_suggestions(\PDO $pdo): void
{
    $pdo->exec("DROP TABLE IF EXISTS `ai_suggestions`;");
}
