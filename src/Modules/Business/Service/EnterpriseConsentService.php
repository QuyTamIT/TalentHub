<?php

declare(strict_types=1);

namespace TalentHub\Modules\Business\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;
use Throwable;

final class EnterpriseConsentService
{
    private const SCOPES = [
        'discovery' => 'enterprise_talent_discovery', 'enterprise_talent_discovery' => 'enterprise_talent_discovery',
        'contact' => 'enterprise_talent_contact', 'enterprise_talent_contact' => 'enterprise_talent_contact',
        'application' => 'application_profile_share', 'application_profile_share' => 'application_profile_share',
    ];

    public function __construct(private readonly PDO $pdo) {}

    public function grant(string $studentId, string $enterpriseId, string $scope, ?DateTimeImmutable $expiresAt, string $requestId): array
    {
        $studentId=$this->uuid($studentId,'studentId');$enterpriseId=$this->uuid($enterpriseId,'enterpriseId');$requestId=$this->requestId($requestId);$scope=$this->scope($scope);
        $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$expiresAt=($expiresAt??$now->modify('+90 days'))->setTimezone(new DateTimeZone('UTC'));
        if($expiresAt<=$now)throw new ApiException(422,'VALIDATION_FAILED','Thời hạn consent phải nằm trong tương lai.');
        $owns=!$this->pdo->inTransaction();if($owns)$this->pdo->beginTransaction();
        try{
            $student=$this->fetchOne('SELECT id,userId FROM student_profiles WHERE id=:id LIMIT 1'.$this->lockSuffix(),['id'=>$studentId]);
            $enterprise=$this->fetchOne("SELECT id FROM enterprises WHERE id=:id AND status='active' AND verificationStatus='verified' LIMIT 1",['id'=>$enterpriseId]);
            if($student===null)throw new ApiException(404,'RESOURCE_NOT_FOUND','Không tìm thấy hồ sơ học viên.');
            if($enterprise===null)throw new ApiException(422,'ENTERPRISE_NOT_ELIGIBLE','Doanh nghiệp chưa được xác minh hoặc không hoạt động.');
            $nowText=$now->format('Y-m-d H:i:s.u');$expiresText=$expiresAt->format('Y-m-d H:i:s.u');
            $consent=$this->fetchOne('SELECT id FROM privacy_consents WHERE studentId=:studentId AND scope=:scope AND isGranted=1 AND revokedAt IS NULL ORDER BY createdAt DESC LIMIT 1'.$this->lockSuffix(),['studentId'=>$studentId,'scope'=>$scope]);
            if($consent===null){$consentId=Uuid::v4();$insert=$this->pdo->prepare('INSERT INTO privacy_consents (id,studentId,scope,isGranted,policyVersion,grantedAt,revokedAt,createdAt) VALUES (:id,:studentId,:scope,1,\'enterprise-consent-1.0\',:grantedAt,NULL,:createdAt)');$insert->execute(['id'=>$consentId,'studentId'=>$studentId,'scope'=>$scope,'grantedAt'=>$nowText,'createdAt'=>$nowText]);}else{$consentId=(string)$consent['id'];}
            $existing=$this->fetchOne('SELECT id,revokedAt,expiresAt FROM enterprise_talent_access_grants WHERE studentId=:studentId AND enterpriseId=:enterpriseId AND scope=:scope LIMIT 1'.$this->lockSuffix(),['studentId'=>$studentId,'enterpriseId'=>$enterpriseId,'scope'=>$scope]);
            if($existing!==null && $existing['revokedAt']===null && (string)$existing['expiresAt']>$nowText){if($owns)$this->pdo->commit();return ['id'=>(string)$existing['id'],'studentId'=>$studentId,'enterpriseId'=>$enterpriseId,'scope'=>$scope,'expiresAt'=>(string)$existing['expiresAt'],'revokedAt'=>null];}
            $id=$existing!==null?(string)$existing['id']:Uuid::v4();
            if($existing===null){$statement=$this->pdo->prepare('INSERT INTO enterprise_talent_access_grants (id,studentId,enterpriseId,consentId,scope,grantedAt,expiresAt,revokedAt,createdAt,updatedAt) VALUES (:id,:studentId,:enterpriseId,:consentId,:scope,:grantedAt,:expiresAt,NULL,:createdAt,:updatedAt)');$statement->execute(['id'=>$id,'studentId'=>$studentId,'enterpriseId'=>$enterpriseId,'consentId'=>$consentId,'scope'=>$scope,'grantedAt'=>$nowText,'expiresAt'=>$expiresText,'createdAt'=>$nowText,'updatedAt'=>$nowText]);}
            else{$statement=$this->pdo->prepare('UPDATE enterprise_talent_access_grants SET consentId=:consentId,grantedAt=:grantedAt,expiresAt=:expiresAt,revokedAt=NULL,updatedAt=:updatedAt WHERE id=:id');$statement->execute(['consentId'=>$consentId,'grantedAt'=>$nowText,'expiresAt'=>$expiresText,'updatedAt'=>$nowText,'id'=>$id]);}
            $this->audit((string)$student['userId'],'enterprise_consent.granted',$id,$requestId,['studentId'=>$studentId,'enterpriseId'=>$enterpriseId,'scope'=>$scope,'expiresAt'=>$expiresText],$nowText);
            if($owns)$this->pdo->commit();return ['id'=>$id,'studentId'=>$studentId,'enterpriseId'=>$enterpriseId,'scope'=>$scope,'expiresAt'=>$expiresText,'revokedAt'=>null];
        }catch(Throwable $exception){if($owns&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $exception;}
    }

    public function revoke(string $studentId,string $enterpriseId,string $scope,string $requestId): array
    {
        $studentId=$this->uuid($studentId,'studentId');$enterpriseId=$this->uuid($enterpriseId,'enterpriseId');$scope=$this->scope($scope);$requestId=$this->requestId($requestId);$now=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $owns=!$this->pdo->inTransaction();if($owns)$this->pdo->beginTransaction();
        try{$row=$this->fetchOne('SELECT g.id,g.revokedAt,sp.userId FROM enterprise_talent_access_grants g INNER JOIN student_profiles sp ON sp.id=g.studentId WHERE g.studentId=:studentId AND g.enterpriseId=:enterpriseId AND g.scope=:scope LIMIT 1'.$this->lockSuffix(),['studentId'=>$studentId,'enterpriseId'=>$enterpriseId,'scope'=>$scope]);if($row===null)throw new ApiException(404,'RESOURCE_NOT_FOUND','Không tìm thấy consent theo doanh nghiệp.');if($row['revokedAt']===null){$update=$this->pdo->prepare('UPDATE enterprise_talent_access_grants SET revokedAt=:revokedAt,updatedAt=:updatedAt WHERE id=:id AND revokedAt IS NULL');$update->execute(['revokedAt'=>$now,'updatedAt'=>$now,'id'=>$row['id']]);$this->audit((string)$row['userId'],'enterprise_consent.revoked',(string)$row['id'],$requestId,['studentId'=>$studentId,'enterpriseId'=>$enterpriseId,'scope'=>$scope],$now);}if($owns)$this->pdo->commit();return ['id'=>(string)$row['id'],'studentId'=>$studentId,'enterpriseId'=>$enterpriseId,'scope'=>$scope,'revokedAt'=>$row['revokedAt']??$now];}catch(Throwable $exception){if($owns&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $exception;}
    }

    private function scope(string $scope): string{$scope=strtolower(trim($scope));if(!isset(self::SCOPES[$scope]))throw new ApiException(422,'VALIDATION_FAILED','Consent scope không hợp lệ.');return self::SCOPES[$scope];}
    private function uuid(string $value,string $field):string{if(!Uuid::isValid($value))throw new ApiException(422,'VALIDATION_FAILED',"{$field} không hợp lệ.");return strtolower($value);}
    private function requestId(string $value):string{if(preg_match('/\A[A-Za-z0-9_-]{16,64}\z/',$value)!==1)throw new ApiException(422,'VALIDATION_FAILED','requestId không hợp lệ.');return substr($value,0,26);}
    private function fetchOne(string $sql,array $params):?array{$s=$this->pdo->prepare($sql);$s->execute($params);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;}
    private function lockSuffix():string{return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE';}
    private function audit(string $userId,string $action,string $entityId,string $requestId,array $metadata,string $now):void{$s=$this->pdo->prepare('INSERT INTO audit_logs (id,userId,action,entityType,entityId,requestId,ipAddress,metadata,createdAt) VALUES (:id,:userId,:action,\'enterprise_consent\',:entityId,:requestId,NULL,:metadata,:createdAt)');$s->execute(['id'=>Uuid::v4(),'userId'=>$userId,'action'=>$action,'entityId'=>$entityId,'requestId'=>$requestId,'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),'createdAt'=>$now]);}
}
