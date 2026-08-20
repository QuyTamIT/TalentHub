<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Reconcile legacy check-ins with the canonical QR session schema';
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['checkins', 'activity_registrations', 'activity_qr_sessions'] as $table) {
            $context->assertTableExists($table);
        }
    }

    public function up(MigrationContext $context): void
    {
        if (!$this->columnExists($context, 'status')) {
            $context->execute("ALTER TABLE checkins ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER qrSessionId");
        }
        if (!$this->columnExists($context, 'checkedInAt')) {
            $context->execute('ALTER TABLE checkins ADD COLUMN checkedInAt DATETIME(6) NULL AFTER status');
        }
        if (!$this->columnExists($context, 'confirmedAt')) {
            $context->execute('ALTER TABLE checkins ADD COLUMN confirmedAt DATETIME(6) NULL AFTER checkedInAt');
        }
        if (!$this->columnExists($context, 'createdAt')) {
            $context->execute('ALTER TABLE checkins ADD COLUMN createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER confirmedAt');
        }

        if (!$this->indexExists($context, 'uq_checkins_registration')) {
            $context->execute('ALTER TABLE checkins ADD UNIQUE KEY uq_checkins_registration (registrationId)');
        }
        if (!$this->foreignKeyExists($context, 'fk_checkins_registration')) {
            $context->execute('ALTER TABLE checkins ADD CONSTRAINT fk_checkins_registration FOREIGN KEY (registrationId) REFERENCES activity_registrations(id) ON DELETE RESTRICT ON UPDATE CASCADE');
        }
        if (!$this->constraintExists($context, 'chk_checkins_status')) {
            $context->execute("ALTER TABLE checkins ADD CONSTRAINT chk_checkins_status CHECK (status IN ('pending', 'checked_in', 'confirmed', 'rejected'))");
        }
        if (!$this->constraintExists($context, 'chk_checkins_checked_in_at')) {
            $context->execute("ALTER TABLE checkins ADD CONSTRAINT chk_checkins_checked_in_at CHECK ((status IN ('checked_in', 'confirmed') AND checkedInAt IS NOT NULL) OR (status IN ('pending', 'rejected') AND checkedInAt IS NULL))");
        }
        if (!$this->constraintExists($context, 'chk_checkins_confirmed_at')) {
            $context->execute("ALTER TABLE checkins ADD CONSTRAINT chk_checkins_confirmed_at CHECK ((status = 'confirmed' AND confirmedAt IS NOT NULL) OR (status <> 'confirmed' AND confirmedAt IS NULL))");
        }
    }

    public function down(MigrationContext $context): void
    {
        // Reconciliation is intentionally forward-only to preserve check-in data.
    }

    public function isReversible(): bool
    {
        return false;
    }

    private function columnExists(MigrationContext $context, string $column): bool
    {
        $statement = $context->pdo()->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $statement->execute(['checkins', $column]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function indexExists(MigrationContext $context, string $index): bool
    {
        $statement = $context->pdo()->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?');
        $statement->execute(['checkins', $index]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function foreignKeyExists(MigrationContext $context, string $constraint): bool
    {
        $statement = $context->pdo()->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name=? AND constraint_name=? AND constraint_type='FOREIGN KEY'");
        $statement->execute(['checkins', $constraint]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function constraintExists(MigrationContext $context, string $constraint): bool
    {
        $statement = $context->pdo()->prepare('SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name=? AND constraint_name=?');
        $statement->execute(['checkins', $constraint]);
        return (int) $statement->fetchColumn() === 1;
    }
};
