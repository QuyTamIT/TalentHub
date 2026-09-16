<?php

declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string
    {
        return 'Create internship_specialties catalog so enterprises can manage Lĩnh vực / Chuyên môn (with backfill of the 6 hardcoded values)';
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function down(MigrationContext $context): void
    {
        if ($context->tableExists('internship_specialties')) {
            $context->execute('DROP TABLE IF EXISTS internship_specialties');
        }
    }

    public function preflight(MigrationContext $context): void
    {
        foreach (['enterprises', 'internship_posts'] as $table) {
            $context->assertTableExists($table);
        }

        if ($context->tableExists('internship_specialties')) {
            throw new RuntimeException('internship_specialties already exists; partial state not supported.');
        }
    }

    public function up(MigrationContext $context): void
    {
        // createdBy is nullable — set when an enterprise adds a specialty via API.
        // Global catalog entries seeded here have createdBy = NULL.
        $context->execute(<<<'SQL'
            CREATE TABLE internship_specialties (
                id CHAR(36) NOT NULL,
                enterpriseId CHAR(36) NULL,
                name VARCHAR(150) NOT NULL,
                slug VARCHAR(160) NOT NULL,
                category VARCHAR(60) NOT NULL DEFAULT 'general',
                description VARCHAR(500) NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                displayOrder INT UNSIGNED NOT NULL DEFAULT 0,
                createdBy CHAR(36) NULL,
                createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uk_internship_specialties_enterprise_slug (enterpriseId, slug),
                KEY idx_internship_specialties_enterprise_status (enterpriseId, status, displayOrder),
                KEY idx_internship_specialties_global (status, displayOrder),
                CONSTRAINT fk_internship_specialties_enterprise FOREIGN KEY (enterpriseId) REFERENCES enterprises(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT chk_internship_specialties_status CHECK (status IN ('active','archived')),
                CONSTRAINT chk_internship_specialties_name_len CHECK (CHAR_LENGTH(TRIM(name)) >= 2)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // Seed 6 global specialties — enterpriseId NULL, createdBy NULL.
        // UUIDs are deterministic (v5) so re-running migration always yields same IDs.
        $globalFields = [
            ['name' => 'Công nghệ thông tin',  'slug' => 'cong-nghe-thong-tin',  'category' => 'technology'],
            ['name' => 'AI / Machine Learning', 'slug' => 'ai-machine-learning',   'category' => 'technology'],
            ['name' => 'Thiết kế UI/UX',        'slug' => 'thiet-ke-ui-ux',      'category' => 'design'],
            ['name' => 'Marketing Digital',       'slug' => 'marketing-digital',   'category' => 'business'],
            ['name' => 'Khoa học Dữ liệu',    'slug' => 'khoa-hoc-du-lieu',  'category' => 'technology'],
            ['name' => 'Kỹ thuật Phần mềm',   'slug' => 'ky-thuat-phan-mem', 'category' => 'technology'],
        ];

        $insert = $context->pdo()->prepare(
            'INSERT INTO internship_specialties '
            . '(id, enterpriseId, name, slug, category, description, status, displayOrder, createdBy) '
            . 'VALUES (:id, NULL, :name, :slug, :category, :description, :active, :order, NULL)'
        );

        $order = 1;
        foreach ($globalFields as $field) {
            $insert->execute([
                ':id'          => $context->uuidV5('internship-specialty:' . $field['slug']),
                ':name'        => $field['name'],
                ':slug'        => $field['slug'],
                ':category'    => $field['category'],
                ':description' => 'Lĩnh vực / chuyên môn hệ thống (global catalog).',
                ':active'      => 'active',
                ':order'       => $order++,
            ]);
        }
    }
};
