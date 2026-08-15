<?php
declare(strict_types=1);
namespace TalentHub\Modules\Business\Repository;

use PDO;

final class BusinessRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByUserId(string $userId): ?array
    {
        $statement=$this->pdo->prepare('SELECT e.id,e.name,e.status,e.logoUrl,e.industry,e.description,e.email,e.phone,e.website,e.address,e.verificationStatus,e.createdAt,e.updatedAt,em.memberRole,u.id AS userId,u.email AS accountEmail,u.fullName FROM enterprise_members em JOIN enterprises e ON e.id=em.enterpriseId JOIN users u ON u.id=em.userId WHERE em.userId=? LIMIT 1');
        $statement->execute([$userId]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    public function update(string $enterpriseId,array $fields): void
    {
        $statement=$this->pdo->prepare('UPDATE enterprises SET name=:name,logoUrl=:logoUrl,industry=:industry,description=:description,email=:email,phone=:phone,website=:website,address=:address WHERE id=:id');
        $statement->execute([...$fields,'id'=>$enterpriseId]);
    }
}
