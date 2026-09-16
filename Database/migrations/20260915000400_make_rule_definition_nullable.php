<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

/**
 * Make ruleDefinitionId nullable in student_badges.
 * Manual badge awards (teacher/school admin) don't have an automated rule,
 * so the column must allow NULL.
 */
return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Make ruleDefinitionId nullable in student_badges for manual badge awards';
    }

    public function isReversible(): bool
    {
        return true;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('student_badges');
    }

    public function up(MigrationContext $context): void
    {
        $context->execute('ALTER TABLE student_badges DROP FOREIGN KEY fk_student_badges_rule');
        $context->execute('ALTER TABLE student_badges MODIFY ruleDefinitionId char(36) COLLATE utf8mb4_unicode_ci NULL');
        $context->execute(<<<'SQL'
ALTER TABLE student_badges ADD CONSTRAINT fk_student_badges_rule
  FOREIGN KEY (ruleDefinitionId) REFERENCES badge_rule_definitions (id)
  ON DELETE RESTRICT ON UPDATE CASCADE
SQL);
    }

    public function down(MigrationContext $context): void
    {
        $context->execute('DELETE FROM student_badges WHERE ruleDefinitionId IS NULL');
        $context->execute('ALTER TABLE student_badges DROP FOREIGN KEY fk_student_badges_rule');
        $context->execute('ALTER TABLE student_badges MODIFY ruleDefinitionId char(36) COLLATE utf8mb4_unicode_ci NOT NULL');
        $context->execute(<<<'SQL'
ALTER TABLE student_badges ADD CONSTRAINT fk_student_badges_rule
  FOREIGN KEY (ruleDefinitionId) REFERENCES badge_rule_definitions (id)
  ON DELETE RESTRICT ON UPDATE CASCADE
SQL);
    }
};
