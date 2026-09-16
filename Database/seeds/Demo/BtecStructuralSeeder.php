<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/**
 * Minimal structural provisioning so SchoolAiProjectCatalogSeeder can finish.
 *
 * btec_demo.sql has a pre-flight guard hard-wired to legacy catalog UUIDs that
 * the current catalog seeder does not produce, so the raw SQL is not runnable
 * as-is on a freshly migrated DB. This seeds ONLY the minimal structural rows
 * (btec school, classes, mentor teacher, hero student) required by
 * SchoolAiProjectCatalogSeeder::assertSchoolsMentorsAndSkills. Idempotent.
 */
final class BtecStructuralSeeder
{
    private const SCHOOL_ID = 'da811c4f-2f74-4fdd-80b0-dd6f26109783';
    private const SCHOOL_NAME = 'Cao đẳng Quốc tế BTEC FPT (Dữ liệu demo)';
    private const CLASS_IDS = [
        'bc0be670-12fd-545b-a70f-3ebf8ce3fad7' => 'BTEC-SE-2026A',
        'a1e2894b-2386-5404-9695-78a78f5a60d3' => 'BTEC-DB-2026A',
    ];
    private const MENTOR_PROFILE = '24000000-0000-4000-8000-000000000011';
    private const MENTOR_USER = '24000000-0000-4000-8000-000000000010';
    private const MENTOR_EMAIL = 'mentor.btec@talenthub.local';
    private const HERO_STUDENT = '95542f8b-6b6a-5cef-9b36-9416a08ead3c';
    private const HERO_USER = '035ec59d-4f95-59d6-b2c1-33189fc20234';
    private const HERO_EMAIL = 'tran-gia-huy@student.btec.talenthub.local';
    private const HERO_CLASS = 'bc0be670-12fd-545b-a70f-3ebf8ce3fad7';

    public function run(PDO $pdo): void
    {
        $clock = (new DateTimeImmutable('2026-08-25 14:30:00', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $hash = '$2y$10$3uAbcG2j2B2018NXBLWeeeEUK4y0Sn6YRANNn/Azr.4FIwD8Se3ay';

        $this->upsert($pdo, 'schools', [
            'id' => self::SCHOOL_ID, 'name' => self::SCHOOL_NAME, 'status' => 'active',
            'logoUrl' => null, 'address' => 'Cơ sở đào tạo BTEC FPT tại TP. Hồ Chí Minh (dữ liệu mô phỏng)',
            'phone' => null, 'email' => 'btec.demo@talenthub.local', 'website' => null,
            'level' => 'Cao đẳng quốc tế', 'studentCount' => 10, 'teacherCount' => 0,
            'academicYear' => '2026-2027', 'createdAt' => $clock, 'updatedAt' => $clock,
        ]);

        foreach (self::CLASS_IDS as $cid => $cname) {
            $this->upsert($pdo, 'classes', [
                'id' => $cid, 'schoolId' => self::SCHOOL_ID, 'name' => $cname,
                'gradeLevel' => 'Năm 1', 'academicYear' => '2026-2027', 'status' => 'active',
                'createdAt' => $clock, 'updatedAt' => $clock,
            ]);
        }

        $this->upsert($pdo, 'users', [
            'id' => self::MENTOR_USER, 'roleId' => $this->roleId($pdo, 'teacher'),
            'email' => self::MENTOR_EMAIL, 'passwordHash' => $hash,
            'fullName' => 'Giảng viên BTEC FPT', 'status' => 'active',
            'lastLoginAt' => null, 'createdAt' => $clock, 'updatedAt' => $clock,
        ]);
        $this->upsert($pdo, 'teacher_profiles', [
            'id' => self::MENTOR_PROFILE, 'userId' => self::MENTOR_USER,
            'schoolId' => self::SCHOOL_ID, 'isSchoolAdmin' => 0,
            'phone' => null, 'specialization' => null, 'bio' => null,
            'createdAt' => $clock, 'updatedAt' => $clock,
        ]);

        $this->upsert($pdo, 'users', [
            'id' => self::HERO_USER, 'roleId' => $this->roleId($pdo, 'student'),
            'email' => self::HERO_EMAIL, 'passwordHash' => $hash,
            'fullName' => 'Trần Gia Huy', 'status' => 'active',
            'lastLoginAt' => null, 'createdAt' => $clock, 'updatedAt' => $clock,
        ]);
        $this->upsert($pdo, 'student_profiles', [
            'id' => self::HERO_STUDENT, 'userId' => self::HERO_USER,
            'classId' => self::HERO_CLASS, 'dateOfBirth' => '2006-02-18',
            'phone' => '0929000101', 'studyStatus' => 'active',
            'talentScore' => null, 'createdAt' => $clock, 'updatedAt' => $clock,
        ]);
    }

    private function roleId(PDO $pdo, string $code): string
    {
        $s = $pdo->prepare('SELECT id FROM roles WHERE code = :c');
        $s->execute(['c' => $code]);
        $id = $s->fetchColumn();
        if (!is_string($id) || $id === '') {
            throw new RuntimeException("Role {$code} is missing. Run RolePermissionSeeder first.");
        }
        return $id;
    }

    /** @param array<string,mixed> $row */
    private function upsert(PDO $pdo, string $table, array $row): void
    {
        $cols = array_keys($row);
        $ph = array_map(static fn (string $c): string => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s) ON DUPLICATE KEY UPDATE `%s`=`%s`',
            $table, implode('`, `', $cols), implode(', ', $ph), $cols[0], $cols[0]
        );
        $pdo->prepare($sql)->execute($row);
    }
}