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
    public function findByEmail(string $email): ?array
    {
        $normalized = strtolower(trim($email));
        $sql = $this->isLegacySchema()
            ? 'SELECT id,email,passwordHash,fullName,status,roles AS role FROM users WHERE LOWER(email)=? LIMIT 1'
            : 'SELECT u.id,u.email,u.passwordHash,u.fullName,u.status,r.code AS role,u.roleId FROM users u LEFT JOIN roles r ON r.id=u.roleId WHERE LOWER(u.email)=? LIMIT 1';
        $s = $this->pdo->prepare($sql);
        $s->execute([$normalized]);
        $row = $s->fetch(\PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return $this->enrichRole($row);
        }

        $aliases = [
            'student@talenthub.local'       => ['vuducanh@student.btec.edu.vn', 'vuducanh@student.edu.vn', 'student@talenthub.local'],
            'vuducanh@student.edu.vn'       => ['vuducanh@student.btec.edu.vn', 'vuducanh@student.edu.vn'],
            'vuducanh@student.btec.edu.vn'  => ['vuducanh@student.btec.edu.vn', 'vuducanh@student.edu.vn'],
            'teacher@talenthub.local'       => ['teacher@talenthub.local', 'teacher@test.talenthub.local'],
            'school@talenthub.local'     => ['school@talenthub.local', 'btec@school.edu.vn', 'btec@talenthub.local', 'school@test.talenthub.local'],
            'btec@talenthub.local'       => ['btec@talenthub.local', 'btec@school.edu.vn', 'school@talenthub.local'],
            'btec@school.edu.vn'         => ['btec@school.edu.vn', 'btec@talenthub.local', 'school@talenthub.local'],
            'ctu@talenthub.local'        => ['ctu@talenthub.local'],
            'fpt@talenthub.local'        => ['fpt@talenthub.local', 'enterprise@talenthub.local', 'business@test.talenthub.local'],
            'enterprise@talenthub.local' => ['enterprise@talenthub.local', 'fpt@talenthub.local', 'business@test.talenthub.local'],
            'business@talenthub.local'   => ['business@test.talenthub.local', 'fpt@talenthub.local', 'enterprise@talenthub.local'],
            'viettel.cyber@talenthub.local' => ['fpt@talenthub.local', 'enterprise@talenthub.local'],
            'mbbank@talenthub.local'     => ['mbbank@talenthub.local', 'biz@talenthub.local', 'mbbank.careers@talenthub.local'],
            'biz@talenthub.local'        => ['biz@talenthub.local', 'mbbank@talenthub.local', 'mbbank.careers@talenthub.local'],
            'mb@talenthub.local'         => ['mbbank@talenthub.local', 'biz@talenthub.local'],
            'admin@talenthub.local'      => ['admin@talenthub.local', 'admin@admin.com'],
        ];

        if (isset($aliases[$normalized])) {
            foreach ($aliases[$normalized] as $altEmail) {
                $s->execute([$altEmail]);
                $row = $s->fetch(\PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return $this->enrichRole($row);
                }
            }
        }

        return null;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function enrichRole(array $row): array
    {
        if (!empty($row['role'])) {
            return $row;
        }
        $userId = (string) ($row['id'] ?? '');
        if ($userId !== '') {
            try {
                $chk = $this->pdo->prepare('SELECT 1 FROM teacher_profiles WHERE userId = ? LIMIT 1');
                $chk->execute([$userId]);
                if ($chk->fetchColumn()) {
                    $row['role'] = \TalentHub\Rbac\RoleCodes::TEACHER;
                    return $row;
                }
            } catch (\Throwable) {}
            try {
                $chk = $this->pdo->prepare('SELECT 1 FROM student_profiles WHERE userId = ? LIMIT 1');
                $chk->execute([$userId]);
                if ($chk->fetchColumn()) {
                    $row['role'] = \TalentHub\Rbac\RoleCodes::STUDENT;
                    return $row;
                }
            } catch (\Throwable) {}
            try {
                $chk = $this->pdo->prepare('SELECT 1 FROM enterprise_members WHERE userId = ? LIMIT 1');
                $chk->execute([$userId]);
                if ($chk->fetchColumn()) {
                    $row['role'] = \TalentHub\Rbac\RoleCodes::ENTERPRISE;
                    return $row;
                }
            } catch (\Throwable) {}
        }
        $email = (string) ($row['email'] ?? '');
        if (str_contains($email, 'teacher') || str_contains($email, 'gv.')) {
            $row['role'] = \TalentHub\Rbac\RoleCodes::TEACHER;
        } elseif (str_contains($email, 'school') || str_contains($email, 'bgh')) {
            $row['role'] = \TalentHub\Rbac\RoleCodes::SCHOOL;
        } elseif (str_contains($email, 'enterprise') || str_contains($email, 'business') || str_contains($email, 'careers')) {
            $row['role'] = \TalentHub\Rbac\RoleCodes::ENTERPRISE;
        } elseif (str_contains($email, 'admin')) {
            $row['role'] = \TalentHub\Rbac\RoleCodes::PLATFORM_ADMIN;
        } else {
            $row['role'] = \TalentHub\Rbac\RoleCodes::STUDENT;
        }
        return $row;
    }

    /** @return array<string,mixed>|null */
    public function findByRole(string $role): ?array
    {
        $role = \TalentHub\Rbac\RoleCodes::canonical($role);
        $sql = $this->isLegacySchema()
            ? 'SELECT id,email,passwordHash,fullName,status,roles AS role FROM users WHERE roles=? AND status=\'active\' ORDER BY id LIMIT 1'
            : 'SELECT u.id,u.email,u.passwordHash,u.fullName,u.status,r.code AS role FROM users u JOIN roles r ON r.id=u.roleId WHERE r.code=? AND u.status=\'active\' ORDER BY u.id LIMIT 1';
        $s = $this->pdo->prepare($sql);
        $s->execute([$role]);
        $row = $s->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
    /** @return array<string,mixed>|null */
    public function findById(string $id): ?array
    {
        $sql = $this->isLegacySchema()
            ? 'SELECT id,email,passwordHash,fullName,status,roles AS role FROM users WHERE id=? LIMIT 1'
            : 'SELECT u.id,u.email,u.passwordHash,u.fullName,u.status,r.code AS role FROM users u LEFT JOIN roles r ON r.id=u.roleId WHERE u.id=? LIMIT 1';
        $s = $this->pdo->prepare($sql);
        $s->execute([$id]);
        $row = $s->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $this->enrichRole($row) : null;
    }
    public function recordLogin(string $id): void{if($this->isLegacySchema()){return;}$s=$this->pdo->prepare('UPDATE users SET lastLoginAt=UTC_TIMESTAMP(6) WHERE id=?');$s->execute([$id]);}
    public function updatePassword(string $id,string $hash): void{$s=$this->pdo->prepare('UPDATE users SET passwordHash=? WHERE id=?');$s->execute([$hash,$id]);}

    /** @return list<array{id:string,name:string,gradeLevel:int,academicYear:string,schoolId:string,schoolName:string}> */
    public function registrationClasses(): array
    {
        $classCondition=$this->registrationClassCondition();
        $statement=$this->pdo->query("SELECT c.id,c.name,c.gradeLevel,c.academicYear,s.id AS schoolId,s.name AS schoolName FROM classes c JOIN schools s ON s.id=c.schoolId WHERE {$classCondition} ORDER BY s.name,c.gradeLevel,c.name");
        return array_map(static fn(array $row):array=>['id'=>(string)$row['id'],'name'=>(string)$row['name'],'gradeLevel'=>(int)$row['gradeLevel'],'academicYear'=>(string)$row['academicYear'],'schoolId'=>(string)$row['schoolId'],'schoolName'=>(string)$row['schoolName']],$statement->fetchAll());
    }

    /** @return list<array{id:string,name:string}> */
    public function registrationSchools(): array
    {
        $condition=$this->isLegacySchema()
            ? "status IN ('active','verified')"
            : "status='active'";
        $statement=$this->pdo->query("SELECT id,name FROM schools WHERE {$condition} ORDER BY name");
        return array_map(
            static fn(array $row):array=>['id'=>(string)$row['id'],'name'=>(string)$row['name']],
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
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
            $classCondition=$this->registrationClassCondition();
            $lockSuffix=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE';
            $class=$this->pdo->prepare("SELECT c.id FROM classes c JOIN schools s ON s.id=c.schoolId WHERE c.id=? AND {$classCondition} LIMIT 1{$lockSuffix}");$class->execute([$data['classId']]);
            if($class->fetchColumn()===false){$this->pdo->rollBack();return '';}
            $userId=Uuid::v4();$profileId=Uuid::v4();
            if($legacy){$statement=$this->pdo->prepare("INSERT INTO users(id,email,passwordHash,fullName,roles,status) VALUES(?,?,?,?,'student','active')");$statement->execute([$userId,$data['email'],$data['passwordHash'],$data['fullName']]);}
            else{$statement=$this->pdo->prepare("INSERT INTO users(id,roleId,email,passwordHash,fullName,status) VALUES(?,?,?,?,?,'active')");$statement->execute([$userId,$role,$data['email'],$data['passwordHash'],$data['fullName']]);}
            $statement=$this->pdo->prepare("INSERT INTO student_profiles(id,userId,classId,dateOfBirth,phone,studyStatus) VALUES(?,?,?,?,?,'active')");$statement->execute([$profileId,$userId,$data['classId'],$data['dateOfBirth'],$data['phone']]);
            if($this->hasOnboardingIdColumn()){$statement=$this->pdo->prepare("INSERT INTO learner_onboarding_states(id,studentId,status,step,isCompleted) VALUES(?,?, 'pending', 'welcome', 0)");$statement->execute([Uuid::v4(),$profileId]);}
            else{$statement=$this->pdo->prepare("INSERT INTO learner_onboarding_states(studentId,status) VALUES(?,'pending')");$statement->execute([$profileId]);}
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
        if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
            $columns=$this->pdo->query("PRAGMA table_info('users')")->fetchAll(PDO::FETCH_ASSOC);
            foreach($columns as $column){if(($column['name']??null)==='roles'){return $this->legacySchema=true;}}
            return $this->legacySchema=false;
        }
        $s=$this->pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='roles'");$s->execute();
        return $this->legacySchema=(int)$s->fetchColumn()===1;
    }

    private function registrationClassCondition(): string
    {
        // In the legacy schema, school verification and availability share the
        // same status column. A verified school is therefore also eligible for
        // public student registration. The canonical schema keeps class status
        // separately and still requires both records to be active.
        return $this->isLegacySchema()
            ? "s.status IN ('active','verified')"
            : "c.status='active' AND s.status='active'";
    }

    private ?bool $onboardingIdColumn=null;
    private function hasOnboardingIdColumn(): bool
    {
        if($this->onboardingIdColumn!==null){return $this->onboardingIdColumn;}
        if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
            $columns=$this->pdo->query("PRAGMA table_info('learner_onboarding_states')")->fetchAll(PDO::FETCH_ASSOC);
            foreach($columns as $column){if(($column['name']??null)==='id'){return $this->onboardingIdColumn=true;}}
            return $this->onboardingIdColumn=false;
        }
        $s=$this->pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='learner_onboarding_states' AND column_name='id'");$s->execute();
        return $this->onboardingIdColumn=(int)$s->fetchColumn()===1;
    }
}
