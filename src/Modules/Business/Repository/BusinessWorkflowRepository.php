<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Repository;

use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Http\CollectionQuery;
use TalentHub\Support\Uuid;
use Throwable;

final class BusinessWorkflowRepository
{
    public function __construct(private readonly PDO $pdo) {}

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

    public function review(string $enterpriseId, string $userId, string $id, string $status, ?string $note): bool
    {
        $this->pdo->beginTransaction();
        try {
            $select = $this->pdo->prepare(
                'SELECT ia.status FROM internship_applications ia '
                . 'INNER JOIN internship_posts ip ON ip.id=ia.postId '
                . 'WHERE ia.id=? AND ip.enterpriseId=? LIMIT 1' . $this->lockSuffix()
            );
            $select->execute([$id, $enterpriseId]);
            $current = $select->fetchColumn();
            if (!is_string($current)) {
                $this->pdo->rollBack();
                return false;
            }
            $allowed = [
                'submitted' => ['reviewing', 'declined'],
                'reviewing' => ['interview', 'accepted', 'declined'],
                'interview' => ['accepted', 'declined'],
            ];
            if (!in_array($status, $allowed[$current] ?? [], true)) {
                throw new ApiException(422, 'ILLEGAL_STATUS_TRANSITION', 'Chuyển trạng thái hồ sơ không hợp lệ.');
            }
            $statement = $this->pdo->prepare(<<<'SQL'
                UPDATE internship_applications ia
                JOIN internship_posts ip ON ip.id=ia.postId
                SET ia.status=?, ia.reviewedBy=?, ia.reviewerNote=?, ia.reviewedAt=UTC_TIMESTAMP(6), ia.updatedAt=UTC_TIMESTAMP(6)
                WHERE ia.id=? AND ip.enterpriseId=? AND ia.status=?
            SQL);
            $statement->execute([$status, $userId, $note, $id, $enterpriseId, $current]);
            if ($statement->rowCount() !== 1) {
                throw new ApiException(409, 'CONCURRENT_MODIFICATION', 'Trạng thái hồ sơ đã thay đổi.');
            }
            $history = $this->pdo->prepare(
                "INSERT INTO application_status_history(id,applicationId,fromStatus,toStatus,changedByUserId,changedByRole,note) VALUES(?,?,?,?,?,'enterprise',?)"
            );
            $history->execute([Uuid::v4(), $id, $current, $status, $userId, $note]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    public function projects(CollectionQuery $query): array
    {
        $order = ['createdAt' => 'createdAt', 'title' => 'title', 'fundingGoal' => 'fundingGoal'][$query->sort];
        $statement = $this->pdo->prepare(
            "SELECT id,schoolId,title,description,fundingGoal,status,createdAt,updatedAt FROM projects WHERE status='in_progress' AND fundingGoal IS NOT NULL ORDER BY {$order} "
            . strtoupper($query->direction) . " LIMIT {$query->limit} OFFSET {$query->offset}"
        );
        $statement->execute();
        return array_values($statement->fetchAll());
    }

    public function sponsor(string $enterpriseId, string $projectId, string $amount, string $currency, ?string $note): string
    {
        $id = Uuid::v4();
        $statement = $this->pdo->prepare("INSERT INTO project_sponsorships(id,enterpriseId,projectId,amount,currency,note) SELECT ?,?,id,?,?,? FROM projects WHERE id=? AND status='in_progress' AND fundingGoal IS NOT NULL");
        $statement->execute([$id, $enterpriseId, $amount, $currency, $note, $projectId]);
        return $statement->rowCount() === 1 ? $id : '';
    }

    /** @return list<array<string,mixed>> */
    public function sponsorships(string $enterpriseId): array
    {
        $statement = $this->pdo->prepare('SELECT ps.*,p.title AS projectTitle FROM project_sponsorships ps JOIN projects p ON p.id=ps.projectId WHERE ps.enterpriseId=? ORDER BY ps.createdAt DESC');
        $statement->execute([$enterpriseId]);
        return array_values($statement->fetchAll());
    }

    public function cancelSponsorship(string $enterpriseId, string $id): bool
    {
        $statement = $this->pdo->prepare("UPDATE project_sponsorships SET status='cancelled',cancelledAt=UTC_TIMESTAMP(6) WHERE id=? AND enterpriseId=? AND status='pledged'");
        $statement->execute([$id, $enterpriseId]);
        return $statement->rowCount() === 1;
    }

    public function createPayment(string $enterpriseId, string $sponsorshipId, string $provider): string
    {
        $id = Uuid::v4();
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare("INSERT INTO payment_orders(id,enterpriseId,sponsorshipId,amount,currency,provider) SELECT ?,enterpriseId,id,amount,currency,? FROM project_sponsorships WHERE id=? AND enterpriseId=? AND status='pledged'");
            $statement->execute([$id, $provider, $sponsorshipId, $enterpriseId]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                return '';
            }
            $this->pdo->prepare("UPDATE project_sponsorships SET status='pending_payment' WHERE id=? AND enterpriseId=?")
                ->execute([$sponsorshipId, $enterpriseId]);
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
        $statement = $this->pdo->prepare('INSERT INTO audit_logs(id,userId,action,entityType,entityId,requestId,metadata) VALUES(?,?,?,?,?,?,?)');
        $statement->execute([Uuid::v4(), $userId, $action, $type, $id, $requestId, '{}']);
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
}
