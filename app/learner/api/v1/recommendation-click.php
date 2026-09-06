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
    if ($request->method !== 'POST') throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    $studentId = $context->studentId('student_profile.update_own');
    $context->mutation($request->header('x-csrf-token'));
    $input = $context->allowedInput($request->json(), ['itemId', 'catalogId', 'actionType']);
    $itemId = is_string($input['itemId'] ?? null) ? trim($input['itemId']) : '';
    $catalogIdProvided = array_key_exists('catalogId', $input);
    $catalogId = $catalogIdProvided && is_string($input['catalogId']) ? trim($input['catalogId']) : null;
    $actionType = is_string($input['actionType'] ?? null) ? trim($input['actionType']) : '';
    if ($itemId === '' || strlen($itemId) > 128 || ($catalogIdProvided && $catalogId === null)
        || ($catalogId !== null && ($catalogId === '' || strlen($catalogId) > 128)) || $actionType === '') {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Dữ liệu click gợi ý không hợp lệ.');
    }
    try {
        $result = $context->recordRecommendationClick($studentId, $itemId, $catalogId, $actionType);
    } catch (InvalidArgumentException) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Dữ liệu click gợi ý không hợp lệ.');
    } catch (\DomainException) {
        throw new ApiException(404, 'NOT_FOUND', 'Gợi ý không tồn tại hoặc không còn khả dụng.');
    }
    JsonResponder::sendSuccess($result, $context->requestId(), 202);
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(new ApiException(503, 'SERVICE_UNAVAILABLE', 'Không thể ghi nhận tương tác lúc này.'), $context?->requestId() ?? 'request-unavailable');
}
