<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;

final class DatabaseCertificateCommandRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return list<array<string,mixed>> */
    public function listForStudent(string $studentId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT id, studentId, title, issuingOrganization, issueDate, expiryDate,
                   credentialId, credentialUrl, verificationStatus, verifiedBy, verifiedAt,
                   createdAt, updatedAt
            FROM certificates
            WHERE studentId = :studentId
            ORDER BY issueDate DESC, createdAt DESC
        SQL
        );
        $statement->execute(['studentId' => $studentId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return array_map($this->normalizeRow(...), $rows ?: []);
    }

    /** @return array<string,mixed>|null */
    public function findById(string $studentId, string $certificateId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            SELECT id, studentId, title, issuingOrganization, issueDate, expiryDate,
                   credentialId, credentialUrl, verificationStatus, verifiedBy, verifiedAt,
                   createdAt, updatedAt
            FROM certificates
            WHERE studentId = :studentId AND id = :id
            LIMIT 1
        SQL
        );
        $statement->execute(['studentId' => $studentId, 'id' => $certificateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function create(string $studentId, array $data): array
    {
        $id = Uuid::v4();
        $isSqlite = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $nowExpr = $isSqlite ? "datetime('now')" : "CURRENT_TIMESTAMP(6)";

        $sql = <<<SQL
            INSERT INTO certificates (
              id, studentId, title, issuingOrganization, issueDate, expiryDate,
              credentialId, credentialUrl, verificationStatus, verifiedBy, verifiedAt,
              createdAt, updatedAt
            ) VALUES (
              :id, :studentId, :title, :issuingOrganization, :issueDate, :expiryDate,
              :credentialId, :credentialUrl, 'unverified', NULL, NULL,
              {$nowExpr}, {$nowExpr}
            )
        SQL;

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'id' => $id,
                'studentId' => $studentId,
                'title' => $data['title'],
                'issuingOrganization' => $data['issuingOrganization'],
                'issueDate' => $data['issueDate'],
                'expiryDate' => $data['expiryDate'] ?? null,
                'credentialId' => $data['credentialId'] ?? null,
                'credentialUrl' => $data['credentialUrl'] ?? null,
            ]);
            $created = $this->findById($studentId, $id)
                ?? throw new ApiException(500, 'CREATION_FAILED', 'Không thể tạo chứng chỉ.');
            $this->pdo->commit();
            return $created;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function update(string $studentId, string $certificateId, array $data): array
    {
        $isSqlite = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $nowExpr = $isSqlite ? "datetime('now')" : "CURRENT_TIMESTAMP(6)";
        $this->pdo->beginTransaction();
        try {
            $existing = $this->findByIdForUpdate($studentId, $certificateId, $isSqlite);
            if ($existing === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy chứng chỉ.');
            }
            if ($existing['verificationStatus'] !== 'unverified') {
                throw new ApiException(422, 'CANNOT_MODIFY_VERIFIED_CERTIFICATE', 'Chỉ có thể chỉnh sửa chứng chỉ ở trạng thái chưa xác minh.');
            }

            $mergedIssueDate = (string) ($data['issueDate'] ?? $existing['issueDate']);
            $mergedExpiryDate = array_key_exists('expiryDate', $data)
                ? $data['expiryDate']
                : $existing['expiryDate'];
            if (is_string($mergedExpiryDate) && $mergedExpiryDate !== '' && $mergedExpiryDate < $mergedIssueDate) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'expiryDate phải lớn hơn hoặc bằng issueDate.');
            }

            $sql = <<<SQL
                UPDATE certificates
                SET title = :title,
                    issuingOrganization = :issuingOrganization,
                    issueDate = :issueDate,
                    expiryDate = :expiryDate,
                    credentialId = :credentialId,
                    credentialUrl = :credentialUrl,
                    updatedAt = {$nowExpr}
                WHERE studentId = :studentId AND id = :id AND verificationStatus = 'unverified'
            SQL;
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'id' => $certificateId,
                'studentId' => $studentId,
                'title' => $data['title'] ?? $existing['title'],
                'issuingOrganization' => $data['issuingOrganization'] ?? $existing['issuingOrganization'],
                'issueDate' => $data['issueDate'] ?? $existing['issueDate'],
                'expiryDate' => array_key_exists('expiryDate', $data) ? $data['expiryDate'] : $existing['expiryDate'],
                'credentialId' => array_key_exists('credentialId', $data) ? $data['credentialId'] : $existing['credentialId'],
                'credentialUrl' => array_key_exists('credentialUrl', $data) ? $data['credentialUrl'] : $existing['credentialUrl'],
            ]);
            if ($statement->rowCount() !== 1) {
                throw new ApiException(409, 'STALE_CERTIFICATE_STATE', 'Trạng thái chứng chỉ đã thay đổi.');
            }
            $updated = $this->findById($studentId, $certificateId)
                ?? throw new ApiException(500, 'UPDATE_FAILED', 'Không thể cập nhật chứng chỉ.');
            $this->pdo->commit();
            return $updated;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(string $studentId, string $certificateId): void
    {
        $isSqlite = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $this->pdo->beginTransaction();
        try {
            $existing = $this->findByIdForUpdate($studentId, $certificateId, $isSqlite);
            if ($existing === null) {
                throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy chứng chỉ.');
            }
            if ($existing['verificationStatus'] !== 'unverified') {
                throw new ApiException(422, 'CANNOT_MODIFY_VERIFIED_CERTIFICATE', 'Chỉ có thể xóa chứng chỉ ở trạng thái chưa xác minh.');
            }
            $statement = $this->pdo->prepare(
                "DELETE FROM certificates WHERE studentId = :studentId AND id = :id AND verificationStatus = 'unverified'"
            );
            $statement->execute(['studentId' => $studentId, 'id' => $certificateId]);
            if ($statement->rowCount() !== 1) {
                throw new ApiException(409, 'STALE_CERTIFICATE_STATE', 'Trạng thái chứng chỉ đã thay đổi.');
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return array<string,mixed>|null */
    private function findByIdForUpdate(string $studentId, string $certificateId, bool $isSqlite): ?array
    {
        $lockSuffix = $isSqlite ? '' : ' FOR UPDATE';
        $statement = $this->pdo->prepare(<<<SQL
            SELECT id, studentId, title, issuingOrganization, issueDate, expiryDate,
                   credentialId, credentialUrl, verificationStatus, verifiedBy, verifiedAt,
                   createdAt, updatedAt
            FROM certificates
            WHERE studentId = :studentId AND id = :id
            LIMIT 1{$lockSuffix}
        SQL
        );
        $statement->execute(['studentId' => $studentId, 'id' => $certificateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'studentId' => (string) $row['studentId'],
            'title' => (string) $row['title'],
            'issuingOrganization' => (string) $row['issuingOrganization'],
            'issueDate' => (string) $row['issueDate'],
            'expiryDate' => isset($row['expiryDate']) && is_string($row['expiryDate']) ? $row['expiryDate'] : null,
            'credentialId' => isset($row['credentialId']) && is_string($row['credentialId']) ? $row['credentialId'] : null,
            'credentialUrl' => isset($row['credentialUrl']) && is_string($row['credentialUrl']) ? $row['credentialUrl'] : null,
            'verificationStatus' => (string) $row['verificationStatus'],
            'verifiedBy' => isset($row['verifiedBy']) && is_string($row['verifiedBy']) ? $row['verifiedBy'] : null,
            'verifiedAt' => isset($row['verifiedAt']) && is_string($row['verifiedAt']) ? $row['verifiedAt'] : null,
            'createdAt' => (string) $row['createdAt'],
            'updatedAt' => (string) $row['updatedAt'],
        ];
    }
}
