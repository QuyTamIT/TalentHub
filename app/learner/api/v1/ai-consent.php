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
    if ($request->method === 'GET') {
        $studentId = $context->studentId('student_profile.read_own');
        JsonResponder::sendSuccess(['scopes' => $context->consentScopes($studentId)], $context->requestId());
    }
    if ($request->method === 'POST') {
        $studentId = $context->studentId('student_profile.update_own');
        $context->mutation($request->header('x-csrf-token'));
        $input = $context->allowedInput($request->json(), ['scope', 'action']);
        $scope = is_string($input['scope'] ?? null) ? trim($input['scope']) : '';
        $action = is_string($input['action'] ?? null) ? trim($input['action']) : '';
        JsonResponder::sendSuccess($context->appendConsent($studentId, $scope, $action), $context->requestId(), 201);
    }
    throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ dữ liệu tạm thời không khả dụng.'), $context?->requestId() ?? 'request-unavailable');
}
