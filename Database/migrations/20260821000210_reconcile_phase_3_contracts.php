<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Reconcile Phase 3 project evidence and profile-share consent contracts';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('projects');
        $context->assertTableExists('project_members');
        $context->assertTableExists('student_profile_shares');
        $context->assertTableExists('privacy_consents');
        $context->assertTableExists('student_profiles');
        $context->assertTableExists('schools');
        $context->assertTableExists('teacher_profiles');
        $context->assertTableExists('users');

        $timeZone = $context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new RuntimeException('Phase 3 reconciliation requires MySQL session time zone +00:00.');
        }

        $this->assertColumns($context, 'projects', [
            'id', 'schoolId', 'mentorTeacherId', 'title', 'description', 'projectUrl',
            'startAt', 'endAt', 'status', 'createdAt', 'updatedAt',
        ]);
        $this->assertColumns($context, 'project_members', [
            'id', 'projectId', 'studentId', 'role', 'status', 'joinedAt', 'leftAt', 'createdAt', 'updatedAt',
        ]);
        $this->assertColumns($context, 'student_profile_shares', [
            'id', 'studentId', 'tokenHash', 'sharedFieldsJson', 'expiresAt', 'revokedAt', 'createdAt',
        ]);
        $this->assertColumns($context, 'privacy_consents', [
            'id', 'studentId', 'scope', 'isGranted', 'policyVersion', 'grantedAt', 'revokedAt', 'createdAt',
        ]);

        foreach (['startAt', 'endAt'] as $column) {
            $type = $this->columnType($context, 'projects', $column);
            if (!in_array($type, ['date', 'datetime'], true)) {
                throw new RuntimeException("projects.{$column} must be DATE or DATETIME before reconciliation.");
            }
        }
        $this->assertOptionalColumnType($context, 'projects', 'category', ['varchar']);
        $this->assertOptionalColumnType($context, 'project_members', 'contribution', ['text', 'mediumtext', 'longtext']);
        $this->assertOptionalColumnType($context, 'student_profile_shares', 'consentId', ['char']);

        $this->assertIndex($context, 'project_members', 'uq_project_members_student');
        $this->assertIndex($context, 'student_profile_shares', 'uq_student_profile_shares_token_hash');
        $this->assertForeignKey($context, 'project_members', 'fk_project_members_project', 'projects');
        $this->assertForeignKey($context, 'project_members', 'fk_project_members_student', 'student_profiles');
        $this->assertForeignKey($context, 'student_profile_shares', 'fk_student_profile_shares_student', 'student_profiles');

        $unsupportedStatus = $context->pdo()->query(
            "SELECT COUNT(*) FROM projects WHERE status NOT IN ('draft','in_progress','completed','archived')"
        )?->fetchColumn();
        if ((int) $unsupportedStatus !== 0) {
            throw new RuntimeException('projects contains unsupported status values.');
        }
        $unsupportedMemberStatus = $context->pdo()->query(
            "SELECT COUNT(*) FROM project_members WHERE status NOT IN ('active','left','removed')"
        )?->fetchColumn();
        if ((int) $unsupportedMemberStatus !== 0) {
            throw new RuntimeException('project_members contains unsupported status values.');
        }

        if ($this->hasColumn($context, 'student_profile_shares', 'consentId')) {
            $orphans = $context->pdo()->query(<<<'SQL'
                SELECT COUNT(*)
                FROM student_profile_shares s
                LEFT JOIN privacy_consents c ON c.id = s.consentId
                WHERE s.consentId IS NOT NULL AND c.id IS NULL
            SQL
            )?->fetchColumn();
            if ((int) $orphans !== 0) {
                throw new RuntimeException('student_profile_shares contains orphan consentId values.');
            }
        }
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();

        if (!$this->hasColumn($context, 'projects', 'category')) {
            $pdo->exec('ALTER TABLE projects ADD COLUMN category VARCHAR(100) NULL AFTER title');
        }
        $pdo->exec("UPDATE projects SET category = 'general' WHERE category IS NULL OR TRIM(category) = ''");
        $pdo->exec('ALTER TABLE projects MODIFY COLUMN category VARCHAR(100) NOT NULL');
        $pdo->exec('ALTER TABLE projects MODIFY COLUMN startAt DATETIME(6) NULL, MODIFY COLUMN endAt DATETIME(6) NULL');

        if (!$this->hasColumn($context, 'project_members', 'contribution')) {
            $pdo->exec('ALTER TABLE project_members ADD COLUMN contribution TEXT NULL AFTER role');
        }

        if (!$this->hasColumn($context, 'student_profile_shares', 'consentId')) {
            $pdo->exec('ALTER TABLE student_profile_shares ADD COLUMN consentId CHAR(36) NULL AFTER studentId');
        }
        if (!$this->hasIndex($context, 'student_profile_shares', 'idx_student_profile_shares_consent')) {
            $pdo->exec('ALTER TABLE student_profile_shares ADD KEY idx_student_profile_shares_consent (consentId)');
        }
        if (!$this->hasForeignKey($context, 'student_profile_shares', 'fk_student_profile_shares_consent')) {
            $pdo->exec(<<<'SQL'
                ALTER TABLE student_profile_shares
                ADD CONSTRAINT fk_student_profile_shares_consent
                FOREIGN KEY (consentId) REFERENCES privacy_consents(id)
                ON DELETE SET NULL ON UPDATE CASCADE
            SQL
            );
        }
    }

    public function down(MigrationContext $context): void
    {
        // Forward recovery only: Phase 3 evidence and consent links must be preserved.
    }

    /** @param list<string> $required */
    private function assertColumns(MigrationContext $context, string $table, array $required): void
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table
        SQL
        );
        $statement->execute(['table' => $table]);
        $columns = array_fill_keys($statement->fetchAll(PDO::FETCH_COLUMN), true);
        foreach ($required as $column) {
            if (!isset($columns[$column])) {
                throw new RuntimeException("{$table} table missing column: {$column}");
            }
        }
    }

    /** @param list<string> $allowedTypes */
    private function assertOptionalColumnType(
        MigrationContext $context,
        string $table,
        string $column,
        array $allowedTypes,
    ): void {
        if (!$this->hasColumn($context, $table, $column)) {
            return;
        }
        $type = $this->columnType($context, $table, $column);
        if (!in_array($type, $allowedTypes, true)) {
            throw new RuntimeException("{$table}.{$column} has incompatible type: {$type}");
        }
    }

    private function columnType(MigrationContext $context, string $table, string $column): string
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT data_type
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
            LIMIT 1
        SQL
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        $type = $statement->fetchColumn();
        if (!is_string($type) || $type === '') {
            throw new RuntimeException("Missing column: {$table}.{$column}");
        }
        return strtolower($type);
    }

    private function hasColumn(MigrationContext $context, string $table, string $column): bool
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column
        SQL
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function assertIndex(MigrationContext $context, string $table, string $index): void
    {
        if (!$this->hasIndex($context, $table, $index)) {
            throw new RuntimeException("{$table} table missing index: {$index}");
        }
    }

    private function hasIndex(MigrationContext $context, string $table, string $index): bool
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index_name
        SQL
        );
        $statement->execute(['table' => $table, 'index_name' => $index]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function assertForeignKey(
        MigrationContext $context,
        string $table,
        string $constraint,
        string $referencedTable,
    ): void {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT referenced_table_name
            FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = :table
              AND constraint_name = :constraint_name
            LIMIT 1
        SQL
        );
        $statement->execute(['table' => $table, 'constraint_name' => $constraint]);
        if ($statement->fetchColumn() !== $referencedTable) {
            throw new RuntimeException("{$table} has incompatible foreign key: {$constraint}");
        }
    }

    private function hasForeignKey(MigrationContext $context, string $table, string $constraint): bool
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = :table
              AND constraint_name = :constraint_name
        SQL
        );
        $statement->execute(['table' => $table, 'constraint_name' => $constraint]);
        return (int) $statement->fetchColumn() === 1;
    }
};
