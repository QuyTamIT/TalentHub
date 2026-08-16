<?php
declare(strict_types=1);
namespace TalentHub\Auth\Service;

use DateTimeImmutable;
use PDOException;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;

final class AuthService
{
    private const REGISTER_FIELDS=['email','password','fullName','classId','dateOfBirth','phone'];
    public function __construct(private readonly AuthRepository $repository) {}

    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    public function registerStudent(array $input,string $requestId='system',?string $ip=null): array
    {
        $details=[];
        foreach(array_keys($input) as $field){if(!in_array($field,self::REGISTER_FIELDS,true)){$details[]=['field'=>(string)$field,'code'=>'FIELD_NOT_ALLOWED','message'=>'Không được phép gửi field này.'];}}
        $email=strtolower(trim(is_string($input['email']??null)?$input['email']:''));
        $password=is_string($input['password']??null)?$input['password']:'';
        $fullName=trim(is_string($input['fullName']??null)?$input['fullName']:'');
        $classId=strtolower(trim(is_string($input['classId']??null)?$input['classId']:''));
        $dateOfBirth=is_string($input['dateOfBirth']??null)?$input['dateOfBirth']:'';
        $phone=trim(is_string($input['phone']??null)?$input['phone']:'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>255){$details[]=['field'=>'email','code'=>'INVALID_EMAIL','message'=>'Email không đúng định dạng.'];}
        if(strlen($password)<12||strlen($password)>255){$details[]=['field'=>'password','code'=>'INVALID_LENGTH','message'=>'Mật khẩu phải có từ 12 đến 255 ký tự.'];}
        if(mb_strlen($fullName)<2||mb_strlen($fullName)>150){$details[]=['field'=>'fullName','code'=>'INVALID_LENGTH','message'=>'Họ tên phải có từ 2 đến 150 ký tự.'];}
        if(!Uuid::isValid($classId)){$details[]=['field'=>'classId','code'=>'INVALID_UUID','message'=>'classId phải là UUID hợp lệ.'];}
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$dateOfBirth);
        if(!$date||$date->format('Y-m-d')!==$dateOfBirth||$date>new DateTimeImmutable('today')){$details[]=['field'=>'dateOfBirth','code'=>'INVALID_DATE','message'=>'Ngày sinh phải hợp lệ và không nằm trong tương lai.'];}
        if(mb_strlen($phone)<6||mb_strlen($phone)>30||preg_match('/^[0-9+() .-]+$/',$phone)!==1){$details[]=['field'=>'phone','code'=>'INVALID_PHONE','message'=>'Số điện thoại không hợp lệ.'];}
        if($details!==[]){throw new ApiException(422,'VALIDATION_FAILED','Dữ liệu gửi lên không hợp lệ.',$details);}
        if($this->repository->findByEmail($email)!==null){throw new ApiException(409,'DUPLICATE_RESOURCE','Email đã được sử dụng.',[['field'=>'email','code'=>'DUPLICATE_EMAIL','message'=>'Email này đã được sử dụng.']]);}
        $hash=password_hash($password,PASSWORD_DEFAULT);if($hash===false){throw new ApiException(500,'INTERNAL_ERROR','Không thể tạo tài khoản.');}
        try{$id=$this->repository->createStudent(['email'=>$email,'passwordHash'=>$hash,'fullName'=>$fullName,'classId'=>$classId,'dateOfBirth'=>$dateOfBirth,'phone'=>$phone],$requestId,$ip);}
        catch(PDOException $exception){if((int)($exception->errorInfo[1]??0)===1062){throw new ApiException(409,'DUPLICATE_RESOURCE','Email đã được sử dụng.',[['field'=>'email','code'=>'DUPLICATE_EMAIL','message'=>'Email này đã được sử dụng.']]);}throw $exception;}
        if($id===''){throw new ApiException(422,'VALIDATION_FAILED','Lớp không tồn tại hoặc không còn nhận học viên.',[['field'=>'classId','code'=>'CLASS_NOT_AVAILABLE','message'=>'Lớp không tồn tại hoặc không còn hoạt động.']]);}
        return $this->current($id);
    }
    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    public function login(array $input,string $requestId='system',?string $ip=null): array
    {
        $email=strtolower(trim(is_string($input['email']??null)?$input['email']:''));$password=is_string($input['password']??null)?$input['password']:'';
        $details=[];
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>255){$details[]=['field'=>'email','code'=>'INVALID_EMAIL','message'=>'Email không đúng định dạng.'];}
        if($password===''){$details[]=['field'=>'password','code'=>'REQUIRED','message'=>'Vui lòng nhập mật khẩu.'];}
        if($details!==[]){throw new ApiException(422,'VALIDATION_FAILED','Vui lòng kiểm tra lại thông tin đăng nhập.',$details);}
        $row=$this->repository->findByEmail($email);
        if(!$row||!password_verify($password,(string)$row['passwordHash'])){$this->repository->audit($row?(string)$row['id']:null,'auth.login_failed',$requestId,$ip,['reason'=>'invalid_credentials']);throw new ApiException(401,'INVALID_CREDENTIALS','Email hoặc mật khẩu không chính xác.');}
        if($row['status']!=='active'){$this->repository->audit((string)$row['id'],'auth.login_failed',$requestId,$ip,['reason'=>'account_not_active']);throw new ApiException(403,'ACCOUNT_NOT_ACTIVE','Tài khoản chưa được phép đăng nhập.');}
        if(password_needs_rehash((string)$row['passwordHash'],PASSWORD_DEFAULT)){$rehash=password_hash($password,PASSWORD_DEFAULT);if($rehash!==false){$this->repository->updatePassword((string)$row['id'],$rehash);}}
        $this->repository->recordLogin((string)$row['id']);$this->repository->audit((string)$row['id'],'auth.login_succeeded',$requestId,$ip);return $this->publicUser($row);
    }
    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    public function current(string $id): array{$row=$this->repository->findById($id);if(!$row||$row['status']!=='active'){throw new ApiException(401,'SESSION_EXPIRED','Phiên đăng nhập không còn hợp lệ.');}return $this->publicUser($row);}
    public function changePassword(string $id,array $input): void
    {
        $current=is_string($input['currentPassword']??null)?$input['currentPassword']:'';$next=is_string($input['newPassword']??null)?$input['newPassword']:'';
        $row=$this->repository->findById($id);
        if(!$row||!password_verify($current,(string)$row['passwordHash'])){throw new ApiException(401,'INVALID_CREDENTIALS','Mật khẩu hiện tại không chính xác.');}
        if(strlen($next)<12||strlen($next)>255){throw new ApiException(422,'VALIDATION_FAILED','Mật khẩu mới phải có từ 12 đến 255 ký tự.');}
        if(hash_equals($current,$next)){throw new ApiException(422,'VALIDATION_FAILED','Mật khẩu mới phải khác mật khẩu hiện tại.');}
        $hash=password_hash($next,PASSWORD_DEFAULT);if($hash===false){throw new ApiException(500,'INTERNAL_ERROR','Không thể cập nhật mật khẩu.');}$this->repository->updatePassword($id,$hash);
    }
    /** @param array<string,mixed> $row @return array{id:string,email:string,fullName:string,role:string,status:string} */
    private function publicUser(array $row): array{return ['id'=>(string)$row['id'],'email'=>(string)$row['email'],'fullName'=>(string)$row['fullName'],'role'=>(string)$row['role'],'status'=>(string)$row['status']];}
}
