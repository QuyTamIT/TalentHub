<?php
declare(strict_types=1);
namespace TalentHub\Modules\Business\Repository;

use PDO;

final class BusinessRepository
{
    /** @var array<string,true>|null */
    private ?array $enterpriseColumns = null;

    public function __construct(private readonly PDO $pdo) {}

    public function findByUserId(string $userId): ?array
    {
        $optional = [];
        foreach (['companySize', 'foundedYear', 'taxCode'] as $column) {
            $optional[] = $this->hasEnterpriseColumn($column) ? "e.{$column}" : "NULL AS {$column}";
        }
        $memberRole = $this->hasColumn('enterprise_members', 'memberRole') ? 'em.memberRole' : 'em.role AS memberRole';
        $statement = $this->pdo->prepare(
            'SELECT e.id, e.name, e.status, e.logoUrl, e.industry, ' . implode(', ', $optional) . ', ' .
            'e.description, e.email, e.phone, e.website, e.address, e.verificationStatus, e.createdAt, e.updatedAt, ' .
            $memberRole . ', u.id AS userId, u.email AS accountEmail, u.fullName ' .
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
            if (in_array($key, $allowed, true) && $this->hasEnterpriseColumn($key)) {
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

    private function hasEnterpriseColumn(string $column): bool
    {
        if ($this->enterpriseColumns === null) {
            $statement = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='enterprises'");
            $this->enterpriseColumns = array_fill_keys(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)), true);
        }
        return isset($this->enterpriseColumns[$column]);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $statement->execute([$table, $column]);
        return (int) $statement->fetchColumn() === 1;
    }
}
