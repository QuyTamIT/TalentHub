<?php

declare(strict_types=1);

namespace TalentHub\Modules\School\Service;

use DateTimeImmutable;
use TalentHub\Http\ApiException;
use TalentHub\Modules\School\Repository\SchoolCredentialManagementRepository;
use TalentHub\Support\Uuid;

final class SchoolCredentialManagementService
{
    private const AUTOMATIC_FACTS = [
        'confirmed_experience_hours',
        'attended_activity_count',
        'submitted_assessment_type_count',
        'published_teacher_evaluation_count',
        'online_learning_minutes',
    ];

    public function __construct(private readonly SchoolCredentialManagementRepository $repository) {}

    public function dashboard(string $actorUserId): array
    {
        return $this->repository->dashboard($this->schoolId($actorUserId), $actorUserId);
    }

    public function createBadge(string $actorUserId, array $input, string $requestId): array
    {
        $criteria = $this->jsonObject($input['criteria'] ?? []);
        if ($criteria === []) {
            $criteria = $this->badgeCriteria($input);
        }

        return $this->repository->createBadge($actorUserId, $this->schoolId($actorUserId), [
            'code' => $this->code($input['code'] ?? null),
            'name' => $this->text($input['name'] ?? null, 'Tên huy hiệu', 255),
            'category' => $this->text($input['category'] ?? 'school_activity', 'Danh mục', 64),
            'description' => $this->text($input['description'] ?? null, 'Mô tả', 5000),
            'criteria' => $criteria,
            'recommendationProfile' => $this->jsonObject($input['recommendationProfile'] ?? []),
            'recommendationEnabled' => !empty($input['recommendationEnabled']),
            'iconUrl' => $this->nullableText($input['iconUrl'] ?? null, 500),
            'level' => $this->integer($input['level'] ?? 1, 1, 100, 'Cấp độ'),
            'status' => $this->status($input['status'] ?? 'active'),
        ], $this->requestId($requestId));
    }

    public function createCertificateCatalog(string $actorUserId, array $input, string $requestId): array
    {
        return $this->repository->createCertificateCatalog($actorUserId, $this->schoolId($actorUserId), [
            'code' => $this->code($input['code'] ?? null),
            'name' => $this->text($input['name'] ?? null, 'Tên chứng chỉ', 255),
            'description' => $this->text($input['description'] ?? null, 'Mô tả', 5000),
            'issuerName' => $this->text($input['issuerName'] ?? null, 'Đơn vị cấp', 255),
            'iconKey' => $this->text($input['iconKey'] ?? 'certificate', 'Biểu tượng', 50),
            'criteria' => $this->jsonObject($input['criteria'] ?? []),
            'recommendationProfile' => $this->jsonObject($input['recommendationProfile'] ?? []),
            'recommendationEnabled' => !empty($input['recommendationEnabled']),
            'status' => $this->status($input['status'] ?? 'active'),
        ], $this->requestId($requestId));
    }

    /** @return array{created:int,skipped:int,total:int,items:list<array<string,mixed>>} */
    public function awardBadgeToTarget(string $actorUserId, array $input, string $requestId): array
    {
        [$studentId, $classId] = $this->target($input);
        $legacyEvidence = is_array($input['evidence'] ?? null) ? $input['evidence'] : [];
        $reason = $this->text($input['reason'] ?? $legacyEvidence['reason'] ?? $legacyEvidence['note'] ?? 'Nhà trường ghi nhận thành tích', 'Lý do/hoạt động tham gia', 1000);
        $activityName = $this->nullableText($input['activityName'] ?? $legacyEvidence['activityName'] ?? null, 255);
        $issuedDate = $this->date($input['issuedDate'] ?? $legacyEvidence['issuedDate'] ?? gmdate('Y-m-d'), 'Ngày cấp');

        return $this->repository->awardBadgeToTarget(
            $actorUserId,
            $this->schoolId($actorUserId),
            $this->uuid((string) ($input['badgeId'] ?? ''), 'badgeId'),
            $studentId,
            $classId,
            $issuedDate,
            ['reason' => $reason, 'activityName' => $activityName, 'source' => 'manual'],
            $this->requestId($requestId),
        );
    }

    /** @return array{created:int,skipped:int,total:int,items:list<array<string,mixed>>} */
    public function issueCertificateToTarget(string $actorUserId, array $input, string $requestId): array
    {
        [$studentId, $classId] = $this->target($input);
        $legacyEvidence = is_array($input['evidence'] ?? null) ? $input['evidence'] : [];
        $reason = $this->text($input['reason'] ?? $legacyEvidence['reason'] ?? $legacyEvidence['note'] ?? 'Nhà trường ghi nhận thành tích', 'Lý do/hoạt động tham gia', 1000);
        $activityName = $this->nullableText($input['activityName'] ?? $legacyEvidence['activityName'] ?? null, 255);
        $issuedDate = $this->date($input['issuedDate'] ?? $legacyEvidence['issuedDate'] ?? gmdate('Y-m-d'), 'Ngày cấp');

        return $this->repository->issueCertificateToTarget(
            $actorUserId,
            $this->schoolId($actorUserId),
            $this->uuid((string) ($input['catalogId'] ?? ''), 'catalogId'),
            $studentId,
            $classId,
            $issuedDate,
            ['reason' => $reason, 'activityName' => $activityName, 'source' => 'manual'],
            $this->requestId($requestId),
        );
    }

    /** @return array<string,mixed> */
    public function awardBadge(string $actorUserId, string $badgeId, string $studentId, array $evidence, string $requestId): array
    {
        $result = $this->awardBadgeToTarget($actorUserId, [
            'badgeId' => $badgeId,
            'studentId' => $studentId,
            'recipientType' => 'student',
            'issuedDate' => $evidence['issuedDate'] ?? gmdate('Y-m-d'),
            'reason' => $evidence['reason'] ?? $evidence['note'] ?? 'Nhà trường ghi nhận thành tích',
            'activityName' => $evidence['activityName'] ?? null,
        ], $requestId);
        return $result['items'][0] ?? ['status' => 'skipped'];
    }

    /** @return array<string,mixed> */
    public function issueCertificate(string $actorUserId, string $catalogId, string $studentId, array $evidence, string $requestId): array
    {
        $result = $this->issueCertificateToTarget($actorUserId, [
            'catalogId' => $catalogId,
            'studentId' => $studentId,
            'recipientType' => 'student',
            'issuedDate' => $evidence['issuedDate'] ?? gmdate('Y-m-d'),
            'reason' => $evidence['reason'] ?? $evidence['note'] ?? 'Nhà trường ghi nhận thành tích',
            'activityName' => $evidence['activityName'] ?? null,
        ], $requestId);
        return $result['items'][0] ?? ['status' => 'skipped'];
    }

    public function revokeCertificate(string $actorUserId, string $awardId, string $reason, string $requestId): array
    {
        return $this->repository->revokeCertificate(
            $actorUserId,
            $this->schoolId($actorUserId),
            $this->uuid($awardId, 'awardId'),
            $this->text($reason, 'Lý do thu hồi', 1000),
            $this->requestId($requestId),
        );
    }

    private function schoolId(string $userId): string
    {
        $this->uuid($userId, 'actorUserId');
        $id = $this->repository->schoolIdForUser($userId);
        if ($id === null) {
            throw new ApiException(403, 'PERMISSION_DENIED', 'Tài khoản không thuộc Nhà trường nào.');
        }
        return $id;
    }

    /** @return array{0:?string,1:?string} */
    private function target(array $input): array
    {
        $type = (string) ($input['recipientType'] ?? (!empty($input['classId']) ? 'class' : 'student'));
        if ($type === 'student') {
            return [$this->uuid((string) ($input['studentId'] ?? ''), 'studentId'), null];
        }
        if ($type === 'class') {
            return [null, $this->uuid((string) ($input['classId'] ?? ''), 'classId')];
        }
        throw new ApiException(422, 'VALIDATION_FAILED', 'Đối tượng nhận phải là sinh viên hoặc lớp học.');
    }

    /** @return array{fact:string,operator:string,value:int} */
    private function badgeCriteria(array $input): array
    {
        if (($input['awardMode'] ?? 'manual') !== 'automatic') {
            return ['fact' => 'manual_award_only', 'operator' => 'gte', 'value' => 1];
        }
        $fact = (string) ($input['fact'] ?? 'online_learning_minutes');
        if (!in_array($fact, self::AUTOMATIC_FACTS, true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Tiêu chí cấp tự động không được hỗ trợ.');
        }
        return [
            'fact' => $fact,
            'operator' => 'gte',
            'value' => $this->integer($input['threshold'] ?? null, 1, 1000000, 'Ngưỡng tự động'),
        ];
    }

    private function requestId(string $value): string
    {
        if (preg_match('/\A[A-Za-z0-9_-]{16,64}\z/', $value) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'requestId không hợp lệ.');
        }
        return substr($value, 0, 26);
    }

    private function uuid(string $value, string $field): string
    {
        if (!Uuid::isValid($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không hợp lệ.");
        }
        return strtolower($value);
    }

    private function code(mixed $value): string
    {
        $code = strtolower($this->text($value, 'Mã thành tích', 100));
        if (preg_match('/\A[a-z0-9][a-z0-9_-]*\z/', $code) !== 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mã chỉ gồm chữ thường, số, gạch ngang hoặc gạch dưới.');
        }
        return $code;
    }

    private function text(mixed $value, string $label, int $max): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if ($value === '' || mb_strlen($value) > $max) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$label} là bắt buộc và không vượt quá {$max} ký tự.");
        }
        return $value;
    }

    private function nullableText(mixed $value, int $max): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $value = trim((string) $value);
        if (mb_strlen($value) > $max) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Giá trị văn bản quá dài.');
        }
        return $value;
    }

    private function integer(mixed $value, int $min, int $max, string $label): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < $min || $parsed > $max) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$label} không hợp lệ.");
        }
        return (int) $parsed;
    }

    private function date(mixed $value, string $label): string
    {
        $raw = is_string($value) ? trim($value) : '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false || $date->format('Y-m-d') !== $raw || $date > new DateTimeImmutable('today')) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$label} phải hợp lệ và không nằm trong tương lai.");
        }
        return $raw;
    }

    private function status(mixed $value): string
    {
        $value = (string) $value;
        if (!in_array($value, ['active', 'inactive', 'deprecated'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Trạng thái danh mục không hợp lệ.');
        }
        return $value;
    }

    private function jsonObject(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'JSON không hợp lệ.');
            }
        }
        if (!is_array($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Dữ liệu JSON phải là object hoặc array.');
        }
        return $value;
    }
}
