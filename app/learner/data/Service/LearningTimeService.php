<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;
use Throwable;

final class LearningTimeService
{
    private const HEARTBEAT_MAX_SECONDS = 90;

    public function __construct(
        private readonly PDO $pdo,
        private readonly BadgeAwardService $badgeAwards,
        private readonly ?DateTimeImmutable $clock = null,
    ) {}

    /** @return array{creditedSeconds:int,totalSeconds:int,totalMinutes:int,newBadges:list<array{id:string,name:string}>} */
    public function recordHeartbeat(string $studentId, string $sessionKey, string $pagePath): array
    {
        if (!Uuid::isValid($studentId) || !Uuid::isValid($sessionKey)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Định danh phiên học không hợp lệ.');
        }
        $pagePath = trim($pagePath);
        if ($pagePath === '' || mb_strlen($pagePath) > 255 || !str_starts_with($pagePath, '/app/learner/')) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Trang học tập không hợp lệ.');
        }

        $now = ($this->clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        $nowSql = $now->format('Y-m-d H:i:s.u');
        $activityDate = $now->format('Y-m-d');
        $owns = !$this->pdo->inTransaction();
        if ($owns) {
            $this->pdo->beginTransaction();
        }

        try {
            $select = $this->pdo->prepare(
                'SELECT id,activeSeconds,lastHeartbeatAt FROM student_learning_time_logs '
                . 'WHERE studentId=:studentId AND activityDate=:activityDate LIMIT 1'
                . $this->lockSuffix(),
            );
            $select->execute(['studentId' => $studentId, 'activityDate' => $activityDate]);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            $credited = 0;

            if (!is_array($row)) {
                $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO student_learning_time_logs
    (id,studentId,activityDate,activeSeconds,lastHeartbeatAt,lastSessionKey,lastPagePath,createdAt,updatedAt)
VALUES
    (:id,:studentId,:activityDate,0,:heartbeatAt,:sessionKey,:pagePath,:createdAt,:updatedAt)
SQL);
                $insert->execute([
                    'id' => Uuid::v4(),
                    'studentId' => $studentId,
                    'activityDate' => $activityDate,
                    'heartbeatAt' => $nowSql,
                    'sessionKey' => strtolower($sessionKey),
                    'pagePath' => $pagePath,
                    'createdAt' => $nowSql,
                    'updatedAt' => $nowSql,
                ]);
            } else {
                $previous = new DateTimeImmutable((string) $row['lastHeartbeatAt'], new DateTimeZone('UTC'));
                $elapsed = $now->getTimestamp() - $previous->getTimestamp();
                if ($elapsed > 0 && $elapsed <= self::HEARTBEAT_MAX_SECONDS) {
                    $credited = min($elapsed, max(0, 86400 - (int) $row['activeSeconds']));
                }
                $update = $this->pdo->prepare(<<<'SQL'
UPDATE student_learning_time_logs
SET activeSeconds=activeSeconds+:credited, lastHeartbeatAt=:heartbeatAt,
    lastSessionKey=:sessionKey, lastPagePath=:pagePath, updatedAt=:updatedAt
WHERE id=:id
SQL);
                $update->execute([
                    'credited' => $credited,
                    'heartbeatAt' => $nowSql,
                    'sessionKey' => strtolower($sessionKey),
                    'pagePath' => $pagePath,
                    'updatedAt' => $nowSql,
                    'id' => $row['id'],
                ]);
            }

            if ($owns) {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($owns && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $total = $this->pdo->prepare('SELECT COALESCE(SUM(activeSeconds),0) FROM student_learning_time_logs WHERE studentId=:studentId');
        $total->execute(['studentId' => $studentId]);
        $totalSeconds = (int) $total->fetchColumn();
        $newBadges = [];
        if ($credited > 0) {
            foreach ($this->badgeAwards->evaluateAndAward($studentId, 'system') as $award) {
                $newBadges[] = [
                    'id' => (string) ($award['badge']['id'] ?? ''),
                    'name' => (string) ($award['badge']['name'] ?? ''),
                ];
            }
        }

        return [
            'creditedSeconds' => $credited,
            'totalSeconds' => $totalSeconds,
            'totalMinutes' => (int) floor($totalSeconds / 60),
            'newBadges' => $newBadges,
        ];
    }

    private function lockSuffix(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }
}
