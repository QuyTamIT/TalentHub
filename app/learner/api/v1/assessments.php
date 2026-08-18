<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/JsonResponder.php';
require_once dirname(__DIR__) . '/LearnerApiContext.php';

use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();

    if ($request->method !== 'GET') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }

    $studentId = $context->studentId('student_profile.read_own');

    // Whitelist query params
    $allowedParams = ['band', 'code'];
    foreach (array_keys($_GET) as $key) {
        if (!in_array($key, $allowedParams, true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Tham số truy vấn không hợp lệ.', [
                ['field' => (string) $key, 'code' => 'FIELD_NOT_ALLOWED', 'message' => 'Không được phép gửi tham số này.'],
            ]);
        }
    }

    $band = $request->queryParam('band');
    if ($band !== null) {
        $band = strtolower(trim((string) $band));
        if (!in_array($band, ['middle', 'high', 'college'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Khung giáo dục không hợp lệ.', [
                ['field' => 'band', 'code' => 'INVALID_BAND', 'message' => 'Khung giáo dục phải là middle, high hoặc college.'],
            ]);
        }
    }

    $code = $request->queryParam('code');
    if ($code !== null) {
        $code = strtolower(trim((string) $code));
        if (!in_array($code, ['holland', 'mbti', 'disc', 'multiple_intelligence'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mã bài đánh giá không hợp lệ.', [
                ['field' => 'code', 'code' => 'INVALID_CODE', 'message' => 'Mã bài đánh giá không nằm trong danh mục hỗ trợ.'],
            ]);
        }

        $detail = $context->assessmentCatalogService()->assessmentDetail($studentId, $code, $band);
        JsonResponder::sendSuccess($detail, $context->requestId());
    }

    $catalog = $context->assessmentCatalogService()->catalog($studentId, $band);
    JsonResponder::sendSuccess($catalog, $context->requestId());
} catch (ApiException $exception) {
    if ($exception->errorCode === 'AUTHENTICATION_REQUIRED') {
        $exception = new ApiException(401, 'AUTH_REQUIRED', $exception->getMessage(), $exception->details);
    }
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable $exception) {
    $msg = $exception->getMessage();
    if (str_contains($msg, 'not found') || str_contains($msg, 'was not found')) {
        JsonResponder::sendError(
            new ApiException(404, 'ASSESSMENT_NOT_FOUND', 'Bài đánh giá không tồn tại hoặc chưa được xuất bản.'),
            $context?->requestId() ?? 'request-unavailable'
        );
    } else {
        JsonResponder::sendError(
            new ApiException(500, 'SOURCE_FAILURE', 'Đã xảy ra lỗi khi tải dữ liệu bài đánh giá.'),
            $context?->requestId() ?? 'request-unavailable'
        );
    }
}
