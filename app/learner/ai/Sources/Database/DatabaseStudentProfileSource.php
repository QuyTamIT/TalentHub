<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use PDO;
use TalentHub\Learner\Ai\Sources\StudentProfileSource;

final class DatabaseStudentProfileSource implements StudentProfileSource
{
    private const PROFILE_SQL = <<<'SQL'
SELECT
    student.studyStatus AS study_status,
    school.name AS school_name,
    class.name AS class_name,
    class.gradeLevel AS grade_level,
    class.academicYear AS academic_year
FROM student_profiles student
INNER JOIN classes class ON class.id = student.classId
INNER JOIN schools school ON school.id = class.schoolId
WHERE student.id = :student_id
LIMIT 1
SQL;

    private const BASIC_SQL = 'SELECT studyStatus AS study_status FROM student_profiles WHERE id = :student_id LIMIT 1';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forStudent(string $studentId): array
    {
        try {
            $row = $this->fetch(self::PROFILE_SQL, $studentId);
        } catch (\PDOException) {
            $row = $this->fetch(self::BASIC_SQL, $studentId);
        }

        if (!is_array($row) || !isset($row['study_status'])) {
            return [];
        }

        $profile = ['study_status' => (string) $row['study_status']];
        foreach (['school_name', 'class_name'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                $profile[$field] = $value;
            }
        }
        if (is_numeric($row['grade_level'] ?? null)) {
            $profile['grade_level'] = (int) $row['grade_level'];
        }
        $academicYear = trim((string) ($row['academic_year'] ?? ''));
        if ($academicYear !== '') {
            $profile['academic_year'] = $academicYear;
        }

        return $profile;
    }

    /** @return array<string,mixed>|null */
    private function fetch(string $sql, string $studentId): ?array
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false || !$statement->execute(['student_id' => trim($studentId)])) {
            return null;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
