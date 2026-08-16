<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Sources\ConsentSource;
use Throwable;

final class DatabaseConsentSource implements ConsentSource
{
    private const SQL = <<<'SQL'
SELECT scope, action, occurredAt AS occurred_at, requestId AS request_id
FROM learner_ai_consent_events
WHERE studentId = :student_id
ORDER BY occurredAt DESC, requestId DESC
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

        $events = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $occurredAt = self::timestamp($row['occurred_at'] ?? null);
            if ($occurredAt === null) {
                continue;
            }

            $events[] = [
                'scope' => (string) $row['scope'],
                'action' => (string) $row['action'],
                'occurred_at' => $occurredAt,
                'request_id' => (string) $row['request_id'],
            ];
        }

        return $events;
    }

    private static function timestamp(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\\TH:i:s.uP');
        } catch (Throwable) {
            return null;
        }
    }
}
