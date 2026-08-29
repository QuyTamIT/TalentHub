<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Repository;

use PDO;
use TalentHub\Http\ApiException;

final class SchoolAuditRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function schoolIdForUser(string $userId): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT sm.schoolId
            FROM school_members sm
            INNER JOIN schools s ON s.id = sm.schoolId
            WHERE sm.userId = :userId AND s.status = 'active'
            LIMIT 2
        SQL);
        $statement->execute(['userId' => $userId]);
        $ids = $statement->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (count($ids) !== 1 || !is_string($ids[0]) || $ids[0] === '') {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Tài khoản phải thuộc đúng một trường đang hoạt động.');
        }
        return $ids[0];
    }

    /**
     * @param array{search?:string,accessType?:string,from?:string,to?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int,limit:int,offset:int}
     */
    public function profileAccessLogs(
        string $schoolId,
        array $filters,
        int $limit,
        int $offset
    ): array {
        $where = ['c.schoolId = :schoolId'];
        $parameters = ['schoolId' => $schoolId];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(studentUser.fullName LIKE :searchStudent OR enterprise.name LIKE :searchEnterprise OR actor.email LIKE :searchActor)';
            $wildcard = '%' . $search . '%';
            $parameters['searchStudent'] = $wildcard;
            $parameters['searchEnterprise'] = $wildcard;
            $parameters['searchActor'] = $wildcard;
        }

        $accessType = trim((string) ($filters['accessType'] ?? ''));
        if ($accessType !== '') {
            $where[] = 'accessLog.accessType = :accessType';
            $parameters['accessType'] = $accessType;
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $where[] = 'accessLog.accessedAt >= :fromDate';
            $parameters['fromDate'] = $from . ' 00:00:00.000000';
        }
        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $where[] = 'accessLog.accessedAt <= :toDate';
            $parameters['toDate'] = $to . ' 23:59:59.999999';
        }

        $whereSql = implode(' AND ', $where);
        $fromSql = <<<SQL
            FROM student_profile_access_logs accessLog
            INNER JOIN student_profiles student ON student.id = accessLog.studentId
            INNER JOIN users studentUser ON studentUser.id = student.userId
            INNER JOIN classes c ON c.id = student.classId
            INNER JOIN enterprises enterprise ON enterprise.id = accessLog.enterpriseId
            LEFT JOIN users actor ON actor.id = accessLog.accessedByUserId
            WHERE {$whereSql}
        SQL;

        $count = $this->pdo->prepare('SELECT COUNT(*) ' . $fromSql);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $statement = $this->pdo->prepare(<<<SQL
            SELECT accessLog.id,
                   accessLog.studentId,
                   studentUser.fullName AS studentName,
                   c.id AS classId,
                   c.name AS className,
                   accessLog.enterpriseId,
                   enterprise.name AS enterpriseName,
                   accessLog.accessedByUserId,
                   actor.email AS actorEmail,
                   accessLog.accessType,
                   accessLog.requestId,
                   accessLog.ipAddress,
                   accessLog.accessedAt
            {$fromSql}
            ORDER BY accessLog.accessedAt DESC, accessLog.id DESC
            LIMIT {$limit} OFFSET {$offset}
        SQL);
        $statement->execute($parameters);

        return [
            'items' => array_values($statement->fetchAll(PDO::FETCH_ASSOC) ?: []),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /** @return array{totalAccesses:int,uniqueEnterprises:int,uniqueStudents:int,recentAccesses:int} */
    public function profileAccessSummary(string $schoolId, string $recentSince): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT COUNT(*) AS totalAccesses,
                   COUNT(DISTINCT accessLog.enterpriseId) AS uniqueEnterprises,
                   COUNT(DISTINCT accessLog.studentId) AS uniqueStudents,
                   SUM(CASE WHEN accessLog.accessedAt >= :recentSince THEN 1 ELSE 0 END) AS recentAccesses
            FROM student_profile_access_logs accessLog
            INNER JOIN student_profiles student ON student.id = accessLog.studentId
            INNER JOIN classes c ON c.id = student.classId
            WHERE c.schoolId = :schoolId
        SQL);
        $statement->execute(['schoolId' => $schoolId, 'recentSince' => $recentSince]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'totalAccesses' => (int) ($row['totalAccesses'] ?? 0),
            'uniqueEnterprises' => (int) ($row['uniqueEnterprises'] ?? 0),
            'uniqueStudents' => (int) ($row['uniqueStudents'] ?? 0),
            'recentAccesses' => (int) ($row['recentAccesses'] ?? 0),
        ];
    }
}
