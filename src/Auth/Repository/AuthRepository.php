<?php
declare(strict_types=1);
namespace TalentHub\Auth\Repository;

use PDO;

final class AuthRepository
{
    public function __construct(private readonly PDO $pdo) {}
    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array{$s=$this->pdo->prepare('SELECT u.id,u.email,u.passwordHash,u.fullName,u.status,r.code AS role FROM users u JOIN roles r ON r.id=u.roleId WHERE u.email=? LIMIT 1');$s->execute([$email]);$row=$s->fetch();return is_array($row)?$row:null;}
    /** @return array<string,mixed>|null */
    public function findById(string $id): ?array{$s=$this->pdo->prepare('SELECT u.id,u.email,u.passwordHash,u.fullName,u.status,r.code AS role FROM users u JOIN roles r ON r.id=u.roleId WHERE u.id=? LIMIT 1');$s->execute([$id]);$row=$s->fetch();return is_array($row)?$row:null;}
    public function recordLogin(string $id): void{$s=$this->pdo->prepare('UPDATE users SET lastLoginAt=UTC_TIMESTAMP(6) WHERE id=?');$s->execute([$id]);}
    public function updatePassword(string $id,string $hash): void{$s=$this->pdo->prepare('UPDATE users SET passwordHash=? WHERE id=?');$s->execute([$hash,$id]);}
    /** @param array<string,mixed> $metadata */
    public function audit(?string $userId,string $action,string $requestId,?string $ip,array $metadata=[]): void
    {
        $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&0x0f)|0x40);$bytes[8]=chr((ord($bytes[8])&0x3f)|0x80);$hex=bin2hex($bytes);$id=substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
        $s=$this->pdo->prepare('INSERT INTO audit_logs(id,userId,action,entityType,entityId,requestId,ipAddress,metadata) VALUES(?,?,?,\'user\',?,?,?,?)');$s->execute([$id,$userId,$action,$userId,$requestId,$ip,json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }
}
