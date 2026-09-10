<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/teacher/includes/cover-upload.php';

function check(bool $condition, string $label): void {
    if (!$condition) {
        throw new RuntimeException('[FAIL] ' . $label);
    }
    echo '[PASS] ' . $label . PHP_EOL;
}

$teacherId = '10000000-0000-4000-8000-000000000022';
$storageDir = dirname(__DIR__) . '/storage/activity-covers';

// Case 1: No file uploaded returns null
$noFile = [
    'name' => '',
    'type' => '',
    'tmp_name' => '',
    'error' => UPLOAD_ERR_NO_FILE,
    'size' => 0,
];
$res1 = teacherActivitiesHandleCoverUpload($noFile, $teacherId);
check($res1 === null, 'No file uploaded returns null');

// Case 2: System upload error throws ApiException
$errFile = [
    'name' => 'test.png',
    'type' => 'image/png',
    'tmp_name' => '',
    'error' => UPLOAD_ERR_INI_SIZE,
    'size' => 0,
];
try {
    teacherActivitiesHandleCoverUpload($errFile, $teacherId);
    check(false, 'Expected ApiException on upload error');
} catch (\TalentHub\Http\ApiException $e) {
    check(str_contains($e->getMessage(), 'dung lượng cho phép'), 'Throws error on oversized PHP ini error');
}

// Case 3: Invalid mime type throws ApiException
$fakeTmp = tempnam(sys_get_temp_dir(), 'test_fake_');
file_put_contents($fakeTmp, 'Hello world text file');
$fakeFile = [
    'name' => 'document.txt',
    'type' => 'text/plain',
    'tmp_name' => $fakeTmp,
    'error' => UPLOAD_ERR_OK,
    'size' => strlen('Hello world text file'),
];
try {
    teacherActivitiesHandleCoverUpload($fakeFile, $teacherId);
    check(false, 'Expected ApiException on invalid mime');
} catch (\TalentHub\Http\ApiException $e) {
    check(str_contains($e->getMessage(), 'Định dạng ảnh không được hỗ trợ'), 'Throws error on text file');
} finally {
    @unlink($fakeTmp);
}

// Case 4: Valid PNG upload succeeds
$validPngTmp = tempnam(sys_get_temp_dir(), 'test_png_');
// Minimal 1x1 transparent PNG
$pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
file_put_contents($validPngTmp, $pngData);
$validFile = [
    'name' => 'banner.png',
    'type' => 'image/png',
    'tmp_name' => $validPngTmp,
    'error' => UPLOAD_ERR_OK,
    'size' => strlen($pngData),
];

$uploadedUrl = teacherActivitiesHandleCoverUpload($validFile, $teacherId);
check(is_string($uploadedUrl) && str_starts_with($uploadedUrl, '/storage/activity-covers/cover-10000000-'), 'Uploaded URL has valid prefix and format: ' . $uploadedUrl);

$uploadedDiskPath = dirname(__DIR__) . $uploadedUrl;
check(is_file($uploadedDiskPath), 'Uploaded file exists on disk');
check(filesize($uploadedDiskPath) === strlen($pngData), 'Uploaded file content matches original size');

// Clean up
@unlink($validPngTmp);
@unlink($uploadedDiskPath);

echo '[ALL PASSED] teacher_activity_cover_upload_test.php completed successfully' . PHP_EOL;
