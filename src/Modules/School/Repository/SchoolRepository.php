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
            // A legacy role string does not prove ownership of any school.
            // Fail closed instead of assigning the first active tenant.
            return null;
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

    /** @param array{specialization:?string,phone:?string,bio:?string} $fields */
    public function updateTeacherProfile(string $profileId, string $schoolId, array $fields): bool
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

        return $stmt->rowCount() > 0;
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

    /** @return array{userId:string,profileId:string,invitationId:string} */
    public function inviteTeacher(
        string $schoolId,
        string $email,
        string $fullName,
        bool $isSchoolAdmin,
        string $initialPasswordHash,
        string $invitedByUserId,
        string $tokenHash,
        string $expiresAt,
    ): array {
        $this->pdo->beginTransaction();
        try {
            $existing = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $existing->execute(['email' => $email]);
            if ($existing->fetchColumn()) {
                throw new RuntimeException('Email đã tồn tại trong hệ thống.');
            }

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
                'hash'     => $initialPasswordHash,
                'fullName' => $fullName,
            ]);

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
                'INSERT INTO school_members (id, schoolId, userId, memberRole)
                 VALUES (:id, :schoolId, :userId, :role)'
            );
            $memberStmt->execute([
                'id'       => Uuid::v4(),
                'schoolId' => $schoolId,
                'userId'   => $userId,
                'role'     => $isSchoolAdmin ? 'admin' : 'member',
            ]);

            $invitationId = $this->createAccountInvitation(
                $userId,
                $invitedByUserId,
                $schoolId,
                $tokenHash,
                $expiresAt
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
        string $tokenHash,
        string $expiresAt,
    ): string {
        $id = Uuid::v4();
        $statement = $this->pdo->prepare(
            'INSERT INTO account_invitations
                (id, userId, invitedByUserId, schoolId, tokenHash, expiresAt)
             VALUES
                (:id, :userId, :invitedByUserId, :schoolId, :tokenHash, :expiresAt)'
        );
        $statement->execute([
            'id' => $id,
            'userId' => $userId,
            'invitedByUserId' => $invitedByUserId,
            'schoolId' => $schoolId,
            'tokenHash' => $tokenHash,
            'expiresAt' => $expiresAt,
        ]);
        return $id;
    }

    /** @return array{status:string,userId?:string,schoolId?:string,role?:string} */
    public function acceptAccountInvitation(string $tokenHash, string $passwordHash): array
    {
        $this->pdo->beginTransaction();
        try {
            $isMySql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
            $statement = $this->pdo->prepare(
                'SELECT ai.id, ai.userId, ai.schoolId, ai.expiresAt, ai.acceptedAt, ai.revokedAt,
                        r.code AS role
                 FROM account_invitations ai
                 JOIN users u ON u.id = ai.userId
                 JOIN roles r ON r.id = u.roleId
                 WHERE ai.tokenHash = :tokenHash
                 LIMIT 1' . ($isMySql ? ' FOR UPDATE' : '')
            );
            $statement->execute(['tokenHash' => $tokenHash]);
            $row = $statement->fetch();
            if (!is_array($row)) {
                $this->pdo->rollBack();
                return ['status' => 'invalid'];
            }
            if ($row['acceptedAt'] !== null) {
                $this->pdo->rollBack();
                return ['status' => 'already_accepted'];
            }
            if ($row['revokedAt'] !== null) {
                $this->pdo->rollBack();
                return ['status' => 'revoked'];
            }
            if (strtotime((string) $row['expiresAt'] . ' UTC') <= time()) {
                $this->pdo->rollBack();
                return ['status' => 'expired'];
            }

            $updateUser = $this->pdo->prepare(
                "UPDATE users SET passwordHash = :passwordHash, status = 'active' WHERE id = :userId AND status = 'pending'"
            );
            $updateUser->execute(['passwordHash' => $passwordHash, 'userId' => $row['userId']]);
            if ($updateUser->rowCount() !== 1) {
                throw new RuntimeException('Tài khoản lời mời không còn ở trạng thái pending.');
            }

            $accept = $this->pdo->prepare(
                'UPDATE account_invitations SET acceptedAt = ' . ($isMySql ? 'UTC_TIMESTAMP(6)' : 'CURRENT_TIMESTAMP') . '
                 WHERE id = :id AND acceptedAt IS NULL AND revokedAt IS NULL'
            );
            $accept->execute(['id' => $row['id']]);
            if ($accept->rowCount() !== 1) {
                throw new RuntimeException('Lời mời đã được xử lý bởi yêu cầu khác.');
            }

            $this->writeAudit(
                (string) $row['userId'],
                'ACCOUNT_INVITATION_ACCEPT',
                'account_invitation',
                (string) $row['id'],
                ['schoolId' => (string) $row['schoolId'], 'role' => (string) $row['role']]
            );
            $this->pdo->commit();

            return [
                'status' => 'accepted',
                'userId' => (string) $row['userId'],
                'schoolId' => (string) $row['schoolId'],
                'role' => (string) $row['role'],
            ];
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
    public function listInternshipApplications(string $schoolId, ?string $status = null): array
    {
        $sql = 'SELECT ia.id, ia.status, ia.appliedAt, ia.reviewedAt,
                       sp.id AS studentId, u.fullName AS studentName,
                       ip.id AS postId, ip.title AS postTitle,
                       e.id AS enterpriseId, e.name AS enterpriseName,
                       assignment.id AS assignmentId, assignment.mentorTeacherId,
                       mentor.fullName AS mentorName
                FROM internship_applications ia
                JOIN student_profiles sp ON sp.id=ia.studentId
                JOIN users u ON u.id=sp.userId
                JOIN classes c ON c.id=sp.classId
                JOIN internship_posts ip ON ip.id=ia.postId
                JOIN enterprises e ON e.id=ip.enterpriseId
                LEFT JOIN internship_mentor_assignments assignment
                  ON assignment.applicationId=ia.id AND assignment.status=\'active\'
                LEFT JOIN teacher_profiles mentorProfile ON mentorProfile.id=assignment.mentorTeacherId
                LEFT JOIN users mentor ON mentor.id=mentorProfile.userId
                WHERE c.schoolId=:schoolId';
        $params = ['schoolId' => $schoolId];
        if ($status !== null) {
            $sql .= ' AND ia.status=:status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY ia.appliedAt DESC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return array_values($statement->fetchAll());
    }

    /** @return array<string,mixed> */
    public function assignInternshipMentor(
        string $schoolId,
        string $applicationId,
        string $mentorTeacherId,
        string $actorUserId,
    ): array {
        $this->pdo->beginTransaction();
        try {
            $application = $this->pdo->prepare(
                "SELECT ia.id FROM internship_applications ia
                 JOIN student_profiles sp ON sp.id=ia.studentId
                 JOIN classes c ON c.id=sp.classId
                 WHERE ia.id=:applicationId AND c.schoolId=:schoolId
                   AND ia.status IN ('interview','accepted') LIMIT 1"
            );
            $application->execute(['applicationId' => $applicationId, 'schoolId' => $schoolId]);
            if (!$application->fetchColumn()) {
                throw new RuntimeException('Ứng tuyển không thuộc trường hoặc chưa ở trạng thái có thể gán mentor.');
            }
            $mentor = $this->pdo->prepare(
                "SELECT tp.id FROM teacher_profiles tp JOIN users u ON u.id=tp.userId
                 WHERE tp.id=:teacherId AND tp.schoolId=:schoolId AND u.status='active' LIMIT 1"
            );
            $mentor->execute(['teacherId' => $mentorTeacherId, 'schoolId' => $schoolId]);
            if (!$mentor->fetchColumn()) {
                throw new RuntimeException('Mentor phải là giáo viên active thuộc cùng trường.');
            }

            $existing = $this->pdo->prepare('SELECT id FROM internship_mentor_assignments WHERE applicationId=:applicationId LIMIT 1');
            $existing->execute(['applicationId' => $applicationId]);
            $assignmentId = $existing->fetchColumn();
            $timestamp = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'UTC_TIMESTAMP(6)' : 'CURRENT_TIMESTAMP';
            if (is_string($assignmentId)) {
                $update = $this->pdo->prepare(
                    "UPDATE internship_mentor_assignments
                     SET mentorTeacherId=:mentorId, assignedByUserId=:actorId, status='active', assignedAt={$timestamp}, endedAt=NULL
                     WHERE id=:id"
                );
                $update->execute(['mentorId' => $mentorTeacherId, 'actorId' => $actorUserId, 'id' => $assignmentId]);
            } else {
                $assignmentId = Uuid::v4();
                $insert = $this->pdo->prepare(
                    'INSERT INTO internship_mentor_assignments
                     (id,applicationId,mentorTeacherId,assignedByUserId,status)
                     VALUES (:id,:applicationId,:mentorId,:actorId,\'active\')'
                );
                $insert->execute([
                    'id' => $assignmentId,
                    'applicationId' => $applicationId,
                    'mentorId' => $mentorTeacherId,
                    'actorId' => $actorUserId,
                ]);
            }
            $this->writeAudit($actorUserId, 'INTERNSHIP_MENTOR_ASSIGN', 'internship_mentor_assignment', $assignmentId, [
                'schoolId' => $schoolId,
                'applicationId' => $applicationId,
                'mentorTeacherId' => $mentorTeacherId,
            ]);
            $this->pdo->commit();

            foreach ($this->listInternshipApplications($schoolId) as $row) {
                if ((string) $row['id'] === $applicationId) {
                    return $row;
                }
            }
            throw new RuntimeException('Không thể đọc lại assignment vừa tạo.');
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
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

    /** @return array<string,int|string> */
    public function dashboardMetrics(string $schoolId): array
    {
        $counts = [
            'activeStudents' => <<<'SQL'
                SELECT COUNT(*) FROM student_profiles sp
                JOIN classes c ON c.id = sp.classId
                WHERE c.schoolId = :schoolId AND sp.studyStatus = 'active'
                SQL,
            'activeTeachers' => <<<'SQL'
                SELECT COUNT(*) FROM teacher_profiles tp
                JOIN users u ON u.id = tp.userId
                WHERE tp.schoolId = :schoolId AND u.status = 'active'
                SQL,
            'totalClasses' => <<<'SQL'
                SELECT COUNT(*) FROM classes
                WHERE schoolId = :schoolId AND status = 'active'
                SQL,
            'publishedActivities' => <<<'SQL'
                SELECT COUNT(*) FROM activities
                WHERE schoolId = :schoolId AND status = 'published'
                SQL,
            'approvedRegistrations' => <<<'SQL'
                SELECT COUNT(*) FROM activity_registrations ar
                JOIN activities a ON a.id = ar.activityId
                WHERE a.schoolId = :schoolId AND ar.status = 'approved'
                SQL,
            'confirmedCheckins' => <<<'SQL'
                SELECT COUNT(*) FROM checkins ci
                JOIN activity_registrations ar ON ar.id = ci.registrationId
                JOIN activities a ON a.id = ar.activityId
                WHERE a.schoolId = :schoolId AND ci.status = 'confirmed'
                SQL,
            'publishedAssessments' => <<<'SQL'
                SELECT COUNT(*) FROM assessments ass
                JOIN activities a ON a.id = ass.activityId
                WHERE a.schoolId = :schoolId AND ass.status = 'published'
                SQL,
            'verifiedSkills' => <<<'SQL'
                SELECT COUNT(*) FROM student_skills ss
                JOIN student_profiles sp ON sp.id = ss.studentId
                JOIN classes c ON c.id = sp.classId
                WHERE c.schoolId = :schoolId AND ss.verificationStatus = 'verified'
                SQL,
            'approvedEnterprisePartners' => <<<'SQL'
                SELECT COUNT(*) FROM school_enterprise_partnerships sep
                WHERE sep.schoolId = :schoolId AND sep.status = 'approved'
                SQL,
            'activeInternshipPosts' => <<<'SQL'
                SELECT COUNT(DISTINCT ip.id) FROM internship_posts ip
                JOIN internship_post_target_schools targets ON targets.postId = ip.id
                JOIN school_enterprise_partnerships sep
                  ON sep.schoolId = targets.schoolId
                 AND sep.enterpriseId = ip.enterpriseId
                 AND sep.status = 'approved'
                WHERE targets.schoolId = :schoolId AND ip.status = 'active'
                SQL,
            'acceptedInternshipApplications' => <<<'SQL'
                SELECT COUNT(*) FROM internship_applications ia
                JOIN student_profiles sp ON sp.id = ia.studentId
                JOIN classes c ON c.id = sp.classId
                WHERE c.schoolId = :schoolId AND ia.status = 'accepted'
                SQL,
            'activeProjects' => <<<'SQL'
                SELECT COUNT(*) FROM projects
                WHERE schoolId = :schoolId AND status = 'in_progress'
                SQL,
        ];

        $metrics = [];
        foreach ($counts as $name => $sql) {
            $statement = $this->pdo->prepare($sql);
            $statement->execute(['schoolId' => $schoolId]);
            $metrics[$name] = (int) $statement->fetchColumn();
        }

        $sponsorshipStatement = $this->pdo->prepare(<<<'SQL'
            SELECT COALESCE(SUM(ps.amount), 0)
            FROM project_sponsorships ps
            JOIN projects p ON p.id = ps.projectId
            WHERE p.schoolId = :schoolId AND ps.status = 'paid'
            SQL);
        $sponsorshipStatement->execute(['schoolId' => $schoolId]);
        $metrics['paidSponsorshipAmount'] = number_format(
            (float) $sponsorshipStatement->fetchColumn(),
            2,
            '.',
            ''
        );

        // Keep the old keys while School pages migrate to the documented contract.
        $metrics['totalStudents'] = $metrics['activeStudents'];
        $metrics['totalTeachers'] = $metrics['activeTeachers'];

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
