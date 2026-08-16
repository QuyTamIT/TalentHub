<?php
declare(strict_types=1);
namespace TalentHub\Modules\Business\Repository;

use PDO;

final class BusinessRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByUserId(string $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT e.id, e.name, e.status, e.logoUrl, e.industry, e.companySize, e.foundedYear, e.taxCode, ' .
            'e.description, e.email, e.phone, e.website, e.address, e.verificationStatus, e.createdAt, e.updatedAt, ' .
            'em.memberRole, u.id AS userId, u.email AS accountEmail, u.fullName ' .
            'FROM enterprise_members em ' .
            'JOIN enterprises e ON e.id = em.enterpriseId ' .
            'JOIN users u ON u.id = em.userId ' .
            'WHERE em.userId = ? LIMIT 1'
        );
        $statement->execute([$userId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function update(string $enterpriseId, array $fields): void
    {
        if (empty($fields)) {
            return;
        }
        $allowed = [
            'name', 'logoUrl', 'industry', 'companySize',
            'foundedYear', 'taxCode', 'description',
            'email', 'phone', 'website', 'address', 'verificationStatus'
        ];
        $setClauses = [];
        $params = ['id' => $enterpriseId];
        foreach ($fields as $key => $val) {
            if (in_array($key, $allowed, true)) {
                $setClauses[] = "{$key} = :{$key}";
                $params[$key] = $val;
            }
        }
        if (empty($setClauses)) {
            return;
        }
        $sql = 'UPDATE enterprises SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }
}
