<?php
declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Add teacher self-service profile fields'; }

    public function preflight(MigrationContext $c): void
    {
        $c->assertTableExists('teacher_profiles');
    }

    public function up(MigrationContext $c): void
    {
        $c->execute("ALTER TABLE teacher_profiles ADD COLUMN phone VARCHAR(30) NULL AFTER isSchoolAdmin, ADD COLUMN specialization VARCHAR(150) NULL AFTER phone, ADD COLUMN bio VARCHAR(1000) NULL AFTER specialization");
    }

    public function down(MigrationContext $c): void
    {
        $c->execute('ALTER TABLE teacher_profiles DROP COLUMN bio, DROP COLUMN specialization, DROP COLUMN phone');
    }
};
