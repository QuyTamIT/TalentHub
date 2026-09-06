<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Lock competing internship applications after a student accepts a placement';
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('internship_applications');
        $context->assertTableAbsent('internship_application_locks');
    }

    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
            CREATE TABLE internship_application_locks (
                applicationId CHAR(36) NOT NULL,
                lockedByApplicationId CHAR(36) NOT NULL,
                reason VARCHAR(255) NOT NULL,
                lockedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (applicationId),
                KEY idx_internship_application_locks_placement (lockedByApplicationId, lockedAt),
                CONSTRAINT fk_internship_application_locks_application
                    FOREIGN KEY (applicationId) REFERENCES internship_applications(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_internship_application_locks_placement
                    FOREIGN KEY (lockedByApplicationId) REFERENCES internship_applications(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $context->execute(<<<'SQL'
            INSERT IGNORE INTO internship_application_locks
                (applicationId, lockedByApplicationId, reason, lockedAt)
            SELECT competing.id,
                   accepted.id,
                   'Sinh viên đã xác nhận một vị trí thực tập khác.',
                   COALESCE(accepted.reviewedAt, accepted.updatedAt, UTC_TIMESTAMP(6))
            FROM internship_applications accepted
            INNER JOIN internship_applications competing
                ON competing.studentId = accepted.studentId
               AND competing.id <> accepted.id
               AND competing.status IN ('submitted','reviewing','interview')
            WHERE accepted.status = 'accepted'
        SQL);
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Internship placement lock history must be retained.');
    }
};
