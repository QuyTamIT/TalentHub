<?php
declare(strict_types=1);
namespace TalentHub\Modules\Business\Service;

use TalentHub\Http\ApiException;
use TalentHub\Modules\Business\Repository\BusinessRepository;

final class BusinessProfileService
{
    private const ALLOWED_FIELDS = [
        'name', 'logoUrl', 'industry', 'companySize', 'foundedYear', 'taxCode',
        'description', 'email', 'phone', 'website', 'address'
    ];

    public function __construct(private readonly BusinessRepository $repository) {}

    public function get(string $userId): array
    {
        $row = $this->repository->findByUserId($userId) ?? throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ doanh nghiệp.');
        return $this->present($row);
    }

    public function update(string $userId, array $input): array
    {
        foreach (array_keys($input) as $field) {
            if (!in_array($field, self::ALLOWED_FIELDS, true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Trường dữ liệu không được phép cập nhật.', [
                    ['field' => (string)$field, 'code' => 'FIELD_NOT_ALLOWED', 'message' => 'Không được phép cập nhật field này.']
                ]);
            }
        }
        $current = $this->get($userId);
        $fields = [];
        $fields['name'] = $this->text($input['name'] ?? $current['name'], 'name', 2, 255, false);

        foreach (['logoUrl' => 500, 'industry' => 150, 'companySize' => 100, 'taxCode' => 50, 'description' => 4000, 'email' => 255, 'phone' => 30, 'website' => 500, 'address' => 500] as $field => $max) {
            $fields[$field] = $this->text($input[$field] ?? $current[$field], $field, 0, $max, true);
        }

        $rawYear = $input['foundedYear'] ?? $current['foundedYear'] ?? null;
        if ($rawYear !== null && $rawYear !== '') {
            if (!is_numeric($rawYear)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Năm thành lập phải là số nguyên hợp lệ.');
            }
            $yearVal = (int) $rawYear;
            $currentYear = (int) date('Y');
            if ($yearVal < 1800 || $yearVal > $currentYear + 1) {
                throw new ApiException(422, 'VALIDATION_FAILED', "Năm thành lập phải từ 1800 đến {$currentYear}.");
            }
            $fields['foundedYear'] = $yearVal;
        } else {
            $fields['foundedYear'] = null;
        }

        if ($fields['email'] !== null && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Email không đúng định dạng.');
        }

        $this->repository->update($current['id'], $fields);
        return $this->get($userId);
    }

    /**
     * @param array{logoUrl?:string,mime?:string,contents?:string,dataUrl?:string} $file
     */
    public function uploadLogo(string $userId, array $file): string
    {
        $enterprise = $this->get($userId);

        $contents = null;
        $mime = null;

        if (!empty($file['dataUrl']) && is_string($file['dataUrl'])) {
            if (preg_match('#^data:(image/[a-zA-Z0-9\+\-\.]+);base64,(.+)$#', $file['dataUrl'], $matches)) {
                $mime = $matches[1];
                $decoded = base64_decode($matches[2], true);
                if ($decoded !== false) {
                    $contents = $decoded;
                }
            }
        } elseif (!empty($file['contents']) && is_string($file['contents'])) {
            $contents = base64_decode($file['contents'], true) ?: $file['contents'];
            $mime = is_string($file['mime'] ?? null) ? $file['mime'] : null;
        }

        if ($contents === null || $contents === '' || $mime === null) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Vui lòng chọn tệp logo hợp lệ.');
        }

        $allowed = [
            'image/png'     => 'png',
            'image/jpeg'    => 'jpg',
            'image/jpg'     => 'jpg',
            'image/webp'    => 'webp',
            'image/svg+xml' => 'svg',
        ];

        if (!isset($allowed[$mime])) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Định dạng logo không được hỗ trợ. Vui lòng chọn tệp PNG, JPEG, WebP hoặc SVG.');
        }

        if (strlen($contents) > 3 * 1024 * 1024) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Dung lượng tệp logo vượt quá 3MB.');
        }

        $ext = $allowed[$mime];
        $dir = dirname(__DIR__, 4) . '/storage/enterprise-logos';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = 'ent-' . substr($enterprise['id'], 0, 8) . '-' . time() . '.' . $ext;
        $abs = $dir . '/' . $filename;
        file_put_contents($abs, $contents);
        $url = '/storage/enterprise-logos/' . $filename;

        $this->repository->update($enterprise['id'], ['logoUrl' => $url]);

        return $url;
    }

    public function dashboard(string $userId): array
    {
        $profile = $this->get($userId);
        return [
            'business' => [
                'id'                 => $profile['id'],
                'name'               => $profile['name'],
                'verificationStatus' => $profile['verificationStatus'],
                'industry'           => $profile['industry'],
                'companySize'        => $profile['companySize'],
                'logoUrl'            => $profile['logoUrl'],
            ],
            'metrics' => [
                'profileCompletion'  => $this->completion($profile),
                'memberRole'         => $profile['memberRole'],
            ],
            'scope' => 'baseline',
        ];
    }

    private function text(mixed $value, string $field, int $min, int $max, bool $nullable): ?string
    {
        if ($nullable && ($value === null || $value === '')) {
            return null;
        }
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

    private function completion(array $profile): int
    {
        $fields = ['name', 'logoUrl', 'industry', 'companySize', 'foundedYear', 'taxCode', 'description', 'email', 'phone', 'website', 'address'];
        $filled = 0;
        foreach ($fields as $field) {
            if (($profile[$field] ?? null) !== null && $profile[$field] !== '') {
                $filled++;
            }
        }
        return (int) round($filled / count($fields) * 100);
    }

    private function present(array $row): array
    {
        $completion = $this->completion($row);
        return [
            'id'                 => (string) $row['id'],
            'userId'             => (string) $row['userId'],
            'accountEmail'       => (string) $row['accountEmail'],
            'accountName'        => (string) $row['fullName'],
            'memberRole'         => (string) $row['memberRole'],
            'name'               => (string) $row['name'],
            'status'             => (string) $row['status'],
            'logoUrl'            => $row['logoUrl'] ?? null,
            'industry'           => $row['industry'] ?? null,
            'companySize'        => $row['companySize'] ?? null,
            'foundedYear'        => isset($row['foundedYear']) && $row['foundedYear'] !== null ? (int)$row['foundedYear'] : null,
            'taxCode'            => $row['taxCode'] ?? null,
            'description'        => $row['description'] ?? null,
            'email'              => $row['email'] ?? null,
            'phone'              => $row['phone'] ?? null,
            'website'            => $row['website'] ?? null,
            'address'            => $row['address'] ?? null,
            'verificationStatus' => (string) $row['verificationStatus'],
            'createdAt'          => gmdate('Y-m-d\TH:i:s\Z', strtotime((string) $row['createdAt'])),
            'updatedAt'          => gmdate('Y-m-d\TH:i:s\Z', strtotime((string) $row['updatedAt'])),
            'profileCompletion'  => $completion,
        ];
    }
}
