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
    if ($request->method !== 'POST') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }
    $studentId = $context->studentId('student_profile.update_own');
    $context->mutation($request->header('x-csrf-token'));
    $input = $context->allowedInput($request->json(), ['itemId', 'verdict', 'reasonCode', 'safeComment']);
    $itemId = is_string($input['itemId'] ?? null) ? trim($input['itemId']) : '';
    $verdict = is_string($input['verdict'] ?? null) ? trim($input['verdict']) : '';
    $reasonCode = is_string($input['reasonCode'] ?? null) ? trim($input['reasonCode']) : '';
    $safeComment = $input['safeComment'] ?? null;
    if ($itemId === '' || !in_array($verdict, ['helpful', 'not_helpful'], true)
        || $reasonCode === '' || ($safeComment !== null && (!is_string($safeComment) || mb_strlen($safeComment) > 500))) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Dữ liệu phản hồi không hợp lệ.');
    }
    JsonResponder::sendSuccess($context->appendFeedback($studentId, $itemId, $verdict, $reasonCode, $safeComment), $context->requestId(), 201);
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ dữ liệu tạm thời không khả dụng.'), $context?->requestId() ?? 'request-unavailable');
}
