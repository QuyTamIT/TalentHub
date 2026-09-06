<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    private const INVALID_DEMO_ASSESSMENT_ID = '23a12d35c229b7f4ed50f5d9f1eac3eb';
    private const REPAIRED_DEMO_ASSESSMENT_ID = '23000000-0000-4000-8000-000000000001';

    public function description(): string
    {
        return 'Reconcile provenance-known demo assessment ids and overall score scale';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('assessments');
        $context->assertTableExists('assessment_scores');

        $collision = $context->pdo()->prepare('SELECT COUNT(*) FROM assessments WHERE id IN (?, ?)');
        $collision->execute([self::INVALID_DEMO_ASSESSMENT_ID, self::REPAIRED_DEMO_ASSESSMENT_ID]);
        if ((int) $collision->fetchColumn() > 1) {
            throw new RuntimeException('The repaired demo assessment id already exists.');
        }
    }

    public function up(MigrationContext $context): void
    {
        // IDs with this reserved prefix come from the legacy demo seed, which
        // stored overallScore on a 0-10 scale. Do not infer scale for real rows.
        $normalizeSeeded = $context->pdo()->prepare(<<<'SQL'
            UPDATE assessments
            SET overallScore = overallScore * 10,
                updatedAt = CURRENT_TIMESTAMP(6)
            WHERE id LIKE '26000000-%'
              AND overallScore BETWEEN 0 AND 10
            SQL);
        $normalizeSeeded->execute();

        $repairDemo = $context->pdo()->prepare(<<<'SQL'
            UPDATE assessments
            SET id = :new_id,
                overallScore = CASE WHEN overallScore BETWEEN 0 AND 10 THEN overallScore * 10 ELSE overallScore END,
                updatedAt = CURRENT_TIMESTAMP(6)
            WHERE id = :old_id
            SQL);
        $repairDemo->execute([
            'new_id' => self::REPAIRED_DEMO_ASSESSMENT_ID,
            'old_id' => self::INVALID_DEMO_ASSESSMENT_ID,
        ]);
    }

    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Assessment score reconciliation is forward-only.');
    }
};
