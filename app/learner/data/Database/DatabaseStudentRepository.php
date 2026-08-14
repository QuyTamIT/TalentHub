<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use TalentHub\Learner\Data\Contracts\StudentRepository;
use TalentHub\Learner\Data\Enums\StudentStudyStatus;
use TalentHub\Learner\Data\Support\Uuid;

final class DatabaseStudentRepository extends AbstractDatabaseRepository implements StudentRepository
{
    private const FIND_SQL = <<<'SQL'
        SELECT
            sp.id,
            sp.userId,
            sp.classId,
            sp.dateOfBirth,
            sp.phone,
            sp.studyStatus,
            u.email,
            u.fullName,
            u.status AS userStatus,
            c.schoolId,
            c.name AS className,
            c.gradeLevel,
            c.academicYear,
            s.name AS schoolName,
            s.status AS schoolStatus
        FROM student_profiles sp
        INNER JOIN users u ON u.id = sp.userId
        INNER JOIN classes c ON c.id = sp.classId
        INNER JOIN schools s ON s.id = c.schoolId
        WHERE sp.id = :student_id
        LIMIT 1
        SQL;

    public function findById(string $studentId): ?array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $student = $this->fetchOne('findById', self::FIND_SQL, ['student_id' => $studentId]);
        if ($student === null) {
            return null;
        }

        $student['id'] = Uuid::normalizeDatabase((string) $student['id'], 'student_profiles.id');
        $student['student_id'] = $student['id'];
        $student['user_id'] = Uuid::normalizeDatabase((string) $student['user_id'], 'student_profiles.userId');
        $student['class_id'] = Uuid::normalizeDatabase((string) $student['class_id'], 'student_profiles.classId');
        $student['school_id'] = Uuid::normalizeDatabase((string) $student['school_id'], 'classes.schoolId');
        $student['study_status'] = StudentStudyStatus::normalize($student['study_status'] ?? null)->value;
        $student['id_origin'] = 'database';

        return $student;
    }
}
