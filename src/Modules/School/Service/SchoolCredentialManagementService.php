<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolCredentialManagementRepository;
use TalentHub\Support\Uuid;

final class SchoolCredentialManagementService
{
    public function __construct(private readonly SchoolCredentialManagementRepository $repository) {}

    public function dashboard(string $schoolUserId): array { return $this->repository->dashboard($this->schoolId($schoolUserId)); }

    public function createBadge(string $schoolUserId, array $input, string $requestId): array
    {
        return $this->repository->createBadge($schoolUserId, $this->schoolId($schoolUserId), [
            'code' => $this->code($input['code'] ?? null), 'name' => $this->text($input['name'] ?? null, 'Tên badge', 255),
            'category' => $this->text($input['category'] ?? 'school', 'Danh mục', 64), 'description' => $this->text($input['description'] ?? null, 'Mô tả', 5000),
            'criteria' => $this->jsonObject($input['criteria'] ?? []), 'recommendationProfile' => $this->jsonObject($input['recommendationProfile'] ?? []),
            'recommendationEnabled' => !empty($input['recommendationEnabled']), 'iconUrl' => $this->nullableText($input['iconUrl'] ?? null, 500),
            'level' => $this->integer($input['level'] ?? 1, 1, 100, 'Cấp độ'), 'status' => $this->status($input['status'] ?? 'active'),
        ], $this->requestId($requestId));
    }

    public function createCertificateCatalog(string $schoolUserId, array $input, string $requestId): array
    {
        return $this->repository->createCertificateCatalog($schoolUserId, $this->schoolId($schoolUserId), [
            'code' => $this->code($input['code'] ?? null), 'name' => $this->text($input['name'] ?? null, 'Tên chứng chỉ', 255),
            'description' => $this->text($input['description'] ?? null, 'Mô tả', 5000), 'issuerName' => $this->text($input['issuerName'] ?? null, 'Đơn vị cấp', 255),
            'iconKey' => $this->text($input['iconKey'] ?? 'certificate', 'Biểu tượng', 50), 'criteria' => $this->jsonObject($input['criteria'] ?? []),
            'recommendationProfile' => $this->jsonObject($input['recommendationProfile'] ?? []), 'recommendationEnabled' => !empty($input['recommendationEnabled']),
            'status' => $this->status($input['status'] ?? 'active'),
        ], $this->requestId($requestId));
    }

    public function awardBadge(string $schoolUserId, string $badgeId, string $studentId, array $evidence, string $requestId): array
    {
        return $this->repository->awardBadge($schoolUserId, $this->schoolId($schoolUserId), $this->uuid($badgeId, 'badgeId'), $this->uuid($studentId, 'studentId'), $this->jsonObject($evidence), $this->requestId($requestId));
    }

    public function issueCertificate(string $schoolUserId, string $catalogId, string $studentId, array $evidence, string $requestId): array
    {
        return $this->repository->issueCertificate($schoolUserId, $this->schoolId($schoolUserId), $this->uuid($catalogId, 'catalogId'), $this->uuid($studentId, 'studentId'), $this->jsonObject($evidence), $this->requestId($requestId));
    }

    public function revokeCertificate(string $schoolUserId, string $awardId, string $reason, string $requestId): array
    {
        $reason = $this->text($reason, 'Lý do thu hồi', 1000);
        return $this->repository->revokeCertificate($schoolUserId, $this->schoolId($schoolUserId), $this->uuid($awardId, 'awardId'), $reason, $this->requestId($requestId));
    }

    private function schoolId(string $userId): string { $this->uuid($userId, 'schoolUserId');$id=$this->repository->schoolIdForUser($userId);if($id===null)throw new ApiException(403,'PERMISSION_DENIED','Tài khoản không thuộc Nhà trường nào.');return $id; }
    private function requestId(string $value): string { if(preg_match('/\A[A-Za-z0-9_-]{16,64}\z/',$value)!==1)throw new ApiException(422,'VALIDATION_FAILED','requestId không hợp lệ.');return substr($value,0,26); }
    private function uuid(string $value, string $field): string { if(!Uuid::isValid($value))throw new ApiException(422,'VALIDATION_FAILED',"{$field} không hợp lệ.");return strtolower($value); }
    private function code(mixed $value): string { $code=strtolower($this->text($value,'Mã credential',100));if(preg_match('/\A[a-z0-9][a-z0-9_-]*\z/',$code)!==1)throw new ApiException(422,'VALIDATION_FAILED','Mã credential chỉ gồm chữ thường, số, gạch ngang hoặc gạch dưới.');return $code; }
    private function text(mixed $value,string $label,int $max): string { $value=is_scalar($value)?trim((string)$value):'';if($value===''||mb_strlen($value)>$max)throw new ApiException(422,'VALIDATION_FAILED',"{$label} là bắt buộc và không vượt quá {$max} ký tự.");return $value; }
    private function nullableText(mixed $value,int $max): ?string { if($value===null||trim((string)$value)==='')return null;$value=trim((string)$value);if(mb_strlen($value)>$max)throw new ApiException(422,'VALIDATION_FAILED','Giá trị văn bản quá dài.');return $value; }
    private function integer(mixed $value,int $min,int $max,string $label): int { $parsed=filter_var($value,FILTER_VALIDATE_INT);if($parsed===false||$parsed<$min||$parsed>$max)throw new ApiException(422,'VALIDATION_FAILED',"{$label} không hợp lệ.");return (int)$parsed; }
    private function status(mixed $value): string { $value=(string)$value;if(!in_array($value,['active','inactive','deprecated'],true))throw new ApiException(422,'VALIDATION_FAILED','Trạng thái catalog không hợp lệ.');return $value; }
    private function jsonObject(mixed $value): array { if(is_string($value)){try{$value=json_decode($value,true,512,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new ApiException(422,'VALIDATION_FAILED','JSON không hợp lệ.');}}if(!is_array($value))throw new ApiException(422,'VALIDATION_FAILED','Dữ liệu JSON phải là object hoặc array.');return $value; }
}
