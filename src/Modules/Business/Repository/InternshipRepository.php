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
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $hasTargetTable = $this->tableExists('internship_post_target_schools');
        $items = [];
        foreach ($rows as $row) {
            $row['audience'] = (string) ($row['audience'] ?? 'public');
            $row['targetSchoolIds'] = [];
            if ($hasTargetTable && $row['audience'] === 'partner_schools') {
                $stmtSchools = $this->pdo->prepare('SELECT schoolId FROM internship_post_target_schools WHERE postId = ?');
                $stmtSchools->execute([$row['id']]);
                $row['targetSchoolIds'] = $stmtSchools->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }
            $items[] = $row;
        }

        return ['items' => $items];
    }

    public function post(string $enterpriseId, string $postId): array
    {
        return $this->ownedPost($enterpriseId, $postId);
    }

    public function createPost(string $enterpriseId, array $fields): array
    {
        $id = Uuid::v4();
        $now = $this->now();

        $audience = (string) ($fields['audience'] ?? 'public');
        if (!in_array($audience, ['public', 'partner_schools'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'audience không hợp lệ.');
        }

        $targetSchoolIds = isset($fields['targetSchoolIds']) && is_array($fields['targetSchoolIds'])
            ? array_values(array_unique(array_filter($fields['targetSchoolIds'], 'is_string')))
            : [];

        unset($fields['targetSchoolIds'], $fields['audience']);

        if ($audience === 'partner_schools') {
            if (empty($targetSchoolIds)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Vui lòng chọn ít nhất 1 trường đối tác.');
            }
            $this->assertApprovedPartnerSchools($enterpriseId, $targetSchoolIds);
        }

        $this->pdo->beginTransaction();
        try {
            $hasAudienceCol = $this->hasColumn('internship_posts', 'audience');
            $insertData = [
                'id' => $id,
                'enterpriseId' => $enterpriseId,
                'title' => (string) ($fields['title'] ?? ''),
                'field' => (string) ($fields['field'] ?? 'IT'),
                'location' => (string) ($fields['location'] ?? ''),
                'workType' => (string) ($fields['workType'] ?? 'Full-time / Hybrid'),
                'duration' => (string) ($fields['duration'] ?? '3 tháng'),
                'educationLevel' => (string) ($fields['educationLevel'] ?? 'Đại học / Cao đẳng'),
                'description' => (string) ($fields['description'] ?? ''),
                'benefits' => isset($fields['benefits']) && $fields['benefits'] !== '' ? (string) $fields['benefits'] : null,
                'skillsJson' => (string) ($fields['skillsJson'] ?? '[]'),
                'requirementsJson' => isset($fields['requirementsJson']) && $fields['requirementsJson'] !== '' ? (string) $fields['requirementsJson'] : null,
                'slots' => (int) ($fields['slots'] ?? 1),
                'deadline' => (string) ($fields['deadline'] ?? $now),
                'createdAt' => $now,
                'updatedAt' => $now,
            ];

            if ($hasAudienceCol) {
                $statement = $this->pdo->prepare(<<<'SQL'
                    INSERT INTO internship_posts
                        (id, enterpriseId, title, field, status, audience, location, workType, duration, educationLevel,
                         description, benefits, skillsJson, requirementsJson, slots, deadline, createdAt, updatedAt)
                    VALUES
                        (:id, :enterpriseId, :title, :field, 'draft', :audience, :location, :workType, :duration, :educationLevel,
                         :description, :benefits, :skillsJson, :requirementsJson, :slots, :deadline, :createdAt, :updatedAt)
                SQL);
                $statement->execute($insertData + ['audience' => $audience]);
            } else {
                $statement = $this->pdo->prepare(<<<'SQL'
                    INSERT INTO internship_posts
                        (id, enterpriseId, title, field, status, location, workType, duration, educationLevel,
                         description, benefits, skillsJson, requirementsJson, slots, deadline, createdAt, updatedAt)
                    VALUES
                        (:id, :enterpriseId, :title, :field, 'draft', :location, :workType, :duration, :educationLevel,
                         :description, :benefits, :skillsJson, :requirementsJson, :slots, :deadline, :createdAt, :updatedAt)
                SQL);
                $statement->execute($insertData);
            }

            if ($audience === 'partner_schools' && $this->tableExists('internship_post_target_schools')) {
                $stmtTarget = $this->pdo->prepare(<<<'SQL'
                    INSERT INTO internship_post_target_schools (postId, schoolId, createdAt)
                    VALUES (:postId, :schoolId, :now)
                SQL);
                foreach ($targetSchoolIds as $schoolId) {
                    $stmtTarget->execute([
                        'postId' => $id,
                        'schoolId' => $schoolId,
                        'now' => $now,
                    ]);
                }
            }

            $this->pdo->commit();
            return $this->ownedPost($enterpriseId, $id);
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
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

            $audience = isset($fields['audience']) ? (string) $fields['audience'] : null;
            if ($audience !== null && !in_array($audience, ['public', 'partner_schools'], true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'audience không hợp lệ.');
            }

            $hasTargetIds = array_key_exists('targetSchoolIds', $fields);
            $targetSchoolIds = $hasTargetIds && is_array($fields['targetSchoolIds'])
                ? array_values(array_unique(array_filter($fields['targetSchoolIds'], 'is_string')))
                : [];

            unset($fields['targetSchoolIds']);

            $effectiveAudience = $audience ?? (string) ($post['audience'] ?? 'public');
            if ($effectiveAudience === 'partner_schools' && $hasTargetIds) {
                if (empty($targetSchoolIds)) {
                    throw new ApiException(422, 'VALIDATION_FAILED', 'Vui lòng chọn ít nhất 1 trường đối tác.');
                }
                $this->assertApprovedPartnerSchools($enterpriseId, $targetSchoolIds);
            }

            $now = $this->now();
            if ($fields !== []) {
                $sets = [];
                $parameters = ['id' => $postId, 'enterpriseId' => $enterpriseId, 'updatedAt' => $now];
                foreach ($fields as $field => $value) {
                    $sets[] = "{$field} = :{$field}";
                    $parameters[$field] = $value;
                }
                $sets[] = 'updatedAt = :updatedAt';
                $statement = $this->pdo->prepare('UPDATE internship_posts SET ' . implode(', ', $sets) . ' WHERE id = :id AND enterpriseId = :enterpriseId');
                $statement->execute($parameters);
            }

            if ($this->tableExists('internship_post_target_schools') && ($hasTargetIds || $audience !== null)) {
                $del = $this->pdo->prepare('DELETE FROM internship_post_target_schools WHERE postId = ?');
                $del->execute([$postId]);

                if ($effectiveAudience === 'partner_schools' && !empty($targetSchoolIds)) {
                    $stmtTarget = $this->pdo->prepare(<<<'SQL'
                        INSERT INTO internship_post_target_schools (postId, schoolId, createdAt)
                        VALUES (:postId, :schoolId, :now)
                    SQL);
                    foreach ($targetSchoolIds as $schoolId) {
                        $stmtTarget->execute([
                            'postId' => $postId,
                            'schoolId' => $schoolId,
                            'now' => $now,
                        ]);
                    }
                }
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
        $hasLocks = $this->tableExists('internship_application_locks');
        $lockSelect = $hasLocks
            ? ', placementLock.lockedByApplicationId, placementLock.reason AS lockReason, placementLock.lockedAt'
            : ', NULL AS lockedByApplicationId, NULL AS lockReason, NULL AS lockedAt';
        $lockJoin = $hasLocks
            ? ' LEFT JOIN internship_application_locks placementLock ON placementLock.applicationId = ia.id'
            : '';
        $statement = $this->pdo->prepare(<<<SQL
            SELECT ia.*, ip.title AS postTitle, u.fullName AS studentName, u.email AS studentEmail{$lockSelect}
            FROM internship_applications ia
            INNER JOIN internship_posts ip ON ip.id = ia.postId
            INNER JOIN student_profiles sp ON sp.id = ia.studentId
            INNER JOIN users u ON u.id = sp.userId
            {$lockJoin}
            WHERE ip.enterpriseId = :enterpriseId
            ORDER BY ia.createdAt DESC, ia.id
        SQL);
        $statement->execute(['enterpriseId' => $enterpriseId]);
        return ['items' => $statement->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    public function application(string $enterpriseId, string $applicationId): array
    {
        $hasSnap = $this->tableExists('application_profile_snapshots');
        $hasLocks = $this->tableExists('internship_application_locks');
        $lockSelect = $hasLocks
            ? ', placementLock.lockedByApplicationId, placementLock.reason AS lockReason, placementLock.lockedAt'
            : ', NULL AS lockedByApplicationId, NULL AS lockReason, NULL AS lockedAt';
        $lockJoin = $hasLocks
            ? ' LEFT JOIN internship_application_locks placementLock ON placementLock.applicationId = ia.id'
            : '';
        if ($hasSnap) {
            $statement = $this->pdo->prepare(<<<SQL
                SELECT ia.id, ia.postId, ia.studentId, ia.status, ia.message, ia.reviewerNote,
                       ia.reviewedAt, ia.appliedAt, ip.title, aps.schemaVersion, aps.snapshotPayload,
                       aps.createdAt AS snapshotCreatedAt{$lockSelect}
                FROM internship_applications ia
                INNER JOIN internship_posts ip ON ip.id = ia.postId
                LEFT JOIN application_profile_snapshots aps ON aps.applicationId = ia.id
                {$lockJoin}
                WHERE ia.id = :applicationId AND ip.enterpriseId = :enterpriseId
                LIMIT 1
            SQL);
            $statement->execute(['applicationId' => $applicationId, 'enterpriseId' => $enterpriseId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ ứng tuyển.');
            }
            if (!empty($row['snapshotPayload'])) {
                $row['snapshot'] = json_decode((string) $row['snapshotPayload'], true, 512, JSON_THROW_ON_ERROR);
            }
            unset($row['snapshotPayload']);
            $row['history'] = $this->history($applicationId);
            return $row;
        }

        $statement = $this->pdo->prepare(<<<SQL
            SELECT ia.*, ip.title AS postTitle, u.fullName AS studentName, u.email AS studentEmail{$lockSelect}
            FROM internship_applications ia
            INNER JOIN internship_posts ip ON ip.id = ia.postId
            INNER JOIN student_profiles sp ON sp.id = ia.studentId
            INNER JOIN users u ON u.id = sp.userId
            {$lockJoin}
            WHERE ia.id = :id AND ip.enterpriseId = :enterpriseId
            LIMIT 1
        SQL);
        $statement->execute(['id' => $applicationId, 'enterpriseId' => $enterpriseId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ ứng tuyển.');
        }
        $row['history'] = $this->history($applicationId);
        return $row;
    }

    public function recordApplicationProfileAccess(
        string $enterpriseId,
        string $userId,
        string $studentId,
        string $applicationId,
        ?string $requestId = null,
        ?string $ipAddress = null
    ): void {
        if (!$this->tableExists('student_profile_access_logs')) {
            return;
        }
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO student_profile_access_logs
                (id, enterpriseId, studentId, accessedByUserId, accessType, requestId, ipAddress, metadata, accessedAt)
            VALUES
                (:id, :enterpriseId, :studentId, :userId, 'application_cv', :requestId, :ipAddress, :metadata, :accessedAt)
        SQL);
        $statement->execute([
            'id' => Uuid::v4(),
            'enterpriseId' => $enterpriseId,
            'studentId' => $studentId,
            'userId' => $userId,
            'requestId' => $requestId,
            'ipAddress' => $ipAddress,
            'metadata' => json_encode([
                'source' => 'internship_application_detail',
                'applicationId' => $applicationId,
            ], JSON_THROW_ON_ERROR),
            'accessedAt' => $this->now(),
        ]);
    }

    public function review(string $enterpriseId, string $userId, string $applicationId, string $expectedStatus, string $targetStatus, string $reviewerNote): array
    {
        $this->pdo->beginTransaction();
        try {
            if ($targetStatus === 'accepted') {
                $candidate = $this->ownedApplication($enterpriseId, $applicationId);
                if ($candidate === null) {
                    throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ ứng tuyển.');
                }
                $this->lockStudentPlacement((string) $candidate['studentId']);
            }
            $application = $this->lockOwnedApplication($enterpriseId, $applicationId);
            if ($application === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ ứng tuyển.');
            }
            $placementLock = $this->applicationLock($applicationId);
            if ($placementLock !== null) {
                throw new ApiException(
                    409,
                    'INTERNSHIP_PLACEMENT_LOCKED',
                    (string) ($placementLock['reason'] ?? 'Hồ sơ đã bị khóa vì sinh viên đã xác nhận một vị trí thực tập khác.')
                );
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
            if ($targetStatus === 'accepted' && $this->hasOtherAcceptedPlacement((string) $application['studentId'], $applicationId)) {
                throw new ApiException(409, 'INTERNSHIP_PLACEMENT_LOCKED', 'Sinh viên đã được tiếp nhận cho một vị trí thực tập khác.');
            }
            $now = $this->now();
            $hasRevCols = $this->hasColumn('internship_applications', 'reviewerNote');
            $validReviewerId = $userId;
            if ($this->tableExists('users')) {
                $chkUser = $this->pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
                $chkUser->execute([$userId]);
                $validReviewerId = $chkUser->fetchColumn() ?: null;
                if (!is_string($validReviewerId) || $validReviewerId === '') {
                    throw new ApiException(403, 'PERMISSION_DENIED', 'Người duyệt không hợp lệ.');
                }
            }

            if ($hasRevCols) {
                $update = $this->pdo->prepare(<<<'SQL'
                    UPDATE internship_applications
                    SET status = :targetStatus, reviewerNote = :reviewerNote, reviewedAt = :reviewedAt,
                        reviewedBy = :reviewedBy, updatedAt = :updatedAt
                    WHERE id = :id AND status = :expectedStatus
                SQL);
                $update->execute(['targetStatus' => $targetStatus, 'reviewerNote' => $reviewerNote === '' ? null : $reviewerNote, 'reviewedAt' => $now, 'reviewedBy' => $validReviewerId, 'updatedAt' => $now, 'id' => $applicationId, 'expectedStatus' => $expectedStatus]);
            } else {
                $update = $this->pdo->prepare('UPDATE internship_applications SET status = :targetStatus, updatedAt = :updatedAt WHERE id = :id AND status = :expectedStatus');
                $update->execute(['targetStatus' => $targetStatus, 'updatedAt' => $now, 'id' => $applicationId, 'expectedStatus' => $expectedStatus]);
            }

            if ($update->rowCount() !== 1) {
                throw new ApiException(409, 'CONCURRENT_MODIFICATION', 'Trạng thái hồ sơ đã thay đổi.');
            }
            $history = $this->pdo->prepare('INSERT INTO application_status_history (id, applicationId, fromStatus, toStatus, changedByUserId, changedByRole, note, createdAt) VALUES (:id, :applicationId, :fromStatus, :toStatus, :changedByUserId, \'enterprise\', :note, :createdAt)');
            $history->execute(['id' => Uuid::v4(), 'applicationId' => $applicationId, 'fromStatus' => $expectedStatus, 'toStatus' => $targetStatus, 'changedByUserId' => $validReviewerId, 'note' => $reviewerNote === '' ? null : $reviewerNote, 'createdAt' => $now]);

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

            if ($targetStatus === 'accepted') {
                $this->lockCompetingApplications($studentId, $applicationId, $now);
                $this->notifySchoolPlacement($applicationId, $studentId, (string) ($application['title'] ?? 'Vị trí thực tập'));
            }

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

        $row['audience'] = (string) ($row['audience'] ?? 'public');
        $row['targetSchoolIds'] = [];
        if ($this->tableExists('internship_post_target_schools') && $row['audience'] === 'partner_schools') {
            $stmtSchools = $this->pdo->prepare('SELECT schoolId FROM internship_post_target_schools WHERE postId = ?');
            $stmtSchools->execute([$postId]);
            $row['targetSchoolIds'] = $stmtSchools->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

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
        $statement = $this->pdo->prepare('SELECT ia.id, ia.postId, ia.studentId, ia.status, ip.title FROM internship_applications ia INNER JOIN internship_posts ip ON ip.id = ia.postId WHERE ia.id = :id AND ip.enterpriseId = :enterpriseId LIMIT 1' . $this->lockSuffix());
        $statement->execute(['id' => $applicationId, 'enterpriseId' => $enterpriseId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    private function ownedApplication(string $enterpriseId, string $applicationId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT ia.id, ia.postId, ia.studentId, ia.status, ip.title '
            . 'FROM internship_applications ia '
            . 'INNER JOIN internship_posts ip ON ip.id = ia.postId '
            . 'WHERE ia.id = :id AND ip.enterpriseId = :enterpriseId LIMIT 1'
        );
        $statement->execute(['id' => $applicationId, 'enterpriseId' => $enterpriseId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockStudentPlacement(string $studentId): void
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM student_profiles WHERE id = :studentId LIMIT 1' . $this->lockSuffix()
        );
        $statement->execute(['studentId' => $studentId]);
        if ($statement->fetchColumn() === false) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ sinh viên.');
        }
    }

    private function hasOtherAcceptedPlacement(string $studentId, string $applicationId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM internship_applications "
            . "WHERE studentId = :studentId AND id <> :applicationId AND status = 'accepted' "
            . 'LIMIT 1' . $this->lockSuffix()
        );
        $statement->execute(['studentId' => $studentId, 'applicationId' => $applicationId]);
        return $statement->fetchColumn() !== false;
    }

    private function history(string $applicationId): array
    {
        $statement = $this->pdo->prepare('SELECT fromStatus, toStatus, changedByRole, note, createdAt FROM application_status_history WHERE applicationId = :applicationId ORDER BY createdAt, id');
        $statement->execute(['applicationId' => $applicationId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function assertApprovedPartnerSchools(string $enterpriseId, array $schoolIds): void
    {
        if (!$this->tableExists('school_enterprise_partnerships')) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($schoolIds), '?'));
        $stmt = $this->pdo->prepare("SELECT schoolId FROM school_enterprise_partnerships WHERE enterpriseId = ? AND status = 'approved' AND schoolId IN ({$placeholders})");
        $stmt->execute(array_merge([$enterpriseId], $schoolIds));
        $approvedIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $diff = array_diff($schoolIds, $approvedIds);
        if (!empty($diff)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Trường đối tác được chọn chưa được phê duyệt quan hệ hợp tác.');
        }
    }

    /** @return array<string,mixed>|null */
    private function applicationLock(string $applicationId): ?array
    {
        if (!$this->tableExists('internship_application_locks')) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT lockedByApplicationId, reason, lockedAt '
            . 'FROM internship_application_locks WHERE applicationId = :applicationId LIMIT 1'
            . $this->lockSuffix()
        );
        $statement->execute(['applicationId' => $applicationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockCompetingApplications(string $studentId, string $placementApplicationId, string $now): void
    {
        if (!$this->tableExists('internship_application_locks')) {
            return;
        }
        $statement = $this->pdo->prepare(
            "SELECT id FROM internship_applications "
            . "WHERE studentId = :studentId AND id <> :placementId "
            . "AND status IN ('submitted','reviewing','interview')" . $this->lockSuffix()
        );
        $statement->execute(['studentId' => $studentId, 'placementId' => $placementApplicationId]);
        $ids = $statement->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $reason = 'Sinh viên đã xác nhận một vị trí thực tập khác.';
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO internship_application_locks (applicationId, lockedByApplicationId, reason, lockedAt) VALUES (:applicationId, :placementId, :reason, :lockedAt)'
            : 'INSERT IGNORE INTO internship_application_locks (applicationId, lockedByApplicationId, reason, lockedAt) VALUES (:applicationId, :placementId, :reason, :lockedAt)';
        $insert = $this->pdo->prepare($sql);
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            $insert->execute([
                'applicationId' => $id,
                'placementId' => $placementApplicationId,
                'reason' => $reason,
                'lockedAt' => $now,
            ]);
        }
    }

    private function notifySchoolPlacement(string $applicationId, string $studentId, string $postTitle): void
    {
        if (!$this->tableExists('school_members')) {
            return;
        }
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT schoolMember.userId, studentUser.fullName AS studentName
            FROM student_profiles student
            INNER JOIN users studentUser ON studentUser.id = student.userId
            INNER JOIN classes class ON class.id = student.classId
            INNER JOIN school_members schoolMember ON schoolMember.schoolId = class.schoolId
            WHERE student.id = :studentId AND schoolMember.memberRole IN ('admin', 'owner')
        SQL);
        $statement->execute(['studentId' => $studentId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $recipient) {
            $userId = (string) ($recipient['userId'] ?? '');
            if ($userId === '') {
                continue;
            }
            $studentName = (string) ($recipient['studentName'] ?? 'Sinh viên');
            $this->getNotificationService()->publish(
                $userId,
                'internship_placement_confirmed',
                'Sinh viên đã được tiếp nhận thực tập',
                "{$studentName} đã được tiếp nhận cho vị trí {$postTitle}. Vui lòng phân công giảng viên mentor.",
                '/app/school/internships.php',
                "internship_placement:school:{$applicationId}"
            );
        }
    }

    private function hasColumn(string $tableName, string $columnName): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("PRAGMA table_info({$tableName})");
            $stmt->execute();
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($cols as $c) {
                if (($c['name'] ?? '') === $columnName) return true;
            }
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name LIMIT 1');
        $stmt->execute(['table_name' => $tableName, 'column_name' => $columnName]);
        return (bool) $stmt->fetchColumn();
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
