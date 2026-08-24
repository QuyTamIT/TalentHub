<?php
declare(strict_types=1);
namespace TalentHub\Auth\Repository;

use PDO;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;
use TalentHub\Support\Uuid;

final class AuthRepository
{
    private ?bool $legacySchema=null;
    public function __construct(private readonly PDO $pdo) {}
    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array{$sql=$this->isLegacySchema()?'SELECT id,email,passwordHash,fullName,status,roles AS role FROM users WHERE email=? LIMIT 1':'SELECT u.id,u.email,u.passwordHash,u.fullName,u.status,r.code AS role FROM users u JOIN roles r ON r.id=u.roleId WHERE u.email=? LIMIT 1';$s=$this->pdo->prepare($sql);$s->execute([$email]);$row=$s->fetch();return is_array($row)?$row:null;}
    /** @return array<string,mixed>|null */
    public function findById(string $id): ?array{$sql=$this->isLegacySchema()?'SELECT id,email,passwordHash,fullName,status,roles AS role FROM users WHERE id=? LIMIT 1':'SELECT u.id,u.email,u.passwordHash,u.fullName,u.status,r.code AS role FROM users u JOIN roles r ON r.id=u.roleId WHERE u.id=? LIMIT 1';$s=$this->pdo->prepare($sql);$s->execute([$id]);$row=$s->fetch();return is_array($row)?$row:null;}
    public function recordLogin(string $id): void{if($this->isLegacySchema()){return;}$s=$this->pdo->prepare('UPDATE users SET lastLoginAt=UTC_TIMESTAMP(6) WHERE id=?');$s->execute([$id]);}
    public function updatePassword(string $id,string $hash): void{$s=$this->pdo->prepare('UPDATE users SET passwordHash=? WHERE id=?');$s->execute([$hash,$id]);}

    /** @return list<array{id:string,name:string,gradeLevel:int,academicYear:string,schoolId:string,schoolName:string}> */
    public function registrationClasses(): array
    {
        $classCondition=$this->isLegacySchema()?"s.status='active'":"c.status='active' AND s.status='active'";
        $statement=$this->pdo->query("SELECT c.id,c.name,c.gradeLevel,c.academicYear,s.id AS schoolId,s.name AS schoolName FROM classes c JOIN schools s ON s.id=c.schoolId WHERE {$classCondition} ORDER BY s.name,c.gradeLevel,c.name");
        return array_map(static fn(array $row):array=>['id'=>(string)$row['id'],'name'=>(string)$row['name'],'gradeLevel'=>(int)$row['gradeLevel'],'academicYear'=>(string)$row['academicYear'],'schoolId'=>(string)$row['schoolId'],'schoolName'=>(string)$row['schoolName']],$statement->fetchAll());
    }

    /** @param array{email:string,passwordHash:string,fullName:string,classId:string,dateOfBirth:string,phone:string} $data */
    public function createStudent(array $data,string $requestId,?string $ip): string
    {
        $this->pdo->beginTransaction();
        try{
            $legacy=$this->isLegacySchema();$role=null;
            if(!$legacy){
                $roleCount=(int)$this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
                if($roleCount===0){
                    require_once dirname(__DIR__,3).'/Database/seeds/System/RolePermissionSeeder.php';
                    (new RolePermissionSeeder())->runWithinTransaction($this->pdo);
                }
                $role=$this->pdo->query("SELECT id FROM roles WHERE code='student' LIMIT 1 FOR UPDATE")->fetchColumn();
                if(!is_string($role)||$role===''){throw new \RuntimeException('Student role is missing from a non-empty roles table. Run the system RBAC seed.');}
            }
            $classCondition=$legacy?"s.status='active'":"c.status='active' AND s.status='active'";
            $class=$this->pdo->prepare("SELECT c.id FROM classes c JOIN schools s ON s.id=c.schoolId WHERE c.id=? AND {$classCondition} LIMIT 1 FOR UPDATE");$class->execute([$data['classId']]);
            if($class->fetchColumn()===false){$this->pdo->rollBack();return '';}
            $userId=Uuid::v4();$profileId=Uuid::v4();
            if($legacy){$statement=$this->pdo->prepare("INSERT INTO users(id,email,passwordHash,fullName,roles,status) VALUES(?,?,?,?,'student','active')");$statement->execute([$userId,$data['email'],$data['passwordHash'],$data['fullName']]);}
            else{$statement=$this->pdo->prepare("INSERT INTO users(id,roleId,email,passwordHash,fullName,status) VALUES(?,?,?,?,?,'active')");$statement->execute([$userId,$role,$data['email'],$data['passwordHash'],$data['fullName']]);}
            $statement=$this->pdo->prepare("INSERT INTO student_profiles(id,userId,classId,dateOfBirth,phone,studyStatus) VALUES(?,?,?,?,?,'active')");$statement->execute([$profileId,$userId,$data['classId'],$data['dateOfBirth'],$data['phone']]);
            if($legacy){$statement=$this->pdo->prepare("INSERT INTO audit_logs(id,userId,action,entityType,entityId) VALUES(?,?,'auth.student_registered','user',?)");$statement->execute([Uuid::v4(),$userId,$userId]);}
            else{$statement=$this->pdo->prepare("INSERT INTO audit_logs(id,userId,action,entityType,entityId,requestId,ipAddress,metadata) VALUES(?,?,'auth.student_registered','user',?,?,?,?)");$statement->execute([Uuid::v4(),$userId,$userId,$requestId,$ip,json_encode(['role'=>'student'],JSON_THROW_ON_ERROR)]);}
            $this->pdo->commit();return $userId;
        }catch(\Throwable $exception){if($this->pdo->inTransaction()){$this->pdo->rollBack();}throw $exception;}
    }
    /** @param array<string,mixed> $metadata */
    public function audit(?string $userId,string $action,string $requestId,?string $ip,array $metadata=[]): void
    {
        if($this->isLegacySchema()){if($userId===null){return;}$s=$this->pdo->prepare('INSERT INTO audit_logs(id,userId,action,entityType,entityId) VALUES(?,?,?,\'user\',?)');$s->execute([Uuid::v4(),$userId,$action,$userId]);return;}
        $s=$this->pdo->prepare('INSERT INTO audit_logs(id,userId,action,entityType,entityId,requestId,ipAddress,metadata) VALUES(?,?,?,\'user\',?,?,?,?)');$s->execute([Uuid::v4(),$userId,$action,$userId,$requestId,$ip,json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }

    private function isLegacySchema(): bool
    {
        if($this->legacySchema!==null){return $this->legacySchema;}
        $s=$this->pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='roles'");$s->execute();
        return $this->legacySchema=(int)$s->fetchColumn()===1;
    }
}
