<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Demo data for the school admin dashboard.
 *
 * Inserts one rich "THPT Nguyễn Trãi" school, one school admin user,
 * 4 classes, 4 teachers, 8 students and the membership/profile rows
 * needed to make the school endpoints return non-empty data.
 *
 * Idempotent: rerun-safe thanks to INSERT IGNORE on every row and
 * idempotent counter refresh on the schools row.
 */
final class SchoolDemoSeeder
{
    public const PASSWORD_ENV = 'TALENTHUB_TEST_PASSWORD';

    private const IDS = [
        'school'             => '20000000-0000-4000-8000-000000000001',
        'adminUser'          => '20000000-0000-4000-8000-000000000010',
        'adminMember'        => '20000000-0000-4000-8000-000000000020',
        'class10a'           => '20000000-0000-4000-8000-000000000030',
        'class10b'           => '20000000-0000-4000-8000-000000000031',
        'class11a'           => '20000000-0000-4000-8000-000000000032',
        'class12a'           => '20000000-0000-4000-8000-000000000033',
        'teacher1'           => '20000000-0000-4000-8000-000000000040',
        'teacher2'           => '20000000-0000-4000-8000-000000000041',
        'teacher3'           => '20000000-0000-4000-8000-000000000042',
        'teacher4'           => '20000000-0000-4000-8000-000000000043',
        'teacherProfile1'    => '20000000-0000-4000-8000-000000000050',
        'teacherProfile2'    => '20000000-0000-4000-8000-000000000051',
        'teacherProfile3'    => '20000000-0000-4000-8000-000000000052',
        'teacherProfile4'    => '20000000-0000-4000-8000-000000000053',
        'studentProfile1'    => '20000000-0000-4000-8000-000000000060',
        'studentProfile2'    => '20000000-0000-4000-8000-000000000061',
        'studentProfile3'    => '20000000-0000-4000-8000-000000000062',
        'studentProfile4'    => '20000000-0000-4000-8000-000000000063',
        'studentProfile5'    => '20000000-0000-4000-8000-000000000064',
        'studentProfile6'    => '20000000-0000-4000-8000-000000000065',
        'studentProfile7'    => '20000000-0000-4000-8000-000000000066',
        'studentProfile8'    => '20000000-0000-4000-8000-000000000067',
        'studentUser1'       => '20000000-0000-4000-8000-000000000070',
        'studentUser2'       => '20000000-0000-4000-8000-000000000071',
        'studentUser3'       => '20000000-0000-4000-8000-000000000072',
        'studentUser4'       => '20000000-0000-4000-8000-000000000073',
        'studentUser5'       => '20000000-0000-4000-8000-000000000074',
        'studentUser6'       => '20000000-0000-4000-8000-000000000075',
        'studentUser7'       => '20000000-0000-4000-8000-000000000076',
        'studentUser8'       => '20000000-0000-4000-8000-000000000077',
        'teacherUser1'       => '20000000-0000-4000-8000-000000000080',
        'teacherUser2'       => '20000000-0000-4000-8000-000000000081',
        'teacherUser3'       => '20000000-0000-4000-8000-000000000082',
        'teacherUser4'       => '20000000-0000-4000-8000-000000000083',
    ];

    public function run(PDO $pdo, string $environment, string $password): void
    {
        if (!in_array(strtolower($environment), ['test', 'testing', 'development', 'local'], true)) {
            throw new RuntimeException('School demo seed is forbidden outside local/test environments.');
        }
        if (strlen($password) < 12) {
            throw new RuntimeException(self::PASSWORD_ENV . ' must contain at least 12 characters.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Unable to hash the demo password.');
        }

        $pdo->beginTransaction();
        try {
            $this->insertSchool($pdo);
            $this->insertUsers($pdo, $hash);
            $this->insertClasses($pdo);
            $this->insertProfiles($pdo);
            $this->insertStudents($pdo);
            $this->insertSchoolMembership($pdo);
            $this->refreshCounters($pdo);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function demoAdminEmail(): string
    {
        return 'school.admin@talenthub.vn';
    }

    private function insertSchool(PDO $pdo): void
    {
        $sql = 'INSERT IGNORE INTO schools (id, name, status, logoUrl, address, phone, email, website, level, academicYear)
                VALUES (:id, :name, :status, :logoUrl, :address, :phone, :email, :website, :level, :academicYear)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id'           => self::IDS['school'],
            'name'         => 'THPT Nguyễn Trãi',
            'status'       => 'active',
            'logoUrl'      => '/assets/img/schools/logo-nguyen-trai.png',
            'address'      => '12 Sư Vạn Hạnh, Quận 10, TP. Hồ Chí Minh',
            'phone'        => '028-3863-1234',
            'email'        => 'c3-nguyentrai@hcm.edu.vn',
            'website'      => 'https://thptnguyentrai.edu.vn',
            'level'        => 'Trung học Phổ thông',
            'academicYear' => '2025 - 2026',
        ]);

        $update = $pdo->prepare(
            'UPDATE schools SET logoUrl = :logoUrl, address = :address, phone = :phone, email = :email,
                               website = :website, level = :level, academicYear = :academicYear
             WHERE id = :id AND name = :name'
        );
        $update->execute([
            'id'           => self::IDS['school'],
            'name'         => 'THPT Nguyễn Trãi',
            'logoUrl'      => '/assets/img/schools/logo-nguyen-trai.png',
            'address'      => '12 Sư Vạn Hạnh, Quận 10, TP. Hồ Chí Minh',
            'phone'        => '028-3863-1234',
            'email'        => 'c3-nguyentrai@hcm.edu.vn',
            'website'      => 'https://thptnguyentrai.edu.vn',
            'level'        => 'Trung học Phổ thông',
            'academicYear' => '2025 - 2026',
        ]);
    }

    private function insertUsers(PDO $pdo, string $hash): void
    {
        $roleId = $this->roleId($pdo, 'school');
        $sql    = 'INSERT IGNORE INTO users (id, roleId, email, passwordHash, fullName, status)
                   VALUES (:id, :roleId, :email, :hash, :fullName, :status)';
        $stmt   = $pdo->prepare($sql);

        $stmt->execute([
            'id'       => self::IDS['adminUser'],
            'roleId'   => $roleId,
            'email'    => $this->demoAdminEmail(),
            'hash'     => $hash,
            'fullName' => 'Ban Giám hiệu THPT Nguyễn Trãi',
            'status'   => 'active',
        ]);

        $teacherRoleId = $this->roleId($pdo, 'teacher');
        $teachers = [
            ['user' => self::IDS['teacherUser1'], 'name' => 'Nguyễn Thị Mai',   'email' => 'gv.mai@talenthub.vn'],
            ['user' => self::IDS['teacherUser2'], 'name' => 'Trần Văn Hùng',   'email' => 'gv.hung@talenthub.vn'],
            ['user' => self::IDS['teacherUser3'], 'name' => 'Lê Thị Hương',    'email' => 'gv.huong@talenthub.vn'],
            ['user' => self::IDS['teacherUser4'], 'name' => 'Phạm Văn Đức',    'email' => 'gv.duc@talenthub.vn'],
        ];
        foreach ($teachers as $t) {
            $stmt->execute([
                'id'       => $t['user'],
                'roleId'   => $teacherRoleId,
                'email'    => $t['email'],
                'hash'     => $hash,
                'fullName' => $t['name'],
                'status'   => 'active',
            ]);
        }

        $studentRoleId = $this->roleId($pdo, 'student');
        $students = [
            ['user' => self::IDS['studentUser1'], 'name' => 'Nguyễn Văn Minh',  'email' => 'hs.minh@talenthub.vn'],
            ['user' => self::IDS['studentUser2'], 'name' => 'Trần Thu Hà',      'email' => 'hs.ha@talenthub.vn'],
            ['user' => self::IDS['studentUser3'], 'name' => 'Lê Hoàng Nam',     'email' => 'hs.nam@talenthub.vn'],
            ['user' => self::IDS['studentUser4'], 'name' => 'Phạm Thị Lan',     'email' => 'hs.lan@talenthub.vn'],
            ['user' => self::IDS['studentUser5'], 'name' => 'Đỗ Quốc Bảo',      'email' => 'hs.bao@talenthub.vn'],
            ['user' => self::IDS['studentUser6'], 'name' => 'Võ Thị Tuyết',     'email' => 'hs.tuyet@talenthub.vn'],
            ['user' => self::IDS['studentUser7'], 'name' => 'Hoàng Minh Khôi',  'email' => 'hs.khoi@talenthub.vn'],
            ['user' => self::IDS['studentUser8'], 'name' => 'Phan Thanh Trúc',  'email' => 'hs.truc@talenthub.vn'],
        ];
        foreach ($students as $s) {
            $stmt->execute([
                'id'       => $s['user'],
                'roleId'   => $studentRoleId,
                'email'    => $s['email'],
                'hash'     => $hash,
                'fullName' => $s['name'],
                'status'   => 'active',
            ]);
        }
    }

    private function insertClasses(PDO $pdo): void
    {
        $sql  = 'INSERT IGNORE INTO classes (id, schoolId, name, gradeLevel, academicYear, status)
                 VALUES (:id, :schoolId, :name, :grade, :year, :status)';
        $stmt = $pdo->prepare($sql);
        $rows = [
            ['id' => self::IDS['class10a'], 'name' => '10A', 'grade' => 10],
            ['id' => self::IDS['class10b'], 'name' => '10B', 'grade' => 10],
            ['id' => self::IDS['class11a'], 'name' => '11A', 'grade' => 11],
            ['id' => self::IDS['class12a'], 'name' => '12A', 'grade' => 12],
        ];
        foreach ($rows as $row) {
            $stmt->execute([
                'id'       => $row['id'],
                'schoolId' => self::IDS['school'],
                'name'     => $row['name'],
                'grade'    => $row['grade'],
                'year'     => '2025 - 2026',
                'status'   => 'active',
            ]);
        }
    }

    private function insertProfiles(PDO $pdo): void
    {
        $sql  = 'INSERT IGNORE INTO teacher_profiles (id, userId, schoolId, isSchoolAdmin, phone, specialization, bio)
                 VALUES (:id, :userId, :schoolId, :isAdmin, :phone, :specialization, :bio)';
        $stmt = $pdo->prepare($sql);
        $pairs = [
            [self::IDS['teacherProfile1'], self::IDS['teacherUser1'], '0901000001', 'Toán học',  'Giáo viên Toán, 12 năm kinh nghiệm'],
            [self::IDS['teacherProfile2'], self::IDS['teacherUser2'], '0901000002', 'Vật lý',    'Giáo viên Vật lý, chủ nhiệm lớp 10B'],
            [self::IDS['teacherProfile3'], self::IDS['teacherUser3'], '0901000003', 'Ngữ văn',   'Giáo viên Ngữ văn, chủ nhiệm lớp 11A'],
            [self::IDS['teacherProfile4'], self::IDS['teacherUser4'], '0901000004', 'Tin học',   'Giáo viên Tin học, chủ nhiệm lớp 12A'],
        ];
        foreach ($pairs as [$id, $userId, $phone, $spec, $bio]) {
            $stmt->execute([
                'id'             => $id,
                'userId'         => $userId,
                'schoolId'       => self::IDS['school'],
                'isAdmin'        => 0,
                'phone'          => $phone,
                'specialization' => $spec,
                'bio'            => $bio,
            ]);
        }
    }

    private function insertStudents(PDO $pdo): void
    {
        $sql  = 'INSERT IGNORE INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus)
                 VALUES (:id, :userId, :classId, :dob, :phone, :status)';
        $stmt = $pdo->prepare($sql);
        $placements = [
            ['id' => self::IDS['studentProfile1'], 'user' => self::IDS['studentUser1'], 'class' => self::IDS['class12a'], 'dob' => '2008-09-12', 'phone' => '0912000001'],
            ['id' => self::IDS['studentProfile2'], 'user' => self::IDS['studentUser2'], 'class' => self::IDS['class11a'], 'dob' => '2008-04-21', 'phone' => '0912000002'],
            ['id' => self::IDS['studentProfile3'], 'user' => self::IDS['studentUser3'], 'class' => self::IDS['class12a'], 'dob' => '2008-11-03', 'phone' => '0912000003'],
            ['id' => self::IDS['studentProfile4'], 'user' => self::IDS['studentUser4'], 'class' => self::IDS['class12a'], 'dob' => '2008-01-15', 'phone' => '0912000004'],
            ['id' => self::IDS['studentProfile5'], 'user' => self::IDS['studentUser5'], 'class' => self::IDS['class10a'], 'dob' => '2009-08-09', 'phone' => '0912000005'],
            ['id' => self::IDS['studentProfile6'], 'user' => self::IDS['studentUser6'], 'class' => self::IDS['class10a'], 'dob' => '2009-02-17', 'phone' => '0912000006'],
            ['id' => self::IDS['studentProfile7'], 'user' => self::IDS['studentUser7'], 'class' => self::IDS['class10b'], 'dob' => '2009-05-22', 'phone' => '0912000007'],
            ['id' => self::IDS['studentProfile8'], 'user' => self::IDS['studentUser8'], 'class' => self::IDS['class11a'], 'dob' => '2008-07-30', 'phone' => '0912000008'],
        ];
        foreach ($placements as $row) {
            $stmt->execute([
                'id'     => $row['id'],
                'userId' => $row['user'],
                'classId'=> $row['class'],
                'dob'    => $row['dob'],
                'phone'  => $row['phone'],
                'status' => 'active',
            ]);
        }
    }

    private function insertSchoolMembership(PDO $pdo): void
    {
        $sql = 'INSERT IGNORE INTO school_members (id, schoolId, userId, memberRole)
                VALUES (:id, :schoolId, :userId, :role)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id'       => self::IDS['adminMember'],
            'schoolId' => self::IDS['school'],
            'userId'   => self::IDS['adminUser'],
            'role'     => 'admin',
        ]);
    }

    private function refreshCounters(PDO $pdo): void
    {
        $students = (int) $pdo->query(
            'SELECT COUNT(*) FROM student_profiles sp
             JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = ' . $pdo->quote(self::IDS['school']) . ' AND sp.studyStatus = \'active\''
        )->fetchColumn();

        $teachers = (int) $pdo->query(
            'SELECT COUNT(*) FROM teacher_profiles WHERE schoolId = ' . $pdo->quote(self::IDS['school'])
        )->fetchColumn();

        $stmt = $pdo->prepare('UPDATE schools SET studentCount = :s, teacherCount = :t WHERE id = :id');
        $stmt->execute(['s' => $students, 't' => $teachers, 'id' => self::IDS['school']]);
    }

    private function roleId(PDO $pdo, string $code): string
    {
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $id = $stmt->fetchColumn();
        if (!is_string($id)) {
            throw new RuntimeException("Role {$code} is missing. Run RolePermissionSeeder first.");
        }
        return $id;
    }
}