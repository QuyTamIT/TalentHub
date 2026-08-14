<?php
declare(strict_types=1);
namespace TalentHub\Modules\Teacher\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherRepository;

final class TeacherProfileService
{
    private const ALLOWED=['fullName','phone','specialization','bio'];
    public function __construct(private readonly TeacherRepository $repository) {}
    /** @return array<string,mixed> */
    public function get(string $userId): array{$row=$this->repository->findByUserId($userId)??throw new ApiException(404,'RESOURCE_NOT_FOUND','Không tìm thấy hồ sơ giáo viên.');return $this->present($row);}
    /** @return array<string,mixed> */
    public function dashboard(string $userId): array
    {
        $profile=$this->get($userId);return ['teacher'=>['id'=>$profile['id'],'fullName'=>$profile['fullName'],'school'=>$profile['school']],'metrics'=>$this->repository->dashboardMetrics($userId),'scope'=>'baseline'];
    }
    /** @return array<string,mixed> */
    public function update(string $userId,array $input): array
    {
        foreach(array_keys($input) as $field){if(!in_array($field,self::ALLOWED,true)){throw new ApiException(422,'VALIDATION_FAILED','Trường dữ liệu không được phép cập nhật.',[['field'=>(string)$field,'code'=>'FIELD_NOT_ALLOWED','message'=>'Không được phép cập nhật field này.']]);}}
        $current=$this->repository->findByUserId($userId)??throw new ApiException(404,'RESOURCE_NOT_FOUND','Không tìm thấy hồ sơ giáo viên.');
        $fullName=$this->text($input['fullName']??$current['fullName'],'fullName',2,150,false);
        $phone=$this->text($input['phone']??$current['phone'],'phone',0,30,true);
        $specialization=$this->text($input['specialization']??$current['specialization'],'specialization',0,150,true);
        $bio=$this->text($input['bio']??$current['bio'],'bio',0,1000,true);
        $this->repository->update($userId,$fullName,$phone,$specialization,$bio);return $this->get($userId);
    }
    private function text(mixed $value,string $field,int $min,int $max,bool $nullable): ?string
    {
        if($value===null&&$nullable){return null;}if(!is_string($value)){throw new ApiException(422,'VALIDATION_FAILED','Dữ liệu gửi lên không hợp lệ.');}$value=trim($value);
        if($nullable&&$value===''){return null;}$length=mb_strlen($value);if($length<$min||$length>$max){throw new ApiException(422,'VALIDATION_FAILED',"{$field} có độ dài không hợp lệ.");}return $value;
    }
    /** @param array<string,mixed> $r @return array<string,mixed> */
    private function present(array $r): array{return ['id'=>(string)$r['id'],'userId'=>(string)$r['userId'],'email'=>(string)$r['email'],'fullName'=>(string)$r['fullName'],'school'=>['id'=>(string)$r['schoolId'],'name'=>(string)$r['schoolName']],'isSchoolAdmin'=>(bool)$r['isSchoolAdmin'],'phone'=>$r['phone'],'specialization'=>$r['specialization'],'bio'=>$r['bio'],'createdAt'=>gmdate('Y-m-d\TH:i:s\Z',strtotime((string)$r['createdAt'])),'updatedAt'=>gmdate('Y-m-d\TH:i:s\Z',strtotime((string)$r['updatedAt']))];}
}
