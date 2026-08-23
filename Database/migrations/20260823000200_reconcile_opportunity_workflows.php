<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Add sponsorship and payment workflows to the canonical student opportunity schema';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['projects', 'internship_posts', 'internship_applications', 'notifications', 'enterprises'] as $table) {
            $context->assertTableExists($table);
        }

        $this->assertColumns($context, 'projects', ['id', 'schoolId', 'title', 'description', 'status']);
        $this->assertColumns($context, 'internship_posts', ['id', 'enterpriseId', 'workType', 'slots', 'status']);
        $this->assertColumns($context, 'internship_applications', ['id', 'postId', 'studentId', 'reviewedBy', 'status']);
        $this->assertColumns($context, 'notifications', ['id', 'userId', 'eventKey', 'readAt']);

        if ($context->pdo()->query('SELECT @@session.time_zone')?->fetchColumn() !== '+00:00') {
            throw new RuntimeException('Opportunity reconciliation requires MySQL session time zone +00:00.');
        }

        if ($context->tableExists('project_sponsorships')) {
            $this->assertColumns($context, 'project_sponsorships', ['id', 'enterpriseId', 'projectId', 'amount', 'currency', 'status']);
        }
        if ($context->tableExists('payment_orders')) {
            $this->assertColumns($context, 'payment_orders', ['id', 'enterpriseId', 'sponsorshipId', 'amount', 'currency', 'paymentStatus']);
        }
    }

    public function up(MigrationContext $context): void
    {
        if (!$this->columnExists($context, 'projects', 'fundingGoal')) {
            $context->execute(<<<'SQL'
                ALTER TABLE projects
                    ADD COLUMN fundingGoal DECIMAL(12,2) NULL AFTER description,
                    ADD CONSTRAINT chk_projects_funding_goal CHECK (fundingGoal IS NULL OR fundingGoal > 0),
                    ADD KEY idx_projects_sponsorable (status, fundingGoal)
            SQL);
        }

        if (!$context->tableExists('project_sponsorships')) {
            $context->execute(<<<'SQL'
                CREATE TABLE project_sponsorships (
                    id CHAR(36) NOT NULL,
                    enterpriseId CHAR(36) NOT NULL,
                    projectId CHAR(36) NOT NULL,
                    amount DECIMAL(12,2) NOT NULL,
                    currency CHAR(3) NOT NULL DEFAULT 'VND',
                    status VARCHAR(20) NOT NULL DEFAULT 'pledged',
                    note VARCHAR(1000) NULL,
                    cancelledAt DATETIME(6) NULL,
                    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                    updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                    PRIMARY KEY (id),
                    KEY idx_sponsorship_enterprise_status (enterpriseId, status),
                    KEY idx_sponsorship_project (projectId),
                    CONSTRAINT fk_sponsorship_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                    CONSTRAINT fk_sponsorship_project FOREIGN KEY (projectId) REFERENCES projects(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                    CONSTRAINT chk_sponsorship_amount CHECK (amount > 0),
                    CONSTRAINT chk_sponsorship_status CHECK (status IN ('pledged','pending_payment','paid','cancelled','refunded'))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }

        if (!$context->tableExists('payment_orders')) {
            $context->execute(<<<'SQL'
                CREATE TABLE payment_orders (
                    id CHAR(36) NOT NULL,
                    enterpriseId CHAR(36) NOT NULL,
                    sponsorshipId CHAR(36) NOT NULL,
                    amount DECIMAL(12,2) NOT NULL,
                    currency CHAR(3) NOT NULL DEFAULT 'VND',
                    provider VARCHAR(100) NOT NULL,
                    paymentStatus VARCHAR(20) NOT NULL DEFAULT 'pending',
                    providerReference VARCHAR(255) NULL,
                    paidAt DATETIME(6) NULL,
                    createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                    updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_payment_sponsorship (sponsorshipId),
                    KEY idx_payment_enterprise_status (enterpriseId, paymentStatus),
                    CONSTRAINT fk_payment_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                    CONSTRAINT fk_payment_sponsorship FOREIGN KEY (sponsorshipId) REFERENCES project_sponsorships(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                    CONSTRAINT chk_payment_amount CHECK (amount > 0),
                    CONSTRAINT chk_payment_status CHECK (paymentStatus IN ('pending','paid','failed','cancelled','refunded'))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        }
    }

    public function down(MigrationContext $context): void
    {
        // Forward-only: sponsorship and payment records must never be discarded by rollback.
    }

    /** @param list<string> $required */
    private function assertColumns(MigrationContext $context, string $table, array $required): void
    {
        $statement = $context->pdo()->prepare(
            'SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table'
        );
        $statement->execute(['table' => $table]);
        $columns = array_fill_keys(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)), true);
        foreach ($required as $column) {
            if (!isset($columns[$column])) {
                throw new RuntimeException("{$table} is not canonical; missing column {$column}.");
            }
        }
    }

    private function columnExists(MigrationContext $context, string $table, string $column): bool
    {
        $statement = $context->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column'
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() === 1;
    }
};
