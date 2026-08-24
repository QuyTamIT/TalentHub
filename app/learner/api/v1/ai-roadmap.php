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
        $roadmap = $context->roadmapService($studentId)->latest($studentId);
        JsonResponder::sendSuccess($roadmap ?? ['state' => 'not_generated'], $context->requestId());
    }

    if ($request->method !== 'POST') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }

    $studentId = $context->studentId('student_profile.update_own');
    $context->mutation($request->header('x-csrf-token'));
    $input = $context->allowedInput($request->json(), ['action']);
    $action = is_string($input['action'] ?? null) ? trim((string) $input['action']) : 'generate';
    if (!in_array($action, ['generate', 'refresh'], true)) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Thao tác phân tích không hợp lệ.', [
            ['field' => 'action', 'code' => 'INVALID_VALUE', 'message' => 'action chỉ nhận generate hoặc refresh.'],
        ]);
    }
    $idempotencyKey = $context->idempotencyKey($request->header('x-idempotency-key'));
    (new PersistentActionRateLimiter($context->pdo()))->consume(
        'learner.ai',
        $studentId,
        isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
    );
    $result = $context->roadmapService($studentId)->generate($studentId, $context->requestId(), $idempotencyKey);
    if (($result['state'] ?? null) === 'forbidden') {
        throw new ApiException(403, 'PERMISSION_DENIED', 'Bạn không có quyền tạo lộ trình này.');
    }
    $status = ($result['state'] ?? null) === 'pending' ? 202 : 200;
    JsonResponder::sendSuccess($result, $context->requestId(), $status);
} catch (ApiException $exception) {
    if ($exception->errorCode === 'AUTHENTICATION_REQUIRED') {
        $exception = new ApiException(401, 'AUTH_REQUIRED', $exception->getMessage(), $exception->details, $exception->headers);
    }
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(
        new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ lộ trình AI tạm thời không khả dụng.'),
        $context?->requestId() ?? 'request-unavailable',
    );
}
