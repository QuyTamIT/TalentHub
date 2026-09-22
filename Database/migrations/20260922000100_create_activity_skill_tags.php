<?php
declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Link catalog skills to activities for activity-scoped teacher grading';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('activities');
        $context->assertTableExists('skills');
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS activity_skill_tags (
    id CHAR(36) NOT NULL PRIMARY KEY,
    activityId CHAR(36) NOT NULL,
    skillId CHAR(36) NOT NULL,
    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_activity_skill_tags_activity_skill (activityId, skillId),
    KEY idx_activity_skill_tags_activity (activityId),
    KEY idx_activity_skill_tags_skill (skillId),
    CONSTRAINT fk_activity_skill_tags_activity FOREIGN KEY (activityId) REFERENCES activities(id),
    CONSTRAINT fk_activity_skill_tags_skill FOREIGN KEY (skillId) REFERENCES skills(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Forward-only: preserve activity skill assignments.');
    }
};
