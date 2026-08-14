<?php
declare(strict_types=1);
namespace TalentHub\Modules\School\Repository;

use PDO;

final class SchoolRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,mixed>|null */
    public function findByUserId(string $userId): ?array
    {
        $sql = 'SELECT s.id, s.name, s.status, s.logoUrl, s.address, s.phone, s.email, s.website,
                       s.level, s.studentCount, s.teacherCount, s.academicYear,
                       s.createdAt, s.updatedAt,
                       sm.memberRole
                FROM school_members sm
                JOIN schools s ON s.id = sm.schoolId
                WHERE sm.userId = :userId
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['userId' => $userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findById(string $schoolId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, status, logoUrl, address, phone, email, website, level,
                    studentCount, teacherCount, academicYear, createdAt, updatedAt
             FROM schools WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $schoolId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function update(string $schoolId, array $fields): void
    {
        $allowed = ['name', 'logoUrl', 'address', 'phone', 'email', 'website', 'level', 'academicYear'];
        $sets = [];
        $params = ['id' => $schoolId];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $sets[] = "{$col} = :{$col}";
                $params[$col] = $fields[$col];
            }
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE schools SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /** @return list<array<string,mixed>> */
    public function listClasses(string $schoolId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, schoolId, name, gradeLevel, academicYear, status,
                    (SELECT COUNT(*) FROM student_profiles sp WHERE sp.classId = c.id AND sp.studyStatus = \'active\') AS studentCount
             FROM classes c
             WHERE c.schoolId = :schoolId AND c.status = \'active\'
             ORDER BY c.gradeLevel ASC, c.name ASC'
        );
        $stmt->execute(['schoolId' => $schoolId]);
        return array_values($stmt->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function listTeachers(string $schoolId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tp.id, tp.userId, tp.isSchoolAdmin, tp.specialization, tp.phone,
                    u.email, u.fullName, u.status AS userStatus
             FROM teacher_profiles tp
             JOIN users u ON u.id = tp.userId
             WHERE tp.schoolId = :schoolId
             ORDER BY u.fullName ASC'
        );
        $stmt->execute(['schoolId' => $schoolId]);
        return array_values($stmt->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function listStudents(string $schoolId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sp.id, sp.userId, sp.classId, sp.dateOfBirth, sp.phone, sp.studyStatus,
                    u.email, u.fullName, u.status AS userStatus,
                    c.name AS className, c.gradeLevel
             FROM student_profiles sp
             JOIN users u ON u.id = sp.userId
             JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = :schoolId
             ORDER BY c.gradeLevel ASC, c.name ASC, u.fullName ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute(['schoolId' => $schoolId]);
        return array_values($stmt->fetchAll());
    }

    /**
     * Demo metrics used by the dashboard summary panel.
     * @return array<string,int>
     */
    public function dashboardMetrics(string $schoolId): array
    {
        $metrics = [
            'totalStudents'  => 0,
            'totalClasses'   => 0,
            'totalTeachers'  => 0,
        ];

        $studentStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM student_profiles sp
             JOIN classes c ON c.id = sp.classId
             WHERE c.schoolId = :schoolId AND sp.studyStatus = \'active\''
        );
        $studentStmt->execute(['schoolId' => $schoolId]);
        $metrics['totalStudents'] = (int) $studentStmt->fetchColumn();

        $classStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM classes WHERE schoolId = :schoolId AND status = \'active\''
        );
        $classStmt->execute(['schoolId' => $schoolId]);
        $metrics['totalClasses'] = (int) $classStmt->fetchColumn();

        $teacherStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM teacher_profiles WHERE schoolId = :schoolId'
        );
        $teacherStmt->execute(['schoolId' => $schoolId]);
        $metrics['totalTeachers'] = (int) $teacherStmt->fetchColumn();

        return $metrics;
    }

    /**
     * Sync the cached counters on the schools row with the real values.
     */
    public function refreshCounters(string $schoolId): void
    {
        $this->pdo->beginTransaction();
        try {
            $studentStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM student_profiles sp
                 JOIN classes c ON c.id = sp.classId
                 WHERE c.schoolId = :schoolId AND sp.studyStatus = \'active\''
            );
            $studentStmt->execute(['schoolId' => $schoolId]);
            $studentCount = (int) $studentStmt->fetchColumn();

            $teacherStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM teacher_profiles WHERE schoolId = :schoolId'
            );
            $teacherStmt->execute(['schoolId' => $schoolId]);
            $teacherCount = (int) $teacherStmt->fetchColumn();

            $updateStmt = $this->pdo->prepare(
                'UPDATE schools SET studentCount = :sc, teacherCount = :tc WHERE id = :id'
            );
            $updateStmt->execute([
                'sc' => $studentCount,
                'tc' => $teacherCount,
                'id' => $schoolId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}