<?php
declare(strict_types=1);
namespace TalentHub\Modules\Student\Service;

use DateTimeImmutable;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Repository\StudentRepository;

final class StudentProfileService
{
    private const ALLOWED_FIELDS = ['fullName', 'dateOfBirth', 'phone', 'location', 'bio', 'avatarUrl', 'headline'];
    public function __construct(private readonly StudentRepository $repository) {}

    public function get(string $userId): array
    {
        $row = $this->repository->findByUserId($userId) ?? throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ học viên.');
        return $this->present($row);
    }

    public function update(string $userId, array $input): array
    {
        foreach (array_keys($input) as $field) {
            if (!in_array($field, self::ALLOWED_FIELDS, true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Trường dữ liệu không được phép cập nhật.', [
                    ['field' => (string) $field, 'code' => 'FIELD_NOT_ALLOWED', 'message' => 'Không được phép cập nhật field này.'],
                ]);
            }
        }
        $current = $this->get($userId);
        $fullName = $this->text($input['fullName'] ?? $current['fullName'], 'fullName', 2, 255);
        $phone = $this->text($input['phone'] ?? $current['phone'], 'phone', 6, 30);
        $date = $this->date($input['dateOfBirth'] ?? $current['dateOfBirth']);
        $location = $this->nullableText($input['location'] ?? $current['location'], 'location', 255);
        $bio = $this->nullableText($input['bio'] ?? $current['bio'], 'bio', 2000);
        $avatarUrl = $this->nullableProfileUrl($input['avatarUrl'] ?? $current['avatarUrl']);
        $headline = $this->nullableText($input['headline'] ?? $current['headline'], 'headline', 255);

        $this->repository->update($userId, $fullName, $date, $phone, $location, $bio, $avatarUrl, $headline);
        return $this->get($userId);
    }

    /**
     * @param array{avatarUrl?:string,mime?:string,contents?:string,dataUrl?:string} $file
     */
    public function uploadAvatar(string $userId, array $file): string
    {
        $student = $this->get($userId);

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
            throw new ApiException(422, 'VALIDATION_FAILED', 'Vui lòng chọn tệp ảnh đại diện hợp lệ.');
        }

        $allowed = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        $imageInfo = @getimagesizefromstring($contents);
        $detectedMime = is_array($imageInfo) && is_string($imageInfo['mime'] ?? null)
            ? strtolower($imageInfo['mime'])
            : null;
        $declaredMime = strtolower($mime) === 'image/jpg' ? 'image/jpeg' : strtolower($mime);

        if ($detectedMime === null || !isset($allowed[$detectedMime]) || $declaredMime !== $detectedMime) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Định dạng hoặc nội dung ảnh không hợp lệ. Vui lòng chọn tệp PNG, JPEG, WebP hoặc GIF.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > 4096 || $height > 4096 || ($width * $height) > 16_000_000) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Kích thước ảnh không hợp lệ hoặc vượt quá giới hạn 4096px.');
        }

        if (strlen($contents) > 5 * 1024 * 1024) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Dung lượng tệp ảnh vượt quá 5MB.');
        }

        $ext = $allowed[$detectedMime];
        $dir = dirname(__DIR__, 4) . '/storage/student-avatars';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new ApiException(500, 'AVATAR_STORAGE_FAILED', 'Không thể lưu ảnh đại diện lúc này.');
        }

        $filename = 'avatar-' . substr($student['id'], 0, 8) . '-' . bin2hex(random_bytes(12)) . '.' . $ext;
        $abs = $dir . '/' . $filename;
        $temporaryPath = $abs . '.tmp';
        $written = @file_put_contents($temporaryPath, $contents, LOCK_EX);
        if ($written !== strlen($contents) || !@rename($temporaryPath, $abs)) {
            @unlink($temporaryPath);
            @unlink($abs);
            throw new ApiException(500, 'AVATAR_STORAGE_FAILED', 'Không thể lưu ảnh đại diện lúc này.');
        }
        $url = '/storage/student-avatars/' . $filename;

        try {
            $this->update($userId, ['avatarUrl' => $url]);
        } catch (\Throwable $exception) {
            @unlink($abs);
            throw $exception;
        }

        $previousAvatarUrl = is_string($student['avatarUrl'] ?? null) ? $student['avatarUrl'] : '';
        $managedPrefix = '/storage/student-avatars/';
        if (str_starts_with($previousAvatarUrl, $managedPrefix)) {
            $previousFilename = basename($previousAvatarUrl);
            if ($previousFilename !== $filename) {
                $previousPath = $dir . '/' . $previousFilename;
                if (is_file($previousPath)) {
                    @unlink($previousPath);
                }
            }
        }

        return $url;
    }

    public function dashboard(string $userId): array
    {
        $profile = $this->get($userId);
        return [
            'student' => ['id' => $profile['id'], 'fullName' => $profile['fullName'], 'school' => $profile['school'], 'class' => $profile['class']],
            'metrics' => ['profileCompletion' => 100, 'studyStatus' => $profile['studyStatus']],
            'scope' => 'baseline',
        ];
    }

    private function text(mixed $value, string $field, int $min, int $max): string
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

    private function nullableText(mixed $value, string $field, int $max): ?string
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

    private function date(mixed $value): string
    {
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'dateOfBirth không hợp lệ.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $date > new DateTimeImmutable('today')) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'dateOfBirth phải là ngày hợp lệ không nằm trong tương lai.');
        }
        return $value;
    }

    private function nullableProfileUrl(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'avatarUrl không hợp lệ.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > 500 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'avatarUrl không hợp lệ hoặc vượt quá 500 ký tự.');
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//') && !str_contains($value, '\\')) {
            return $value;
        }
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'avatarUrl phải là URL HTTPS hoặc đường dẫn ứng dụng hợp lệ.');
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $host = parse_url($value, PHP_URL_HOST);
        $user = parse_url($value, PHP_URL_USER);
        $password = parse_url($value, PHP_URL_PASS);
        if ($scheme !== 'https' || !is_string($host) || $host === '' || $user !== null || $password !== null) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'avatarUrl phải dùng HTTPS và không chứa thông tin đăng nhập.');
        }
        return $value;
    }

    private function present(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'userId' => (string) $row['userId'],
            'email' => (string) $row['email'],
            'fullName' => (string) $row['fullName'],
            'school' => ['id' => (string) $row['schoolId'], 'name' => (string) $row['schoolName']],
            'class' => ['id' => (string) $row['classId'], 'name' => (string) $row['className'], 'gradeLevel' => (int) $row['gradeLevel'], 'academicYear' => (string) $row['academicYear']],
            'dateOfBirth' => (string) $row['dateOfBirth'],
            'phone' => (string) $row['phone'],
            'location' => isset($row['location']) && is_string($row['location']) ? $row['location'] : null,
            'bio' => isset($row['bio']) && is_string($row['bio']) ? $row['bio'] : null,
            'avatarUrl' => isset($row['avatarUrl']) && is_string($row['avatarUrl'])
                ? $row['avatarUrl']
                : (isset($row['avatarurl']) && is_string($row['avatarurl'])
                    ? $row['avatarurl']
                    : (isset($row['avatar_url']) && is_string($row['avatar_url']) ? $row['avatar_url'] : null)),
            'headline' => isset($row['headline']) && is_string($row['headline']) ? $row['headline'] : null,
            'studyStatus' => (string) $row['studyStatus'],
            'createdAt' => gmdate('Y-m-d\TH:i:s\Z', strtotime((string) $row['createdAt'])),
            'updatedAt' => gmdate('Y-m-d\TH:i:s\Z', strtotime((string) $row['updatedAt'])),
        ];
    }
}
