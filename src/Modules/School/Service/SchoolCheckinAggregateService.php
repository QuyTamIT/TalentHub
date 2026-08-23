<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use PDO;

final class SchoolCheckinAggregateService
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array{confirmedCheckins:int,confirmedHours:string} */
    public function confirmedForSchool(string $schoolId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT COUNT(DISTINCT c.id) confirmedCheckins,
                   COALESCE(SUM(CASE WHEN el.status = 'confirmed' THEN el.hours ELSE 0 END), 0) confirmedHours
            FROM checkins c
            INNER JOIN activity_registrations ar ON ar.id = c.registrationId
            INNER JOIN activities a ON a.id = ar.activityId
            LEFT JOIN experience_logs el ON el.checkinId = c.id AND el.activityId = a.id
            WHERE a.schoolId = :schoolId
              AND c.status = 'confirmed'
        SQL);
        $statement->execute(['schoolId' => $schoolId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'confirmedCheckins' => (int) ($row['confirmedCheckins'] ?? 0),
            'confirmedHours' => number_format((float) ($row['confirmedHours'] ?? 0), 2, '.', ''),
        ];
    }
}
