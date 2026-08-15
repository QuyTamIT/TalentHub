<?php
declare(strict_types=1);
use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;
return new class extends AbstractMigration {
    public function description(): string { return 'Create persistent authentication rate limits'; }
    public function preflight(MigrationContext $c): void { $c->assertTableAbsent('auth_rate_limits'); }
    public function up(MigrationContext $c): void {
        $c->execute("CREATE TABLE auth_rate_limits (
            bucketKey CHAR(64) NOT NULL,
            scope VARCHAR(20) NOT NULL,
            failureCount SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            windowStartedAt DATETIME(6) NOT NULL,
            blockedUntil DATETIME(6) NULL,
            updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            PRIMARY KEY(bucketKey),
            KEY idx_auth_rate_limits_cleanup(updatedAt),
            KEY idx_auth_rate_limits_blocked(blockedUntil),
            CONSTRAINT chk_auth_rate_limits_scope CHECK(scope IN('identity','ip'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(MigrationContext $c): void { $c->execute('DROP TABLE auth_rate_limits'); }
};
