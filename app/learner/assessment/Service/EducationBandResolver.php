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
            'SELECT c.gradeLevel FROM student_profiles sp LEFT JOIN classes c ON c.id = sp.classId WHERE sp.id = :student_id LIMIT 1'
        );
        if ($statement === false || !$statement->execute(['student_id' => $studentId])) {
            throw new RuntimeException('Failed to query student class information.');
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row !== false && ($row['gradeLevel'] ?? null) !== null) {
            $grade = (int) $row['gradeLevel'];
            if ($grade >= 6 && $grade <= 9) {
                return 'middle';
            }
            if ($grade >= 10 && $grade <= 12) {
                return 'high';
            }
        }

        if ($band !== null) {
            return $band;
        }

        throw new EducationBandRequired('Explicit education band confirmation is required.');
    }
}
