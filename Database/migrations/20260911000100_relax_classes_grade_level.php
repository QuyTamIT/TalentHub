<?php
declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration
{
    public function description(): string
    {
        return 'Relax classes.gradeLevel: drop CHECK 1-12, change TINYINT -> VARCHAR(50), support custom grade labels for college/university schools.';
    }

    public function preflight(MigrationContext $c): void
    {
        $c->assertTableExists('classes');
        $c->assertTableExists('schools');
    }

    public function up(MigrationContext $c): void
    {
        // Step 1: Archive Tiểu học (gradeLevel 1-5) that belong to schools
        //        NOT in the CĐ/ĐH group.  Schools in the CĐ/ĐH group use grades 1-4
        //        as "Năm 1".."Năm 4" labels and must NOT be archived.
        //
        // Patterns match: 'Đại học', 'Cao đẳng', 'BTEC', etc.
        $c->execute(
            "UPDATE classes c
                JOIN schools s ON s.id = c.schoolId
               SET c.status = 'archived'
             WHERE CAST(c.gradeLevel AS UNSIGNED) BETWEEN 1 AND 5
               AND c.status = 'active'
               AND NOT (
                 s.level LIKE '%Đại học%'
              OR s.level LIKE '%Cao đẳng%'
              OR s.name  LIKE '%BTEC%'
              OR s.name  LIKE '%Đại học%'
              OR s.name  LIKE '%Cao đẳng%'
               )"
        );

        // Step 2: Drop the CHECK constraint.
        $c->execute('ALTER TABLE classes DROP CONSTRAINT chk_classes_grade');

        // Step 3: Change column type from TINYINT UNSIGNED to VARCHAR(50).
        $c->execute(
            "ALTER TABLE classes
             MODIFY COLUMN gradeLevel VARCHAR(50) NOT NULL"
        );

        // Step 4: Map CĐ/ĐH school active classes (grade 1-4) to human-readable
        //          labels "Năm 1", "Năm 2", etc.
        $c->execute(
            "UPDATE classes c
                JOIN schools s ON s.id = c.schoolId
               SET c.gradeLevel = CONCAT('Năm ', c.gradeLevel)
             WHERE (s.level LIKE '%Đại học%'
                    OR s.level LIKE '%Cao đẳng%'
                    OR s.name  LIKE '%BTEC%'
                    OR s.name  LIKE '%Đại học%'
                    OR s.name  LIKE '%Cao đẳng%')
                 AND CAST(c.gradeLevel AS UNSIGNED) BETWEEN 1 AND 4
                 AND c.status = 'active'"
        );

        // Step 5: Normalise all remaining numeric gradeLevel values to string.
        // After step 4, only THCS/THPT schools (grade 6-12) should remain as numbers.
        $c->execute(
            "UPDATE classes
               SET gradeLevel = CAST(gradeLevel AS CHAR)
             WHERE gradeLevel REGEXP '^[0-9]+$'"
        );
    }

    public function down(MigrationContext $c): void
    {
        // Revert gradeLevel back to TINYINT UNSIGNED with CHECK constraint.
        // Note: any non-numeric gradeLevel values will be lost.
        $c->execute(
            "ALTER TABLE classes
             MODIFY COLUMN gradeLevel TINYINT UNSIGNED NOT NULL"
        );
        $c->execute(
            "ALTER TABLE classes
             ADD CONSTRAINT chk_classes_grade CHECK (gradeLevel BETWEEN 1 AND 12)"
        );
    }
};
