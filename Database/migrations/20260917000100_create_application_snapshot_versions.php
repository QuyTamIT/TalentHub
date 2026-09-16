<?php
declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Append-only versions of application profile snapshots'; }
    public function isReversible(): bool { return false; }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('application_profile_snapshots');
        $context->assertTableAbsent('application_profile_snapshot_versions');
    }

    public function up(MigrationContext $context): void
    {
        // The original unique applicationId and all existing history remain intact.
        // Ownership and consent are inherited through the immutable source snapshot.
        $context->execute(<<<'SQL'
            CREATE TABLE application_profile_snapshot_versions (
                id CHAR(36) NOT NULL,
                sourceSnapshotId CHAR(36) NOT NULL,
                revision INT UNSIGNED NOT NULL,
                schemaVersion VARCHAR(50) NOT NULL,
                sourceHash CHAR(64) NOT NULL,
                snapshotPayload JSON NOT NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_app_snapshot_revision (sourceSnapshotId, revision),
                UNIQUE KEY uq_app_snapshot_schema (sourceSnapshotId, schemaVersion),
                CONSTRAINT fk_app_snapshot_version_source FOREIGN KEY (sourceSnapshotId)
                    REFERENCES application_profile_snapshots(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_app_snapshot_version_revision CHECK (revision > 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(MigrationContext $context): void {}
};
