<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;
require_once dirname(__DIR__, 2) . '/ai/Queue/TransactionalAiOutboxPublisher.php';

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;
use TalentHub\Learner\Data\Contracts\BadgeRepository;
use TalentHub\Learner\Data\Support\Uuid;
use TalentHub\Learner\Ai\Queue\TransactionalAiOutboxPublisher;
use Throwable;

final class DatabaseBadgeRepository extends AbstractDatabaseRepository implements BadgeRepository
{
    private ?bool $schoolScopeSupported = null;

    public function activeRules(): array
    {
        return $this->fetchActiveRules(null);
    }

    public function activeRulesForStudent(string $studentId): array
    {
        return $this->fetchActiveRules(Uuid::normalizeDatabase($studentId, 'student_id'));
    }

    /** @return list<array{badge:array<string,mixed>,rule:array<string,mixed>}> */
    private function fetchActiveRules(?string $studentId): array
    {
        $scopeSql = '1 = 1';
        if ($this->supportsSchoolScope()) {
            $scopeSql = $studentId === null
                ? 'b.schoolId IS NULL'
                : <<<'SQL'
                (
                    b.schoolId IS NULL
                    OR b.schoolId = (
                        SELECT c.schoolId
                        FROM student_profiles sp
                        INNER JOIN classes c ON c.id = sp.classId
                        WHERE sp.id = :student_id
                        LIMIT 1
                    )
                )
                SQL;
        }
        $sql = <<<'SQL'
            SELECT
                b.id AS badge_id,
                b.code AS badge_code,
                b.name AS badge_name,
                b.category AS badge_category,
                b.description AS badge_description,
                b.iconUrl AS badge_icon_url,
                b.level AS badge_level,
                b.status AS badge_status,
                r.id AS rule_id,
                r.badgeId AS rule_badge_id,
                r.ruleType AS rule_type,
                r.thresholdCriteria AS threshold_criteria,
                r.version AS rule_version,
                r.isActive AS is_active
            FROM badges b
            INNER JOIN badge_rule_definitions r ON r.badgeId = b.id
            WHERE b.status = 'active' AND r.isActive = 1
              AND {{SCOPE}}
            ORDER BY b.level ASC, b.createdAt ASC
        SQL;
        $sql = str_replace('{{SCOPE}}', $scopeSql, $sql);

        $parameters = $studentId === null || !$this->supportsSchoolScope()
            ? []
            : ['student_id' => $studentId];
        $rows = $this->fetchAll('activeRules', $sql, $parameters);
        $result = [];
        foreach ($rows as $row) {
            $criteria = $this->decodeJson($row['threshold_criteria'] ?? null, 'thresholdCriteria');
            $result[] = [
                'badge' => [
                    'id' => (string) $row['badge_id'],
                    'code' => (string) $row['badge_code'],
                    'name' => (string) $row['badge_name'],
                    'category' => (string) $row['badge_category'],
                    'description' => (string) $row['badge_description'],
                    'iconUrl' => $row['badge_icon_url'] !== null ? (string) $row['badge_icon_url'] : null,
                    'level' => (int) $row['badge_level'],
                    'status' => (string) $row['badge_status'],
                ],
                'rule' => [
                    'id' => (string) $row['rule_id'],
                    'badgeId' => (string) $row['rule_badge_id'],
                    'ruleType' => (string) $row['rule_type'],
                    'thresholdCriteria' => $criteria,
                    'version' => (int) $row['rule_version'],
                    'isActive' => (bool) $row['is_active'],
                ],
            ];
        }

        return $result;
    }

    private function supportsSchoolScope(): bool
    {
        if ($this->schoolScopeSupported !== null) {
            return $this->schoolScopeSupported;
        }

        try {
            $statement = $this->pdo->prepare('SELECT schoolId FROM badges WHERE 1 = 0');
            $statement->execute();
            return $this->schoolScopeSupported = true;
        } catch (PDOException) {
            return $this->schoolScopeSupported = false;
        }
    }

    public function awardedBadges(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');

        $sql = <<<'SQL'
            SELECT
                b.id,
                b.code,
                b.name,
                b.category,
                b.description,
                b.iconUrl AS icon_url,
                b.level,
                sb.id AS student_badge_id,
                sb.awardedAt AS awarded_at,
                sb.awardedBy AS awarded_by,
                sb.awardContext AS award_context
            FROM badges b
            INNER JOIN student_badges sb ON sb.badgeId = b.id
            WHERE sb.studentId = :student_id
            ORDER BY sb.awardedAt DESC, b.level ASC
        SQL;

        $rows = $this->fetchAll('awardedBadges', $sql, ['student_id' => $studentId]);
        $result = [];
        foreach ($rows as $row) {
            $context = $this->decodeJson($row['award_context'] ?? null, 'awardContext');
            $result[] = [
                'id' => (string) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'category' => (string) $row['category'],
                'description' => (string) $row['description'],
                'iconUrl' => $row['icon_url'] !== null ? (string) $row['icon_url'] : null,
                'level' => (int) $row['level'],
                'studentBadgeId' => (string) $row['student_badge_id'],
                'awardedAt' => (string) $row['awarded_at'],
                'awardedBy' => (string) $row['awarded_by'],
                'awardContext' => $context,
            ];
        }

        return $result;
    }

    public function isAwarded(string $studentId, string $badgeId): bool
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $badgeId = Uuid::normalizeDatabase($badgeId, 'badge_id');

        $sql = 'SELECT 1 FROM student_badges WHERE studentId = :student_id AND badgeId = :badge_id LIMIT 1';
        $row = $this->fetchOne('isAwarded', $sql, [
            'student_id' => $studentId,
            'badge_id' => $badgeId,
        ]);

        return $row !== null;
    }

    public function insertAward(
        string $studentId,
        string $badgeId,
        string $ruleDefinitionId,
        string $awardedBy,
        array $awardContext,
        DateTimeImmutable $awardedAt
    ): bool {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $badgeId = Uuid::normalizeDatabase($badgeId, 'badge_id');
        $ruleDefinitionId = Uuid::normalizeDatabase($ruleDefinitionId, 'rule_definition_id');

        if ($this->supportsSchoolScope()
            && !$this->isAwardScopeValid($studentId, $badgeId, $ruleDefinitionId)) {
            throw new RuntimeException('Badge award rejected because the rule, badge, or student school scope is invalid.');
        }

        $id = \TalentHub\Support\Uuid::v4();
        $awardedAtStr = $awardedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $awardContextJson = json_encode($awardContext, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $stmt = $this->pdo->prepare(<<<'SQL'
                INSERT INTO student_badges (id, studentId, badgeId, ruleDefinitionId, awardedAt, awardedBy, awardContext)
                VALUES (:id, :student_id, :badge_id, :rule_definition_id, :awarded_at, :awarded_by, :award_context)
            SQL);

            $stmt->execute([
                'id' => $id,
                'student_id' => $studentId,
                'badge_id' => $badgeId,
                'rule_definition_id' => $ruleDefinitionId,
                'awarded_at' => $awardedAtStr,
                'awarded_by' => $awardedBy,
                'award_context' => $awardContextJson,
            ]);

            TransactionalAiOutboxPublisher::publish($this->pdo,'badge',$id,TransactionalAiOutboxPublisher::version(),[$studentId],'badge.awarded',['badge_id'=>$badgeId]);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return true;
        } catch (PDOException $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($this->isDuplicateKey($e)) {
                return false;
            }
            throw $e;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function userForStudent(string $studentId): ?array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');

        $sql = <<<'SQL'
            SELECT sp.userId AS user_id, u.fullName AS full_name
            FROM student_profiles sp
            INNER JOIN users u ON u.id = sp.userId
            WHERE sp.id = :student_id
            LIMIT 1
        SQL;

        $row = $this->fetchOne('userForStudent', $sql, ['student_id' => $studentId]);
        if ($row === null) {
            return null;
        }

        return [
            'userId' => (string) $row['user_id'],
            'fullName' => (string) $row['full_name'],
        ];
    }

    public function withTransaction(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $operation();
        }

        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            return (int) ($e->errorInfo[1] ?? 0) === 1062;
        }

        if ($driver !== 'sqlite' || (int) ($e->errorInfo[1] ?? 0) !== 19) {
            return false;
        }

        $message = strtolower($e->getMessage());
        return str_contains($message, 'student_badges.studentid, student_badges.badgeid')
            || str_contains($message, 'unique constraint failed: student_badges.studentid, student_badges.badgeid');
    }

    private function isAwardScopeValid(string $studentId, string $badgeId, string $ruleDefinitionId): bool
    {
        $row = $this->fetchOne('isAwardScopeValid', <<<'SQL'
SELECT 1 AS valid_scope
FROM badges b
INNER JOIN badge_rule_definitions r ON r.id = :rule_id AND r.badgeId = b.id
INNER JOIN student_profiles sp ON sp.id = :student_id
INNER JOIN classes c ON c.id = sp.classId
WHERE b.id = :badge_id
  AND (b.schoolId IS NULL OR b.schoolId = c.schoolId)
LIMIT 1
SQL, [
            'rule_id' => $ruleDefinitionId,
            'student_id' => $studentId,
            'badge_id' => $badgeId,
        ]);
        return $row !== null;
    }
}
