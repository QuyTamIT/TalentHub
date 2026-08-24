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
        $latest = $context->recommendationService($studentId)->latest($studentId);
        JsonResponder::sendSuccess($latest ?? ['state' => 'not_generated', 'items' => []], $context->requestId());
    }
    if ($request->method === 'POST') {
        $studentId = $context->studentId('student_profile.update_own');
        $context->mutation($request->header('x-csrf-token'));
        $context->allowedInput($request->json(), []);
        $idempotencyKey = $context->idempotencyKey($request->header('x-idempotency-key'));
        (new PersistentActionRateLimiter($context->pdo()))->consume(
            'learner.ai',
            $studentId,
            is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : null,
        );
        JsonResponder::sendSuccess(
            $context->recommendationService($studentId)->generate($studentId, $context->requestId(), $idempotencyKey),
            $context->requestId(),
            202,
        );
    }
    throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ dữ liệu tạm thời không khả dụng.'), $context?->requestId() ?? 'request-unavailable');
}
