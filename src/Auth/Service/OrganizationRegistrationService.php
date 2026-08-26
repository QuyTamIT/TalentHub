<?php
declare(strict_types=1);

namespace TalentHub\Auth\Service;

use PDO;
use RuntimeException;
use TalentHub\Support\Uuid;

final class OrganizationRegistrationService
{
    public const EXPIRY_DAYS=3;

    public function __construct(private readonly PDO $pdo) {}

    /** @param array<string,string> $input @return array<string,string> */
    public function register(array $input): array
    {
        $type=strtolower(trim($input['type']??''));$name=trim($input['organizationName']??'');
        $fullName=trim($input['fullName']??'');$email=strtolower(trim($input['email']??''));
        $phone=trim($input['phone']??'');$address=trim($input['address']??'');$password=$input['password']??'';
        if(!in_array($type,['school','enterprise'],true)||mb_strlen($name)<2||mb_strlen($fullName)<2||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($phone)<6||mb_strlen($address)<5||strlen($password)<12){throw new RuntimeException('Vui lòng điền đầy đủ thông tin hợp lệ; mật khẩu tối thiểu 12 ký tự.');}
        $this->purgeExpired();
        $used=$this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');$used->execute([$email]);
        if((int)$used->fetchColumn()>0){throw new RuntimeException('Email đã được sử dụng.');}
        $pending=$this->pdo->prepare("SELECT COUNT(*) FROM organization_registration_requests WHERE email=? AND status='pending' AND expiresAt>UTC_TIMESTAMP(6)");$pending->execute([$email]);
        if((int)$pending->fetchColumn()>0){throw new RuntimeException('Email này đã có yêu cầu đăng ký đang chờ Admin xử lý.');}
        $hash=password_hash($password,PASSWORD_DEFAULT);if($hash===false){throw new RuntimeException('Không thể bảo vệ mật khẩu đăng ký.');}
        $id=Uuid::v4();$expiresAt=gmdate('Y-m-d H:i:s',time()+self::EXPIRY_DAYS*86400);
        $statement=$this->pdo->prepare("INSERT INTO organization_registration_requests(id,type,organizationName,fullName,email,phone,address,passwordHash,status,expiresAt) VALUES(?,?,?,?,?,?,?,?,'pending',?)");
        $statement->execute([$id,$type,$name,$fullName,$email,$phone,$address,$hash,$expiresAt]);
        return ['id'=>$id,'email'=>$email,'type'=>$type,'status'=>'pending','expiresAt'=>$expiresAt];
    }

    public function purgeExpired(): int
    {
        $statement=$this->pdo->prepare("DELETE FROM organization_registration_requests WHERE status='pending' AND expiresAt<=UTC_TIMESTAMP(6)");
        $statement->execute();return $statement->rowCount();
    }
}
