<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/JsonResponder.php';
require_once dirname(__DIR__) . '/LearnerApiContext.php';

use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Support\Uuid;

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();

    if ($request->method === 'POST') {
        $studentId = $context->studentId('student_profile.update_own');
        $context->mutation($request->header('x-csrf-token'));

        $input = $context->allowedInput($request->json(), ['assessmentCode', 'educationBand']);

        $code = strtolower(trim((string) ($input['assessmentCode'] ?? '')));
        if (!in_array($code, ['holland', 'mbti', 'disc', 'multiple_intelligence'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mã bài đánh giá không hợp lệ.', [
                ['field' => 'assessmentCode', 'code' => 'INVALID_CODE', 'message' => 'Mã bài đánh giá không nằm trong danh mục hỗ trợ.'],
            ]);
        }

        $band = strtolower(trim((string) ($input['educationBand'] ?? '')));
        if (!in_array($band, ['middle', 'high', 'college'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Khung giáo dục không hợp lệ.', [
                ['field' => 'educationBand', 'code' => 'INVALID_BAND', 'message' => 'Khung giáo dục phải là middle, high hoặc college.'],
            ]);
        }

        $attempt = $context->assessmentService()->startOrResume($studentId, $code, $band);
        JsonResponder::sendSuccess($attempt, $context->requestId(), 200);
    }

    if ($request->method === 'GET') {
        $studentId = $context->studentId('student_profile.read_own');

        $allowedParams = ['attemptId'];
        foreach (array_keys($_GET) as $key) {
            if (!in_array($key, $allowedParams, true)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Tham số truy vấn không hợp lệ.', [
                    ['field' => (string) $key, 'code' => 'FIELD_NOT_ALLOWED', 'message' => 'Không được phép gửi tham số này.'],
                ]);
            }
        }

        $attemptId = trim((string) $request->queryParam('attemptId'));
        if (!Uuid::isValid($attemptId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mã lượt làm bài không hợp lệ.', [
                ['field' => 'attemptId', 'code' => 'INVALID_UUID', 'message' => 'attemptId phải là chuỗi UUID hợp lệ.'],
            ]);
        }

        $attempt = $context->assessmentService()->ownedAttemptWithQuestions($studentId, $attemptId);
        JsonResponder::sendSuccess($attempt, $context->requestId());
    }

    throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
} catch (ApiException $exception) {
    if ($exception->errorCode === 'AUTHENTICATION_REQUIRED') {
        $exception = new ApiException(401, 'AUTH_REQUIRED', $exception->getMessage(), $exception->details);
    }
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable $exception) {
    $msg = $exception->getMessage();
    if (str_contains($msg, 'was not found') || str_contains($msg, 'not found')) {
        JsonResponder::sendError(
            new ApiException(404, 'ASSESSMENT_ATTEMPT_NOT_FOUND', 'Không tìm thấy lượt làm bài đánh giá.'),
            $context?->requestId() ?? 'request-unavailable'
        );
    } elseif (str_contains($msg, 'Retake is not allowed within 90 days')) {
        JsonResponder::sendError(
            new ApiException(422, 'VALIDATION_FAILED', 'Chưa đủ thời gian để làm lại bài đánh giá (90 ngày).', [
                ['field' => 'assessmentCode', 'code' => 'RETAKE_LOCKED', 'message' => 'Chưa đủ thời gian 90 ngày kể từ lần nộp trước.'],
            ]),
            $context?->requestId() ?? 'request-unavailable'
        );
    } else {
        JsonResponder::sendError(
            new ApiException(500, 'SOURCE_FAILURE', 'Đã xảy ra lỗi khi xử lý lượt làm bài.'),
            $context?->requestId() ?? 'request-unavailable'
        );
    }
}
