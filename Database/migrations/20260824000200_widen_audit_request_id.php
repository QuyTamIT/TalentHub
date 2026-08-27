<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Allow browser request IDs in audit logs';
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('audit_logs');

        $statement = $context->pdo()->query(<<<'SQL'
SELECT data_type, character_maximum_length
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'audit_logs'
  AND column_name = 'requestId'
LIMIT 1
SQL);
        $column = $statement?->fetch(PDO::FETCH_NUM);
        if (!is_array($column) || !in_array(strtolower((string) ($column[0] ?? '')), ['char', 'varchar'], true)) {
            throw new RuntimeException('audit_logs.requestId must be a character column before widening.');
        }
    }

    public function up(MigrationContext $context): void
    {
        $length = $context->pdo()->query(<<<'SQL'
SELECT character_maximum_length
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'audit_logs'
  AND column_name = 'requestId'
LIMIT 1
SQL)?->fetchColumn();

        if ((int) $length < 64) {
            $context->execute('ALTER TABLE audit_logs MODIFY COLUMN requestId VARCHAR(64) NULL');
        }
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Audit request IDs may exceed 26 characters after this migration.');
    }
};
