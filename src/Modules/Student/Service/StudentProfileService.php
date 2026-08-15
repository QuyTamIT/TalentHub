<?php
declare(strict_types=1);
namespace TalentHub\Modules\Student\Service;

use DateTimeImmutable;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Repository\StudentRepository;

final class StudentProfileService
{
    private const ALLOWED_FIELDS=['fullName','dateOfBirth','phone'];
    public function __construct(private readonly StudentRepository $repository) {}

    public function get(string $userId): array
    {
        $row=$this->repository->findByUserId($userId)??throw new ApiException(404,'RESOURCE_NOT_FOUND','Không tìm thấy hồ sơ học viên.');
        return $this->present($row);
    }

    public function update(string $userId,array $input): array
    {
        foreach(array_keys($input) as $field){if(!in_array($field,self::ALLOWED_FIELDS,true)){throw new ApiException(422,'VALIDATION_FAILED','Trường dữ liệu không được phép cập nhật.',[['field'=>(string)$field,'code'=>'FIELD_NOT_ALLOWED','message'=>'Không được phép cập nhật field này.']]);}}
        $current=$this->get($userId);$fullName=$this->text($input['fullName']??$current['fullName'],'fullName',2,255);$phone=$this->text($input['phone']??$current['phone'],'phone',6,30);$date=$this->date($input['dateOfBirth']??$current['dateOfBirth']);
        $this->repository->update($userId,$fullName,$date,$phone);return $this->get($userId);
    }

    public function dashboard(string $userId): array
    {
        $profile=$this->get($userId);
        return ['student'=>['id'=>$profile['id'],'fullName'=>$profile['fullName'],'school'=>$profile['school'],'class'=>$profile['class']],'metrics'=>['profileCompletion'=>100,'studyStatus'=>$profile['studyStatus']],'scope'=>'baseline'];
    }

    private function text(mixed $value,string $field,int $min,int $max): string
    {
        if(!is_string($value)){throw new ApiException(422,'VALIDATION_FAILED',"{$field} không hợp lệ.");}$value=trim($value);$length=mb_strlen($value);if($length<$min||$length>$max){throw new ApiException(422,'VALIDATION_FAILED',"{$field} phải có từ {$min} đến {$max} ký tự.");}return $value;
    }

    private function date(mixed $value): string
    {
        if(!is_string($value)){throw new ApiException(422,'VALIDATION_FAILED','dateOfBirth không hợp lệ.');}$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value||$date>new DateTimeImmutable('today')){throw new ApiException(422,'VALIDATION_FAILED','dateOfBirth phải là ngày hợp lệ không nằm trong tương lai.');}return $value;
    }

    private function present(array $row): array
    {
        return ['id'=>(string)$row['id'],'userId'=>(string)$row['userId'],'email'=>(string)$row['email'],'fullName'=>(string)$row['fullName'],'school'=>['id'=>(string)$row['schoolId'],'name'=>(string)$row['schoolName']],'class'=>['id'=>(string)$row['classId'],'name'=>(string)$row['className'],'gradeLevel'=>(int)$row['gradeLevel'],'academicYear'=>(string)$row['academicYear']],'dateOfBirth'=>(string)$row['dateOfBirth'],'phone'=>(string)$row['phone'],'studyStatus'=>(string)$row['studyStatus'],'createdAt'=>gmdate('Y-m-d\TH:i:s\Z',strtotime((string)$row['createdAt'])),'updatedAt'=>gmdate('Y-m-d\TH:i:s\Z',strtotime((string)$row['updatedAt']))];
    }
}
