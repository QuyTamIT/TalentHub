<?php
declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Repository;

use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;

final class TeacherActivityRepository
{
    /** @var array<string,string> */
    private const STATUS_TRANSITIONS = [
        'draft' => 'published',
        'published' => 'ongoing',
        'ongoing' => 'completed',
        'completed' => 'archived',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?NotificationService $notifications = null
    ) {}

    public function teacherIdForUser(string $userId): ?string
    {
        $statement = $this->pdo->prepare('SELECT id FROM teacher_profiles WHERE userId = :userId LIMIT 1');
        $statement->execute(['userId' => $userId]);
        $teacherId = $statement->fetchColumn();

        return $teacherId === false ? null : (string) $teacherId;
    }

    /** @return list<array<string,mixed>> */
    public function list(string $teacherId, string $search = ''): array
    {
        $sql = "
            SELECT
                a.id,
                a.title,
                a.category,
                a.startAt,
                a.endAt,
                a.capacity,
                a.status,
                (
                    SELECT COUNT(*)
                    FROM activity_registrations ar
                    WHERE ar.activityId = a.id
                      AND ar.status IN ('approved', 'attended')
                ) AS registered_count
            FROM activities a
            WHERE a.createdByTeacherId = :teacherId
        ";
        $params = ['teacherId' => $teacherId];

        if ($search !== '') {
            $sql .= ' AND LOWER(a.title) LIKE :search';
            $params['search'] = '%' . mb_strtolower($search) . '%';
        }

        $sql .= ' ORDER BY a.startAt DESC, a.title ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(string $teacherId, string $activityId): ?array
    {
        $statement = $this->pdo->prepare("
            SELECT
                a.id,
                a.title,
                a.category,
                a.startAt,
                a.endAt,
                a.capacity,
                a.status,
                (
                    SELECT COUNT(*)
                    FROM activity_registrations ar
                    WHERE ar.activityId = a.id
                      AND ar.status IN ('approved', 'attended')
                ) AS registered_count
            FROM activities a
            WHERE a.createdByTeacherId = :teacherId
              AND a.id = :activityId
            LIMIT 1
        ");
        $statement->execute(['teacherId' => $teacherId, 'activityId' => $activityId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function registrations(string $teacherId, string $activityId): array
    {
        $statement = $this->pdo->prepare("
            SELECT
                ar.id,
                ar.status,
                u.fullName AS student_name,
                u.email AS student_email
            FROM activity_registrations ar
            INNER JOIN activities a ON a.id = ar.activityId
            INNER JOIN student_profiles sp ON sp.id = ar.studentId
            INNER JOIN users u ON u.id = sp.userId
            WHERE a.createdByTeacherId = :teacherId
              AND ar.activityId = :activityId
            ORDER BY u.fullName ASC
        ");
        $statement->execute(['teacherId' => $teacherId, 'activityId' => $activityId]);

        return $statement->fetchAll();
    }

    /** @param array{title:string,category:string,startAt:string,endAt:string,capacity:int} $data */
    public function create(string $teacherId, string $schoolId, string $activityId, array $data): void
    {
        $statement = $this->pdo->prepare("
            INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status)
            VALUES (:id, :schoolId, :teacherId, :title, :category, :startAt, :endAt, :capacity, 'draft')
        ");
        $statement->execute([
            'id' => $activityId,
            'schoolId' => $schoolId,
            'teacherId' => $teacherId,
            'title' => $data['title'],
            'category' => $data['category'],
            'startAt' => $data['startAt'],
            'endAt' => $data['endAt'],
            'capacity' => $data['capacity'],
        ]);
    }

    /** @param array{title:string,category:string,startAt:string,endAt:string,capacity:int} $data */
    public function update(string $teacherId, string $activityId, array $data): bool
    {
        $statement = $this->pdo->prepare("
            UPDATE activities
            SET title = :title,
                category = :category,
                startAt = :startAt,
                endAt = :endAt,
                capacity = :capacity
            WHERE id = :activityId
              AND createdByTeacherId = :teacherId
        ");
        $statement->execute([
            'title' => $data['title'],
            'category' => $data['category'],
            'startAt' => $data['startAt'],
            'endAt' => $data['endAt'],
            'capacity' => $data['capacity'],
            'activityId' => $activityId,
            'teacherId' => $teacherId,
        ]);

        return $this->find($teacherId, $activityId) !== null;
    }

    public function advanceStatus(string $teacherId, string $activityId, string $expectedStatus, string $nextStatus): bool
    {
        if ((self::STATUS_TRANSITIONS[$expectedStatus] ?? null) !== $nextStatus) {
            throw new \InvalidArgumentException('Invalid activity status transition.');
        }

        $statement = $this->pdo->prepare("
            UPDATE activities
            SET status = :nextStatus
            WHERE id = :activityId
              AND createdByTeacherId = :teacherId
              AND status = :expectedStatus
        ");
        $statement->execute([
            'nextStatus' => $nextStatus,
            'activityId' => $activityId,
            'teacherId' => $teacherId,
            'expectedStatus' => $expectedStatus,
        ]);

        return $statement->rowCount() === 1;
    }

    /** @return array{id:string,activityId:string,status:string,updatedAt:string} */
    public function transitionRegistration(
        string $teacherId,
        string $actorUserId,
        string $requestId,
        string $activityId,
        string $registrationId,
        string $expectedStatus,
        string $nextStatus,
    ): array {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $lock = $this->lockSuffix();
            $activity = $this->pdo->prepare(
                "SELECT id,title,capacity FROM activities WHERE id=:activityId AND createdByTeacherId=:teacherId{$lock}"
            );
            $activity->execute(['activityId' => $activityId, 'teacherId' => $teacherId]);
            $activityRow = $activity->fetch();
            if (!is_array($activityRow)) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hoạt động thuộc giáo viên này.');
            }

            $registration = $this->pdo->prepare(
                "SELECT id,studentId,status FROM activity_registrations WHERE id=:registrationId AND activityId=:activityId{$lock}"
            );
            $registration->execute(['registrationId' => $registrationId, 'activityId' => $activityId]);
            $registrationRow = $registration->fetch();
            if (!is_array($registrationRow)) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy đăng ký thuộc hoạt động này.');
            }
            if ((string) $registrationRow['status'] !== $expectedStatus) {
                throw new ApiException(409, 'STATUS_CONFLICT', 'Đăng ký đã được xử lý hoặc trạng thái đã thay đổi.');
            }

            if ($nextStatus === 'approved') {
                $occupied = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM activity_registrations WHERE activityId=:activityId AND status IN ('approved','attended')"
                );
                $occupied->execute(['activityId' => $activityId]);
                if ((int) $occupied->fetchColumn() >= (int) $activityRow['capacity']) {
                    throw new ApiException(409, 'CAPACITY_REACHED', 'Hoạt động đã đủ chỗ.');
                }
            }

            $updatedAt = gmdate('Y-m-d H:i:s');
            $update = $this->pdo->prepare(
                'UPDATE activity_registrations SET status=:nextStatus,updatedAt=:updatedAt '
                . 'WHERE id=:registrationId AND activityId=:activityId AND status=:expectedStatus'
            );
            $update->execute([
                'nextStatus' => $nextStatus,
                'updatedAt' => $updatedAt,
                'registrationId' => $registrationId,
                'activityId' => $activityId,
                'expectedStatus' => $expectedStatus,
            ]);
            if ($update->rowCount() !== 1) {
                throw new ApiException(409, 'STATUS_CONFLICT', 'Đăng ký đã được xử lý hoặc trạng thái đã thay đổi.');
            }

            $audit = $this->pdo->prepare(
                'INSERT INTO audit_logs (id,userId,action,entityType,entityId,requestId,ipAddress,metadata,createdAt) '
                . 'VALUES (:id,:userId,:action,:entityType,:entityId,:requestId,NULL,:metadata,:createdAt)'
            );
            $audit->execute([
                'id' => Uuid::v4(),
                'userId' => $actorUserId,
                'action' => 'activity_registration.' . $nextStatus,
                'entityType' => 'activity_registration',
                'entityId' => $registrationId,
                'requestId' => $requestId,
                'metadata' => json_encode([
                    'activityId' => $activityId,
                    'previousStatus' => $expectedStatus,
                    'status' => $nextStatus,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'createdAt' => $updatedAt,
            ]);

            $studentId = (string) $registrationRow['studentId'];
            $studentUserId = $this->userIdForStudent($studentId);
            if ($nextStatus === 'approved') {
                $this->getNotificationService()->publish(
                    $studentUserId,
                    'activity_registration_approved',
                    'Đăng ký hoạt động được phê duyệt',
                    'Đăng ký tham gia hoạt động ' . ($activityRow['title'] ?? '') . ' của bạn đã được giáo viên phê duyệt.',
                    '/app/learner/my-activities.php',
                    'activity_registration_approved:' . $registrationId,
                    $studentId
                );
            } elseif ($nextStatus === 'rejected') {
                $this->getNotificationService()->publish(
                    $studentUserId,
                    'activity_registration_rejected',
                    'Đăng ký hoạt động bị từ chối',
                    'Đăng ký tham gia hoạt động ' . ($activityRow['title'] ?? '') . ' của bạn không được phê duyệt.',
                    '/app/learner/my-activities.php',
                    'activity_registration_rejected:' . $registrationId,
                    $studentId
                );
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return [
                'id' => $registrationId,
                'activityId' => $activityId,
                'status' => $nextStatus,
                'updatedAt' => $updatedAt,
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function lockSuffix(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

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
            throw new \RuntimeException('Notification recipient is missing for the managed registration.');
        }
        return $userId;
    }
}
