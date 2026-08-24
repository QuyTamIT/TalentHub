<?php
declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Repository;

use PDO;

final class TeacherStudentRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,mixed>|null */
    public function findTeacherByUserId(string $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT tp.id, tp.userId, tp.schoolId, tp.isSchoolAdmin, u.email, u.fullName, s.name AS schoolName
             FROM teacher_profiles tp
             INNER JOIN users u ON u.id = tp.userId
             INNER JOIN schools s ON s.id = tp.schoolId
             WHERE tp.userId = :userId
             LIMIT 1'
        );
        $statement->execute(['userId' => $userId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function activitiesForFilter(string $teacherId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.id, a.title
             FROM activities a
             WHERE a.createdByTeacherId = :teacherId
             ORDER BY a.startAt DESC, a.title ASC'
        );
        $statement->execute(['teacherId' => $teacherId]);

        return $statement->fetchAll();
    }

    /**
     * @param array{search:string,activityId:string,status:string} $filters
     * @return list<array<string,mixed>>
     */
    public function listRegistrations(string $teacherId, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->registrationWhere($teacherId, $filters);

        $sql = "
            SELECT
                ar.id AS registrationId,
                ar.status AS registrationStatus,
                ar.registeredAt,
                a.id AS activityId,
                a.title AS activityTitle,
                a.category AS activityCategory,
                a.startAt AS activityStartAt,
                sp.id AS studentId,
                u.fullName,
                u.email,
                (
                    SELECT COUNT(DISTINCT ar_count.activityId)
                    FROM activity_registrations ar_count
                    INNER JOIN activities a_count ON a_count.id = ar_count.activityId
                    WHERE a_count.createdByTeacherId = :teacherIdForCount
                      AND ar_count.studentId = ar.studentId
                ) AS teacherActivityCount,
                assessment.status AS assessmentStatus,
                assessment.overallScore,
                assessment.updatedAt AS assessmentUpdatedAt
            FROM activity_registrations ar
            INNER JOIN activities a ON a.id = ar.activityId
            INNER JOIN student_profiles sp ON sp.id = ar.studentId
            INNER JOIN users u ON u.id = sp.userId
            LEFT JOIN assessments assessment
              ON assessment.activityId = ar.activityId
             AND assessment.studentId = ar.studentId
             AND assessment.teacherId = :teacherIdForAssessment
            {$where}
            ORDER BY ar.registeredAt DESC, u.fullName ASC, a.startAt DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params + [
            'teacherIdForCount' => $teacherId,
            'teacherIdForAssessment' => $teacherId,
        ]);

        return $statement->fetchAll();
    }

    /** @param array{search:string,activityId:string,status:string} $filters */
    public function countRegistrations(string $teacherId, array $filters): int
    {
        [$where, $params] = $this->registrationWhere($teacherId, $filters);
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM activity_registrations ar
             INNER JOIN activities a ON a.id = ar.activityId
             INNER JOIN student_profiles sp ON sp.id = ar.studentId
             INNER JOIN users u ON u.id = sp.userId
             {$where}"
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** @return array{uniqueStudents:int,totalRegistrations:int,assessedRegistrations:int,pendingRegistrations:int} */
    public function summary(string $teacherId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                COUNT(DISTINCT ar.studentId) AS uniqueStudents,
                COUNT(*) AS totalRegistrations,
                COUNT(DISTINCT assessment.id) AS assessedRegistrations,
                SUM(CASE WHEN ar.status = \'pending\' THEN 1 ELSE 0 END) AS pendingRegistrations
             FROM activity_registrations ar
             INNER JOIN activities a ON a.id = ar.activityId
             LEFT JOIN assessments assessment
               ON assessment.activityId = ar.activityId
              AND assessment.studentId = ar.studentId
              AND assessment.teacherId = :teacherIdForAssessment
             WHERE a.createdByTeacherId = :teacherId'
        );
        $statement->execute([
            'teacherId' => $teacherId,
            'teacherIdForAssessment' => $teacherId,
        ]);
        $row = $statement->fetch() ?: [];

        return [
            'uniqueStudents' => (int) ($row['uniqueStudents'] ?? 0),
            'totalRegistrations' => (int) ($row['totalRegistrations'] ?? 0),
            'assessedRegistrations' => (int) ($row['assessedRegistrations'] ?? 0),
            'pendingRegistrations' => (int) ($row['pendingRegistrations'] ?? 0),
        ];
    }

    /**
     * @param array{search:string,activityId:string,status:string} $filters
     * @return array{0:string,1:array<string,string>}
     */
    private function registrationWhere(string $teacherId, array $filters): array
    {
        $clauses = ['a.createdByTeacherId = :teacherId'];
        $params = ['teacherId' => $teacherId];

        if ($filters['search'] !== '') {
            $clauses[] = '(LOWER(u.fullName) LIKE :search OR LOWER(u.email) LIKE :search)';
            $params['search'] = '%' . mb_strtolower($filters['search']) . '%';
        }

        if ($filters['activityId'] !== '') {
            $clauses[] = 'a.id = :activityId';
            $params['activityId'] = $filters['activityId'];
        }

        if ($filters['status'] !== '') {
            $clauses[] = 'ar.status = :status';
            $params['status'] = $filters['status'];
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }
}
