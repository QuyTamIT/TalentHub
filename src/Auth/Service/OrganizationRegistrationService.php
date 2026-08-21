<?php
declare(strict_types=1);
namespace TalentHub\Auth\Service;
use PDO;use RuntimeException;use TalentHub\Support\Uuid;
final class OrganizationRegistrationService{
 public function __construct(private readonly PDO $pdo){}
 /** @param array<string,string> $input @return array<string,string> */
 public function register(array $input):array{$type=strtolower(trim($input['type']??''));$name=trim($input['organizationName']??'');$fullName=trim($input['fullName']??'');$email=strtolower(trim($input['email']??''));$phone=trim($input['phone']??'');$address=trim($input['address']??'');$password=$input['password']??'';
  if(!in_array($type,['school','enterprise'],true)||mb_strlen($name)<2||mb_strlen($fullName)<2||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($phone)<6||mb_strlen($address)<5||strlen($password)<12){throw new RuntimeException('Vui lòng điền đầy đủ thông tin hợp lệ; mật khẩu tối thiểu 12 ký tự.');}
  $s=$this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');$s->execute([$email]);if((int)$s->fetchColumn()>0){throw new RuntimeException('Email đã được sử dụng.');}
  $userId=Uuid::v4();$organizationId=Uuid::v4();$hash=password_hash($password,PASSWORD_DEFAULT);$legacy=$this->columnExists('users','roles');$table=$type==='school'?'schools':'enterprises';$this->pdo->beginTransaction();try{
   if($legacy){$this->pdo->prepare("INSERT INTO users(id,email,passwordHash,fullName,roles,status) VALUES(?,?,?,?,?,'pending')")->execute([$userId,$email,$hash,$fullName,$type]);}else{$role=$this->pdo->prepare('SELECT id FROM roles WHERE code=?');$role->execute([$type]);$roleId=$role->fetchColumn();if(!is_string($roleId)){throw new RuntimeException('Vai trò tổ chức chưa được seed.');}$this->pdo->prepare("INSERT INTO users(id,roleId,email,passwordHash,fullName,status) VALUES(?,?,?,?,?,'pending')")->execute([$userId,$roleId,$email,$hash,$fullName]);}
   $cols=$this->columns($table);$all=['id'=>$organizationId,'name'=>$name,'status'=>$legacy?'pending':'inactive','email'=>$email,'phone'=>$phone,'address'=>$address,'verificationStatus'=>'pending'];$insert=array_intersect_key($all,array_flip($cols));$names=array_keys($insert);$this->pdo->prepare('INSERT INTO '.$table.'('.implode(',',$names).') VALUES('.implode(',',array_fill(0,count($names),'?')).')')->execute(array_values($insert));
   $member=$type==='school'?'school_members':'enterprise_members';if($this->tableExists($member)){$foreign=$type==='school'?'schoolId':'enterpriseId';$this->pdo->prepare("INSERT INTO {$member}(id,{$foreign},userId,memberRole) VALUES(?,?,?,'admin')")->execute([Uuid::v4(),$organizationId,$userId]);}
   $this->pdo->prepare('INSERT INTO audit_logs(id,userId,action,entityType,entityId) VALUES(?,?,?,?,?)')->execute([Uuid::v4(),$userId,'auth.organization_registration_submitted',$type,$organizationId]);$this->pdo->commit();return ['id'=>$userId,'organizationId'=>$organizationId,'email'=>$email,'type'=>$type,'status'=>'pending'];
  }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}
 private function tableExists(string $t):bool{$s=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$s->execute([$t]);return(int)$s->fetchColumn()===1;}
 private function columnExists(string $t,string $c):bool{return in_array($c,$this->columns($t),true);}
 /** @return list<string> */private function columns(string $t):array{$s=$this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');$s->execute([$t]);return$s->fetchAll(PDO::FETCH_COLUMN);}
}
