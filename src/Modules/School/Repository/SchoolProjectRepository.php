<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;

final class SchoolProjectRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function schoolIdForUser(string $userId): string
    {
        if ($this->tableExists('school_teachers')) {
            $stmt = $this->pdo->prepare('SELECT schoolId FROM school_teachers WHERE userId = ? LIMIT 1');
            $stmt->execute([$userId]);
            $schoolId = $stmt->fetchColumn();
            if (is_string($schoolId) && $schoolId !== '') {
                return $schoolId;
            }
        }

        if ($this->tableExists('schools')) {
            $schoolId = $this->pdo->query('SELECT id FROM schools LIMIT 1')?->fetchColumn();
            if (is_string($schoolId) && $schoolId !== '') {
                return $schoolId;
            }
        }

        throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy thông tin trường học tương ứng.');
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

        $fundingGoal = isset($input['fundingGoal']) ? (string) $input['fundingGoal'] : null;
        if ($fundingGoal !== null) {
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $fundingGoal) || (float) $fundingGoal <= 0) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Mục tiêu tài trợ (fundingGoal) phải là số dương lớn hơn 0.');
            }
        }

        $category = trim((string) ($input['category'] ?? 'general'));
        if ($category === '') {
            $category = 'general';
        }

        $status = trim((string) ($input['status'] ?? 'in_progress'));
        if (!in_array($status, ['draft', 'in_progress', 'completed', 'archived'], true)) {
            $status = 'in_progress';
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
        $startAt = isset($input['startAt']) && is_string($input['startAt']) ? trim($input['startAt']) : null;
        $endAt = isset($input['endAt']) && is_string($input['endAt']) ? trim($input['endAt']) : null;

        $id = Uuid::v4();
        $now = $this->now();

        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO projects (
                id, schoolId, mentorTeacherId, title, category, description,
                projectUrl, fundingGoal, startAt, endAt, status, createdAt, updatedAt
            ) VALUES (
                :id, :schoolId, :mentorTeacherId, :title, :category, :description,
                :projectUrl, :fundingGoal, :startAt, :endAt, :status, :createdAt, :updatedAt
            )
        SQL);

        $stmt->execute([
            'id' => $id,
            'schoolId' => $schoolId,
            'mentorTeacherId' => $mentorTeacherId,
            'title' => $title,
            'category' => $category,
            'description' => $description,
            'projectUrl' => $projectUrl,
            'fundingGoal' => $fundingGoal,
            'startAt' => $startAt,
            'endAt' => $endAt,
            'status' => $status,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);

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
                    COALESCE((SELECT COUNT(*) FROM project_members pm WHERE pm.projectId = p.id AND pm.status = 'active'), 0) AS membersCount
             FROM projects p
             WHERE p.schoolId = :schoolId
             ORDER BY p.createdAt DESC"
        );
        $stmt->execute(['schoolId' => $schoolId]);
        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    public function updateProject(string $schoolId, string $projectId, array $input): array
    {
        $current = $this->getProject($schoolId, $projectId);
        $now = $this->now();

        $title = isset($input['title']) ? trim((string) $input['title']) : (string) $current['title'];
        $category = isset($input['category']) ? trim((string) $input['category']) : (string) ($current['category'] ?? 'general');
        $description = array_key_exists('description', $input) ? (is_string($input['description']) ? trim($input['description']) : null) : $current['description'];
        $status = isset($input['status']) ? trim((string) $input['status']) : (string) $current['status'];
        $fundingGoal = array_key_exists('fundingGoal', $input) ? (string) $input['fundingGoal'] : $current['fundingGoal'];

        $stmt = $this->pdo->prepare(
            "UPDATE projects
             SET title = :title, category = :category, description = :description, status = :status,
                 fundingGoal = :fundingGoal, updatedAt = :updatedAt
             WHERE id = :id AND schoolId = :schoolId"
        );
        $stmt->execute([
            'title' => $title,
            'category' => $category,
            'description' => $description,
            'status' => $status,
            'fundingGoal' => $fundingGoal,
            'updatedAt' => $now,
            'id' => $projectId,
            'schoolId' => $schoolId,
        ]);

        return $this->getProject($schoolId, $projectId);
    }

    private function assertTeacherBelongsToSchool(string $mentorTeacherId, string $schoolId): void
    {
        if ($this->tableExists('teacher_profiles')) {
            $stmt = $this->pdo->prepare('SELECT schoolId FROM teacher_profiles WHERE id = ? LIMIT 1');
            $stmt->execute([$mentorTeacherId]);
            $tSchool = $stmt->fetchColumn();
            if ($tSchool !== false && $tSchool !== null && (string) $tSchool !== $schoolId) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Giáo viên hướng dẫn không thuộc trường học này.');
            }
        }
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
