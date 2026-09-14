<?php
declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

/**
 * Migration: Cho phép 'suspended' làm giá trị hợp lệ của enterprises.verificationStatus
 * và của schools.status để hỗ trợ hành động "Đình chỉ" từ admin (đối với tổ chức
 * đã xác minh). Trước đây cột này chỉ chấp nhận ('pending','verified','rejected')
 * khiến admin không thể đình chỉ doanh nghiệp đã verified.
 */
return new class extends AbstractMigration {
    public function description(): string { return 'Allow suspended status for verified organizations (enterprises + schools).'; }

    public function preflight(MigrationContext $c): void
    {
        $c->assertTableExists('enterprises');
        $c->assertTableExists('schools');
    }

    public function up(MigrationContext $c): void
    {
        // 1. Enterprises: relax check constraint to include 'suspended' in verificationStatus
        //    MySQL 8 supports DROP/ADD CHECK inside ALTER TABLE.
        $c->execute('ALTER TABLE enterprises DROP CHECK chk_enterprises_verification');
        $c->execute("ALTER TABLE enterprises ADD CONSTRAINT chk_enterprises_verification CHECK(verificationStatus IN('pending','verified','rejected','suspended'))");

        // 2. Schools: cũng có cùng vấn đề nếu admin cố đình chỉ trường.
        //    schools.status hiện đã cho phép 'suspended' (xem migration 20260814000100).
        //    Tuy nhiên cần thêm cột verificationStatus để đồng bộ với API admin
        //    (hiện tại code admin đọc 'verificationStatus' hoặc fallback sang 'status').
        $stmt = $c->pdo()->prepare(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'schools' AND column_name = 'verificationStatus'"
        );
        $stmt->execute();
        if ((int) $stmt->fetchColumn() === 0) {
            $c->execute("ALTER TABLE schools ADD COLUMN verificationStatus VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER status");
            $c->execute("ALTER TABLE schools ADD CONSTRAINT chk_schools_verification CHECK(verificationStatus IN('pending','verified','rejected','suspended'))");
        }
    }

    public function down(MigrationContext $c): void
    {
        // Rollback an toàn: set suspended về verified trước, rồi mới drop/add lại check cũ
        $c->execute("UPDATE enterprises SET verificationStatus='verified' WHERE verificationStatus='suspended'");
        $c->execute('ALTER TABLE enterprises DROP CHECK chk_enterprises_verification');
        $c->execute("ALTER TABLE enterprises ADD CONSTRAINT chk_enterprises_verification CHECK(verificationStatus IN('pending','verified','rejected'))");

        // Với schools: chỉ drop check + column nếu tồn tại
        $stmt = $c->pdo()->prepare(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'schools' AND column_name = 'verificationStatus'"
        );
        $stmt->execute();
        if ((int) $stmt->fetchColumn() > 0) {
            $c->execute('ALTER TABLE schools DROP CHECK chk_schools_verification');
            $c->execute('ALTER TABLE schools DROP COLUMN verificationStatus');
        }
    }
};