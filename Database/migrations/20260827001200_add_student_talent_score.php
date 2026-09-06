<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Add the optional legacy talent score compatibility field to student profiles';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('student_profiles');
        if ($this->hasColumn($context, 'student_profiles', 'talentScore')) {
            $this->assertColumnMetadata($context);
        }
        if ($this->hasConstraint($context, 'student_profiles', 'chk_student_profiles_talent_score')) {
            $this->assertRangeConstraint($context);
        }
    }

    public function up(MigrationContext $context): void
    {
        if (!$this->hasColumn($context, 'student_profiles', 'talentScore')) {
            $context->pdo()->exec(
                'ALTER TABLE student_profiles ADD COLUMN talentScore DECIMAL(5,2) NULL AFTER studyStatus'
            );
        }
        $this->assertColumnMetadata($context);
        if (!$this->hasConstraint($context, 'student_profiles', 'chk_student_profiles_talent_score')) {
            $context->pdo()->exec(
                'ALTER TABLE student_profiles ADD CONSTRAINT chk_student_profiles_talent_score CHECK (talentScore IS NULL OR talentScore BETWEEN 0 AND 100)'
            );
        }
        $this->assertRangeConstraint($context);
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('The talent score compatibility field is forward-only.');
    }

    private function hasColumn(MigrationContext $context, string $table, string $column): bool
    {
        $statement = $context->pdo()->prepare(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1'
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (bool) $statement->fetchColumn();
    }

    private function assertColumnMetadata(MigrationContext $context): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'student_profiles'
              AND column_name = 'talentScore'
            LIMIT 1
            SQL);
        $statement->execute();
        $column = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($column)
            || strtolower((string) $column['COLUMN_TYPE']) !== 'decimal(5,2)'
            || (string) $column['IS_NULLABLE'] !== 'YES'
            || $column['COLUMN_DEFAULT'] !== null
        ) {
            throw new RuntimeException('student_profiles.talentScore must be nullable DECIMAL(5,2) with a NULL default.');
        }
    }

    private function hasConstraint(MigrationContext $context, string $table, string $constraint): bool
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT 1
            FROM information_schema.table_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = :table
              AND constraint_name = :constraint
              AND constraint_type = 'CHECK'
            LIMIT 1
            SQL);
        $statement->execute(['table' => $table, 'constraint' => $constraint]);
        return (bool) $statement->fetchColumn();
    }

    private function assertRangeConstraint(MigrationContext $context): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT check_clause
            FROM information_schema.check_constraints
            WHERE constraint_schema = DATABASE()
              AND constraint_name = 'chk_student_profiles_talent_score'
            LIMIT 1
            SQL);
        $statement->execute();
        $clause = $statement->fetchColumn();
        $normalized = is_string($clause)
            ? preg_replace('/[^a-z0-9]+/', '', strtolower($clause))
            : null;
        if ($normalized !== 'talentscoreisnullortalentscorebetween0and100') {
            throw new RuntimeException('student_profiles.talentScore must be constrained to the inclusive 0-100 range.');
        }
    }
};
