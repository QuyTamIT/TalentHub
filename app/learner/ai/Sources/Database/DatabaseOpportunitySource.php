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
