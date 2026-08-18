<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

use PDO;
use RuntimeException;
use Throwable;

final class CompletePortalDemoSeeder
{
    public const PASSWORD_ENV = 'TALENTHUB_DEMO_PASSWORD';

    private const USERS = [
        'enterprise' => [
            'id' => '31000000-0000-4000-8000-000000000001',
            'email' => 'enterprise@talenthub.local',
            'fullName' => 'TalentHub Demo Enterprise',
        ],
        'school' => [
            'id' => '31000000-0000-4000-8000-000000000002',
            'email' => 'school@talenthub.local',
            'fullName' => 'TalentHub Demo School',
        ],
        'teacher' => [
            'id' => '31000000-0000-4000-8000-000000000003',
            'email' => 'teacher@talenthub.local',
            'fullName' => 'TalentHub Demo Teacher',
        ],
        'student' => [
            'id' => '31000000-0000-4000-8000-000000000004',
            'email' => 'student@talenthub.local',
            'fullName' => 'TalentHub Demo Student',
        ],
    ];

    private const IDS = [
        'school' => '41000000-0000-4000-8000-000000000001',
        'class' => '41000000-0000-4000-8000-000000000002',
        'enterprise' => '41000000-0000-4000-8000-000000000003',
        'studentProfile' => '41000000-0000-4000-8000-000000000011',
        'teacherProfile' => '41000000-0000-4000-8000-000000000012',
        'schoolMember' => '41000000-0000-4000-8000-000000000013',
        'enterpriseMember' => '41000000-0000-4000-8000-000000000014',
    ];

    private const REQUIRED_PERMISSIONS = [
        'student' => ['student_profile.read_own', 'student_dashboard.read_own'],
        'teacher' => ['teacher_profile.read_own', 'teacher_dashboard.read_own'],
        'school' => ['school_profile.read_own', 'school_dashboard.read_own'],
        'enterprise' => ['business_profile.read_own', 'business_dashboard.read_own'],
    ];

    private const OWNED_ROW_SCOPE_COLUMNS = [
        'student_profiles' => 'classId',
        'teacher_profiles' => 'schoolId',
        'school_members' => 'schoolId',
        'enterprise_members' => 'enterpriseId',
    ];

    private const OWNED_ROW_COLUMNS = [
        'student_profiles' => ['classId', 'dateOfBirth', 'phone', 'studyStatus'],
        'teacher_profiles' => ['schoolId', 'isSchoolAdmin', 'phone', 'specialization', 'bio'],
        'school_members' => ['schoolId', 'memberRole'],
        'enterprise_members' => ['enterpriseId', 'memberRole'],
    ];

    private const ROW_BY_ID_COLUMNS = [
        'schools' => ['name', 'email'],
        'classes' => ['schoolId', 'name', 'academicYear', 'gradeLevel'],
        'enterprises' => ['name', 'email', 'taxCode'],
        'student_profiles' => ['userId', 'classId'],
        'teacher_profiles' => ['userId', 'schoolId'],
        'school_members' => ['userId', 'schoolId'],
        'enterprise_members' => ['userId', 'enterpriseId'],
    ];

    public function run(PDO $pdo, string $environment, string $password): void
    {
        if (!in_array(strtolower($environment), ['local', 'test'], true)) {
            throw new RuntimeException('Complete portal demo seed is forbidden outside local/test environments.');
        }
        if (strlen($password) < 12) {
            throw new RuntimeException(self::PASSWORD_ENV . ' must contain at least 12 characters.');
        }
        if ($pdo->inTransaction()) {
            throw new RuntimeException('Complete portal demo seed must own its transaction.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Unable to hash the complete demo password.');
        }

        $pdo->beginTransaction();
        try {
            $roleIds = $this->roleIds($pdo);
            $this->assertRequiredPermissions($pdo);
            $this->upsertUsers($pdo, $roleIds, $passwordHash);
            $this->upsertSchool($pdo);
            $this->upsertClass($pdo);
            $this->upsertEnterprise($pdo);
            $this->upsertStudentProfile($pdo);
            $this->upsertTeacherProfile($pdo);
            $this->upsertSchoolMember($pdo);
            $this->upsertEnterpriseMember($pdo);
            $this->verifyCompleteScope($pdo, $password);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, string> */
    private function roleIds(PDO $pdo): array
    {
        $statement = $pdo->query(
            "SELECT code, id FROM roles WHERE code IN ('student', 'teacher', 'school', 'enterprise')"
        );
        $roles = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        if (count($roles) !== 4) {
            throw new RuntimeException('Complete demo requires all four canonical roles.');
        }

        return array_map('strval', $roles);
    }

    private function assertRequiredPermissions(PDO $pdo): void
    {
        foreach (self::REQUIRED_PERMISSIONS as $role => $permissions) {
            $placeholders = implode(',', array_fill(0, count($permissions), '?'));
            $statement = $pdo->prepare(
                "SELECT COUNT(DISTINCT p.code)
                 FROM roles r
                 JOIN role_permissions rp ON rp.roleId = r.id
                 JOIN permissions p ON p.id = rp.permissionId
                 WHERE r.code = ? AND p.code IN ({$placeholders})"
            );
            $statement->execute([$role, ...$permissions]);
            if ((int) $statement->fetchColumn() !== count($permissions)) {
                throw new RuntimeException("Required portal permissions are not mapped for role {$role}.");
            }
        }
    }

    /** @param array<string, string> $roleIds */
    private function upsertUsers(PDO $pdo, array $roleIds, string $passwordHash): void
    {
        foreach (self::USERS as $role => $user) {
            $byEmail = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1 FOR UPDATE');
            $byEmail->execute([$user['email']]);
            $emailId = $byEmail->fetchColumn();

            $byId = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
            $byId->execute([$user['id']]);
            $idEmail = $byId->fetchColumn();

            if ($emailId !== false && (string) $emailId !== $user['id']) {
                throw new RuntimeException("Demo email {$user['email']} belongs to another user.");
            }
            if ($idEmail !== false && (string) $idEmail !== $user['email']) {
                throw new RuntimeException("Demo user ID {$user['id']} belongs to another email.");
            }

            if ($emailId !== false || $idEmail !== false) {
                $statement = $pdo->prepare(
                    'UPDATE users
                     SET roleId = ?, passwordHash = ?, fullName = ?, status = ?
                     WHERE id = ? AND email = ?'
                );
                $statement->execute([
                    $roleIds[$role],
                    $passwordHash,
                    $user['fullName'],
                    'active',
                    $user['id'],
                    $user['email'],
                ]);
                continue;
            }

            $statement = $pdo->prepare(
                'INSERT INTO users (id, roleId, email, passwordHash, fullName, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $user['id'],
                $roleIds[$role],
                $user['email'],
                $passwordHash,
                $user['fullName'],
                'active',
            ]);
        }
    }

    private function upsertSchool(PDO $pdo): void
    {
        $expectedName = 'TalentHub Demo School';
        $expectedEmail = 'school@talenthub.local';
        $values = [
            self::IDS['school'],
            $expectedName,
            'active',
            '/assets/img/schools/logo-nguyen-trai.png',
            '12 Demo Education Street',
            '028-3800-0001',
            'school@talenthub.local',
            'https://school.talenthub.local',
            'High school',
            1,
            1,
            '2026-2027',
        ];
        $existing = $this->rowById($pdo, 'schools', self::IDS['school'], ['name', 'email']);
        if ($existing !== null
            && ((string) $existing['name'] !== $expectedName || (string) $existing['email'] !== $expectedEmail)) {
            throw new RuntimeException('Complete demo school ID belongs to another school.');
        }
        $fingerprint = $pdo->prepare(
            'SELECT id FROM schools
             WHERE id <> ? AND (name = ? OR email = ?)
             LIMIT 1 FOR UPDATE'
        );
        $fingerprint->execute([self::IDS['school'], $expectedName, $expectedEmail]);
        if ($fingerprint->fetchColumn() !== false) {
            throw new RuntimeException('Complete demo school fingerprint belongs to another school ID.');
        }

        if ($existing === null) {
            $statement = $pdo->prepare(
                'INSERT INTO schools
                 (id, name, status, logoUrl, address, phone, email, website, level, studentCount, teacherCount, academicYear)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute($values);
            return;
        }

        $statement = $pdo->prepare(
            'UPDATE schools
             SET status = ?, logoUrl = ?, address = ?, phone = ?, website = ?, level = ?,
                 studentCount = ?, teacherCount = ?, academicYear = ?
             WHERE id = ?'
        );
        $statement->execute([
            $values[2], $values[3], $values[4], $values[5], $values[7], $values[8],
            $values[9], $values[10], $values[11], $values[0],
        ]);
    }

    private function upsertClass(PDO $pdo): void
    {
        $expectedName = 'Demo Class 12A';
        $expectedAcademicYear = '2026-2027';
        $expectedGradeLevel = 12;
        $existing = $this->rowById(
            $pdo,
            'classes',
            self::IDS['class'],
            ['schoolId', 'name', 'academicYear', 'gradeLevel']
        );
        if ($existing !== null
            && ((string) $existing['schoolId'] !== self::IDS['school']
                || (string) $existing['name'] !== $expectedName
                || (string) $existing['academicYear'] !== $expectedAcademicYear
                || (int) $existing['gradeLevel'] !== $expectedGradeLevel)) {
            throw new RuntimeException('Complete demo class ID belongs to another school.');
        }
        $fingerprint = $pdo->prepare(
            'SELECT id FROM classes
             WHERE id <> ? AND schoolId = ? AND name = ? AND academicYear = ?
             LIMIT 1 FOR UPDATE'
        );
        $fingerprint->execute([
            self::IDS['class'],
            self::IDS['school'],
            $expectedName,
            $expectedAcademicYear,
        ]);
        if ($fingerprint->fetchColumn() !== false) {
            throw new RuntimeException('Complete demo class fingerprint belongs to another class ID.');
        }

        if ($existing === null) {
            $statement = $pdo->prepare(
                'INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                self::IDS['class'],
                self::IDS['school'],
                $expectedName,
                $expectedGradeLevel,
                $expectedAcademicYear,
                'active',
            ]);
            return;
        }

        $statement = $pdo->prepare(
            'UPDATE classes SET status = ? WHERE id = ?'
        );
        $statement->execute(['active', self::IDS['class']]);
    }

    private function upsertEnterprise(PDO $pdo): void
    {
        $expectedName = 'TalentHub Demo Enterprise';
        $expectedEmail = 'enterprise@talenthub.local';
        $expectedTaxCode = 'DEMO-4100000003';
        $existing = $this->rowById(
            $pdo,
            'enterprises',
            self::IDS['enterprise'],
            ['name', 'email', 'taxCode']
        );
        if ($existing !== null
            && ((string) $existing['name'] !== $expectedName
                || (string) $existing['email'] !== $expectedEmail
                || (string) $existing['taxCode'] !== $expectedTaxCode)) {
            throw new RuntimeException('Complete demo enterprise ID belongs to another enterprise.');
        }

        $values = [
            $expectedName,
            'active',
            '/assets/images/fpt-software-logo.svg',
            'Education technology',
            'Demo enterprise for local portal verification.',
            $expectedEmail,
            '028-3800-0002',
            'https://enterprise.talenthub.local',
            'Demo Education Street',
            'verified',
            '50-200',
            2020,
            $expectedTaxCode,
        ];
        $fingerprint = $pdo->prepare(
            'SELECT id FROM enterprises
             WHERE id <> ? AND (name = ? OR email = ? OR taxCode = ?)
             LIMIT 1 FOR UPDATE'
        );
        $fingerprint->execute([
            self::IDS['enterprise'],
            $expectedName,
            $expectedEmail,
            $expectedTaxCode,
        ]);
        if ($fingerprint->fetchColumn() !== false) {
            throw new RuntimeException('Complete demo enterprise fingerprint belongs to another enterprise ID.');
        }
        if ($existing === null) {
            $statement = $pdo->prepare(
                'INSERT INTO enterprises
                 (id, name, status, logoUrl, industry, description, email, phone, website, address,
                  verificationStatus, companySize, foundedYear, taxCode)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([self::IDS['enterprise'], ...$values]);
            return;
        }

        $statement = $pdo->prepare(
            'UPDATE enterprises
             SET status = ?, logoUrl = ?, industry = ?, description = ?, phone = ?, website = ?, address = ?,
                 verificationStatus = ?, companySize = ?, foundedYear = ?
             WHERE id = ?'
        );
        $statement->execute([
            $values[1],
            $values[2],
            $values[3],
            $values[4],
            $values[6],
            $values[7],
            $values[8],
            $values[9],
            $values[10],
            $values[11],
            self::IDS['enterprise'],
        ]);
    }

    private function upsertStudentProfile(PDO $pdo): void
    {
        $this->upsertOwnedRow(
            $pdo,
            'student_profiles',
            self::IDS['studentProfile'],
            self::USERS['student']['id'],
            'classId',
            self::IDS['class'],
            ['classId' => self::IDS['class'], 'dateOfBirth' => '2008-05-20', 'phone' => '0900000001', 'studyStatus' => 'active']
        );
    }

    private function upsertTeacherProfile(PDO $pdo): void
    {
        $this->upsertOwnedRow(
            $pdo,
            'teacher_profiles',
            self::IDS['teacherProfile'],
            self::USERS['teacher']['id'],
            'schoolId',
            self::IDS['school'],
            [
                'schoolId' => self::IDS['school'],
                'isSchoolAdmin' => 0,
                'phone' => '0900000002',
                'specialization' => 'STEM education',
                'bio' => 'TalentHub demo teacher profile.',
            ]
        );
    }

    private function upsertSchoolMember(PDO $pdo): void
    {
        $this->upsertOwnedRow(
            $pdo,
            'school_members',
            self::IDS['schoolMember'],
            self::USERS['school']['id'],
            'schoolId',
            self::IDS['school'],
            ['schoolId' => self::IDS['school'], 'memberRole' => 'admin']
        );
    }

    private function upsertEnterpriseMember(PDO $pdo): void
    {
        $this->upsertOwnedRow(
            $pdo,
            'enterprise_members',
            self::IDS['enterpriseMember'],
            self::USERS['enterprise']['id'],
            'enterpriseId',
            self::IDS['enterprise'],
            ['enterpriseId' => self::IDS['enterprise'], 'memberRole' => 'owner']
        );
    }

    /** @param array<string, scalar> $values */
    private function upsertOwnedRow(
        PDO $pdo,
        string $table,
        string $id,
        string $userId,
        string $scopeColumn,
        string $scopeId,
        array $values,
    ): void
    {
        $allowedScopeColumn = self::OWNED_ROW_SCOPE_COLUMNS[$table] ?? null;
        $allowedColumns = self::OWNED_ROW_COLUMNS[$table] ?? null;
        if ($allowedScopeColumn !== $scopeColumn || $allowedColumns === null) {
            throw new RuntimeException("Unsupported owned-row scope definition for {$table}.");
        }
        if (array_diff(array_keys($values), $allowedColumns) !== []
            || !array_key_exists($scopeColumn, $values)
            || (string) $values[$scopeColumn] !== $scopeId) {
            throw new RuntimeException("Invalid owned-row columns or scope for {$table}.");
        }

        $fixed = $this->rowById($pdo, $table, $id, ['userId', $scopeColumn]);
        $byUser = $pdo->prepare(
            "SELECT id, {$scopeColumn} FROM {$table} WHERE userId = ? LIMIT 1 FOR UPDATE"
        );
        $byUser->execute([$userId]);
        $userRow = $byUser->fetch(PDO::FETCH_ASSOC);

        if ($fixed !== null && (string) $fixed['userId'] !== $userId) {
            throw new RuntimeException("Complete demo {$table} ID belongs to another user.");
        }
        if ($fixed !== null && (string) $fixed[$scopeColumn] !== $scopeId) {
            throw new RuntimeException("Complete demo {$table} ID belongs to another scope.");
        }
        if ($userRow !== false && (string) $userRow['id'] !== $id) {
            throw new RuntimeException("Demo user {$userId} already has another {$table} row.");
        }
        if ($userRow !== false && (string) $userRow[$scopeColumn] !== $scopeId) {
            throw new RuntimeException("Demo user {$userId} already belongs to another {$table} scope.");
        }

        if ($fixed === null) {
            $columns = array_keys($values);
            $columnList = implode(', ', ['id', 'userId', ...$columns]);
            $placeholders = implode(', ', array_fill(0, count($columns) + 2, '?'));
            $statement = $pdo->prepare("INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})");
            $statement->execute([$id, $userId, ...array_values($values)]);
            return;
        }

        $sets = array_map(static fn(string $column): string => "{$column} = ?", array_keys($values));
        $statement = $pdo->prepare(
            "UPDATE {$table} SET " . implode(', ', $sets) . ' WHERE id = ? AND userId = ?'
        );
        $statement->execute([...array_values($values), $id, $userId]);
    }

    /** @param list<string> $columns @return array<string, mixed>|null */
    private function rowById(PDO $pdo, string $table, string $id, array $columns): ?array
    {
        $allowedColumns = self::ROW_BY_ID_COLUMNS[$table] ?? null;
        if ($allowedColumns === null || array_diff($columns, $allowedColumns) !== []) {
            throw new RuntimeException("Unsupported row lookup for {$table}.");
        }
        $statement = $pdo->prepare(
            'SELECT ' . implode(', ', $columns) . " FROM {$table} WHERE id = ? LIMIT 1 FOR UPDATE"
        );
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function verifyCompleteScope(PDO $pdo, string $password): void
    {
        $statement = $pdo->prepare(
            'SELECT u.id, u.email, u.passwordHash, u.status, r.code AS roleCode
             FROM users u JOIN roles r ON r.id = u.roleId
             WHERE u.id = ? LIMIT 1'
        );
        foreach (self::USERS as $role => $user) {
            $statement->execute([$user['id']]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)
                || (string) $row['email'] !== $user['email']
                || (string) $row['roleCode'] !== $role
                || (string) $row['status'] !== 'active'
                || !password_verify($password, (string) $row['passwordHash'])) {
                throw new RuntimeException("Complete demo user verification failed for {$role}.");
            }
        }

        $this->assertExists(
            $pdo,
            'SELECT COUNT(*) FROM schools
             WHERE id = ? AND name = ? AND email = ? AND status = ?',
            [self::IDS['school'], 'TalentHub Demo School', 'school@talenthub.local', 'active'],
            'school fingerprint'
        );
        $this->assertExists(
            $pdo,
            'SELECT COUNT(*) FROM classes
             WHERE id = ? AND schoolId = ? AND name = ? AND gradeLevel = ? AND academicYear = ? AND status = ?',
            [self::IDS['class'], self::IDS['school'], 'Demo Class 12A', 12, '2026-2027', 'active'],
            'class fingerprint'
        );
        $this->assertExists(
            $pdo,
            'SELECT COUNT(*) FROM enterprises
             WHERE id = ? AND name = ? AND email = ? AND taxCode = ? AND status = ? AND verificationStatus = ?',
            [self::IDS['enterprise'], 'TalentHub Demo Enterprise', 'enterprise@talenthub.local', 'DEMO-4100000003', 'active', 'verified'],
            'enterprise fingerprint'
        );
        $this->assertExists($pdo, 'SELECT COUNT(*) FROM student_profiles sp JOIN classes c ON c.id = sp.classId JOIN schools s ON s.id = c.schoolId WHERE sp.id = ? AND sp.userId = ? AND sp.classId = ? AND sp.studyStatus = ? AND c.id = ? AND c.schoolId = ? AND c.status = ? AND s.id = ? AND s.status = ?', [self::IDS['studentProfile'], self::USERS['student']['id'], self::IDS['class'], 'active', self::IDS['class'], self::IDS['school'], 'active', self::IDS['school'], 'active'], 'student scope');
        $this->assertExists($pdo, 'SELECT COUNT(*) FROM teacher_profiles tp JOIN schools s ON s.id = tp.schoolId WHERE tp.id = ? AND tp.userId = ? AND tp.schoolId = ? AND tp.isSchoolAdmin = 0 AND s.id = ? AND s.status = ?', [self::IDS['teacherProfile'], self::USERS['teacher']['id'], self::IDS['school'], self::IDS['school'], 'active'], 'teacher scope');
        $this->assertExists($pdo, 'SELECT COUNT(*) FROM school_members sm JOIN schools s ON s.id = sm.schoolId WHERE sm.id = ? AND sm.userId = ? AND sm.schoolId = ? AND sm.memberRole = ? AND s.id = ? AND s.status = ?', [self::IDS['schoolMember'], self::USERS['school']['id'], self::IDS['school'], 'admin', self::IDS['school'], 'active'], 'school scope');
        $this->assertExists($pdo, 'SELECT COUNT(*) FROM enterprise_members em JOIN enterprises e ON e.id = em.enterpriseId WHERE em.id = ? AND em.userId = ? AND em.enterpriseId = ? AND em.memberRole = ? AND e.id = ? AND e.status = ? AND e.verificationStatus = ?', [self::IDS['enterpriseMember'], self::USERS['enterprise']['id'], self::IDS['enterprise'], 'owner', self::IDS['enterprise'], 'active', 'verified'], 'enterprise scope');

        foreach ([
            ['student_profiles', self::USERS['student']['id']],
            ['teacher_profiles', self::USERS['teacher']['id']],
            ['school_members', self::USERS['school']['id']],
            ['enterprise_members', self::USERS['enterprise']['id']],
        ] as [$table, $userId]) {
            $statement = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE userId = ?");
            $statement->execute([$userId]);
            if ((int) $statement->fetchColumn() !== 1) {
                throw new RuntimeException("Duplicate or missing {$table} scope for user {$userId}.");
            }
        }

        $this->assertRequiredPermissions($pdo);
    }

    /** @param list<mixed> $parameters */
    private function assertExists(PDO $pdo, string $sql, array $parameters, string $label): void
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException("Complete demo verification failed for {$label}.");
        }
    }
}
