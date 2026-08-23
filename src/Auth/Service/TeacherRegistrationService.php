<?php
declare(strict_types=1);

namespace TalentHub\Auth\Service;

use PDO;
use RuntimeException;
use TalentHub\Support\Uuid;

final class TeacherRegistrationService
{
    public function __construct(private readonly PDO $pdo) {}

    /** @param array<string,string> $input @return array<string,string> */
    public function register(array $input): array
    {
        $fullName=trim($input['fullName']??'');$email=strtolower(trim($input['email']??''));
        $phone=trim($input['phone']??'');$specialization=trim($input['specialization']??'');
        $schoolId=trim($input['schoolId']??'');$password=$input['password']??'';
        if(mb_strlen($fullName)<2||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($phone)<6||mb_strlen($specialization)<2||!Uuid::isValid($schoolId)||strlen($password)<12){throw new RuntimeException('Vui lòng điền đầy đủ thông tin hợp lệ; mật khẩu tối thiểu 12 ký tự.');}
        $school=$this->pdo->prepare("SELECT id FROM schools WHERE id=? AND status='active'");$school->execute([$schoolId]);
        if(!is_string($school->fetchColumn())){throw new RuntimeException('Nhà trường đã chọn không tồn tại hoặc chưa hoạt động.');}
        $duplicate=$this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');$duplicate->execute([$email]);
        if((int)$duplicate->fetchColumn()>0){throw new RuntimeException('Email đã được sử dụng.');}

        $userId=Uuid::v4();$profileId=Uuid::v4();$hash=password_hash($password,PASSWORD_DEFAULT);
        $legacy=$this->columnExists('users','roles');$this->pdo->beginTransaction();
        try{
            if($legacy){$this->pdo->prepare("INSERT INTO users(id,email,passwordHash,fullName,roles,status) VALUES(?,?,?,?,?,'pending')")->execute([$userId,$email,$hash,$fullName,'teacher']);}
            else{$role=$this->pdo->prepare("SELECT id FROM roles WHERE code='teacher'");$role->execute();$roleId=$role->fetchColumn();if(!is_string($roleId)){throw new RuntimeException('Vai trò giáo viên chưa được cấu hình.');}$this->pdo->prepare("INSERT INTO users(id,roleId,email,passwordHash,fullName,status) VALUES(?,?,?,?,?,'pending')")->execute([$userId,$roleId,$email,$hash,$fullName]);}
            $columns=$this->columns('teacher_profiles');$profile=['id'=>$profileId,'userId'=>$userId,'schoolId'=>$schoolId,'isSchoolAdmin'=>0,'phone'=>$phone,'specialization'=>$specialization];$profile=array_intersect_key($profile,array_flip($columns));$names=array_keys($profile);
            $this->pdo->prepare('INSERT INTO teacher_profiles('.implode(',',$names).') VALUES('.implode(',',array_fill(0,count($names),'?')).')')->execute(array_values($profile));
            if($legacy){$this->pdo->prepare("INSERT INTO audit_logs(id,userId,action,entityType,entityId) VALUES(?,?,?,'user',?)")->execute([Uuid::v4(),$userId,'auth.teacher_registration_submitted',$userId]);}
            else{$this->pdo->prepare("INSERT INTO audit_logs(id,userId,action,entityType,entityId) VALUES(?,?,?,'user',?)")->execute([Uuid::v4(),$userId,'auth.teacher_registration_submitted',$userId]);}
            $this->pdo->commit();return ['id'=>$userId,'email'=>$email,'status'=>'pending'];
        }catch(\Throwable $exception){if($this->pdo->inTransaction()){$this->pdo->rollBack();}throw $exception;}
    }

    private function columnExists(string $table,string $column): bool{return in_array($column,$this->columns($table),true);}
    /** @return list<string> */
    private function columns(string $table): array{$statement=$this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');$statement->execute([$table]);return $statement->fetchAll(PDO::FETCH_COLUMN);}
}
