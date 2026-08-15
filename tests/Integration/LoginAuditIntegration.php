<?php
declare(strict_types=1);
namespace TalentHub\Tests\Integration;

use PDO;
use RuntimeException;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;
use TalentHub\Database\Seeds\Testing\MinimalAuthRbacSeeder;
use TalentHub\Http\ApiException;

final class LoginAuditIntegration
{
    private const IP='203.0.113.25';

    /** @return list<string> */
    public function run(PDO $pdo,string $database,MigrationRunner $runner,string $password): array
    {
        if(preg_match('/test/i',$database)!==1){throw new RuntimeException('Login audit integration requires DB_DATABASE containing test.');}
        if((string)$pdo->query('SELECT DATABASE()')->fetchColumn()!==$database){throw new RuntimeException('Connected database mismatch.');}
        if((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0){throw new RuntimeException('Login audit integration requires an empty database.');}
        try{
            $runner->migrate();(new RolePermissionSeeder())->run($pdo);(new MinimalAuthRbacSeeder())->run($pdo,'test',$password);$auth=new AuthService(new AuthRepository($pdo));
            $unknownMessage=$this->expectFailure($auth,'unknown@test.talenthub.local','wrong-password','audit-unknown-00000000001',401,'INVALID_CREDENTIALS');
            $knownMessage=$this->expectFailure($auth,'student@test.talenthub.local','wrong-password','audit-known-0000000000001',401,'INVALID_CREDENTIALS');
            if($unknownMessage!==$knownMessage){throw new RuntimeException('Known and unknown account failures expose different messages.');}
            $studentId=(string)$pdo->query("SELECT id FROM users WHERE email='student@test.talenthub.local'")->fetchColumn();
            $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$studentId]);
            $this->expectFailure($auth,'student@test.talenthub.local',$password,'audit-inactive-0000000001',403,'ACCOUNT_NOT_ACTIVE');
            $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$studentId]);
            $auth->login(['email'=>'student@test.talenthub.local','password'=>$password],'audit-success-000000000001',self::IP);
            $this->assertAudit($pdo,'audit-unknown-00000000001','auth.login_failed',null,'invalid_credentials');
            $this->assertAudit($pdo,'audit-known-0000000000001','auth.login_failed',$studentId,'invalid_credentials');
            $this->assertAudit($pdo,'audit-inactive-0000000001','auth.login_failed',$studentId,'account_not_active');
            $this->assertAudit($pdo,'audit-success-000000000001','auth.login_succeeded',$studentId,null);
            return ['generic failure response: OK','unknown/known failure audit identity: OK','inactive account reason: OK','successful login audit: OK','sensitive metadata exclusion: OK'];
        }finally{try{$runner->rollback(null,1);}catch(\Throwable){}}
    }

    private function expectFailure(AuthService $auth,string $email,string $password,string $requestId,int $status,string $code): string
    {
        try{$auth->login(['email'=>$email,'password'=>$password],$requestId,self::IP);}catch(ApiException $exception){if($exception->status===$status&&$exception->errorCode===$code){return $exception->getMessage();}throw $exception;}throw new RuntimeException("Expected {$status} {$code}.");
    }

    private function assertAudit(PDO $pdo,string $requestId,string $action,?string $userId,?string $reason): void
    {
        $statement=$pdo->prepare('SELECT userId,entityId,action,ipAddress,metadata FROM audit_logs WHERE requestId=?');$statement->execute([$requestId]);$row=$statement->fetch();
        if(!is_array($row)||$row['action']!==$action||$row['ipAddress']!==self::IP||$row['userId']!==$userId||$row['entityId']!==$userId){throw new RuntimeException("Audit identity mismatch for {$requestId}.");}
        $metadata=json_decode((string)$row['metadata'],true,512,JSON_THROW_ON_ERROR);if(!is_array($metadata)){throw new RuntimeException('Audit metadata must be JSON.');}
        if(($metadata['reason']??null)!==$reason){throw new RuntimeException("Audit reason mismatch for {$requestId}.");}
        $serialized=strtolower(json_encode($metadata,JSON_THROW_ON_ERROR));foreach(['password','hash','email'] as $forbidden){if(str_contains($serialized,$forbidden)){throw new RuntimeException("Audit metadata contains forbidden field {$forbidden}.");}}
    }
}
