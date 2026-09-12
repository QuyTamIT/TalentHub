<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Repository;

require_once dirname(__DIR__, 4) . '/app/learner/ai/Queue/TransactionalAiOutboxPublisher.php';

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;
use TalentHub\Learner\Ai\Queue\TransactionalAiOutboxPublisher;

final class SchoolProjectRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function schoolIdForUser(string $userId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT sm.schoolId
             FROM school_members sm
             INNER JOIN schools s ON s.id = sm.schoolId
             WHERE sm.userId = :userId AND s.status = \'active\'
             LIMIT 2'
        );
        $stmt->execute(['userId' => $userId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (count($ids) === 1 && is_string($ids[0]) && $ids[0] !== '') {
            return $ids[0];
        }

        throw new ApiException(403, 'PERMISSION_DENIED', 'Tài khoản phải thuộc đúng một trường đang hoạt động.');
    }

    /**
     * Creates a new innovation/research project for the school.
     *
     * @param string $schoolId
     * @param string $userId
     * @param array<string, mixed> $input
     * @param string $requestId
     * @return array<string, mixed>
     */
    public function createProject(string $schoolId, string $userId, array $input, string $requestId): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Tiêu đề dự án không được để trống.');
        }

        $fundingGoal = $this->normalizeFundingGoal($input['fundingGoal'] ?? null);

        $category = trim((string) ($input['category'] ?? 'general'));
        if ($category === '') {
            $category = 'general';
        }

        $status = trim((string) ($input['status'] ?? 'draft'));
        if (!in_array($status, ['draft', 'in_progress', 'completed', 'archived'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Trạng thái dự án không hợp lệ.');
        }

        $mentorTeacherId = isset($input['mentorTeacherId']) && is_string($input['mentorTeacherId']) && trim($input['mentorTeacherId']) !== ''
            ? trim($input['mentorTeacherId'])
            : null;

        // Verify mentor teacher belongs to the same school if specified
        if ($mentorTeacherId !== null) {
            $this->assertTeacherBelongsToSchool($mentorTeacherId, $schoolId);
        }

        $description = isset($input['description']) && is_string($input['description']) ? trim($input['description']) : null;
        $projectUrl = isset($input['projectUrl']) && is_string($input['projectUrl']) ? trim($input['projectUrl']) : null;
        $startAt = isset($input['startAt']) && is_string($input['startAt']) && trim($input['startAt']) !== '' ? trim($input['startAt']) : null;
        $endAt = isset($input['endAt']) && is_string($input['endAt']) && trim($input['endAt']) !== '' ? trim($input['endAt']) : null;
        if ($startAt !== null && !$this->validDate($startAt)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Ngày bắt đầu không hợp lệ.');
        }
        if ($endAt !== null && !$this->validDate($endAt)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Ngày kết thúc không hợp lệ.');
        }
        if ($startAt !== null && $endAt !== null && $endAt < $startAt) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.');
        }

        $topic = isset($input['topic']) && is_string($input['topic']) ? trim($input['topic']) : null;
        $authorIds = isset($input['authorIds']) && is_array($input['authorIds']) ? $input['authorIds'] : [];
        $skillTags = $this->normalizeSkillTags($input['skillTags'] ?? []);

        $id = Uuid::v4();
        $now = $this->now();

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO projects (
                id, schoolId, mentorTeacherId, title, category, topic, description,
                projectUrl, fundingGoal, startAt, endAt, status, createdAt, updatedAt
            ) VALUES (
                :id, :schoolId, :mentorTeacherId, :title, :category, :topic, :description,
                :projectUrl, :fundingGoal, :startAt, :endAt, :status, :createdAt, :updatedAt
            )
SQL);

            $stmt->execute([
                'id' => $id,
                'schoolId' => $schoolId,
                'mentorTeacherId' => $mentorTeacherId,
                'title' => $title,
                'category' => $category,
                'topic' => $topic,
                'description' => $description,
                'projectUrl' => $projectUrl,
                'fundingGoal' => $fundingGoal,
                'startAt' => $startAt,
                'endAt' => $endAt,
                'status' => $status,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);

            $this->writeAudit($userId, 'PROJECT_CREATE', $id, $requestId, [
                'schoolId' => $schoolId,
                'mentorTeacherId' => $mentorTeacherId,
                'status' => $status,
            ]);

            // Insert authorIds
            $recipients = [];
            if (!empty($authorIds)) {
                $memberStmt = $this->pdo->prepare('INSERT INTO project_members (id, projectId, studentId, role, status, joinedAt, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                foreach ($authorIds as $studentId) {
                    if (is_string($studentId) && trim($studentId) !== '') {
                        $trimmed = trim($studentId);
                        $memberStmt->execute([
                            Uuid::v4(), $id, $trimmed, 'member', 'active', $now, $now, $now
                        ]);
                        $recipients[] = $trimmed;
                    }
                }
            }
            $this->replaceProjectSkillTags($id, $skillTags, $now);
            $this->publishProjectChanged($id, $recipients, $status);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $exception;
        }

        return $this->getProject($schoolId, $id);
    }

    public function getProject(string $schoolId, string $projectId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projects WHERE id = :id AND schoolId = :schoolId LIMIT 1');
        $stmt->execute(['id' => $projectId, 'schoolId' => $schoolId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy dự án trong trường học.');
        }

        // Calculate raised amount and sponsors count
        $stmtStats = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS raisedAmount, COUNT(DISTINCT enterpriseId) AS sponsorsCount
             FROM project_sponsorships
             WHERE projectId = ? AND status = 'paid'"
        );
        $stmtStats->execute([$projectId]);
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: ['raisedAmount' => '0.00', 'sponsorsCount' => 0];

        $row['raisedAmount'] = (string) $stats['raisedAmount'];
        $row['sponsorsCount'] = (int) $stats['sponsorsCount'];

        return $row;
    }

    /**
     * Lists projects of a school with live sponsorship progress.
     *
     * @param string $schoolId
     * @return array{items: list<array<string, mixed>>}
     */
    public function listSchoolProjects(string $schoolId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*,
                    COALESCE((SELECT SUM(ps.amount) FROM project_sponsorships ps WHERE ps.projectId = p.id AND ps.status = 'paid'), 0) AS raisedAmount,
                    COALESCE((SELECT COUNT(DISTINCT ps.enterpriseId) FROM project_sponsorships ps WHERE ps.projectId = p.id AND ps.status = 'paid'), 0) AS sponsorsCount,
                    COALESCE((SELECT COUNT(*) FROM project_members pm WHERE pm.projectId = p.id AND pm.status = 'active'), 0) AS membersCount,
                    COALESCE((SELECT COUNT(*) FROM project_members pm WHERE pm.projectId = p.id AND pm.status = 'pending'), 0) AS pendingMembersCount
             FROM projects p
             WHERE p.schoolId = :schoolId
             ORDER BY p.createdAt DESC"
        );
        $stmt->execute(['schoolId' => $schoolId]);
        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    /**
     * Lists all members and applicants for a school project.
     *
     * @param string $schoolId
     * @param string $projectId
     * @return list<array<string, mixed>>
     */
    public function listProjectMembers(string $schoolId, string $projectId): array
    {
        $this->getProject($schoolId, $projectId);

        $stmt = $this->pdo->prepare(
            "SELECT 
                pm.id,
                pm.projectId,
                pm.studentId,
                pm.role,
                pm.status,
                pm.joinedAt,
                pm.createdAt,
                pm.updatedAt,
                u.fullName AS studentName,
                u.email AS studentEmail,
                sp.phone AS studentPhone,
                c.name AS className,
                c.id AS classId
             FROM project_members pm
             INNER JOIN student_profiles sp ON sp.id = pm.studentId
             INNER JOIN users u ON u.id = sp.userId
             LEFT JOIN classes c ON c.id = sp.classId
             WHERE pm.projectId = :projectId
             ORDER BY 
                CASE WHEN pm.status = 'pending' THEN 0 WHEN pm.status = 'active' THEN 1 ELSE 2 END,
                pm.createdAt DESC"
        );
        $stmt->execute(['projectId' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Approves or rejects a student's project membership.
     *
     * @param string $schoolId
     * @param string $userId
     * @param string $projectId
     * @param string $studentId
     * @param string $status
     * @return array<string, mixed>
     */
    public function updateMemberStatus(string $schoolId, string $userId, string $projectId, string $studentId, string $status): array
    {
        $status = trim(strtolower($status));
        if (!in_array($status, ['active', 'rejected', 'left', 'removed'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Trạng thái thành viên không hợp lệ.');
        }

        $project = $this->getProject($schoolId, $projectId);

        $checkStmt = $this->pdo->prepare('SELECT id, status, joinedAt FROM project_members WHERE projectId = :projectId AND studentId = :studentId LIMIT 1');
        $checkStmt->execute(['projectId' => $projectId, 'studentId' => $studentId]);
        $member = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($member)) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy thông tin đăng ký của sinh viên trong dự án.');
        }

        $now = $this->now();
        $joinedAt = $member['joinedAt'];
        if ($status === 'active' && ($joinedAt === null || $joinedAt === '')) {
            $joinedAt = $now;
        }

        $this->pdo->beginTransaction();
        try {
            $updateStmt = $this->pdo->prepare(
                'UPDATE project_members 
                 SET status = :status, joinedAt = :joinedAt, updatedAt = :updatedAt 
                 WHERE projectId = :projectId AND studentId = :studentId'
            );
            $updateStmt->execute([
                'status' => $status,
                'joinedAt' => $joinedAt,
                'updatedAt' => $now,
                'projectId' => $projectId,
                'studentId' => $studentId,
            ]);

            if ($status === 'active') {
                $this->publishProjectChanged($projectId, [$studentId], (string) $project['status']);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $fetchStmt = $this->pdo->prepare(
            'SELECT pm.*, u.fullName AS studentName, u.email AS studentEmail, sp.phone AS studentPhone, c.name AS className
             FROM project_members pm
             INNER JOIN student_profiles sp ON sp.id = pm.studentId
             INNER JOIN users u ON u.id = sp.userId
             LEFT JOIN classes c ON c.id = sp.classId
             WHERE pm.projectId = :projectId AND pm.studentId = :studentId LIMIT 1'
        );
        $fetchStmt->execute(['projectId' => $projectId, 'studentId' => $studentId]);
        return $fetchStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateProject(string $schoolId, string $userId, string $projectId, array $input): array
    {
        $current = $this->getProject($schoolId, $projectId);
        $now = $this->now();

        $title = isset($input['title']) ? trim((string) $input['title']) : (string) $current['title'];
        $category = isset($input['category']) ? trim((string) $input['category']) : (string) ($current['category'] ?? 'general');
        $topic = array_key_exists('topic', $input) ? (is_string($input['topic']) ? trim($input['topic']) : null) : ($current['topic'] ?? null);
        $description = array_key_exists('description', $input) ? (is_string($input['description']) ? trim($input['description']) : null) : $current['description'];
        $status = isset($input['status']) ? trim((string) $input['status']) : (string) $current['status'];
        if (!in_array($status, ['draft', 'in_progress', 'completed', 'archived'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Trạng thái dự án không hợp lệ.');
        }
        if ($title === '' || mb_strlen($title) > 255) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Tiêu đề dự án phải có từ 1 đến 255 ký tự.');
        }
        $fundingGoal = array_key_exists('fundingGoal', $input)
            ? $this->normalizeFundingGoal($input['fundingGoal'])
            : $current['fundingGoal'];
        $skillTags = array_key_exists('skillTags', $input) ? $this->normalizeSkillTags($input['skillTags']) : null;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE projects
                 SET title = :title, category = :category, topic = :topic, description = :description, status = :status,
                     fundingGoal = :fundingGoal, updatedAt = :updatedAt
                 WHERE id = :id AND schoolId = :schoolId"
            );
            $stmt->execute([
                'title' => $title,
                'category' => $category,
                'topic' => $topic,
                'description' => $description,
                'status' => $status,
                'fundingGoal' => $fundingGoal,
                'updatedAt' => $now,
                'id' => $projectId,
                'schoolId' => $schoolId,
            ]);
            if ($skillTags !== null) $this->replaceProjectSkillTags($projectId, $skillTags, $now);

            $this->writeAudit($userId, 'PROJECT_UPDATE', $projectId, 'school-project-ui', [
                'schoolId' => $schoolId,
                'changes' => array_keys($input),
            ]);
            $members = $this->pdo->prepare('SELECT studentId FROM project_members WHERE projectId = :projectId AND status = "active"');
            $members->execute(['projectId' => $projectId]);
            $recipients = array_values(array_filter(array_map('strval', $members->fetchAll(PDO::FETCH_COLUMN) ?: [])));
            $this->publishProjectChanged($projectId, $recipients, $status);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $exception;
        }

        return $this->getProject($schoolId, $projectId);
    }

    /** @param list<string> $recipients */
    private function publishProjectChanged(string $projectId, array $recipients, string $status): void
    {
        if ($recipients === []) {
            return;
        }
        $published = TransactionalAiOutboxPublisher::publish(
            $this->pdo,
            'project',
            $projectId,
            TransactionalAiOutboxPublisher::version(),
            $recipients,
            'project.changed',
            ['status' => $status],
        );
        if ($published !== true) {
            throw new \RuntimeException('Không ghi được sự kiện làm mới AI cho dự án ' . $projectId . '.');
        }
    }

    private function assertTeacherBelongsToSchool(string $mentorTeacherId, string $schoolId): void
    {
        if ($this->tableExists('teacher_profiles')) {
            $stmt = $this->pdo->prepare('SELECT schoolId FROM teacher_profiles WHERE id = ? LIMIT 1');
            $stmt->execute([$mentorTeacherId]);
            $tSchool = $stmt->fetchColumn();
            if (!is_string($tSchool) || $tSchool === '' || $tSchool !== $schoolId) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Giáo viên hướng dẫn không thuộc trường học này.');
            }
        }
    }

    /** @return list<string> */
    private function normalizeSkillTags(mixed $value): array
    {
        if (!is_array($value)) throw new ApiException(422, 'VALIDATION_FAILED', 'skillTags phải là một danh sách.');
        $codes = array_values(array_unique(array_filter(array_map(static fn ($item): string => strtolower(trim((string) $item)), $value))));
        if ($codes === []) return [];
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $stmt = $this->pdo->prepare("SELECT code FROM skills WHERE status='active' AND code IN ({$placeholders})");
        $stmt->execute($codes);
        $valid = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        sort($valid);
        if (count($valid) !== count($codes)) throw new ApiException(422, 'VALIDATION_FAILED', 'skillTags chứa kỹ năng không tồn tại hoặc đã inactive.');
        return $valid;
    }

    /** @param list<string> $codes */
    private function replaceProjectSkillTags(string $projectId, array $codes, string $now): void
    {
        if (!$this->tableExists('project_skill_tags')) return;
        $delete = $this->pdo->prepare('DELETE FROM project_skill_tags WHERE projectId=:projectId');
        $delete->execute(['projectId' => $projectId]);
        if ($codes === []) return;
        $lookup = $this->pdo->prepare('SELECT id FROM skills WHERE code=:code AND status="active" LIMIT 1');
        $insert = $this->pdo->prepare('INSERT INTO project_skill_tags (id,projectId,skillId,verifiedAt,createdAt) VALUES (:id,:projectId,:skillId,:verifiedAt,:createdAt)');
        foreach ($codes as $code) {
            $lookup->execute(['code' => $code]);
            $skillId = $lookup->fetchColumn();
            if (is_string($skillId) && $skillId !== '') $insert->execute(['id'=>Uuid::v4(),'projectId'=>$projectId,'skillId'=>$skillId,'verifiedAt'=>$now,'createdAt'=>$now]);
        }
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** @param array<string,mixed> $metadata */
    private function writeAudit(string $userId, string $action, string $projectId, string $requestId, array $metadata): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (id, userId, action, entityType, entityId, requestId, metadata)
             VALUES (:id, :userId, :action, \'project\', :entityId, :requestId, :metadata)'
        );
        $stmt->execute([
            'id' => Uuid::v4(),
            'userId' => $userId,
            'action' => $action,
            'entityId' => $projectId,
            'requestId' => $requestId,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private function normalizeFundingGoal(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $str = trim((string) $raw);
        if ($str === '') {
            return null;
        }

        // Remove whitespace
        $str = str_replace(' ', '', $str);

        // If both comma and dot exist: determine which one is the decimal separator
        if (str_contains($str, ',') && str_contains($str, '.')) {
            if (strrpos($str, ',') > strrpos($str, '.')) {
                // Comma is decimal separator (e.g. 10.000.000,50)
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                // Dot is decimal separator (e.g. 10,000,000.50)
                $str = str_replace(',', '', $str);
            }
        } elseif (substr_count($str, '.') > 1) {
            // Multiple dots (e.g. 10.000.000 -> 10000000)
            $str = str_replace('.', '', $str);
        } elseif (substr_count($str, ',') > 1) {
            // Multiple commas (e.g. 10,000,000 -> 10000000)
            $str = str_replace(',', '', $str);
        } elseif (preg_match('/^\d{1,3}\.\d{3}$/', $str)) {
            // Exactly 3 digits after a single dot: VND thousands separator (e.g. 10.000 -> 10000)
            $str = str_replace('.', '', $str);
        } elseif (str_contains($str, ',')) {
            if (preg_match('/^\d{1,3},\d{3}$/', $str)) {
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace(',', '.', $str);
            }
        }

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $str) || (float) $str <= 0) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mục tiêu tài trợ (fundingGoal) phải là số dương lớn hơn 0.');
        }

        return $str;
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
