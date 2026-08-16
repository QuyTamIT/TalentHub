<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Sources\OpportunitySource;
use Throwable;

final class DatabaseOpportunitySource implements OpportunitySource
{
    private const REQUIRED_COLUMNS = [
        'student_profiles' => ['id'],
        'internship_posts' => ['id', 'enterpriseId', 'title', 'location', 'deadline', 'status'],
        'enterprises' => ['id', 'status', 'verificationStatus'],
    ];

    private const SQL = <<<'SQL'
SELECT
    post.id AS opportunity_id,
    enterprise.id AS enterprise_id,
    post.title,
    post.location,
    post.deadline
FROM internship_posts post
INNER JOIN enterprises enterprise ON enterprise.id = post.enterpriseId
WHERE EXISTS (SELECT 1 FROM student_profiles student WHERE student.id = :student_id)
  AND post.status = 'active'
  AND enterprise.status = 'active'
  AND enterprise.verificationStatus IN ('verified', 'approved')
ORDER BY post.deadline ASC, post.id ASC
SQL;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forStudent(string $studentId): array
    {
        if (!$this->hasOpportunityContract()) {
            return [];
        }

        try {
            $statement = $this->pdo->prepare(self::SQL);
            if ($statement === false || !$statement->execute(['student_id' => trim($studentId)])) {
                return [];
            }

            $opportunities = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $deadline = self::timestamp($row['deadline'] ?? null);
                if ($deadline === null) {
                    continue;
                }

                $opportunities[] = [
                    'opportunity_id' => (string) $row['opportunity_id'],
                    'enterprise_id' => (string) $row['enterprise_id'],
                    'title' => (string) $row['title'],
                    'location' => (string) $row['location'],
                    'deadline_at' => $deadline,
                ];
            }

            return $opportunities;
        } catch (Throwable) {
            return [];
        }
    }

    private function hasOpportunityContract(): bool
    {
        foreach (self::REQUIRED_COLUMNS as $table => $requiredColumns) {
            if (array_diff($requiredColumns, $this->columnsFor($table)) !== []) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function columnsFor(string $table): array
    {
        try {
            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = match ($driver) {
                'sqlite' => match ($table) {
                    'student_profiles' => 'PRAGMA table_info(student_profiles)',
                    'internship_posts' => 'PRAGMA table_info(internship_posts)',
                    'enterprises' => 'PRAGMA table_info(enterprises)',
                    default => null,
                },
                'mysql' => match ($table) {
                    'student_profiles' => 'SHOW COLUMNS FROM student_profiles',
                    'internship_posts' => 'SHOW COLUMNS FROM internship_posts',
                    'enterprises' => 'SHOW COLUMNS FROM enterprises',
                    default => null,
                },
                default => null,
            };
            if ($sql === null) {
                return [];
            }

            $rows = $this->pdo->query($sql)?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $name = $row['name'] ?? $row['Field'] ?? null;
            if (is_string($name)) {
                $columns[] = $name;
            }
        }

        return $columns;
    }

    private static function timestamp(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(DATE_ATOM);
        } catch (Throwable) {
            return null;
        }
    }
}
