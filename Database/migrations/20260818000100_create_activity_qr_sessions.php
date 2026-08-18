<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create managed activity QR sessions and link check-ins to sessions';
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['activities', 'activity_registrations', 'teacher_profiles'] as $table) {
            $context->assertTableExists($table);
        }
    }

    public function up(MigrationContext $context): void
    {
        if (!$context->tableExists('activity_qr_sessions')) {
            $context->execute("CREATE TABLE activity_qr_sessions (
                id CHAR(36) NOT NULL,
                activityId CHAR(36) NOT NULL,
                createdByTeacherId CHAR(36) NOT NULL,
                tokenHash VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                expiresAt DATETIME(6) NOT NULL,
                maxScans INT UNSIGNED NOT NULL,
                usedScans INT UNSIGNED NOT NULL DEFAULT 0,
                revokedAt DATETIME(6) NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_activity_qr_sessions_token_hash (tokenHash),
                KEY idx_activity_qr_sessions_activity (activityId),
                KEY idx_activity_qr_sessions_teacher (createdByTeacherId),
                KEY idx_activity_qr_sessions_status_expiry (status, expiresAt),
                CONSTRAINT fk_activity_qr_sessions_activity FOREIGN KEY (activityId) REFERENCES activities(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_activity_qr_sessions_teacher FOREIGN KEY (createdByTeacherId) REFERENCES teacher_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_activity_qr_sessions_max_scans CHECK (maxScans > 0),
                CONSTRAINT chk_activity_qr_sessions_status CHECK (status IN ('active', 'expired', 'revoked')),
                CONSTRAINT chk_activity_qr_sessions_used_scans CHECK (usedScans <= maxScans)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        if ($context->tableExists('activity_qr_tokens')) {
            $this->backfillLegacyTokens($context);
        }

        if (!$context->tableExists('checkins')) {
            $context->execute("CREATE TABLE checkins (
                id CHAR(36) NOT NULL,
                registrationId CHAR(36) NOT NULL,
                qrSessionId CHAR(36) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                checkedInAt DATETIME(6) NULL,
                confirmedAt DATETIME(6) NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_checkins_registration (registrationId),
                KEY idx_checkins_qr_session (qrSessionId),
                CONSTRAINT fk_checkins_registration FOREIGN KEY (registrationId) REFERENCES activity_registrations(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_checkins_qr_session FOREIGN KEY (qrSessionId) REFERENCES activity_qr_sessions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT chk_checkins_status CHECK (status IN ('pending', 'checked_in', 'confirmed', 'rejected')),
                CONSTRAINT chk_checkins_checked_in_at CHECK ((status IN ('checked_in', 'confirmed') AND checkedInAt IS NOT NULL) OR (status IN ('pending', 'rejected') AND checkedInAt IS NULL)),
                CONSTRAINT chk_checkins_confirmed_at CHECK ((status = 'confirmed' AND confirmedAt IS NOT NULL) OR (status <> 'confirmed' AND confirmedAt IS NULL))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            return;
        }

        if (!$this->columnExists($context, 'checkins', 'qrSessionId')) {
            $context->execute('ALTER TABLE checkins ADD COLUMN qrSessionId CHAR(36) NULL AFTER registrationId');
        }

        if ($this->columnExists($context, 'checkins', 'qrTokenId')) {
            $legacyHash = $this->columnExists($context, 'activity_qr_tokens', 'tokenHash')
                ? 't.tokenHash'
                : 'SHA2(t.token, 256)';
            $context->execute("UPDATE checkins c
                JOIN activity_qr_tokens t ON t.id=c.qrTokenId
                JOIN activity_qr_sessions s ON s.id=t.id OR s.tokenHash={$legacyHash}
                SET c.qrSessionId=s.id
                WHERE c.qrSessionId IS NULL");
        }

        $missing = (int) $context->pdo()->query('SELECT COUNT(*) FROM checkins WHERE qrSessionId IS NULL')->fetchColumn();
        if ($missing !== 0) {
            throw new RuntimeException("Cannot link {$missing} check-in row(s) to a QR session.");
        }

        $context->execute('ALTER TABLE checkins MODIFY qrSessionId CHAR(36) NOT NULL');
        if (!$this->indexExists($context, 'checkins', 'idx_checkins_qr_session')) {
            $context->execute('ALTER TABLE checkins ADD KEY idx_checkins_qr_session (qrSessionId)');
        }
        if (!$this->foreignKeyExists($context, 'checkins', 'fk_checkins_qr_session')) {
            $context->execute('ALTER TABLE checkins ADD CONSTRAINT fk_checkins_qr_session FOREIGN KEY (qrSessionId) REFERENCES activity_qr_sessions(id) ON DELETE RESTRICT ON UPDATE CASCADE');
        }

        if ($this->columnExists($context, 'checkins', 'qrTokenId')) {
            foreach ($this->foreignKeysForColumn($context, 'checkins', 'qrTokenId') as $constraint) {
                $context->execute('ALTER TABLE checkins DROP FOREIGN KEY `' . str_replace('`', '``', $constraint) . '`');
            }
            $context->execute('ALTER TABLE checkins DROP COLUMN qrTokenId');
        }
    }

    public function down(MigrationContext $context): void
    {
        // This migration intentionally preserves legacy QR tokens but may contain
        // new sessions that have no legacy equivalent. An automatic rollback
        // could therefore lose production check-in data.
    }

    public function isReversible(): bool
    {
        return false;
    }

    private function backfillLegacyTokens(MigrationContext $context): void
    {
        $tokenHash = $this->columnExists($context, 'activity_qr_tokens', 'tokenHash')
            ? 't.tokenHash'
            : ($this->columnExists($context, 'activity_qr_tokens', 'token') ? 'SHA2(t.token, 256)' : null);
        $expiresAt = $this->columnExists($context, 'activity_qr_tokens', 'validUntil')
            ? 't.validUntil'
            : ($this->columnExists($context, 'activity_qr_tokens', 'expiresAt') ? 't.expiresAt' : null);
        if ($tokenHash === null || $expiresAt === null) {
            throw new RuntimeException('Legacy activity_qr_tokens lacks token/hash or expiry columns required for a lossless migration.');
        }

        $status = $this->columnExists($context, 'activity_qr_tokens', 'status')
            ? "CASE WHEN t.status IN ('active','expired','revoked') THEN t.status ELSE 'revoked' END"
            : "CASE WHEN {$expiresAt} <= UTC_TIMESTAMP(6) THEN 'expired' ELSE 'active' END";
        $createdAt = $this->columnExists($context, 'activity_qr_tokens', 'createdAt') ? 't.createdAt' : 'UTC_TIMESTAMP(6)';
        $checkinJoin = $context->tableExists('checkins') && $this->columnExists($context, 'checkins', 'qrTokenId')
            ? 'LEFT JOIN (SELECT qrTokenId, COUNT(*) used FROM checkins GROUP BY qrTokenId) c ON c.qrTokenId=t.id'
            : 'LEFT JOIN (SELECT NULL qrTokenId, 0 used) c ON 1=0';

        $context->execute("INSERT INTO activity_qr_sessions
            (id, activityId, createdByTeacherId, tokenHash, status, expiresAt, maxScans, usedScans, revokedAt, createdAt, updatedAt)
            SELECT t.id, t.activityId, a.createdByTeacherId, {$tokenHash}, {$status}, {$expiresAt},
                   4294967295, COALESCE(c.used, 0),
                   CASE WHEN {$status}='revoked' THEN UTC_TIMESTAMP(6) ELSE NULL END,
                   {$createdAt}, UTC_TIMESTAMP(6)
            FROM activity_qr_tokens t
            JOIN activities a ON a.id=t.activityId
            {$checkinJoin}
            LEFT JOIN activity_qr_sessions s ON s.id=t.id OR s.tokenHash={$tokenHash}
            WHERE s.id IS NULL");
    }

    private function columnExists(MigrationContext $context, string $table, string $column): bool
    {
        $statement = $context->pdo()->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $statement->execute([$table, $column]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function indexExists(MigrationContext $context, string $table, string $index): bool
    {
        $statement = $context->pdo()->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?');
        $statement->execute([$table, $index]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function foreignKeyExists(MigrationContext $context, string $table, string $constraint): bool
    {
        $statement = $context->pdo()->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name=? AND constraint_name=? AND constraint_type='FOREIGN KEY'");
        $statement->execute([$table, $constraint]);
        return (int) $statement->fetchColumn() === 1;
    }

    /** @return list<string> */
    private function foreignKeysForColumn(MigrationContext $context, string $table, string $column): array
    {
        $statement = $context->pdo()->prepare('SELECT DISTINCT constraint_name FROM information_schema.key_column_usage WHERE constraint_schema=DATABASE() AND table_name=? AND column_name=? AND referenced_table_name IS NOT NULL');
        $statement->execute([$table, $column]);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }
};
