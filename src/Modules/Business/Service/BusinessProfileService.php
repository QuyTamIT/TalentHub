<?php
declare(strict_types=1);
namespace TalentHub\Modules\Business\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\Business\Repository\BusinessRepository;

final class BusinessProfileService
{
    private const ALLOWED_FIELDS=['name','logoUrl','industry','description','email','phone','website','address'];
    public function __construct(private readonly BusinessRepository $repository) {}

    public function get(string $userId): array
    {
        $row=$this->repository->findByUserId($userId)??throw new ApiException(404,'RESOURCE_NOT_FOUND','Không tìm thấy hồ sơ doanh nghiệp.');return $this->present($row);
    }

    public function update(string $userId,array $input): array
    {
        foreach(array_keys($input) as $field){if(!in_array($field,self::ALLOWED_FIELDS,true)){throw new ApiException(422,'VALIDATION_FAILED','Trường dữ liệu không được phép cập nhật.',[['field'=>(string)$field,'code'=>'FIELD_NOT_ALLOWED','message'=>'Không được phép cập nhật field này.']]);}}
        $current=$this->get($userId);$fields=[];
        $fields['name']=$this->text($input['name']??$current['name'],'name',2,255,false);
        foreach(['logoUrl'=>500,'industry'=>150,'description'=>4000,'email'=>255,'phone'=>30,'website'=>500,'address'=>500] as $field=>$max){$fields[$field]=$this->text($input[$field]??$current[$field],$field,0,$max,true);}
        if($fields['email']!==null&&!filter_var($fields['email'],FILTER_VALIDATE_EMAIL)){throw new ApiException(422,'VALIDATION_FAILED','Email không đúng định dạng.');}
        $this->repository->update($current['id'],$fields);return $this->get($userId);
    }

    public function dashboard(string $userId): array
    {
        $profile=$this->get($userId);return ['business'=>['id'=>$profile['id'],'name'=>$profile['name'],'verificationStatus'=>$profile['verificationStatus']],'metrics'=>['profileCompletion'=>$this->completion($profile),'memberRole'=>$profile['memberRole']],'scope'=>'baseline'];
    }

    private function text(mixed $value,string $field,int $min,int $max,bool $nullable): ?string
    {
        if($nullable&&($value===null||$value==='')){return null;}if(!is_string($value)){throw new ApiException(422,'VALIDATION_FAILED',"{$field} không hợp lệ.");}$value=trim($value);$length=mb_strlen($value);if($length<$min||$length>$max){throw new ApiException(422,'VALIDATION_FAILED',"{$field} phải có từ {$min} đến {$max} ký tự.");}return $value;
    }

    private function completion(array $profile): int
    {
        $fields=['name','logoUrl','industry','description','email','phone','website','address'];$filled=0;foreach($fields as $field){if(($profile[$field]??null)!==null&&$profile[$field]!==''){$filled++;}}return (int)round($filled/count($fields)*100);
    }

    private function present(array $row): array
    {
        return ['id'=>(string)$row['id'],'userId'=>(string)$row['userId'],'accountEmail'=>(string)$row['accountEmail'],'accountName'=>(string)$row['fullName'],'memberRole'=>(string)$row['memberRole'],'name'=>(string)$row['name'],'status'=>(string)$row['status'],'logoUrl'=>$row['logoUrl'],'industry'=>$row['industry'],'description'=>$row['description'],'email'=>$row['email'],'phone'=>$row['phone'],'website'=>$row['website'],'address'=>$row['address'],'verificationStatus'=>(string)$row['verificationStatus'],'createdAt'=>gmdate('Y-m-d\TH:i:s\Z',strtotime((string)$row['createdAt'])),'updatedAt'=>gmdate('Y-m-d\TH:i:s\Z',strtotime((string)$row['updatedAt']))];
    }
}
