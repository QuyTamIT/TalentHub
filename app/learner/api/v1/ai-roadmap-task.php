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
use TalentHub\Support\Uuid;

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();
    if ($request->method !== 'POST') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }

    $studentId = $context->studentId('student_profile.update_own');
    $context->mutation($request->header('x-csrf-token'));
    $input = $context->allowedInput($request->json(), ['taskId', 'status']);
    $taskId = is_string($input['taskId'] ?? null) ? trim((string) $input['taskId']) : '';
    if (!Uuid::isValid($taskId)) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Mã nhiệm vụ không hợp lệ.', [
            ['field' => 'taskId', 'code' => 'INVALID_UUID', 'message' => 'taskId phải là UUID hợp lệ.'],
        ]);
    }
    $status = is_string($input['status'] ?? null) ? trim((string) $input['status']) : '';
    if (!in_array($status, ['in_progress', 'completed', 'skipped'], true)) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Trạng thái nhiệm vụ không hợp lệ.', [
            ['field' => 'status', 'code' => 'INVALID_VALUE', 'message' => 'Trạng thái không nằm trong danh sách cho phép.'],
        ]);
    }
    $idempotencyKey = $context->idempotencyKey($request->header('x-idempotency-key'));
    (new PersistentActionRateLimiter($context->pdo()))->consume(
        'learner.ai',
        $studentId,
        isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
    );
    $result = $context->roadmapService($studentId)->updateTask($studentId, $taskId, $status, $idempotencyKey);
    if (($result['state'] ?? null) === 'forbidden') {
        throw new ApiException(403, 'PERMISSION_DENIED', 'Bạn không có quyền cập nhật nhiệm vụ này.');
    }
    if (($result['state'] ?? null) === 'invalid_task_transition') {
        throw new ApiException(409, 'ROADMAP_TASK_UPDATE_REJECTED', 'Không thể cập nhật nhiệm vụ với trạng thái đã chọn.');
    }
    if (($result['state'] ?? null) !== 'task_updated') {
        throw new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ tiến độ lộ trình tạm thời không khả dụng.');
    }
    JsonResponder::sendSuccess($result, $context->requestId());
} catch (ApiException $exception) {
    if ($exception->errorCode === 'AUTHENTICATION_REQUIRED') {
        $exception = new ApiException(401, 'AUTH_REQUIRED', $exception->getMessage(), $exception->details, $exception->headers);
    }
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(
        new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ tiến độ lộ trình tạm thời không khả dụng.'),
        $context?->requestId() ?? 'request-unavailable',
    );
}
