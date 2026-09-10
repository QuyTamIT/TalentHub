<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    private const ORIGINAL = "status IN ('submitted','reviewing','interview','accepted','declined','withdrawn')";
    private const FINAL = "status IN ('invited','submitted','reviewing','interview','accepted','declined','withdrawn')";

    public function description(): string
    {
        return 'Add invited to the canonical internship application lifecycle';
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['internship_applications', 'application_status_history'] as $table) {
            $context->assertTableExists($table);
        }

        $applicationCheck = $this->checkClause($context, 'internship_applications', 'chk_internship_applications_status');
        $historyCheck = $this->checkClause($context, 'application_status_history', 'chk_application_status_history_status');
        foreach ([$applicationCheck, $historyCheck] as $check) {
            if (!in_array($check, [$this->normalize(self::ORIGINAL), $this->normalize(self::FINAL)], true)) {
                throw new RuntimeException('Internship application status CHECK is not compatible with the canonical lifecycle.');
            }
        }

        $unsupported = $context->pdo()->query(<<<'SQL'
            SELECT COUNT(*) FROM internship_applications
            WHERE status NOT IN ('invited','submitted','reviewing','interview','accepted','declined','withdrawn')
        SQL)?->fetchColumn();
        if ((int) $unsupported !== 0) {
            throw new RuntimeException('internship_applications contains unsupported status values.');
        }
    }

    public function up(MigrationContext $context): void
    {
        if (!hash_equals($this->normalize(self::FINAL), $this->checkClause($context, 'internship_applications', 'chk_internship_applications_status'))) {
            $context->execute(<<<'SQL'
                ALTER TABLE internship_applications
                    DROP CHECK chk_internship_applications_status,
                    ADD CONSTRAINT chk_internship_applications_status
                    CHECK (status IN ('invited','submitted','reviewing','interview','accepted','declined','withdrawn'))
            SQL);
        }
        if (!hash_equals($this->normalize(self::FINAL), $this->checkClause($context, 'application_status_history', 'chk_application_status_history_status'))) {
            $context->execute(<<<'SQL'
                ALTER TABLE application_status_history
                    DROP CHECK chk_application_status_history_status,
                    ADD CONSTRAINT chk_application_status_history_status
                    CHECK (toStatus IN ('invited','submitted','reviewing','interview','accepted','declined','withdrawn'))
            SQL);
        }
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Invited internship application status migration is irreversible.');
    }

    private function checkClause(MigrationContext $context, string $table, string $name): string
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT cc.check_clause
            FROM information_schema.table_constraints tc
            INNER JOIN information_schema.check_constraints cc
                ON cc.constraint_schema = tc.constraint_schema
               AND cc.constraint_name = tc.constraint_name
            WHERE tc.table_schema = DATABASE()
              AND tc.table_name = :table
              AND tc.constraint_name = :name
              AND tc.constraint_type = 'CHECK'
        SQL);
        $statement->execute(['table' => $table, 'name' => $name]);
        $clause = $statement->fetchColumn();
        if (!is_string($clause)) {
            throw new RuntimeException("{$table} is missing CHECK {$name}.");
        }
        return $this->normalize($clause);
    }

    private function normalize(string $value): string
    {
        $value = str_replace(["_utf8mb4", "\\'", 'tostatus'], ['', "'", 'status'], strtolower($value));
        return preg_replace('/[\s`()]+/', '', $value) ?? '';
    }
};