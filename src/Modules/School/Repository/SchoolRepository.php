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
        if ((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() === 1;
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
                       (SELECT COUNT(*) FROM student_profiles sp INNER JOIN users su ON su.id = sp.userId WHERE sp.classId = c.id AND sp.studyStatus = \'active\' AND su.status = \'active\') AS studentCount,
                       (SELECT COALESCE(ROUND(AVG(CASE
                           WHEN sp.phone IS NOT NULL AND sp.phone <> \'\' AND sp.dateOfBirth IS NOT NULL THEN 100
                           WHEN (sp.phone IS NOT NULL AND sp.phone <> \'\') OR sp.dateOfBirth IS NOT NULL THEN 50
                           ELSE 0 END)), 0)
                        FROM student_profiles sp INNER JOIN users su ON su.id = sp.userId WHERE sp.classId = c.id AND sp.studyStatus = \'active\' AND su.status = \'active\') AS profileCompletion
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
            'SELECT tp.id, tp.userId, tp.isSchoolAdmin,
                    COALESCE(tp.specialization, \'Giảng viên chuyên ngành\') AS specialization,
                    tp.phone, tp.bio,
                    u.email, u.fullName, u.status AS userStatus
             FROM teacher_profiles tp
             JOIN users u ON u.id = tp.userId
             WHERE tp.schoolId = :schoolId AND u.status = \'active\'
             GROUP BY u.fullName, tp.id, tp.userId, tp.isSchoolAdmin, tp.specialization, tp.phone, tp.bio, u.email, u.status
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

    /**
     * @param array{specialization:?string,phone:?string,bio:?string} $fields
     */
    public function updateTeacherProfile(string $profileId, string $schoolId, array $fields): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE teacher_profiles
             SET specialization = :specialization, phone = :phone, bio = :bio
             WHERE id = :id AND schoolId = :schoolId'
        );
        $stmt->execute([
            'specialization' => $fields['specialization'],
            'phone' => $fields['phone'],
            'bio' => $fields['bio'],
            'id' => $profileId,
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
        array $invitation,
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
                     VALUES (:id, :roleId, :email, :hash, :fullName, \'pending\')'
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

            $invitationId = $this->createAccountInvitation(
                $userId,
                (string) $invitation['invitedByUserId'],
                $schoolId,
                'teacher',
                (string) $invitation['tokenHash'],
                (string) $invitation['expiresAt'],
            );

            $this->pdo->commit();
            return ['userId' => $userId, 'profileId' => $profileId, 'invitationId' => $invitationId];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function createAccountInvitation(
        string $userId,
        string $invitedByUserId,
        string $schoolId,
        string $accountRole,
        string $tokenHash,
        string $expiresAt,
    ): string {
        $now = gmdate('Y-m-d H:i:s.u');
        $revoke = $this->pdo->prepare(
            'UPDATE account_invitations
             SET revokedAt = :now
             WHERE userId = :userId AND acceptedAt IS NULL AND revokedAt IS NULL'
        );
        $revoke->execute(['now' => $now, 'userId' => $userId]);

        $id = Uuid::v4();
        $insert = $this->pdo->prepare(
            'INSERT INTO account_invitations
                (id, userId, invitedByUserId, schoolId, accountRole, tokenHash, expiresAt, createdAt)
             VALUES
                (:id, :userId, :invitedByUserId, :schoolId, :accountRole, :tokenHash, :expiresAt, :createdAt)'
        );
        $insert->execute([
            'id' => $id,
            'userId' => $userId,
            'invitedByUserId' => $invitedByUserId,
            'schoolId' => $schoolId,
            'accountRole' => $accountRole,
            'tokenHash' => $tokenHash,
            'expiresAt' => $expiresAt,
            'createdAt' => $now,
        ]);
        return $id;
    }

    /** @return array<string,mixed>|null */
    public function findAccountInvitation(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ai.id, ai.userId, ai.schoolId, ai.accountRole, ai.expiresAt,
                    ai.acceptedAt, ai.revokedAt, u.email, u.fullName, u.status AS userStatus,
                    r.code AS actualRole, s.name AS schoolName
             FROM account_invitations ai
             INNER JOIN users u ON u.id = ai.userId
             INNER JOIN roles r ON r.id = u.roleId
             INNER JOIN schools s ON s.id = ai.schoolId
             WHERE ai.tokenHash = :tokenHash
             LIMIT 1'
        );
        $stmt->execute(['tokenHash' => $tokenHash]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function acceptAccountInvitation(string $tokenHash, string $passwordHash, string $acceptedAt, string $requestId): string
    {
        $this->pdo->beginTransaction();
        try {
            $lockSuffix = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $lock = $this->pdo->prepare(
                'SELECT id, userId FROM account_invitations
                 WHERE tokenHash = :tokenHash
                   AND acceptedAt IS NULL
                   AND revokedAt IS NULL
                   AND expiresAt > :acceptedAt
                 LIMIT 1' . $lockSuffix
            );
            $lock->execute(['tokenHash' => $tokenHash, 'acceptedAt' => $acceptedAt]);
            $invitation = $lock->fetch();
            if (!is_array($invitation)) {
                throw new \TalentHub\Http\ApiException(410, 'INVITATION_NOT_ACTIVE', 'Lời mời đã hết hạn, bị thu hồi hoặc đã được sử dụng.');
            }

            $updateInvitation = $this->pdo->prepare(
                'UPDATE account_invitations SET acceptedAt = :acceptedAt
                 WHERE id = :id AND acceptedAt IS NULL AND revokedAt IS NULL'
            );
            $updateInvitation->execute(['acceptedAt' => $acceptedAt, 'id' => $invitation['id']]);
            if ($updateInvitation->rowCount() !== 1) {
                throw new \TalentHub\Http\ApiException(409, 'INVITATION_ALREADY_USED', 'Lời mời đã được sử dụng.');
            }

            $updateUser = $this->pdo->prepare(
                "UPDATE users SET passwordHash = :passwordHash, status = 'active' WHERE id = :userId AND status = 'pending'"
            );
            $updateUser->execute(['passwordHash' => $passwordHash, 'userId' => $invitation['userId']]);
            if ($updateUser->rowCount() !== 1) {
                throw new \TalentHub\Http\ApiException(409, 'INVITATION_ACCOUNT_STATE_INVALID', 'Tài khoản không còn ở trạng thái chờ kích hoạt.');
            }

            $this->writeAudit(
                (string) $invitation['userId'],
                'ACCOUNT_INVITATION_ACCEPT',
                'account_invitation',
                (string) $invitation['id'],
                null,
                $requestId,
            );
            $this->pdo->commit();
            return (string) $invitation['userId'];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
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

    /** @return list<array<string,mixed>> */
    public function listInternshipApplications(string $schoolId): array
    {
        $hasLocks = $this->hasTable('internship_application_locks');
        $lockSelect = $hasLocks
            ? ', placementLock.lockedByApplicationId, placementLock.reason AS lockReason, placementLock.lockedAt'
            : ', NULL AS lockedByApplicationId, NULL AS lockReason, NULL AS lockedAt';
        $lockJoin = $hasLocks
            ? ' LEFT JOIN internship_application_locks placementLock ON placementLock.applicationId = ia.id'
            : '';
        $stmt = $this->pdo->prepare(
            'SELECT ia.id, ia.status, ia.appliedAt, ia.updatedAt,
                    sp.id AS studentId, sp.userId AS studentUserId, u.fullName AS studentName,
                    ip.id AS postId, ip.title AS postTitle,
                    e.id AS enterpriseId, e.name AS enterpriseName,
                    ima.mentorTeacherId, tp.userId AS mentorUserId, mentor.fullName AS mentorName'
                    . $lockSelect . '
             FROM internship_applications ia
             INNER JOIN student_profiles sp ON sp.id = ia.studentId
             INNER JOIN users u ON u.id = sp.userId
             INNER JOIN classes c ON c.id = sp.classId
             INNER JOIN internship_posts ip ON ip.id = ia.postId
             INNER JOIN enterprises e ON e.id = ip.enterpriseId
             LEFT JOIN internship_mentor_assignments ima ON ima.applicationId = ia.id
             LEFT JOIN teacher_profiles tp ON tp.id = ima.mentorTeacherId
             LEFT JOIN users mentor ON mentor.id = tp.userId'
             . $lockJoin . '
             WHERE c.schoolId = :schoolId
             ORDER BY ia.updatedAt DESC, ia.appliedAt DESC'
        );
        $stmt->execute(['schoolId' => $schoolId]);
        return array_values($stmt->fetchAll());
    }

    /** @return array<string,mixed> */
    public function assignInternshipMentor(string $schoolId, string $applicationId, string $mentorTeacherId, string $assignedByUserId): array
    {
        $application = $this->pdo->prepare(
            'SELECT ia.id, ia.status FROM internship_applications ia
             INNER JOIN student_profiles sp ON sp.id = ia.studentId
             INNER JOIN classes c ON c.id = sp.classId
             WHERE ia.id = :applicationId AND c.schoolId = :schoolId LIMIT 1'
        );
        $application->execute(['applicationId' => $applicationId, 'schoolId' => $schoolId]);
        $applicationRow = $application->fetch();
        if (!is_array($applicationRow)) {
            throw new \TalentHub\Http\ApiException(404, 'APPLICATION_NOT_FOUND', 'Không tìm thấy đơn thực tập trong trường.');
        }
        if ((string) ($applicationRow['status'] ?? '') !== 'accepted') {
            throw new \TalentHub\Http\ApiException(422, 'PLACEMENT_NOT_CONFIRMED', 'Chỉ có thể phân công mentor sau khi sinh viên được tiếp nhận thực tập.');
        }

        // If mentorTeacherId is empty, unassign the mentor
        if ($mentorTeacherId === '' || $mentorTeacherId === '0' || $mentorTeacherId === 'none') {
            $this->pdo->beginTransaction();
            try {
                $this->pdo->prepare('DELETE FROM internship_mentor_assignments WHERE applicationId = :applicationId')->execute(['applicationId' => $applicationId]);
                $this->writeAudit($assignedByUserId, 'INTERNSHIP_MENTOR_UNASSIGN', 'internship_application', $applicationId, ['schoolId' => $schoolId]);
                $this->pdo->commit();
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
                throw $exception;
            }
            foreach ($this->listInternshipApplications($schoolId) as $item) {
                if ((string) $item['id'] === $applicationId) { return $item; }
            }
            return ['id' => $applicationId, 'mentorTeacherId' => null, 'mentorName' => null];
        }

        $teacher = $this->pdo->prepare(
            'SELECT id FROM teacher_profiles '
            . 'WHERE (id = :teacherId OR userId = :teacherIdAlt) AND schoolId = :schoolId LIMIT 1'
        );
        $teacher->execute(['teacherId' => $mentorTeacherId, 'teacherIdAlt' => $mentorTeacherId, 'schoolId' => $schoolId]);
        $resolvedTeacherId = $teacher->fetchColumn();
        if (!$resolvedTeacherId) {
            throw new \TalentHub\Http\ApiException(422, 'MENTOR_NOT_FOUND', 'Không tìm thấy thông tin giáo viên hướng dẫn.');
        }
        $mentorTeacherId = (string) $resolvedTeacherId;

        $now = gmdate('Y-m-d H:i:s.u');
        $this->pdo->beginTransaction();
        try {
            $existing = $this->pdo->prepare('SELECT id FROM internship_mentor_assignments WHERE applicationId = :applicationId LIMIT 1');
            $existing->execute(['applicationId' => $applicationId]);
            $assignmentId = $existing->fetchColumn();
            if (is_string($assignmentId) && $assignmentId !== '') {
                $update = $this->pdo->prepare('UPDATE internship_mentor_assignments SET mentorTeacherId = :mentorTeacherId, assignedByUserId = :assignedByUserId, updatedAt = :updatedAt WHERE id = :id');
                $update->execute(['mentorTeacherId' => $mentorTeacherId, 'assignedByUserId' => $assignedByUserId, 'updatedAt' => $now, 'id' => $assignmentId]);
            } else {
                $assignmentId = Uuid::v4();
                $insert = $this->pdo->prepare('INSERT INTO internship_mentor_assignments (id, applicationId, mentorTeacherId, assignedByUserId, assignedAt, updatedAt) VALUES (:id, :applicationId, :mentorTeacherId, :assignedByUserId, :assignedAt, :updatedAt)');
                $insert->execute(['id' => $assignmentId, 'applicationId' => $applicationId, 'mentorTeacherId' => $mentorTeacherId, 'assignedByUserId' => $assignedByUserId, 'assignedAt' => $now, 'updatedAt' => $now]);
            }
            $this->writeAudit($assignedByUserId, 'INTERNSHIP_MENTOR_ASSIGN', 'internship_application', $applicationId, ['schoolId' => $schoolId, 'mentorTeacherId' => $mentorTeacherId]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $exception;
        }

        foreach ($this->listInternshipApplications($schoolId) as $item) {
            if ((string) $item['id'] === $applicationId) { return $item; }
        }
        return ['id' => $applicationId, 'mentorTeacherId' => $mentorTeacherId, 'status' => $applicationRow['status'] ?? 'accepted'];
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
            'activeStudents' => 0, 'activeTeachers' => 0, 'totalClasses' => 0,
            'publishedActivities' => 0, 'approvedRegistrations' => 0,
            'confirmedCheckins' => 0, 'publishedAssessments' => 0,
            'verifiedSkills' => 0, 'approvedEnterprisePartners' => 0,
            'activeInternshipPosts' => 0, 'acceptedInternshipApplications' => 0,
            'activeProjects' => 0, 'paidSponsorshipAmount' => '0.00',
        ];

        $studentStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM student_profiles sp
             INNER JOIN users u ON u.id = sp.userId
             JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = :schoolId AND sp.studyStatus = \'active\' AND u.status = \'active\''
        );
        $studentStmt->execute(['schoolId' => $schoolId]);
        $metrics['activeStudents'] = (int) $studentStmt->fetchColumn();

        $classStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM classes WHERE schoolId = :schoolId AND status = \'active\''
        );
        $classStmt->execute(['schoolId' => $schoolId]);
        $metrics['totalClasses'] = (int) $classStmt->fetchColumn();

        $teacherStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM teacher_profiles tp INNER JOIN users u ON u.id = tp.userId WHERE tp.schoolId = :schoolId AND u.status = 'active'"
        );
        $teacherStmt->execute(['schoolId' => $schoolId]);
        $metrics['activeTeachers'] = (int) $teacherStmt->fetchColumn();

        $queries = [
            'publishedActivities' => "SELECT COUNT(*) FROM activities WHERE schoolId = :schoolId AND status = 'published'",
            'approvedRegistrations' => "SELECT COUNT(*) FROM activity_registrations ar INNER JOIN activities a ON a.id = ar.activityId WHERE a.schoolId = :schoolId AND ar.status = 'approved'",
            'confirmedCheckins' => "SELECT COUNT(*) FROM checkins ci INNER JOIN activity_registrations ar ON ar.id = ci.registrationId INNER JOIN activities a ON a.id = ar.activityId WHERE a.schoolId = :schoolId AND ci.status = 'confirmed'",
            'publishedAssessments' => "SELECT COUNT(*) FROM assessments ass INNER JOIN activities a ON a.id = ass.activityId WHERE a.schoolId = :schoolId AND ass.status = 'published'",
            'verifiedSkills' => "SELECT COUNT(*) FROM student_skills ss INNER JOIN student_profiles sp ON sp.id = ss.studentId INNER JOIN classes c ON c.id = sp.classId WHERE c.schoolId = :schoolId AND ss.verificationStatus = 'verified'",
            'approvedEnterprisePartners' => "SELECT COUNT(*) FROM school_enterprise_partnerships WHERE schoolId = :schoolId AND status = 'approved'",
            'activeInternshipPosts' => "SELECT COUNT(DISTINCT ip.id) FROM internship_posts ip INNER JOIN internship_post_target_schools targets ON targets.postId = ip.id INNER JOIN school_enterprise_partnerships sep ON sep.schoolId = targets.schoolId AND sep.enterpriseId = ip.enterpriseId AND sep.status = 'approved' WHERE targets.schoolId = :schoolId AND ip.status = 'active' AND ip.audience = 'partner_schools'",
            'acceptedInternshipApplications' => "SELECT COUNT(*) FROM internship_applications ia INNER JOIN student_profiles sp ON sp.id = ia.studentId INNER JOIN classes c ON c.id = sp.classId WHERE c.schoolId = :schoolId AND ia.status IN ('accepted', 'hired')",
            'activeProjects' => "SELECT COUNT(*) FROM projects WHERE schoolId = :schoolId AND status = 'in_progress'",
            'paidSponsorshipAmount' => "SELECT COALESCE(SUM(ps.amount), 0) FROM project_sponsorships ps INNER JOIN projects p ON p.id = ps.projectId WHERE p.schoolId = :schoolId AND ps.status = 'paid'",
        ];
        foreach ($queries as $metric => $sql) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['schoolId' => $schoolId]);
            $value = $stmt->fetchColumn();
            $metrics[$metric] = $metric === 'paidSponsorshipAmount'
                ? number_format((float) $value, 2, '.', '')
                : (int) $value;
        }

        // Backward-compatible aliases for existing school UI consumers.
        $metrics['totalStudents'] = $metrics['activeStudents'];
        $metrics['totalTeachers'] = $metrics['activeTeachers'];

        return $metrics;
    }

    /** @return list<array{name:string,count:int,category?:string}> */
    public function verifiedSkillDistribution(string $schoolId): array
    {
        $categoryLabels = [
            'technical'   => 'Kỹ thuật & Công nghệ',
            'tech'        => 'Kỹ thuật & Công nghệ',
            'academic'    => 'Logic - Toán học',
            'business'    => 'Kinh doanh & Quản lý',
            'creative'    => 'Nghệ thuật & Sáng tạo',
            'soft'        => 'Ngoại ngữ & Giao tiếp',
            'soft_skill'  => 'Ngoại ngữ & Giao tiếp',
            'sports'      => 'Thể chất & Đời sống',
        ];

        $stmt = $this->pdo->prepare(
            "SELECT sk.category AS rawCategory, COUNT(DISTINCT ss.id) AS skillCount
             FROM student_skills ss
             INNER JOIN skills sk ON sk.id = ss.skillId
             INNER JOIN student_profiles sp ON sp.id = ss.studentId
             INNER JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = :schoolId AND ss.verificationStatus = 'verified'
             GROUP BY sk.category ORDER BY skillCount DESC"
        );
        $stmt->execute(['schoolId' => $schoolId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $result = [];
        foreach ($rows as $row) {
            $cat = (string) $row['rawCategory'];
            $label = $categoryLabels[$cat] ?? ucfirst($cat);
            $result[] = [
                'name'     => $label,
                'category' => $cat,
                'count'    => (int) $row['skillCount'],
            ];
        }
        return $result;
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
                 INNER JOIN users u ON u.id = sp.userId
                 JOIN classes c ON c.id = sp.classId
                 WHERE c.schoolId = :schoolId AND sp.studyStatus = \'active\' AND u.status = \'active\''
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
