<?php
declare(strict_types=1);

namespace TalentHub\Modules\Student\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Domain\Activity\ActivityPolicy;
use TalentHub\Domain\Activity\CatalogBucket;
use TalentHub\Http\ApiException;
use TalentHub\Support\Clock\ClockInterface;
use TalentHub\Support\Uuid;

/**
 * Read-only catalog for students. Replaces the inline bucket logic that used
 * to live in the UI pages and the AI sources. Front-end renders, never re-
 * derives the rule.
 */
final class StudentActivityService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ClockInterface $clock,
        private readonly ActivityPolicy $policy,
    ) {}

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

    /**
     * Categorise each row of the catalog by the student's view: open/upcoming/
     * full/closed/enrolled/pending/waitlisted/cancelled/rejected.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public function withCatalogBuckets(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $regPolicy = $this->loadRegistrationPolicy((string) ($row['id'] ?? ''));
            $myRegistration = $this->loadMyRegistration((string) ($row['id'] ?? ''), (string) ($row['_studentId'] ?? ''));
            $approvedCount = (int) ($row['total_registered'] ?? 0);
            $capacity = isset($row['capacity']) ? (int) $row['capacity'] : null;
            $row['bucket'] = $this->policy->catalogBucket($row, $regPolicy, $myRegistration, $approvedCount, $capacity);
            $grouped[] = $row;
        }
        return $grouped;
    }

    /** @return array<string,mixed> */
    private function loadRegistrationPolicy(string $activityId): array
    {
        if ($activityId === '') return [];
        $stmt = $this->pdo->prepare(
            'SELECT activityId, registrationOpensAt, registrationClosesAt, cancellationClosesAt, approvalMode '
            . 'FROM activity_registration_policies WHERE activityId = :id LIMIT 1'
        );
        $stmt->execute(['id' => $activityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /** @return array<string,mixed>|null */
    private function loadMyRegistration(string $activityId, string $studentId): ?array
    {
        if ($activityId === '' || $studentId === '') return null;
        $stmt = $this->pdo->prepare(
            'SELECT id, activityId, studentId, status FROM activity_registrations '
            . 'WHERE activityId = :aid AND studentId = :sid ORDER BY registeredAt DESC LIMIT 1'
        );
        $stmt->execute(['aid' => $activityId, 'sid' => $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
