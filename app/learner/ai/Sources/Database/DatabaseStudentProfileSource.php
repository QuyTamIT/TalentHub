<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use PDO;
use TalentHub\Learner\Ai\Sources\StudentProfileSource;

final class DatabaseStudentProfileSource implements StudentProfileSource
{
    private const SQL = 'SELECT studyStatus AS study_status FROM student_profiles WHERE id = :student_id LIMIT 1';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forStudent(string $studentId): array
    {
        $statement = $this->pdo->prepare(self::SQL);
        if ($statement === false || !$statement->execute(['student_id' => trim($studentId)])) {
            return [];
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !isset($row['study_status'])) {
            return [];
        }

        return ['study_status' => (string) $row['study_status']];
    }
}
