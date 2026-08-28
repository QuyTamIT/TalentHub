<?php
declare(strict_types=1);

namespace TalentHub\Modules\Student\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;

final class StudentActivityService
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Get school ID and class ID for the given student profile or user.
     * @return array{studentId: string, schoolId: string|null, classId: string|null}
     */
    public function resolveStudentScope(string $studentOrUserId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sp.id AS studentId, sp.classId, c.schoolId
            FROM student_profiles sp
            LEFT JOIN classes c ON c.id = sp.classId
            WHERE sp.id = :id1 OR sp.userId = :id2
            LIMIT 1
        ");
        $stmt->execute(['id1' => $studentOrUserId, 'id2' => $studentOrUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'studentId' => $studentOrUserId,
                'schoolId' => null,
                'classId' => null,
            ];
        }

        return [
            'studentId' => (string) $row['studentId'],
            'schoolId' => $row['schoolId'] ? (string) $row['schoolId'] : null,
            'classId' => $row['classId'] ? (string) $row['classId'] : null,
        ];
    }

    /**
     * Discover active & eligible activities for student.
     *
     * @return list<array<string,mixed>>
     */
    public function discover(string $studentOrUserId, ?string $category = null, string $search = ''): array
    {
        $scope = $this->resolveStudentScope($studentOrUserId);
        $schoolId = $scope['schoolId'];

        $sql = "
            SELECT 
                a.id,
                a.schoolId,
                a.createdByTeacherId,
                a.title,
                a.category,
                a.startAt,
                a.endAt,
                a.capacity,
                a.status,
                s.name AS schoolName,
                u.fullName AS responsibleTeacherName,
                COUNT(r.id) AS total_registered,
                MAX(CASE WHEN r.studentId = :studentId AND r.status IN ('pending', 'approved', 'attended') THEN 1 ELSE 0 END) AS is_registered
            FROM activities a
            LEFT JOIN schools s ON s.id = a.schoolId
            LEFT JOIN teacher_profiles tp ON tp.id = a.createdByTeacherId
            LEFT JOIN users u ON u.id = tp.userId
            LEFT JOIN activity_registrations r ON r.activityId = a.id AND r.status IN ('pending', 'approved', 'attended')
            WHERE (a.schoolId = :schoolId OR a.schoolId IS NULL OR :schoolIdNull = 1)
              AND a.status IN ('published', 'open', 'ongoing')
              AND COALESCE(a.endAt, a.startAt) >= NOW()
        ";

        $params = [
            'studentId' => $scope['studentId'],
            'schoolId' => $schoolId ?? '',
            'schoolIdNull' => $schoolId === null ? 1 : 0,
        ];

        if ($category !== null && trim($category) !== '' && $category !== 'Tất cả') {
            $sql .= " AND (a.category = :category OR a.category LIKE :categoryLike)";
            $params['category'] = $category;
            $params['categoryLike'] = '%' . $category . '%';
        }

        if (trim($search) !== '') {
            $sql .= " AND (a.title LIKE :search OR a.category LIKE :search2 OR s.name LIKE :search3)";
            $params['search'] = '%' . trim($search) . '%';
            $params['search2'] = '%' . trim($search) . '%';
            $params['search3'] = '%' . trim($search) . '%';
        }

        $sql .= "
            GROUP BY a.id
            ORDER BY a.startAt ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find single activity detail for student.
     * @return array<string,mixed>|null
     */
    public function find(string $studentOrUserId, string $activityId): ?array
    {
        $scope = $this->resolveStudentScope($studentOrUserId);
        $schoolId = $scope['schoolId'];

        $sql = "
            SELECT 
                a.id,
                a.schoolId,
                a.createdByTeacherId,
                a.title,
                a.category,
                a.startAt,
                a.endAt,
                a.capacity,
                a.status,
                s.name AS schoolName,
                u.fullName AS responsibleTeacherName,
                COUNT(r.id) AS total_registered
            FROM activities a
            LEFT JOIN schools s ON s.id = a.schoolId
            LEFT JOIN teacher_profiles tp ON tp.id = a.createdByTeacherId
            LEFT JOIN users u ON u.id = tp.userId
            LEFT JOIN activity_registrations r ON r.activityId = a.id AND r.status IN ('pending', 'approved', 'attended')
            WHERE a.id = :activityId
              AND (a.schoolId = :schoolId OR a.schoolId IS NULL OR :schoolIdNull = 1)
              AND a.status IN ('published', 'open', 'ongoing', 'completed')
            GROUP BY a.id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'activityId' => $activityId,
            'schoolId' => $schoolId ?? '',
            'schoolIdNull' => $schoolId === null ? 1 : 0,
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
