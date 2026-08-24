<?php
declare(strict_types=1);
namespace TalentHub\Modules\School\Repository;

use PDO;
use RuntimeException;
use TalentHub\Support\Uuid;

final class SchoolRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,mixed>|null */
    public function findByUserId(string $userId): ?array
    {
        if (!$this->hasTable('school_members')) {
            $stmt=$this->pdo->prepare("SELECT s.id,s.name,s.status,NULL AS logoUrl,NULL AS address,NULL AS phone,NULL AS email,NULL AS website,NULL AS level,0 AS studentCount,0 AS teacherCount,'' AS academicYear,CURRENT_TIMESTAMP AS createdAt,CURRENT_TIMESTAMP AS updatedAt,'admin' AS memberRole FROM users u JOIN schools s ON s.status='active' WHERE u.id=:userId AND u.roles='school' ORDER BY s.name LIMIT 1");
            $stmt->execute(['userId'=>$userId]);$row=$stmt->fetch();return is_array($row)?$row:null;
        }
        $sql = 'SELECT s.id, s.name, s.status, s.logoUrl, s.address, s.phone, s.email, s.website,
                       s.level, s.studentCount, s.teacherCount, s.academicYear,
                       s.createdAt, s.updatedAt,
                       sm.memberRole
                FROM school_members sm
                JOIN schools s ON s.id = sm.schoolId
                WHERE sm.userId = :userId
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['userId' => $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    private function hasTable(string $table): bool
    {
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$stmt->execute([$table]);return (int)$stmt->fetchColumn()===1;
    }

    /** @return array<string,mixed>|null */
    public function findById(string $schoolId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, status, logoUrl, address, phone, email, website, level,
                    studentCount, teacherCount, academicYear, createdAt, updatedAt
             FROM schools WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $schoolId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function update(string $schoolId, array $fields): void
    {
        $allowed = ['name', 'logoUrl', 'address', 'phone', 'email', 'website', 'level', 'academicYear'];
        $sets = [];
        $params = ['id' => $schoolId];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $fields[$col];
            }
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE schools SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /** @return list<array<string,mixed>> */
    public function listClasses(string $schoolId, bool $includeArchived = false): array
    {
        $sql = 'SELECT id, schoolId, name, gradeLevel, academicYear, status,
                       (SELECT COUNT(*) FROM student_profiles sp WHERE sp.classId = c.id AND sp.studyStatus = \'active\') AS studentCount
                FROM classes c
                WHERE c.schoolId = :schoolId';
        if (!$includeArchived) {
            $sql .= ' AND c.status = \'active\'';
        }
        $sql .= ' ORDER BY c.gradeLevel ASC, c.name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['schoolId' => $schoolId]);
        return array_values($stmt->fetchAll());
    }

    /** @return array<string,mixed>|null */
    public function findClassById(string $classId, string $schoolId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, schoolId, name, gradeLevel, academicYear, status, createdAt, updatedAt
             FROM classes WHERE id = :id AND schoolId = :schoolId LIMIT 1'
        );
        $stmt->execute(['id' => $classId, 'schoolId' => $schoolId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @param array{name:string,gradeLevel:int,academicYear:string,status?:string} $data
     */
    public function createClass(string $schoolId, array $data): string
    {
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare(
            'INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status)
             VALUES (:id, :schoolId, :name, :gradeLevel, :academicYear, :status)'
        );
        $stmt->execute([
            'id'           => $id,
            'schoolId'     => $schoolId,
            'name'         => $data['name'],
            'gradeLevel'   => $data['gradeLevel'],
            'academicYear' => $data['academicYear'],
            'status'       => $data['status'] ?? 'active',
        ]);
        return $id;
    }

    /**
     * @param array<string,mixed> $fields
     */
    public function updateClass(string $classId, string $schoolId, array $fields): void
    {
        $allowed = ['name', 'gradeLevel', 'academicYear', 'status'];
        $sets = [];
        $params = ['id' => $classId, 'schoolId' => $schoolId];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $fields[$col];
            }
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE classes SET ' . implode(', ', $sets)
             . ' WHERE id = :id AND schoolId = :schoolId';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /** @return list<array<string,mixed>> */
    public function listTeachers(string $schoolId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tp.id, tp.userId, tp.isSchoolAdmin, tp.specialization, tp.phone, tp.bio,
                    u.email, u.fullName, u.status AS userStatus
             FROM teacher_profiles tp
             JOIN users u ON u.id = tp.userId
             WHERE tp.schoolId = :schoolId
             ORDER BY u.fullName ASC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $stmt->execute(['schoolId' => $schoolId]);
        return array_values($stmt->fetchAll());
    }

    /** @return array<string,mixed>|null */
    public function findTeacherById(string $profileId, string $schoolId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tp.id, tp.userId, tp.schoolId, tp.isSchoolAdmin, tp.specialization, tp.phone, tp.bio,
                    u.email, u.fullName, u.status AS userStatus
             FROM teacher_profiles tp
             JOIN users u ON u.id = tp.userId
             WHERE tp.id = :id AND tp.schoolId = :schoolId LIMIT 1'
        );
        $stmt->execute(['id' => $profileId, 'schoolId' => $schoolId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function setTeacherAdmin(string $profileId, string $schoolId, bool $isAdmin): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE teacher_profiles SET isSchoolAdmin = :flag
             WHERE id = :id AND schoolId = :schoolId'
        );
        $stmt->execute([
            'flag'     => $isAdmin ? 1 : 0,
            'id'       => $profileId,
            'schoolId' => $schoolId,
        ]);
    }

    public function deactivateTeacher(string $profileId, string $schoolId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users u JOIN teacher_profiles tp ON tp.userId = u.id
             SET u.status = \'suspended\'
             WHERE tp.id = :id AND tp.schoolId = :schoolId'
        );
        $stmt->execute(['id' => $profileId, 'schoolId' => $schoolId]);
    }

    public function reactivateTeacher(string $profileId, string $schoolId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users u JOIN teacher_profiles tp ON tp.userId = u.id
             SET u.status = \'active\'
             WHERE tp.id = :id AND tp.schoolId = :schoolId'
        );
        $stmt->execute(['id' => $profileId, 'schoolId' => $schoolId]);
    }

    /**
     * @return array{userId:string,profileId:string}|null
     */
    public function inviteTeacher(
        string $schoolId,
        string $email,
        string $fullName,
        bool $isSchoolAdmin,
        string $passwordHash,
    ): array {
        $this->pdo->beginTransaction();
        try {
            $existing = $this->pdo->prepare('SELECT id, roleId FROM users WHERE email = :email LIMIT 1');
            $existing->execute(['email' => $email]);
            $existingUser = $existing->fetch();

            if ($existingUser) {
                $userId = (string) $existingUser['id'];
            } else {
                $userId = Uuid::v4();
                $roleId = $this->teacherRoleId();
                $stmt = $this->pdo->prepare(
                    'INSERT INTO users (id, roleId, email, passwordHash, fullName, status)
                     VALUES (:id, :roleId, :email, :hash, :fullName, \'active\')'
                );
                $stmt->execute([
                    'id'       => $userId,
                    'roleId'   => $roleId,
                    'email'    => $email,
                    'hash'     => $passwordHash,
                    'fullName' => $fullName,
                ]);
            }

            $profileStmt = $this->pdo->prepare(
                'SELECT id FROM teacher_profiles WHERE userId = :userId LIMIT 1'
            );
            $profileStmt->execute(['userId' => $userId]);
            $profileId = $profileStmt->fetchColumn();
            if (!$profileId) {
                $profileId = Uuid::v4();
                $insertProfile = $this->pdo->prepare(
                    'INSERT INTO teacher_profiles (id, userId, schoolId, isSchoolAdmin)
                     VALUES (:id, :userId, :schoolId, :flag)'
                );
                $insertProfile->execute([
                    'id'       => $profileId,
                    'userId'   => $userId,
                    'schoolId' => $schoolId,
                    'flag'     => $isSchoolAdmin ? 1 : 0,
                ]);
            } else {
                $profileId = (string) $profileId;
                $updateProfile = $this->pdo->prepare(
                    'UPDATE teacher_profiles SET schoolId = :schoolId, isSchoolAdmin = :flag
                     WHERE id = :id'
                );
                $updateProfile->execute([
                    'schoolId' => $schoolId,
                    'flag'     => $isSchoolAdmin ? 1 : 0,
                    'id'       => $profileId,
                ]);
            }

            $memberStmt = $this->pdo->prepare(
                'SELECT id FROM school_members WHERE userId = :userId LIMIT 1'
            );
            $memberStmt->execute(['userId' => $userId]);
            if (!$memberStmt->fetchColumn()) {
                $memberStmt = $this->pdo->prepare(
                    'INSERT INTO school_members (id, schoolId, userId, memberRole)
                     VALUES (:id, :schoolId, :userId, :role)'
                );
                $memberStmt->execute([
                    'id'       => Uuid::v4(),
                    'schoolId' => $schoolId,
                    'userId'   => $userId,
                    'role'     => $isSchoolAdmin ? 'admin' : 'member',
                ]);
            }

            $this->pdo->commit();
            return ['userId' => $userId, 'profileId' => $profileId];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public function listStudents(string $schoolId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sp.id, sp.userId, sp.classId, sp.dateOfBirth, sp.phone, sp.studyStatus,
                    u.email, u.fullName, u.status AS userStatus,
                    c.name AS className, c.gradeLevel
             FROM student_profiles sp
             JOIN users u ON u.id = sp.userId
             JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = :schoolId
             ORDER BY c.gradeLevel ASC, c.name ASC, u.fullName ASC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $stmt->execute(['schoolId' => $schoolId]);
        return array_values($stmt->fetchAll());
    }

    public function countStudents(string $schoolId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM student_profiles sp
             JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = :schoolId'
        );
        $stmt->execute(['schoolId' => $schoolId]);
        return (int) $stmt->fetchColumn();
    }

    public function countTeachers(string $schoolId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM teacher_profiles WHERE schoolId = :schoolId'
        );
        $stmt->execute(['schoolId' => $schoolId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string,mixed>|null */
    public function findStudentById(string $studentProfileId, string $schoolId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sp.id, sp.userId, sp.classId, sp.dateOfBirth, sp.phone, sp.studyStatus,
                    u.email, u.fullName, u.status AS userStatus,
                    c.schoolId, c.name AS className, c.gradeLevel
             FROM student_profiles sp
             JOIN users u ON u.id = sp.userId
             JOIN classes c ON c.id = sp.classId
             WHERE sp.id = :id AND c.schoolId = :schoolId LIMIT 1'
        );
        $stmt->execute(['id' => $studentProfileId, 'schoolId' => $schoolId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @param array{userId:string,classId:string,dateOfBirth:string,phone:string} $data
     */
    public function createStudentProfile(string $schoolId, array $data): string
    {
        $classStmt = $this->pdo->prepare(
            'SELECT id FROM classes WHERE id = :id AND schoolId = :schoolId LIMIT 1'
        );
        $classStmt->execute(['id' => $data['classId'], 'schoolId' => $schoolId]);
        if (!$classStmt->fetchColumn()) {
            throw new RuntimeException('Lớp không thuộc trường hiện tại.');
        }

        $profileId = Uuid::v4();
        $stmt = $this->pdo->prepare(
            'INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus)
             VALUES (:id, :userId, :classId, :dob, :phone, \'active\')'
        );
        $stmt->execute([
            'id'      => $profileId,
            'userId'  => $data['userId'],
            'classId' => $data['classId'],
            'dob'     => $data['dateOfBirth'],
            'phone'   => $data['phone'],
        ]);
        return $profileId;
    }

    /**
     * @param array<string,mixed> $fields
     */
    public function updateStudentProfile(string $profileId, string $schoolId, array $fields): void
    {
        $allowed = ['classId', 'dateOfBirth', 'phone', 'studyStatus'];
        $sets = [];
        $params = ['id' => $profileId];
        if (array_key_exists('classId', $fields)) {
            $classStmt = $this->pdo->prepare(
                'SELECT id FROM classes WHERE id = :id AND schoolId = :schoolId LIMIT 1'
            );
            $classStmt->execute(['id' => $fields['classId'], 'schoolId' => $schoolId]);
            if (!$classStmt->fetchColumn()) {
                throw new RuntimeException('Lớp không thuộc trường hiện tại.');
            }
        }
        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $fields[$col];
            }
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE student_profiles SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @return array<string,int>
     */
    public function dashboardMetrics(string $schoolId): array
    {
        $metrics = [
            'totalStudents'  => 0,
            'totalClasses'   => 0,
            'totalTeachers'  => 0,
        ];

        $studentStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM student_profiles sp
             JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = :schoolId AND sp.studyStatus = \'active\''
        );
        $studentStmt->execute(['schoolId' => $schoolId]);
        $metrics['totalStudents'] = (int) $studentStmt->fetchColumn();

        $classStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM classes WHERE schoolId = :schoolId AND status = \'active\''
        );
        $classStmt->execute(['schoolId' => $schoolId]);
        $metrics['totalClasses'] = (int) $classStmt->fetchColumn();

        $teacherStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM teacher_profiles WHERE schoolId = :schoolId'
        );
        $teacherStmt->execute(['schoolId' => $schoolId]);
        $metrics['totalTeachers'] = (int) $teacherStmt->fetchColumn();

        return $metrics;
    }

    /**
     * Sync the cached counters on the schools row with the real values.
     */
    public function refreshCounters(string $schoolId): void
    {
        $this->pdo->beginTransaction();
        try {
            $studentStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM student_profiles sp
                 JOIN classes c ON c.id = sp.classId
                 WHERE c.schoolId = :schoolId AND sp.studyStatus = \'active\''
            );
            $studentStmt->execute(['schoolId' => $schoolId]);
            $studentCount = (int) $studentStmt->fetchColumn();

            $teacherStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM teacher_profiles WHERE schoolId = :schoolId'
            );
            $teacherStmt->execute(['schoolId' => $schoolId]);
            $teacherCount = (int) $teacherStmt->fetchColumn();

            $updateStmt = $this->pdo->prepare(
                'UPDATE schools SET studentCount = :sc, teacherCount = :tc WHERE id = :id'
            );
            $updateStmt->execute([
                'sc' => $studentCount,
                'tc' => $teacherCount,
                'id' => $schoolId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function writeAudit(
        ?string $userId,
        string $action,
        string $entityType,
        ?string $entityId,
        ?array $metadata = null,
        ?string $requestId = null,
        ?string $ipAddress = null,
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (id, userId, action, entityType, entityId, requestId, ipAddress, metadata)
             VALUES (:id, :userId, :action, :entityType, :entityId, :requestId, :ipAddress, :metadata)'
        );
        $stmt->execute([
            'id'         => Uuid::v4(),
            'userId'     => $userId,
            'action'     => $action,
            'entityType' => $entityType,
            'entityId'   => $entityId,
            'requestId'  => $requestId,
            'ipAddress'  => $ipAddress,
            'metadata'   => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function listReports(string $schoolId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, schoolId, generatedByUserId, reportType, fileUrl, periodStart, periodEnd, createdAt
             FROM reports WHERE schoolId = :schoolId
             ORDER BY createdAt DESC LIMIT ' . (int) $limit
        );
        $stmt->execute(['schoolId' => $schoolId]);
        return array_values($stmt->fetchAll());
    }

    /**
     * @param array{reportType:string,fileUrl:string,periodStart:string,periodEnd:string} $data
     */
    public function insertReport(string $schoolId, string $userId, array $data): string
    {
        $id = Uuid::v4();
        $stmt = $this->pdo->prepare(
            'INSERT INTO reports (id, schoolId, generatedByUserId, reportType, fileUrl, periodStart, periodEnd)
             VALUES (:id, :schoolId, :userId, :reportType, :fileUrl, :periodStart, :periodEnd)'
        );
        $stmt->execute([
            'id'         => $id,
            'schoolId'   => $schoolId,
            'userId'     => $userId,
            'reportType' => $data['reportType'],
            'fileUrl'    => $data['fileUrl'],
            'periodStart'=> $data['periodStart'],
            'periodEnd'  => $data['periodEnd'],
        ]);
        return $id;
    }

    private function teacherRoleId(): string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => 'teacher']);
        $id = $stmt->fetchColumn();
        if (!is_string($id)) {
            throw new RuntimeException('Role teacher chưa được seed. Hãy chạy RolePermissionSeeder trước.');
        }
        return $id;
    }
}
