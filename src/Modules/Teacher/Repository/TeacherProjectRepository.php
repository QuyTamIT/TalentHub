<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Repository;
require_once dirname(__DIR__, 4) . '/app/learner/ai/Queue/TransactionalAiOutboxPublisher.php';

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;
use TalentHub\Learner\Ai\Queue\TransactionalAiOutboxPublisher;

final class TeacherProjectRepository
{
    private ?NotificationService $notifications = null;

    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function teacherInfoForUser(string $userId): array
    {
        if ($this->tableExists('teacher_profiles')) {
            $stmt = $this->pdo->prepare('SELECT id, schoolId FROM teacher_profiles WHERE userId = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return ['teacherId' => (string) $row['id'], 'schoolId' => (string) $row['schoolId']];
            }
        }

        if ($this->tableExists('school_teachers')) {
            $stmt = $this->pdo->prepare('SELECT schoolId FROM school_teachers WHERE userId = ? LIMIT 1');
            $stmt->execute([$userId]);
            $schoolId = $stmt->fetchColumn();
            if (is_string($schoolId) && $schoolId !== '') {
                return ['teacherId' => $userId, 'schoolId' => $schoolId];
            }
        }

        throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ giáo viên.');
    }

    /**
     * Adds a student member to a project under the teacher's supervision.
     * Enforces safeguarding check: student must belong to the same school as project/teacher.
     *
     * @param string $teacherUserId
     * @param string $projectId
     * @param array<string, mixed> $input
     * @param string $requestId
     * @return array<string, mixed>
     */
    public function addMember(string $teacherUserId, string $projectId, array $input, string $requestId): array
    {
        $teacherInfo = $this->teacherInfoForUser($teacherUserId);
        $schoolId = $teacherInfo['schoolId'];

        $studentId = trim((string) ($input['studentId'] ?? ''));
        if (!Uuid::isValid($studentId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'studentId không đúng định dạng UUID.');
        }

        $role = trim((string) ($input['role'] ?? 'member'));
        if ($role === '') {
            $role = 'member';
        }

        // 1. Verify project exists and belongs to the teacher's school
        $stmtProj = $this->pdo->prepare('SELECT id, schoolId, title FROM projects WHERE id = ? LIMIT 1');
        $stmtProj->execute([$projectId]);
        $proj = $stmtProj->fetch(PDO::FETCH_ASSOC);
        if (!is_array($proj)) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy dự án.');
        }
        if ((string) ($proj['schoolId'] ?? '') !== $schoolId) {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Giáo viên chỉ có thể quản lý dự án thuộc trường học của mình.');
        }

        // 2. Safeguarding check: verify student belongs to the same school
        $studentSchoolId = $this->schoolIdForStudent($studentId);
        if ($studentSchoolId !== null && $studentSchoolId !== $schoolId) {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Học sinh không thuộc cùng trường học với dự án.');
        }

        $this->pdo->beginTransaction();
        try {
        // 3. Check for existing active membership
        $stmtCheck = $this->pdo->prepare("SELECT id, status FROM project_members WHERE projectId = ? AND studentId = ? LIMIT 1");
        $stmtCheck->execute([$projectId, $studentId]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $now = $this->now();

        if (is_array($existing)) {
            if ($existing['status'] === 'active') {
                throw new ApiException(409, 'CONFLICT', 'Học sinh đã là thành viên đang hoạt động của dự án này.');
            }
            // Re-activate member if previously left/removed
            $memberId = (string) $existing['id'];
            $stmtUpdate = $this->pdo->prepare(
                "UPDATE project_members SET status = 'active', role = :role, joinedAt = :joinedAt, leftAt = NULL, updatedAt = :updatedAt WHERE id = :id"
            );
            $stmtUpdate->execute(['role' => $role, 'joinedAt' => $now, 'updatedAt' => $now, 'id' => $memberId]);
        } else {
            $memberId = Uuid::v4();
            $stmtInsert = $this->pdo->prepare(<<<'SQL'
                INSERT INTO project_members (id, projectId, studentId, role, status, joinedAt, createdAt, updatedAt)
                VALUES (:id, :projectId, :studentId, :role, 'active', :joinedAt, :createdAt, :updatedAt)
            SQL);
            $stmtInsert->execute([
                'id' => $memberId,
                'projectId' => $projectId,
                'studentId' => $studentId,
                'role' => $role,
                'joinedAt' => $now,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
        }

        // 4. Notify student
        $studentUserId = $this->userIdForStudent($studentId);
        if ($studentUserId !== null) {
            $this->getNotificationService()->publish(
                $studentUserId,
                'project_member_added',
                'Bạn đã được thêm vào dự án mới!',
                "Bạn đã được thêm vào dự án \"{$proj['title']}\" với vai trò {$role}.",
                "/app/learner/talent-passport.php",
                "project_member:{$projectId}:{$studentId}"
            );
        }

        TransactionalAiOutboxPublisher::publish($this->pdo,'project_membership',$memberId,TransactionalAiOutboxPublisher::version(),[$studentId],'project.membership_updated',['project_id'=>$projectId,'status'=>'active']);
        $this->pdo->commit();
        return $this->getMember($projectId, $memberId);
        } catch (\Throwable $exception) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $exception; }
    }

    public function getMember(string $projectId, string $memberId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pm.*, sp.userId, u.fullName AS studentName, u.email AS studentEmail
             FROM project_members pm
             INNER JOIN student_profiles sp ON sp.id = pm.studentId
             INNER JOIN users u ON u.id = sp.userId
             WHERE pm.id = :memberId AND pm.projectId = :projectId LIMIT 1"
        );
        $stmt->execute(['memberId' => $memberId, 'projectId' => $projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            // Fallback for minimal schema
            $stmtFallback = $this->pdo->prepare("SELECT * FROM project_members WHERE id = :memberId AND projectId = :projectId LIMIT 1");
            $stmtFallback->execute(['memberId' => $memberId, 'projectId' => $projectId]);
            return $stmtFallback->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        return $row;
    }

    public function listMembers(string $projectId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pm.*, sp.userId, u.fullName AS studentName, u.email AS studentEmail
             FROM project_members pm
             INNER JOIN student_profiles sp ON sp.id = pm.studentId
             INNER JOIN users u ON u.id = sp.userId
             WHERE pm.projectId = :projectId AND pm.status = 'active'
             ORDER BY pm.joinedAt ASC"
        );
        $stmt->execute(['projectId' => $projectId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            $stmtFallback = $this->pdo->prepare("SELECT * FROM project_members WHERE projectId = :projectId AND status = 'active' ORDER BY joinedAt ASC");
            $stmtFallback->execute(['projectId' => $projectId]);
            $items = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
        }

        return ['items' => $items ?: []];
    }

    private function schoolIdForStudent(string $studentId): ?string
    {
        if ($this->tableExists('student_profiles') && $this->tableExists('classes')) {
            $stmt = $this->pdo->prepare(
                "SELECT c.schoolId
                 FROM student_profiles sp
                 INNER JOIN classes c ON c.id = sp.classId
                 WHERE sp.id = ? LIMIT 1"
            );
            $stmt->execute([$studentId]);
            $sId = $stmt->fetchColumn();
            if (is_string($sId) && $sId !== '') {
                return $sId;
            }
        }
        return null;
    }

    private function userIdForStudent(string $studentId): ?string
    {
        if ($this->tableExists('student_profiles')) {
            $stmt = $this->pdo->prepare('SELECT userId FROM student_profiles WHERE id = ? LIMIT 1');
            $stmt->execute([$studentId]);
            $uId = $stmt->fetchColumn();
            return is_string($uId) && $uId !== '' ? $uId : null;
        }
        return null;
    }

    private function getNotificationService(): NotificationService
    {
        if (!class_exists('TalentHub\Learner\Data\Service\NotificationService', false)) {
            require_once dirname(__DIR__, 4) . '/app/learner/data/Contracts/NotificationRepository.php';
            require_once dirname(__DIR__, 4) . '/app/learner/data/Service/NotificationService.php';
            require_once dirname(__DIR__, 4) . '/app/learner/data/Database/DatabaseNotificationRepository.php';
        }
        if ($this->notifications === null) {
            $this->notifications = new NotificationService(new DatabaseNotificationRepository($this->pdo));
        }
        return $this->notifications;
    }

    private function tableExists(string $tableName): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = ? LIMIT 1");
            $stmt->execute([$tableName]);
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        $stmt->execute([$tableName]);
        return (bool) $stmt->fetchColumn();
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
