<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;

final class InternshipRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?NotificationService $notifications = null
    ) {}


    public function enterpriseIdForUser(string $userId): string
    {
        $statement = $this->pdo->prepare('SELECT enterpriseId FROM enterprise_members WHERE userId = :userId ORDER BY id');
        $statement->execute(['userId' => $userId]);
        $ids = $statement->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (count($ids) !== 1) {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Tài khoản phải thuộc đúng một doanh nghiệp.');
        }
        return (string) $ids[0];
    }

    public function posts(string $enterpriseId): array
    {
        $statement = $this->pdo->prepare('SELECT ip.*, COUNT(ia.id) AS applicantCount FROM internship_posts ip LEFT JOIN internship_applications ia ON ia.postId = ip.id WHERE ip.enterpriseId = :enterpriseId GROUP BY ip.id ORDER BY ip.createdAt DESC, ip.id');
        $statement->execute(['enterpriseId' => $enterpriseId]);
        return ['items' => $statement->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    public function post(string $enterpriseId, string $postId): array
    {
        return $this->ownedPost($enterpriseId, $postId);
    }

    public function createPost(string $enterpriseId, array $fields): array
    {
        $id = Uuid::v4();
        $now = $this->now();
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO internship_posts
                (id, enterpriseId, title, field, status, location, workType, duration, educationLevel,
                 description, benefits, skillsJson, requirementsJson, slots, deadline, createdAt, updatedAt)
            VALUES
                (:id, :enterpriseId, :title, :field, 'draft', :location, :workType, :duration, :educationLevel,
                 :description, :benefits, :skillsJson, :requirementsJson, :slots, :deadline, :createdAt, :updatedAt)
        SQL);
        $statement->execute($fields + ['id' => $id, 'enterpriseId' => $enterpriseId, 'createdAt' => $now, 'updatedAt' => $now]);
        return $this->ownedPost($enterpriseId, $id);
    }

    public function updatePost(string $enterpriseId, string $postId, array $fields): array
    {
        $this->pdo->beginTransaction();
        try {
            $post = $this->lockOwnedPost($enterpriseId, $postId);
            if ($post === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy tin tuyển dụng.');
            }
            if (!in_array((string) $post['status'], ['draft', 'active'], true)) {
                throw new ApiException(422, 'ILLEGAL_STATUS_TRANSITION', 'Không thể sửa tin ở trạng thái hiện tại.');
            }
            if ($fields !== []) {
                $sets = [];
                $parameters = ['id' => $postId, 'enterpriseId' => $enterpriseId, 'updatedAt' => $this->now()];
                foreach ($fields as $field => $value) {
                    $sets[] = "{$field} = :{$field}";
                    $parameters[$field] = $value;
                }
                $sets[] = 'updatedAt = :updatedAt';
                $statement = $this->pdo->prepare('UPDATE internship_posts SET ' . implode(', ', $sets) . ' WHERE id = :id AND enterpriseId = :enterpriseId');
                $statement->execute($parameters);
            }
            $this->pdo->commit();
            return $this->ownedPost($enterpriseId, $postId);
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function transitionPost(string $enterpriseId, string $postId, string $expectedStatus, string $targetStatus): array
    {
        $this->pdo->beginTransaction();
        try {
            $post = $this->lockOwnedPost($enterpriseId, $postId);
            if ($post === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy tin tuyển dụng.');
            }
            if ((string) $post['status'] !== $expectedStatus) {
                throw new ApiException(409, 'CONCURRENT_MODIFICATION', 'Trạng thái tin đã thay đổi.');
            }
            $allowed = $expectedStatus === 'draft' && $targetStatus === 'active'
                || $expectedStatus === 'active' && $targetStatus === 'closed';
            if (!$allowed) {
                throw new ApiException(422, 'ILLEGAL_STATUS_TRANSITION', 'Chuyển trạng thái tin không hợp lệ.');
            }
            if ($targetStatus === 'active' && new DateTimeImmutable((string) $post['deadline'], new DateTimeZone('UTC')) < new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Không thể đăng tin đã hết hạn.');
            }
            $statement = $this->pdo->prepare('UPDATE internship_posts SET status = :targetStatus, updatedAt = :updatedAt WHERE id = :id AND enterpriseId = :enterpriseId AND status = :expectedStatus');
            $statement->execute(['targetStatus' => $targetStatus, 'updatedAt' => $this->now(), 'id' => $postId, 'enterpriseId' => $enterpriseId, 'expectedStatus' => $expectedStatus]);
            if ($statement->rowCount() !== 1) {
                throw new ApiException(409, 'CONCURRENT_MODIFICATION', 'Trạng thái tin đã thay đổi.');
            }
            $this->pdo->commit();
            return $this->ownedPost($enterpriseId, $postId);
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function applications(string $enterpriseId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT ia.id, ia.postId, ia.studentId, ia.status, ia.message, ia.reviewerNote,
                   ia.reviewedAt, ia.appliedAt, ip.title
            FROM internship_applications ia
            INNER JOIN internship_posts ip ON ip.id = ia.postId
            WHERE ip.enterpriseId = :enterpriseId
            ORDER BY ia.appliedAt DESC, ia.id
        SQL);
        $statement->execute(['enterpriseId' => $enterpriseId]);
        return ['items' => $statement->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    public function application(string $enterpriseId, string $applicationId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT ia.id, ia.postId, ia.studentId, ia.status, ia.message, ia.reviewerNote,
                   ia.reviewedAt, ia.appliedAt, ip.title, aps.schemaVersion, aps.snapshotPayload,
                   aps.createdAt AS snapshotCreatedAt
            FROM internship_applications ia
            INNER JOIN internship_posts ip ON ip.id = ia.postId
            INNER JOIN application_profile_snapshots aps ON aps.applicationId = ia.id
            WHERE ia.id = :applicationId AND ip.enterpriseId = :enterpriseId
            LIMIT 1
        SQL);
        $statement->execute(['applicationId' => $applicationId, 'enterpriseId' => $enterpriseId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ ứng tuyển.');
        }
        $row['snapshot'] = json_decode((string) $row['snapshotPayload'], true, 512, JSON_THROW_ON_ERROR);
        unset($row['snapshotPayload']);
        $row['history'] = $this->history($applicationId);
        return $row;
    }

    public function review(string $enterpriseId, string $userId, string $applicationId, string $expectedStatus, string $targetStatus, string $reviewerNote): array
    {
        $this->pdo->beginTransaction();
        try {
            $application = $this->lockOwnedApplication($enterpriseId, $applicationId);
            if ($application === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ ứng tuyển.');
            }
            $current = (string) $application['status'];
            if ($current !== $expectedStatus) {
                throw new ApiException(409, 'CONCURRENT_MODIFICATION', 'Trạng thái hồ sơ đã thay đổi.');
            }
            $allowed = [
                'submitted' => ['reviewing', 'declined'],
                'reviewing' => ['interview', 'accepted', 'declined'],
                'interview' => ['accepted', 'declined'],
            ];
            if (!in_array($targetStatus, $allowed[$current] ?? [], true)) {
                throw new ApiException(422, 'ILLEGAL_STATUS_TRANSITION', 'Chuyển trạng thái hồ sơ không hợp lệ.');
            }
            $now = $this->now();
            $update = $this->pdo->prepare(<<<'SQL'
                UPDATE internship_applications
                SET status = :targetStatus, reviewerNote = :reviewerNote, reviewedAt = :reviewedAt,
                    reviewedBy = :reviewedBy, updatedAt = :updatedAt
                WHERE id = :id AND status = :expectedStatus
            SQL);
            $update->execute(['targetStatus' => $targetStatus, 'reviewerNote' => $reviewerNote === '' ? null : $reviewerNote, 'reviewedAt' => $now, 'reviewedBy' => $userId, 'updatedAt' => $now, 'id' => $applicationId, 'expectedStatus' => $expectedStatus]);
            if ($update->rowCount() !== 1) {
                throw new ApiException(409, 'CONCURRENT_MODIFICATION', 'Trạng thái hồ sơ đã thay đổi.');
            }
            $history = $this->pdo->prepare('INSERT INTO application_status_history (id, applicationId, fromStatus, toStatus, changedByUserId, changedByRole, note, createdAt) VALUES (:id, :applicationId, :fromStatus, :toStatus, :changedByUserId, \'enterprise\', :note, :createdAt)');
            $history->execute(['id' => Uuid::v4(), 'applicationId' => $applicationId, 'fromStatus' => $expectedStatus, 'toStatus' => $targetStatus, 'changedByUserId' => $userId, 'note' => $reviewerNote === '' ? null : $reviewerNote, 'createdAt' => $now]);

            $studentId = (string) ($application['studentId'] ?? '');
            if ($studentId === '') {
                throw new \RuntimeException('Application is missing its notification recipient.');
            }
            $studentUserId = $this->userIdForStudent($studentId);
            $this->getNotificationService()->publish(
                $studentUserId,
                'internship_application_status_changed',
                'Cập nhật trạng thái ứng tuyển',
                'Hồ sơ ứng tuyển cho vị trí ' . ($application['title'] ?? '') . ' của bạn đã chuyển sang trạng thái ' . $targetStatus . '.',
                '/app/learner/ecosystem.php',
                'internship_application_status:' . $applicationId . ':' . $targetStatus,
                $studentId
            );

            $this->pdo->commit();
            return $this->application($enterpriseId, $applicationId);
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function ownedPost(string $enterpriseId, string $postId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM internship_posts WHERE id = :id AND enterpriseId = :enterpriseId LIMIT 1');
        $statement->execute(['id' => $postId, 'enterpriseId' => $enterpriseId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) { throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy tin tuyển dụng.'); }
        return $row;
    }

    private function lockOwnedPost(string $enterpriseId, string $postId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM internship_posts WHERE id = :id AND enterpriseId = :enterpriseId LIMIT 1' . $this->lockSuffix());
        $statement->execute(['id' => $postId, 'enterpriseId' => $enterpriseId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockOwnedApplication(string $enterpriseId, string $applicationId): ?array
    {
        $statement = $this->pdo->prepare('SELECT ia.id, ia.studentId, ia.status, ip.title FROM internship_applications ia INNER JOIN internship_posts ip ON ip.id = ia.postId WHERE ia.id = :id AND ip.enterpriseId = :enterpriseId LIMIT 1' . $this->lockSuffix());
        $statement->execute(['id' => $applicationId, 'enterpriseId' => $enterpriseId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function history(string $applicationId): array
    {
        $statement = $this->pdo->prepare('SELECT fromStatus, toStatus, changedByRole, note, createdAt FROM application_status_history WHERE applicationId = :applicationId ORDER BY createdAt, id');
        $statement->execute(['applicationId' => $applicationId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function now(): string { return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'); }
    private function rollback(): void { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } }
    private function lockSuffix(): string { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE'; }


    private function getNotificationService(): NotificationService
    {
        if (!class_exists('TalentHub\Learner\Data\Service\NotificationService', false)) {
            require_once dirname(__DIR__, 4) . '/app/learner/data/Contracts/NotificationRepository.php';
            require_once dirname(__DIR__, 4) . '/app/learner/data/Service/NotificationService.php';
            require_once dirname(__DIR__, 4) . '/app/learner/data/Database/DatabaseNotificationRepository.php';
        }
        return $this->notifications ?? new NotificationService(new DatabaseNotificationRepository($this->pdo));
    }


    private function userIdForStudent(string $studentId): string
    {
        $stmt = $this->pdo->prepare('SELECT userId FROM student_profiles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $studentId]);
        $userId = $stmt->fetchColumn();
        if (!is_string($userId) || $userId === '') {
            throw new \RuntimeException('Notification recipient is missing for the internship application.');
        }
        return $userId;
    }
}
