<?php

declare(strict_types=1);

namespace TalentHub\Learner\Assessment\Service;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use TalentHub\Learner\Data\Support\Uuid;

final class EducationBandRequired extends RuntimeException
{
}

final class EducationBandResolver
{
    private const VALID_BANDS = ['middle', 'high', 'college'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function resolve(string $studentId, ?string $confirmedBand): string
    {
        $band = null;
        if ($confirmedBand !== null && trim($confirmedBand) !== '') {
            $band = strtolower(trim($confirmedBand));
            if (!in_array($band, self::VALID_BANDS, true)) {
                throw new InvalidArgumentException("Invalid education band: {$confirmedBand}");
            }
        }

        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $statement = $this->pdo->prepare(
            'SELECT c.gradeLevel, s.level AS schoolLevel, s.name AS schoolName '
            . 'FROM student_profiles sp '
            . 'LEFT JOIN classes c ON c.id = sp.classId '
            . 'LEFT JOIN schools s ON s.id = c.schoolId '
            . 'WHERE sp.id = :student_id LIMIT 1'
        );
        if ($statement === false || !$statement->execute(['student_id' => $studentId])) {
            throw new RuntimeException('Failed to query student class information.');
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $schoolBand = $this->bandFromSchoolLevel(is_array($row) ? ($row['schoolLevel'] ?? null) : null);
        if ($schoolBand === null && is_array($row) && isset($row['schoolName'])) {
            $schoolBand = $this->bandFromSchoolLevel($row['schoolName']);
        }
        if ($schoolBand === 'college') {
            return 'college';
        }
        if ($row !== false && ($row['gradeLevel'] ?? null) !== null) {
            $grade = (int) $row['gradeLevel'];
            if ($grade >= 6 && $grade <= 9) {
                return 'middle';
            }
            if ($grade >= 10 && $grade <= 12) {
                return 'high';
            }
            if ($grade >= 1 && $grade <= 5) {
                return 'college';
            }
        }
        if ($schoolBand !== null) {
            return $schoolBand;
        }

        if ($band !== null) {
            return $band;
        }

        return 'high';
    }

    private function bandFromSchoolLevel(mixed $level): ?string
    {
        if (!is_string($level) || trim($level) === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($level), 'UTF-8');
        if (
            str_contains($normalized, 'đại học')
            || str_contains($normalized, 'cao đẳng')
            || str_contains($normalized, 'dai hoc')
            || str_contains($normalized, 'cao dang')
            || str_contains($normalized, 'university')
            || str_contains($normalized, 'college')
        ) {
            return 'college';
        }
        if (str_contains($normalized, 'trung học cơ sở') || preg_match('/\bthcs\b/u', $normalized) === 1) {
            return 'middle';
        }
        if (str_contains($normalized, 'trung học phổ thông') || preg_match('/\bthpt\b/u', $normalized) === 1) {
            return 'high';
        }

        return null;
    }
}
