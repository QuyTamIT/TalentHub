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

    if ($request->method !== 'PATCH') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }

    $studentId = $context->studentId('student_profile.update_own');
    $context->mutation($request->header('x-csrf-token'));

    $input = $context->allowedInput($request->json(), ['attemptId', 'questionId', 'answer']);

    $attemptId = trim((string) ($input['attemptId'] ?? ''));
    if (!Uuid::isValid($attemptId)) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Mã lượt làm bài không hợp lệ.', [
            ['field' => 'attemptId', 'code' => 'INVALID_UUID', 'message' => 'attemptId phải là chuỗi UUID hợp lệ.'],
        ]);
    }

    $questionId = trim((string) ($input['questionId'] ?? ''));
    if (!Uuid::isValid($questionId)) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Mã câu hỏi không hợp lệ.', [
            ['field' => 'questionId', 'code' => 'INVALID_UUID', 'message' => 'questionId phải là chuỗi UUID hợp lệ.'],
        ]);
    }

    if (!array_key_exists('answer', $input)) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Thiếu câu trả lời.', [
            ['field' => 'answer', 'code' => 'REQUIRED_FIELD', 'message' => 'Cần cung cấp giá trị câu trả lời.'],
        ]);
    }

    $answer = $input['answer'];
    $saved = $context->assessmentService()->saveAnswer($studentId, $attemptId, $questionId, $answer);
    JsonResponder::sendSuccess($saved, $context->requestId(), 200);
} catch (ApiException $exception) {
    if ($exception->errorCode === 'AUTHENTICATION_REQUIRED') {
        $exception = new ApiException(401, 'AUTH_REQUIRED', $exception->getMessage(), $exception->details);
    }
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable $exception) {
    $msg = $exception->getMessage();
    if (str_contains($msg, 'was not found') || str_contains($msg, 'not found')) {
        JsonResponder::sendError(
            new ApiException(404, 'ASSESSMENT_ATTEMPT_NOT_FOUND', 'Không tìm thấy lượt làm bài hoặc câu hỏi.'),
            $context?->requestId() ?? 'request-unavailable'
        );
    } elseif (str_contains($msg, 'cannot be modified') || str_contains($msg, 'not in progress') || str_contains($msg, 'submitted attempt')) {
        JsonResponder::sendError(
            new ApiException(409, 'ATTEMPT_NOT_IN_PROGRESS', 'Lượt làm bài đã hoàn thành hoặc không thể chỉnh sửa.'),
            $context?->requestId() ?? 'request-unavailable'
        );
    } elseif (str_contains($msg, 'Likert') || str_contains($msg, 'integer between 1 and 5')) {
        JsonResponder::sendError(
            new ApiException(422, 'VALIDATION_FAILED', 'Câu trả lời không hợp lệ.', [
                ['field' => 'answer', 'code' => 'INVALID_ANSWER', 'message' => 'Câu trả lời phải là số nguyên từ 1 đến 5.'],
            ]),
            $context?->requestId() ?? 'request-unavailable'
        );
    } else {
        JsonResponder::sendError(
            new ApiException(500, 'SOURCE_FAILURE', 'Đã xảy ra lỗi khi lưu câu trả lời.'),
            $context?->requestId() ?? 'request-unavailable'
        );
    }
}
