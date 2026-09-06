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
    $idempotencyKey = $context->idempotencyKey($request->header('x-idempotency-key'));
    $input = $context->allowedInput($request->json(), ['itemId', 'roadmapId', 'verdict', 'reasonCode', 'safeComment']);
    $itemId = is_string($input['itemId'] ?? null) ? trim($input['itemId']) : '';
    $roadmapId = is_string($input['roadmapId'] ?? null) ? trim($input['roadmapId']) : '';
    $verdict = is_string($input['verdict'] ?? null) ? trim($input['verdict']) : '';
    $reasonCode = is_string($input['reasonCode'] ?? null) ? trim($input['reasonCode']) : '';
    $safeComment = $input['safeComment'] ?? null;
    if (($itemId === '') === ($roadmapId === '') || !in_array($verdict, ['helpful', 'not_helpful'], true)
        || $reasonCode === '' || ($safeComment !== null && (!is_string($safeComment) || mb_strlen($safeComment) > 500))
        || ($roadmapId !== '' && $safeComment !== null)) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Dữ liệu phản hồi không hợp lệ.');
    }
    (new PersistentActionRateLimiter($context->pdo()))->consume(
        'learner.ai',
        $studentId,
        isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
    );
    if ($roadmapId !== '') {
        if (!Uuid::isValid($roadmapId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mã lộ trình không hợp lệ.');
        }
        $hex = substr(hash('sha256', $studentId . '|' . $roadmapId . '|' . $idempotencyKey), 0, 32);
        $hex[12] = '4';
        $hex[16] = ['8','9','a','b'][hexdec($hex[16]) % 4];
        $feedbackRequestId = sprintf('%s-%s-%s-%s-%s', substr($hex,0,8), substr($hex,8,4), substr($hex,12,4), substr($hex,16,4), substr($hex,20));
        $result = $context->appendRoadmapFeedback($studentId, $roadmapId, $verdict, $reasonCode, $feedbackRequestId);
        if (($result['state'] ?? null) === 'invalid_feedback') {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Phản hồi lộ trình không hợp lệ.');
        }
        if (($result['state'] ?? null) !== 'feedback_saved') {
            throw new ApiException(503, 'SERVICE_UNAVAILABLE', 'Không thể lưu phản hồi lộ trình lúc này.');
        }
        $refresh = $context->dispatchAiRefresh($studentId);
        $result['refresh_state'] = ($refresh['status'] === 'pending' && $refresh['job_keys'] !== []) ? 'pending' : 'unavailable';
        $result['refresh_job_keys'] = $refresh['job_keys'];
        $result['refresh_dispatch_status'] = $refresh['status'];
        JsonResponder::sendSuccess($result, $context->requestId(), 201);
    }
    $result = $context->appendFeedback($studentId, $itemId, $verdict, $reasonCode, is_string($safeComment) ? $safeComment : null, $idempotencyKey);
    if (($result['state'] ?? null) === 'idempotency_conflict') {
        throw new ApiException(409, 'IDEMPOTENCY_CONFLICT', 'Khóa chống lặp đã được dùng cho một phản hồi khác.');
    }
    $refresh = $context->dispatchAiRefresh($studentId);
    $result['refresh_state'] = ($refresh['status'] === 'pending' && $refresh['job_keys'] !== []) ? 'pending' : 'unavailable';
    $result['refresh_job_keys'] = $refresh['job_keys'];
    $result['refresh_dispatch_status'] = $refresh['status'];
    JsonResponder::sendSuccess($result, $context->requestId(), 201);
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ dữ liệu tạm thời không khả dụng.'), $context?->requestId() ?? 'request-unavailable');
}
