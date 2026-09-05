<?php

declare(strict_types=1);

namespace TalentHub\Modules\Contact\Repository;

use PDO;
use PDOException;
use RuntimeException;
use TalentHub\Support\Uuid;

final class ConsultationRequestRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array{fullName:string,audience:string,email:string,phone:?string,message:string,contactConsent:int} $data
     *  @return array{id:string,created:bool}
     */
    public function create(array $data, string $idempotencyKey): array
    {
        $id = Uuid::v4();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO consultation_requests
                 (id, fullName, audience, email, phone, message, contactConsent, status, idempotencyKey)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $id,
                $data['fullName'],
                $data['audience'],
                $data['email'],
                $data['phone'],
                $data['message'],
                $data['contactConsent'],
                'new',
                $idempotencyKey,
            ]);
            return ['id' => $id, 'created' => true];
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            $existing = $this->pdo->prepare('SELECT id FROM consultation_requests WHERE idempotencyKey = ? LIMIT 1');
            $existing->execute([$idempotencyKey]);
            $existingId = $existing->fetchColumn();
            if (!is_string($existingId) || $existingId === '') {
                throw $exception;
            }
            return ['id' => $existingId, 'created' => false];
        }
    }

    /** @return list<array<string,mixed>> */
    public function list(string $status = ''): array
    {
        $params = [];
        $where = '';
        if ($status !== '') {
            $where = ' WHERE consultation_requests.status = ?';
            $params[] = $status;
        }
        $statement = $this->pdo->prepare(
            'SELECT consultation_requests.id, consultation_requests.fullName, consultation_requests.audience,
                    consultation_requests.email, consultation_requests.phone, consultation_requests.status,
                    consultation_requests.createdAt, consultation_requests.updatedAt
             FROM consultation_requests' . $where . '
             ORDER BY consultation_requests.createdAt DESC LIMIT 200'
        );
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function find(string $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT consultation_requests.id, consultation_requests.fullName, consultation_requests.audience,
                    consultation_requests.email, consultation_requests.phone, consultation_requests.message,
                    consultation_requests.contactConsent, consultation_requests.status,
                    consultation_requests.createdAt, consultation_requests.updatedAt,
                    consultation_requests.completedAt, consultation_requests.handledByUserId,
                    users.fullName AS handledByName
             FROM consultation_requests
             LEFT JOIN users ON users.id = consultation_requests.handledByUserId
             WHERE consultation_requests.id = ? LIMIT 1'
        );
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed> */
    public function updateStatus(string $id, string $status, string $actorId): array
    {
        $this->pdo->beginTransaction();
        try {
            $lock = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $statement = $this->pdo->prepare('SELECT status FROM consultation_requests WHERE id = ?' . $lock);
            $statement->execute([$id]);
            $current = $statement->fetchColumn();
            if (!is_string($current)) {
                throw new RuntimeException('Không tìm thấy yêu cầu tư vấn.');
            }
            if ($current !== $status) {
                $allowed = ['new' => 'in_progress', 'in_progress' => 'completed'];
                if (($allowed[$current] ?? null) !== $status) {
                    throw new RuntimeException('Trạng thái chỉ được cập nhật theo đúng thứ tự xử lý.');
                }
                $completedAt = $status === 'completed' ? gmdate('Y-m-d H:i:s.u') : null;
                $update = $this->pdo->prepare(
                    'UPDATE consultation_requests
                     SET status = ?, handledByUserId = ?, completedAt = ?
                     WHERE id = ?'
                );
                $update->execute([$status, $actorId, $completedAt, $id]);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $this->find($id) ?? throw new RuntimeException('Không tìm thấy yêu cầu tư vấn.');
    }
}
