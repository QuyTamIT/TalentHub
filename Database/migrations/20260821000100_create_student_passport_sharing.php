<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create student profile details, profile sharing, and expand privacy consent scopes';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('users');
        $context->assertTableExists('student_profiles');
        $context->assertTableExists('privacy_consents');

        $timeZoneStatement = $context->pdo()->query('SELECT @@session.time_zone');
        $timeZone = $timeZoneStatement === false ? false : $timeZoneStatement->fetchColumn();
        if ($timeZone !== '+00:00') {
            throw new \RuntimeException('Passport sharing migration requires MySQL session time zone +00:00.');
        }

        if ($context->tableExists('student_profile_details')) {
            $this->assertProfileDetailsContract($context);
        }

        if ($context->tableExists('student_profile_shares')) {
            $this->assertProfileSharesContract($context);
        }
    }

    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS student_profile_details (
  studentId CHAR(36) NOT NULL,
  location VARCHAR(255) NULL,
  bio TEXT NULL,
  avatarUrl VARCHAR(500) NULL,
  headline VARCHAR(255) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (studentId),
  CONSTRAINT fk_student_profile_details_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS student_profile_shares (
  id CHAR(36) NOT NULL,
  studentId CHAR(36) NOT NULL,
  tokenHash CHAR(64) NOT NULL,
  sharedFieldsJson JSON NOT NULL,
  expiresAt DATETIME(6) NOT NULL,
  revokedAt DATETIME(6) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_profile_shares_token_hash (tokenHash),
  KEY idx_student_profile_shares_student_active (studentId, revokedAt, expiresAt),
  CONSTRAINT fk_student_profile_shares_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_student_profile_shares_json CHECK (JSON_VALID(sharedFieldsJson)),
  CONSTRAINT chk_student_profile_shares_expiry CHECK (expiresAt > createdAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );

        // Expand privacy consent scope check constraint if not already expanded
        if ($this->hasCheckConstraint($context, 'privacy_consents', 'chk_privacy_consents_scope')) {
            $pdo->exec('ALTER TABLE privacy_consents DROP CHECK chk_privacy_consents_scope');
        }
        $pdo->exec(<<<'SQL'
ALTER TABLE privacy_consents ADD CONSTRAINT chk_privacy_consents_scope CHECK (
  scope IN ('assessment','skills','activity','evaluation','profile_share','application_profile_share')
);
SQL
        );
    }

    public function down(MigrationContext $context): void
    {
        // Passport sharing migration is intentionally forward-only to preserve user sharing and profile data.
    }

    private function assertProfileDetailsContract(MigrationContext $context): void
    {
        $statement = $context->pdo()->query(<<<'SQL'
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'student_profile_details'
        SQL
        );
        $columns = $statement ? $statement->fetchAll(\PDO::FETCH_KEY_PAIR) : [];
        foreach (['studentId', 'location', 'bio', 'avatarUrl', 'headline', 'createdAt', 'updatedAt'] as $col) {
            if (!isset($columns[$col])) {
                throw new \RuntimeException("student_profile_details table missing column: {$col}");
            }
        }
    }

    private function assertProfileSharesContract(MigrationContext $context): void
    {
        $statement = $context->pdo()->query(<<<'SQL'
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'student_profile_shares'
        SQL
        );
        $columns = $statement ? $statement->fetchAll(\PDO::FETCH_KEY_PAIR) : [];
        foreach (['id', 'studentId', 'tokenHash', 'sharedFieldsJson', 'expiresAt', 'revokedAt', 'createdAt'] as $col) {
            if (!isset($columns[$col])) {
                throw new \RuntimeException("student_profile_shares table missing column: {$col}");
            }
        }
    }

    private function hasCheckConstraint(MigrationContext $context, string $table, string $constraintName): bool
    {
        $statement = $context->pdo()->prepare(<<<'SQL'
            SELECT 1
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
              AND constraint_type = 'CHECK'
              AND constraint_name = :constraint_name
            LIMIT 1
        SQL
        );
        $statement->execute(['table_name' => $table, 'constraint_name' => $constraintName]);
        return (bool) $statement->fetchColumn();
    }
};
