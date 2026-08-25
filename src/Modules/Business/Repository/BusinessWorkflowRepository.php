<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Repository;

use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Http\CollectionQuery;
use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Support\Uuid;
use Throwable;

final class BusinessWorkflowRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?InternshipRepository $internships = null
    ) {}

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function enterpriseId(string $userId): ?string
    {
        $statement = $this->pdo->prepare('SELECT enterpriseId FROM enterprise_members WHERE userId=? LIMIT 1');
        $statement->execute([$userId]);
        $id = $statement->fetchColumn();
        return is_string($id) ? $id : null;
    }

    public function studentId(string $userId): ?string
    {
        $statement = $this->pdo->prepare('SELECT id FROM student_profiles WHERE userId=? LIMIT 1');
        $statement->execute([$userId]);
        $id = $statement->fetchColumn();
        return is_string($id) ? $id : null;
    }

    /** @return list<array<string,mixed>> */
    public function posts(string $enterpriseId, CollectionQuery $query): array
    {
        $order = ['createdAt' => 'createdAt', 'title' => 'title', 'deadline' => 'deadline'][$query->sort];
        $where = 'enterpriseId=:owner';
        $parameters = ['owner' => $enterpriseId];
        if (isset($query->filters['status'])) {
            $where .= ' AND status=:status';
            $parameters['status'] = $query->filters['status'];
        }
        $statement = $this->pdo->prepare("SELECT * FROM internship_posts WHERE {$where} ORDER BY {$order} " . strtoupper($query->direction) . " LIMIT {$query->limit} OFFSET {$query->offset}");
        $statement->execute($parameters);
        return array_values($statement->fetchAll());
    }

    public function post(string $enterpriseId, string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM internship_posts WHERE id=? AND enterpriseId=?');
        $statement->execute([$id, $enterpriseId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function insertPost(string $enterpriseId, array $data): string
    {
        $id = Uuid::v4();
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO internship_posts
                (id, enterpriseId, title, field, location, workType, duration, educationLevel,
                 description, benefits, skillsJson, requirementsJson, slots, deadline, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
        SQL);
        $statement->execute([
            $id, $enterpriseId, $data['title'], $data['field'], $data['location'], $data['workType'],
            $data['duration'], $data['educationLevel'], $data['description'], $data['benefits'],
            $data['skillsJson'], $data['requirementsJson'], $data['slots'], $data['deadline'],
        ]);
        return $id;
    }

    public function updatePost(string $enterpriseId, string $id, array $data): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE internship_posts
            SET title=?, field=?, location=?, workType=?, duration=?, educationLevel=?, description=?,
                benefits=?, skillsJson=?, requirementsJson=?, slots=?, deadline=?
            WHERE id=? AND enterpriseId=? AND status='draft'
        SQL);
        $statement->execute([
            $data['title'], $data['field'], $data['location'], $data['workType'], $data['duration'],
            $data['educationLevel'], $data['description'], $data['benefits'], $data['skillsJson'],
            $data['requirementsJson'], $data['slots'], $data['deadline'], $id, $enterpriseId,
        ]);
    }

    public function transitionPost(string $enterpriseId, string $id, string $from, string $to): bool
    {
        $statement = $this->pdo->prepare('UPDATE internship_posts SET status=? WHERE id=? AND enterpriseId=? AND status=?');
        $statement->execute([$to, $id, $enterpriseId, $from]);
        return $statement->rowCount() === 1;
    }

    /** @return list<array<string,mixed>> */
    public function publicPosts(CollectionQuery $query): array
    {
        $order = ['createdAt' => 'createdAt', 'title' => 'title', 'deadline' => 'deadline'][$query->sort];
        $statement = $this->pdo->prepare(
            "SELECT id,enterpriseId,title,field,description,location,workType,duration,educationLevel,benefits,skillsJson,requirementsJson,slots,deadline,createdAt FROM internship_posts WHERE status='active' AND deadline>=UTC_TIMESTAMP(6) ORDER BY {$order} "
            . strtoupper($query->direction) . " LIMIT {$query->limit} OFFSET {$query->offset}"
        );
        $statement->execute();
        return array_values($statement->fetchAll());
    }

    /** @return array<string,mixed> */
    public function apply(string $studentId, string $userId, string $postId, string $message, string $requestId): array
    {
        return $this->applicationCommandService()->submit($studentId, $userId, $requestId, $postId, $message);
    }

    /** @return list<array<string,mixed>> */
    public function studentApplications(string $studentId): array
    {
        $statement = $this->pdo->prepare('SELECT ia.*,ip.title,ip.enterpriseId FROM internship_applications ia JOIN internship_posts ip ON ip.id=ia.postId WHERE ia.studentId=? ORDER BY ia.appliedAt DESC');
        $statement->execute([$studentId]);
        return array_values($statement->fetchAll());
    }

    /** @return array<string,mixed> */
    public function withdraw(string $studentId, string $userId, string $id, string $requestId, string $reason): array
    {
        return $this->applicationCommandService()->withdraw($studentId, $userId, $requestId, $id, $reason);
    }

    /** @return list<array<string,mixed>> */
    public function applications(string $enterpriseId, string $postId): array
    {
        $statement = $this->pdo->prepare('SELECT ia.* FROM internship_applications ia JOIN internship_posts ip ON ip.id=ia.postId WHERE ip.enterpriseId=? AND ip.id=? ORDER BY ia.appliedAt DESC');
        $statement->execute([$enterpriseId, $postId]);
        return array_values($statement->fetchAll());
    }

    public function applicationStatus(string $enterpriseId, string $id): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT ia.status FROM internship_applications ia '
            . 'INNER JOIN internship_posts ip ON ip.id=ia.postId '
            . 'WHERE ia.id=? AND ip.enterpriseId=? LIMIT 1'
        );
        $statement->execute([$id, $enterpriseId]);
        $status = $statement->fetchColumn();
        return is_string($status) ? $status : null;
    }

    public function review(string $enterpriseId, string $userId, string $id, string $status, ?string $note, ?string $expectedStatus = null): bool
    {
        $expected = $expectedStatus ?? $this->applicationStatus($enterpriseId, $id);
        if ($expected === null) {
            return false;
        }
        $this->getInternshipRepository()->review($enterpriseId, $userId, $id, $expected, $status, $note ?? '');
        return true;
    }

    private function getInternshipRepository(): InternshipRepository
    {
        return $this->internships ?? new InternshipRepository($this->pdo);
    }

    /** @return list<array<string,mixed>> */
    public function projects(CollectionQuery $query): array
    {
        $order = ['createdAt' => 'p.createdAt', 'title' => 'p.title', 'fundingGoal' => 'p.fundingGoal'][$query->sort];
        $direction = strtoupper($query->direction);

        $sql = "SELECT p.*,
                       s.name AS schoolName,
                       s.code AS schoolCode,
                       COALESCE((SELECT SUM(ps.amount) FROM project_sponsorships ps WHERE ps.projectId = p.id AND ps.status = 'paid'), 0) AS raisedAmount,
                       COALESCE((SELECT COUNT(DISTINCT ps.enterpriseId) FROM project_sponsorships ps WHERE ps.projectId = p.id AND ps.status = 'paid'), 0) AS sponsorsCount,
                       COALESCE((SELECT COUNT(*) FROM project_members pm WHERE pm.projectId = p.id AND pm.status = 'active'), 0) AS membersCount
                FROM projects p
                LEFT JOIN schools s ON s.id = p.schoolId
                WHERE p.status = 'in_progress' AND p.fundingGoal IS NOT NULL
                ORDER BY {$order} {$direction}
                LIMIT {$query->limit} OFFSET {$query->offset}";

        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $items = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($items as &$item) {
            $item['fundingGoal'] = (string) $item['fundingGoal'];
            $item['raisedAmount'] = (string) $item['raisedAmount'];
            $item['percentage'] = (float) $item['fundingGoal'] > 0
                ? (int) min(100, round(((float) $item['raisedAmount'] / (float) $item['fundingGoal']) * 100))
                : 0;
            $item['members'] = $this->projectMembers((string) $item['id']);
        }
        unset($item);

        return array_values($items);
    }

    private function projectMembers(string $projectId): array
    {
        if (!$this->tableExists('project_members') || !$this->tableExists('student_profiles') || !$this->tableExists('users')) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT pm.id, pm.role, pm.joinedAt, u.fullName AS name, u.email
             FROM project_members pm
             INNER JOIN student_profiles sp ON sp.id = pm.studentId
             INNER JOIN users u ON u.id = sp.userId
             WHERE pm.projectId = ? AND pm.status = 'active'
             ORDER BY pm.joinedAt ASC"
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sponsor(string $enterpriseId, string $projectId, string $amount, string $currency, ?string $note): string
    {
        $id = Uuid::v4();
        $now = $this->now();
        $statement = $this->pdo->prepare("INSERT INTO project_sponsorships(id,enterpriseId,projectId,amount,currency,status,note,createdAt,updatedAt) SELECT ?,?,id,?,?,'pledged',?,?,? FROM projects WHERE id=? AND status='in_progress' AND fundingGoal IS NOT NULL");
        $statement->execute([$id, $enterpriseId, $amount, $currency, $note, $now, $now, $projectId]);
        return $statement->rowCount() === 1 ? $id : '';
    }

    /** @return list<array<string,mixed>> */
    public function sponsorships(string $enterpriseId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT ps.*,
                    p.title AS projectTitle,
                    p.category AS projectCategory,
                    p.fundingGoal,
                    s.name AS schoolName,
                    po.id AS paymentOrderId,
                    po.paymentStatus,
                    po.providerReference,
                    po.paidAt
             FROM project_sponsorships ps
             INNER JOIN projects p ON p.id = ps.projectId
             LEFT JOIN schools s ON s.id = p.schoolId
             LEFT JOIN payment_orders po ON po.sponsorshipId = ps.id
             WHERE ps.enterpriseId = ?
             ORDER BY ps.createdAt DESC"
        );
        $statement->execute([$enterpriseId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function cancelSponsorship(string $enterpriseId, string $id): bool
    {
        $now = $this->now();
        $statement = $this->pdo->prepare("UPDATE project_sponsorships SET status='cancelled',cancelledAt=:now,updatedAt=:now WHERE id=:id AND enterpriseId=:enterpriseId AND status='pledged'");
        $statement->execute(['now' => $now, 'id' => $id, 'enterpriseId' => $enterpriseId]);
        return $statement->rowCount() === 1;
    }

    public function createPayment(string $enterpriseId, string $sponsorshipId, string $provider): string
    {
        $id = Uuid::v4();
        $now = $this->now();
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare("INSERT INTO payment_orders(id,enterpriseId,sponsorshipId,amount,currency,provider,paymentStatus,createdAt,updatedAt) SELECT ?,enterpriseId,id,amount,currency,?,'pending',?,? FROM project_sponsorships WHERE id=? AND enterpriseId=? AND status='pledged'");
            $statement->execute([$id, $provider, $now, $now, $sponsorshipId, $enterpriseId]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                return '';
            }
            $this->pdo->prepare("UPDATE project_sponsorships SET status='pending_payment',updatedAt=? WHERE id=? AND enterpriseId=?")
                ->execute([$now, $sponsorshipId, $enterpriseId]);
            $this->pdo->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    public function payments(string $enterpriseId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM payment_orders WHERE enterpriseId=? ORDER BY createdAt DESC');
        $statement->execute([$enterpriseId]);
        return array_values($statement->fetchAll());
    }

    public function audit(string $userId, string $action, string $type, string $id, string $requestId): void
    {
        $statement = $this->pdo->prepare('INSERT INTO audit_logs(id,userId,action,entityType,entityId,requestId,metadata,createdAt) VALUES(?,?,?,?,?,?,?,?)');
        $statement->execute([Uuid::v4(), $userId, $action, $type, $id, $requestId, '{}', $this->now()]);
    }

    private function applicationCommandService(): \TalentHub\Learner\Data\Service\ApplicationCommandService
    {
        require_once dirname(__DIR__, 4) . '/app/learner/data/bootstrap.php';
        return new \TalentHub\Learner\Data\Service\ApplicationCommandService(
            new \TalentHub\Learner\Data\Database\DatabaseApplicationCommandRepository($this->pdo)
        );
    }

    private function lockSuffix(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }

    private function tableExists(string $tableName): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = ? LIMIT 1");
            $stmt->execute([$tableName]);
            return (bool) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        $stmt->execute([$tableName]);
        return (bool) $stmt->fetchColumn();
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
