<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use DateTimeImmutable;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseCertificateCommandRepository;
use TalentHub\Support\Uuid;

final class CertificateCommandService
{
    private const ALLOWED_FIELDS = [
        'title',
        'issuingOrganization',
        'issueDate',
        'expiryDate',
        'credentialId',
        'credentialUrl',
    ];

    public function __construct(private readonly DatabaseCertificateCommandRepository $repository) {}

    /** @return list<array<string,mixed>> */
    public function list(string $studentId): array
    {
        return $this->repository->listForStudent($studentId);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(string $studentId, array $input): array
    {
        $this->assertAllowedFields($input);

        $title = $this->requireText($input['title'] ?? null, 'title', 2, 255);
        $issuingOrg = $this->requireText($input['issuingOrganization'] ?? null, 'issuingOrganization', 2, 255);
        $issueDate = $this->requireDate($input['issueDate'] ?? null, 'issueDate', true);
        $expiryDate = $this->optionalDate($input['expiryDate'] ?? null, 'expiryDate', $issueDate);
        $credentialId = $this->optionalText($input['credentialId'] ?? null, 'credentialId', 255);
        $credentialUrl = $this->optionalUrl($input['credentialUrl'] ?? null, 'credentialUrl');

        return $this->repository->create($studentId, [
            'title' => $title,
            'issuingOrganization' => $issuingOrg,
            'issueDate' => $issueDate,
            'expiryDate' => $expiryDate,
            'credentialId' => $credentialId,
            'credentialUrl' => $credentialUrl,
        ]);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function update(string $studentId, string $certificateId, array $input): array
    {
        $this->assertAllowedFields($input, ['id']);
        if (!Uuid::isValid($certificateId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'ID chứng chỉ không hợp lệ.');
        }
        if ($input === []) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Cần cung cấp ít nhất một trường để cập nhật.');
        }

        $existing = $this->repository->findById($studentId, $certificateId);
        if ($existing === null) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy chứng chỉ.');
        }
        if (($existing['verificationStatus'] ?? null) !== 'unverified') {
            throw new ApiException(422, 'CANNOT_MODIFY_VERIFIED_CERTIFICATE', 'Chỉ có thể chỉnh sửa chứng chỉ ở trạng thái chưa xác minh.');
        }

        $data = [];
        if (array_key_exists('title', $input)) {
            $data['title'] = $this->requireText($input['title'], 'title', 2, 255);
        }
        if (array_key_exists('issuingOrganization', $input)) {
            $data['issuingOrganization'] = $this->requireText($input['issuingOrganization'], 'issuingOrganization', 2, 255);
        }
        if (array_key_exists('issueDate', $input)) {
            $data['issueDate'] = $this->requireDate($input['issueDate'], 'issueDate', true);
        }
        if (array_key_exists('expiryDate', $input)) {
            $issueDateRef = $data['issueDate'] ?? (string) $existing['issueDate'];
            $data['expiryDate'] = $this->optionalDate($input['expiryDate'], 'expiryDate', $issueDateRef);
        }
        if (array_key_exists('credentialId', $input)) {
            $data['credentialId'] = $this->optionalText($input['credentialId'], 'credentialId', 255);
        }
        if (array_key_exists('credentialUrl', $input)) {
            $data['credentialUrl'] = $this->optionalUrl($input['credentialUrl'], 'credentialUrl');
        }

        $mergedIssueDate = $data['issueDate'] ?? (string) $existing['issueDate'];
        $mergedExpiryDate = array_key_exists('expiryDate', $data)
            ? $data['expiryDate']
            : ($existing['expiryDate'] ?? null);
        $this->optionalDate($mergedExpiryDate, 'expiryDate', $mergedIssueDate);

        return $this->repository->update($studentId, $certificateId, $data);
    }

    public function delete(string $studentId, string $certificateId): void
    {
        if (!Uuid::isValid($certificateId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'ID chứng chỉ không hợp lệ.');
        }
        $this->repository->delete($studentId, $certificateId);
    }

    /** @param array<string,mixed> $input @param list<string> $extraAllowed */
    private function assertAllowedFields(array $input, array $extraAllowed = []): void
    {
        $allowed = array_merge(self::ALLOWED_FIELDS, $extraAllowed);
        foreach (array_keys($input) as $field) {
            if (!in_array($field, $allowed, true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Trường dữ liệu không được phép cập nhật.', [
                    ['field' => (string) $field, 'code' => 'FIELD_NOT_ALLOWED', 'message' => 'Không được phép cập nhật field này.'],
                ]);
            }
        }
    }

    private function requireText(mixed $value, string $field, int $min, int $max): string
    {
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không hợp lệ.");
        }
        $value = trim($value);
        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phải có từ {$min} đến {$max} ký tự.");
        }
        return $value;
    }

    private function optionalText(mixed $value, string $field, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không hợp lệ.");
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không được vượt quá {$max} ký tự.");
        }
        return $value;
    }

    private function optionalUrl(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không hợp lệ.");
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > 500 || !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phải là URL HTTPS hợp lệ và không vượt quá 500 ký tự.");
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $host = parse_url($value, PHP_URL_HOST);
        $user = parse_url($value, PHP_URL_USER);
        $password = parse_url($value, PHP_URL_PASS);
        if ($scheme !== 'https' || !is_string($host) || $host === '' || $user !== null || $password !== null) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phải dùng HTTPS, có host hợp lệ và không chứa thông tin đăng nhập.");
        }
        return $value;
    }

    private function requireDate(mixed $value, string $field, bool $mustBePastOrToday = false): string
    {
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không hợp lệ.");
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phải là ngày hợp lệ theo định dạng YYYY-MM-DD.");
        }
        if ($mustBePastOrToday && $date > new DateTimeImmutable('today')) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không được nằm trong tương lai.");
        }
        return $value;
    }

    private function optionalDate(mixed $value, string $field, ?string $afterOrEqualDate = null): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} không hợp lệ.");
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phải là ngày hợp lệ theo định dạng YYYY-MM-DD.");
        }
        if ($afterOrEqualDate !== null) {
            $ref = DateTimeImmutable::createFromFormat('!Y-m-d', $afterOrEqualDate);
            if ($ref && $date < $ref) {
                throw new ApiException(422, 'VALIDATION_FAILED', "{$field} phải lớn hơn hoặc bằng ngày cấp ({$afterOrEqualDate}).");
            }
        }
        return $value;
    }
}
