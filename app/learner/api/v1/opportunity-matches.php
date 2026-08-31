<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/JsonResponder.php';
require_once dirname(__DIR__) . '/LearnerApiContext.php';

use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Data\Security\PersistentActionRateLimiter;

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();

    if ($request->method === 'GET') {
        $studentId = $context->studentId('student_profile.read_own');
        JsonResponder::sendSuccess(
            $context->opportunityMatchService($studentId)->latest($studentId),
            $context->requestId(),
        );
    }

    if ($request->method !== 'POST') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }

    $studentId = $context->studentId('student_profile.update_own');
    $context->mutation($request->header('x-csrf-token'));
    $context->allowedInput($request->json(), []);
    $idempotencyKey = $context->idempotencyKey($request->header('x-idempotency-key'));
    (new PersistentActionRateLimiter($context->pdo()))->consume(
        'learner.ai',
        $studentId,
        isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
    );
    $result = $context->opportunityMatchService($studentId)->generate(
        $studentId,
        $context->requestId(),
        $idempotencyKey,
    );
    JsonResponder::sendSuccess($result, $context->requestId(), 202);
} catch (ApiException $exception) {
    if ($exception->errorCode === 'AUTHENTICATION_REQUIRED') {
        $exception = new ApiException(401, 'AUTH_REQUIRED', $exception->getMessage(), $exception->details, $exception->headers);
    }
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(
        new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ AI gợi ý cơ hội tạm thời không khả dụng.'),
        $context?->requestId() ?? 'request-unavailable',
    );
}
