<?php
declare(strict_types=1);

/**
 * TalentHub - Teacher Activity Cover Upload Handler.
 */

use TalentHub\Http\ApiException;

if (!function_exists('teacherActivitiesHandleCoverUpload')) {
    /**
     * Handles an uploaded cover image file for an activity.
     *
     * @param array<string,mixed> $file Typically $_FILES['coverFile']
     * @param string $teacherId UUID or ID of the teacher
     * @return string|null Relative web URL like '/storage/activity-covers/cover-xxx.webp' or null if no file was uploaded
     * @throws ApiException if upload validation fails
     */
    function teacherActivitiesHandleCoverUpload(array $file, string $teacherId): ?string
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'Tệp ảnh vượt quá dung lượng cho phép của máy chủ.',
                UPLOAD_ERR_FORM_SIZE  => 'Tệp ảnh vượt quá dung lượng cho phép của biểu mẫu.',
                UPLOAD_ERR_PARTIAL    => 'Tệp ảnh chỉ mới được tải lên một phần.',
                UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm trên máy chủ để lưu ảnh.',
                UPLOAD_ERR_CANT_WRITE => 'Không thể ghi tệp ảnh vào ổ đĩa máy chủ.',
                UPLOAD_ERR_EXTENSION  => 'Quá trình tải ảnh bị chặn bởi một tiện ích máy chủ.',
            ];
            $msg = $errorMessages[$errorCode] ?? ('Lỗi tải ảnh lên (mã lỗi ' . $errorCode . ').');
            throw new ApiException(422, 'VALIDATION_FAILED', $msg);
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Không tìm thấy tệp ảnh tạm thời trên máy chủ.');
        }

        $fileSize = (int) ($file['size'] ?? 0);
        if ($fileSize <= 0) {
            $fileSize = (int) (filesize($tmpPath) ?: 0);
        }
        if ($fileSize > 5 * 1024 * 1024) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Dung lượng ảnh bìa không được vượt quá 5MB.');
        }

        // Validate MIME type securely using finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower((string) $finfo->file($tmpPath));

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowedMimes[$detectedMime])) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Định dạng ảnh không được hỗ trợ. Vui lòng chọn tệp JPG, PNG hoặc WebP.');
        }

        // Validate image dimensions using getimagesize
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Tệp tải lên không phải là định dạng hình ảnh hợp lệ.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > 4096 || $height > 4096) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Kích thước ảnh không hợp lệ (tối đa 4096 x 4096 pixel).');
        }

        $ext = $allowedMimes[$detectedMime];
        $storageDir = dirname(__DIR__, 3) . '/storage/activity-covers';
        if (!is_dir($storageDir) && !@mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            throw new ApiException(500, 'STORAGE_ERROR', 'Không thể khởi tạo thư mục lưu trữ ảnh hoạt động.');
        }

        $cleanTeacherId = preg_replace('/[^a-zA-Z0-9]/', '', $teacherId) ?: 'teacher';
        $prefix = substr($cleanTeacherId, 0, 8);
        $randomHex = bin2hex(random_bytes(6));
        $filename = 'cover-' . $prefix . '-' . $randomHex . '.' . $ext;
        $destinationPath = $storageDir . '/' . $filename;
        $tempDestination = $destinationPath . '.tmp';

        $fileContents = file_get_contents($tmpPath);
        if ($fileContents === false) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Không thể đọc nội dung tệp ảnh tải lên.');
        }

        $written = @file_put_contents($tempDestination, $fileContents, LOCK_EX);
        if ($written !== strlen($fileContents) || !@rename($tempDestination, $destinationPath)) {
            @unlink($tempDestination);
            @unlink($destinationPath);
            throw new ApiException(500, 'STORAGE_ERROR', 'Không thể ghi ảnh vào thư mục lưu trữ.');
        }

        return '/storage/activity-covers/' . $filename;
    }
}
