<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Testing;

use PDO;
use RuntimeException;
use Throwable;

final class MinimalAuthRbacSeeder
{
    public const PASSWORD_ENV = 'TALENTHUB_TEST_PASSWORD';

    private const IDS = [
        'school' => '10000000-0000-4000-8000-000000000001',
        'class' => '10000000-0000-4000-8000-000000000002',
        'enterprise' => '10000000-0000-4000-8000-000000000003',
        'studentUser' => '10000000-0000-4000-8000-000000000011',
        'teacherUser' => '10000000-0000-4000-8000-000000000012',
        'schoolUser' => '10000000-0000-4000-8000-000000000013',
        'businessUser' => '10000000-0000-4000-8000-000000000014',
        'studentProfile' => '10000000-0000-4000-8000-000000000021',
        'teacherProfile' => '10000000-0000-4000-8000-000000000022',
        'schoolMember' => '10000000-0000-4000-8000-000000000023',
        'enterpriseMember' => '10000000-0000-4000-8000-000000000024',
    ];

    public function run(PDO $pdo, string $environment, string $password): void
    {
        if (!in_array(strtolower($environment), ['test', 'testing', 'development', 'local'], true)) {
            throw new RuntimeException('Minimal test seed is forbidden outside test/development environments.');
        }
        if (strlen($password) < 12) {
            throw new RuntimeException(self::PASSWORD_ENV . ' must contain at least 12 characters.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Unable to hash the test password.');
        }

        $pdo->beginTransaction();
        try {
            $this->insertCatalogs($pdo);
            $this->insertUsers($pdo, $hash);
            $this->insertScopes($pdo);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function insertCatalogs(PDO $pdo): void
    {
        $this->insertIgnore($pdo, 'INSERT IGNORE INTO schools (id, name, status) VALUES (?, ?, ?)',
            [self::IDS['school'], 'TalentHub Test School', 'active']);
        $this->insertIgnore($pdo, 'INSERT IGNORE INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES (?, ?, ?, ?, ?)',
            [self::IDS['class'], self::IDS['school'], 'Test Class 12A', 12, '2026-2027']);
    }

    private function insertUsers(PDO $pdo, string $hash): void
    {
        $roleIds = $this->roleIds($pdo);
        $users = [
            [self::IDS['studentUser'], $roleIds['student'], 'student@test.talenthub.local', $hash, 'Test Student'],
            [self::IDS['teacherUser'], $roleIds['teacher'], 'teacher@test.talenthub.local', $hash, 'Test Teacher'],
            [self::IDS['schoolUser'], $roleIds['school'], 'school@test.talenthub.local', $hash, 'Test School User'],
            [self::IDS['businessUser'], $roleIds['business'], 'business@test.talenthub.local', $hash, 'Test Business User'],
        ];
        foreach ($users as $user) {
            $this->insertIgnore($pdo,
                'INSERT IGNORE INTO users (id, roleId, email, passwordHash, fullName, status) VALUES (?, ?, ?, ?, ?, ?)',
                [...$user, 'active']);
        }

        $this->insertIgnore($pdo,
            'INSERT IGNORE INTO enterprises (id, name, status, email, verificationStatus) VALUES (?, ?, ?, ?, ?)',
            [self::IDS['enterprise'], 'TalentHub Test Business', 'active', 'business@test.talenthub.local', 'pending']);
    }

    private function insertScopes(PDO $pdo): void
    {
        $this->insertIgnore($pdo,
            'INSERT IGNORE INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus) VALUES (?, ?, ?, ?, ?, ?)',
            [self::IDS['studentProfile'], self::IDS['studentUser'], self::IDS['class'], '2008-05-20', '0900000001', 'active']);
        $this->insertIgnore($pdo,
            'INSERT IGNORE INTO teacher_profiles (id, userId, schoolId, isSchoolAdmin) VALUES (?, ?, ?, ?)',
            [self::IDS['teacherProfile'], self::IDS['teacherUser'], self::IDS['school'], 0]);
        $this->insertIgnore($pdo,
            'INSERT IGNORE INTO school_members (id, schoolId, userId, memberRole) VALUES (?, ?, ?, ?)',
            [self::IDS['schoolMember'], self::IDS['school'], self::IDS['schoolUser'], 'member']);
        $this->insertIgnore($pdo,
            'INSERT IGNORE INTO enterprise_members (id, enterpriseId, userId, memberRole) VALUES (?, ?, ?, ?)',
            [self::IDS['enterpriseMember'], self::IDS['enterprise'], self::IDS['businessUser'], 'member']);
    }

    /** @return array<string, string> */
    private function roleIds(PDO $pdo): array
    {
        $statement = $pdo->query("SELECT code, id FROM roles WHERE code IN ('student', 'teacher', 'school', 'business')");
        $roles = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        if (count($roles) !== 4) {
            throw new RuntimeException('Run RolePermissionSeeder before MinimalAuthRbacSeeder.');
        }
        return $roles;
    }

    /** @param list<mixed> $values */
    private function insertIgnore(PDO $pdo, string $sql, array $values): void
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($values);
    }
}
