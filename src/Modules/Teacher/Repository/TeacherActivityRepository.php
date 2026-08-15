<?php
declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Repository;

use PDO;

final class TeacherActivityRepository
{
    /** @var array<string,string> */
    private const STATUS_TRANSITIONS = [
        'draft' => 'published',
        'published' => 'ongoing',
        'ongoing' => 'completed',
        'completed' => 'archived',
    ];

    public function __construct(private readonly PDO $pdo) {}

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
}
