<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/JsonResponder.php';
require_once dirname(__DIR__) . '/LearnerApiContext.php';

use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Data\Security\PersistentActionRateLimiter;

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();

    if ($request->method === 'GET') {
        $studentId = $context->studentId('student_profile.read_own');
        $scopes = $context->consentScopes($studentId);

        if (!in_array('activity', $scopes, true)) {
            JsonResponder::sendSuccess(['state' => 'consent_required', 'items' => []], $context->requestId());
        }

        $items = $context->groupMatchingService($studentId)->match($studentId, 10);
        $state = 'ready';
        if ($items === []) {
            if (!in_array('skills', $scopes, true) && !in_array('assessment', $scopes, true)) {
                $state = 'data_insufficient';
            } else {
                $state = 'ready';
            }
        }

        JsonResponder::sendSuccess(['state' => $state, 'items' => $items], $context->requestId());
    }

    if ($request->method !== 'POST') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }

    $studentId = $context->studentId('student_profile.update_own');
    $context->mutation($request->header('x-csrf-token'));
    $input = $context->allowedInput($request->json(), ['catalog_id', 'action']);

    $catalogId = is_string($input['catalog_id'] ?? null) ? trim((string) $input['catalog_id']) : '';
    $action = is_string($input['action'] ?? null) ? trim((string) $input['action']) : '';

    if ($catalogId === '' || strlen($catalogId) > 128) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Mã danh mục không hợp lệ.', [
            ['field' => 'catalog_id', 'code' => 'INVALID_VALUE', 'message' => 'catalog_id là bắt buộc.'],
        ]);
    }

    if (!in_array($action, ['join_group', 'open_catalog_item'], true)) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Hành động không hợp lệ.', [
            ['field' => 'action', 'code' => 'INVALID_VALUE', 'message' => 'action chỉ nhận join_group hoặc open_catalog_item.'],
        ]);
    }

    $context->idempotencyKey($request->header('x-idempotency-key'));
    (new PersistentActionRateLimiter($context->pdo()))->consume(
        'learner.ai',
        $studentId,
        isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
    );

    $result = $context->groupMatchingService($studentId)->resolveAction($studentId, $catalogId, $action);

    if (in_array($result['state'] ?? '', ['action_ready', 'catalog_opened'], true)) {
        AiMetricsCollector::shared()->record([
            'recommendation_click' => true,
            'recommendation_action' => $action,
        ]);
    }

    JsonResponder::sendSuccess($result, $context->requestId(), 200);
} catch (ApiException $exception) {
    if ($exception->errorCode === 'AUTHENTICATION_REQUIRED') {
        $exception = new ApiException(401, 'AUTH_REQUIRED', $exception->getMessage(), $exception->details, $exception->headers);
    }
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(
        new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ ghép nhóm AI tạm thời không khả dụng.'),
        $context?->requestId() ?? 'request-unavailable',
    );
}
