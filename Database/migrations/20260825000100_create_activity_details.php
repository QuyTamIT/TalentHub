<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create additive school-scoped activity detail metadata';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['activities', 'teacher_profiles'] as $table) {
            $context->assertTableExists($table);
        }
        if ($context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn() !== '+00:00') {
            throw new RuntimeException('Activity details migration requires MySQL session time zone +00:00.');
        }
        $missingTeacher = $context->pdo()->query(<<<'SQL'
            SELECT COUNT(*)
            FROM activities activity
            LEFT JOIN teacher_profiles teacher ON teacher.id = activity.createdByTeacherId
            WHERE teacher.id IS NULL OR teacher.schoolId <> activity.schoolId
        SQL
        )?->fetchColumn();
        if ((int) $missingTeacher !== 0) {
            throw new RuntimeException('Every activity must have a responsible teacher from its own school before adding activity details.');
        }
        if ($context->tableExists('activity_details')) {
            $this->assertExistingDetailsContract($context);
            return;
        }
        if ($this->semanticEquivalentExists($context)) {
            throw new RuntimeException('A semantic equivalent activity detail metadata table already exists.');
        }
    }

    public function up(MigrationContext $context): void
    {
        if ($context->tableExists('activity_details')) {
            return;
        }
        $context->execute(<<<'SQL'
            CREATE TABLE activity_details (
                activityId CHAR(36) NOT NULL,
                responsibleTeacherId CHAR(36) NULL,
                audienceScope VARCHAR(24) NOT NULL DEFAULT 'school_only',
                displayCategory VARCHAR(120) NOT NULL,
                filterCategory VARCHAR(120) NOT NULL,
                summary VARCHAR(500) NOT NULL,
                description TEXT NOT NULL,
                experienceHighlights JSON NOT NULL,
                skillTags JSON NOT NULL,
                eligibilityRules JSON NOT NULL,
                benefitItems JSON NOT NULL,
                locationName VARCHAR(255) NOT NULL,
                locationAddress VARCHAR(500) NULL,
                deliveryMode VARCHAR(24) NOT NULL DEFAULT 'in_person',
                onlineMeetingUrl VARCHAR(500) NULL,
                organizerName VARCHAR(255) NOT NULL,
                organizerContact VARCHAR(255) NULL,
                organizerEmail VARCHAR(255) NULL,
                organizerPhone VARCHAR(30) NULL,
                coverImageUrl VARCHAR(500) NULL,
                coverImageAlt VARCHAR(255) NULL,
                feeAmount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                currency CHAR(3) NOT NULL DEFAULT 'VND',
                targetAudience VARCHAR(255) NOT NULL,
                certificateLabel VARCHAR(255) NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
                    ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (activityId),
                KEY idx_activity_details_scope_category (audienceScope, filterCategory),
                KEY idx_activity_details_responsible_teacher (responsibleTeacherId),
                CONSTRAINT fk_activity_details_activity FOREIGN KEY (activityId)
                    REFERENCES activities(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_activity_details_teacher FOREIGN KEY (responsibleTeacherId)
                    REFERENCES teacher_profiles(id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT chk_activity_details_scope CHECK (audienceScope IN ('school_only')),
                CONSTRAINT chk_activity_details_delivery CHECK
                    (deliveryMode IN ('in_person', 'online', 'hybrid')),
                CONSTRAINT chk_activity_details_fee CHECK (feeAmount >= 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL
        );
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only: activity details may be referenced by learner history and audit records.
    }

    private function semanticEquivalentExists(MigrationContext $context): bool
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT table_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND column_name IN ('activityId', 'audienceScope', 'filterCategory')
            GROUP BY table_name
            HAVING COUNT(DISTINCT column_name) = 3
        SQL
        );
        $statement->execute();
        return $statement->fetchColumn() !== false;
    }

    private function assertExistingDetailsContract(MigrationContext $context): void
    {
        $expected = [
            ['activityId', 'char', false, 36, null, null, null],
            ['responsibleTeacherId', 'char', true, 36, null, null, null],
            ['audienceScope', 'varchar', false, 24, 'school_only', null, null],
            ['displayCategory', 'varchar', false, 120, null, null, null],
            ['filterCategory', 'varchar', false, 120, null, null, null],
            ['summary', 'varchar', false, 500, null, null, null],
            ['description', 'text', false, 65535, null, null, null],
            ['experienceHighlights', 'json', false, null, null, null, null],
            ['skillTags', 'json', false, null, null, null, null],
            ['eligibilityRules', 'json', false, null, null, null, null],
            ['benefitItems', 'json', false, null, null, null, null],
            ['locationName', 'varchar', false, 255, null, null, null],
            ['locationAddress', 'varchar', true, 500, null, null, null],
            ['deliveryMode', 'varchar', false, 24, 'in_person', null, null],
            ['onlineMeetingUrl', 'varchar', true, 500, null, null, null],
            ['organizerName', 'varchar', false, 255, null, null, null],
            ['organizerContact', 'varchar', true, 255, null, null, null],
            ['organizerEmail', 'varchar', true, 255, null, null, null],
            ['organizerPhone', 'varchar', true, 30, null, null, null],
            ['coverImageUrl', 'varchar', true, 500, null, null, null],
            ['coverImageAlt', 'varchar', true, 255, null, null, null],
            ['feeAmount', 'decimal', false, null, '0.00', 12, 2],
            ['currency', 'char', false, 3, 'VND', null, null],
            ['targetAudience', 'varchar', false, 255, null, null, null],
            ['certificateLabel', 'varchar', true, 255, null, null, null],
            ['createdAt', 'datetime', false, null, 'CURRENT_TIMESTAMP(6)', null, null],
            ['updatedAt', 'datetime', false, null, 'CURRENT_TIMESTAMP(6)', null, null],
        ];
        $columns = $this->columns($context, 'activity_details');
        if (count($columns) !== count($expected)) {
            throw new RuntimeException('Existing activity_details table must contain exactly 27 canonical columns.');
        }
        foreach ($expected as [$name, $type, $nullable, $length, $default, $precision, $scale]) {
            $this->assertColumn($columns, $name, $type, $nullable, $length, $default, $precision, $scale);
        }
        $table = $context->pdo()->query("SELECT engine, table_collation FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='activity_details'")?->fetch(PDO::FETCH_ASSOC);
        if (is_array($table)) {
            $table = array_change_key_case($table, CASE_LOWER);
        }
        if (!is_array($table) || strtoupper((string) $table['engine']) !== 'INNODB' || (string) $table['table_collation'] !== 'utf8mb4_unicode_ci') {
            throw new RuntimeException('Existing activity_details table engine or collation is incompatible.');
        }
        $this->assertIndex($context, 'PRIMARY', true, ['activityId']);
        $this->assertIndex($context, 'idx_activity_details_scope_category', false, ['audienceScope', 'filterCategory']);
        $this->assertIndex($context, 'idx_activity_details_responsible_teacher', false, ['responsibleTeacherId']);
        $this->assertForeignKey($context, 'fk_activity_details_activity', 'activityId', 'activities', 'id', 'CASCADE', 'CASCADE');
        $this->assertForeignKey($context, 'fk_activity_details_teacher', 'responsibleTeacherId', 'teacher_profiles', 'id', 'SET NULL', 'CASCADE');
        $this->assertCheck($context, 'chk_activity_details_scope', "audienceScope IN ('school_only')");
        $this->assertCheck($context, 'chk_activity_details_delivery', "deliveryMode IN ('in_person', 'online', 'hybrid')");
        $this->assertCheck($context, 'chk_activity_details_fee', 'feeAmount >= 0');
    }

    /** @return array<string,array<string,mixed>> */
    private function columns(MigrationContext $context, string $table): array
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT column_name, data_type, is_nullable, column_default, character_maximum_length,
                   numeric_precision, numeric_scale, datetime_precision, extra
            FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table
        SQL
        );
        $statement->execute(['table' => $table]);
        $columns = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $columns[(string) $row['column_name']] = $row;
        }
        return $columns;
    }

    /** @param array<string,array<string,mixed>> $columns */
    private function assertColumn(array $columns, string $name, string $type, bool $nullable, ?int $length, ?string $default, ?int $precision, ?int $scale): void
    {
        $column = $columns[$name] ?? null;
        $lengthMatches = $type === 'json'
            // Native JSON has version-dependent character metadata; data_type=json is the exact storage contract.
            ? true
            : ($column['character_maximum_length'] === null ? null : (int) $column['character_maximum_length']) === $length;
        if (!is_array($column)
            || strtolower((string) $column['data_type']) !== $type
            || ((string) $column['is_nullable'] === 'YES') !== $nullable
            || !$lengthMatches
            || ($column['numeric_precision'] === null ? null : (int) $column['numeric_precision']) !== $precision
            || ($column['numeric_scale'] === null ? null : (int) $column['numeric_scale']) !== $scale
            || strtoupper((string) ($column['column_default'] ?? '')) !== strtoupper((string) ($default ?? ''))) {
            throw new RuntimeException("Existing activity_details.{$name} has incompatible exact metadata.");
        }
        if (in_array($name, ['createdAt', 'updatedAt'], true)) {
            $extra = $this->normalizeExtra((string) ($column['extra'] ?? ''));
            $expectedExtra = $name === 'updatedAt'
                ? 'default_generated on update current_timestamp(6)'
                : 'default_generated';
            if ((int) ($column['datetime_precision'] ?? -1) !== 6 || $extra !== $expectedExtra) {
                throw new RuntimeException("Existing activity_details.{$name} must retain exact DATETIME(6) generated metadata.");
            }
        }
    }

    /** @param list<string> $columns */
    private function assertIndex(MigrationContext $context, string $name, bool $unique, array $columns): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT non_unique, GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') columns_list
            FROM information_schema.statistics
            WHERE table_schema=DATABASE() AND table_name='activity_details' AND index_name=:name
            GROUP BY non_unique
        SQL
        );
        $statement->execute(['name' => $name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row = array_change_key_case($row, CASE_LOWER);
        }
        if (!is_array($row) || (((int) $row['non_unique'] === 0) !== $unique) || (string) $row['columns_list'] !== implode(',', $columns)) {
            throw new RuntimeException("Existing activity_details index {$name} is incompatible.");
        }
    }

    private function assertForeignKey(MigrationContext $context, string $name, string $column, string $table, string $referencedColumn, string $delete, string $update): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT kcu.column_name, kcu.referenced_table_name, kcu.referenced_column_name, rc.delete_rule, rc.update_rule
            FROM information_schema.key_column_usage kcu
            INNER JOIN information_schema.referential_constraints rc
              ON rc.constraint_schema=kcu.constraint_schema AND rc.constraint_name=kcu.constraint_name AND rc.table_name=kcu.table_name
            WHERE kcu.constraint_schema=DATABASE() AND kcu.table_name='activity_details' AND kcu.constraint_name=:name
        SQL
        );
        $statement->execute(['name' => $name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row = array_change_key_case($row, CASE_LOWER);
        }
        if (!is_array($row) || (string) $row['column_name'] !== $column || (string) $row['referenced_table_name'] !== $table
            || (string) $row['referenced_column_name'] !== $referencedColumn || strtoupper((string) $row['delete_rule']) !== $delete
            || strtoupper((string) $row['update_rule']) !== $update) {
            throw new RuntimeException("Existing activity_details foreign key {$name} is incompatible.");
        }
    }

    private function assertCheck(MigrationContext $context, string $name, string $expected): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT cc.check_clause FROM information_schema.table_constraints tc
            INNER JOIN information_schema.check_constraints cc ON cc.constraint_schema=tc.constraint_schema AND cc.constraint_name=tc.constraint_name
            WHERE tc.table_schema=DATABASE() AND tc.table_name='activity_details' AND tc.constraint_name=:name AND tc.constraint_type='CHECK'
        SQL
        );
        $statement->execute(['name' => $name]);
        if (!hash_equals($this->normalizeCheck($expected), $this->normalizeCheck((string) $statement->fetchColumn()))) {
            throw new RuntimeException("Existing activity_details CHECK {$name} is incompatible.");
        }
    }

    private function normalizeCheck(string $value): string
    {
        // MySQL INFORMATION_SCHEMA escapes quoted CHECK literals as \' in CHECK_CLAUSE.
        $value = str_replace(["_utf8mb4", "\\'"], ['', "'"], $value);
        // MySQL canonicalizes a one-item IN predicate to equality; keep multi-value IN predicates intact.
        $value = preg_replace(
            "/`?([a-z_][a-z0-9_]*)`?\\s+IN\\s*\\(\\s*'([^']*)'\\s*\\)/i",
            "$1 = '$2'",
            $value,
        ) ?? $value;
        return preg_replace('/[\s`()]+/', '', strtolower($value)) ?? '';
    }

    private function normalizeExtra(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower($value)) ?? '');
    }
};
