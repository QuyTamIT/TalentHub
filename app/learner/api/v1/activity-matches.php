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
    if (!in_array($request->method, ['GET', 'POST'], true)) throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    $studentId = $context->studentId('student_profile.read_own');
    if ($request->method === 'POST') {
        $studentId = $context->studentId('student_profile.update_own');
        $context->mutation($request->header('x-csrf-token'));
        $context->allowedInput($request->json(), []);
        (new PersistentActionRateLimiter($context->pdo()))->consume('learner.ai', $studentId, $_SERVER['REMOTE_ADDR'] ?? null);
    }
    // Read-only readiness check. Never create schema or bypass migration history in an HTTP request.
    try {
        $context->pdo()->query('SELECT id FROM learner_activity_match_runs LIMIT 0');
    } catch (PDOException $exception) {
        if (($exception->errorInfo[0] ?? '') === '42S02') {
            throw new ApiException(503, 'ACTIVITY_MATCH_STORAGE_UNAVAILABLE', 'Hệ thống lưu gợi ý hoạt động chưa được thiết lập. Vui lòng liên hệ quản trị viên.');
        }
        throw $exception;
    }
    $service = $context->activityMatchService($studentId);
    JsonResponder::sendSuccess($request->method === 'GET' ? $service->latest($studentId) : $service->generate($studentId), $context->requestId());
} catch (\TalentHub\Learner\Ai\Consent\ProviderConsentDenied) {
    JsonResponder::sendSuccess(['state'=>'consent_required','items'=>[]], $context?->requestId() ?? 'request-unavailable');
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(new ApiException(503, 'SERVICE_UNAVAILABLE', 'Gợi ý hoạt động tạm thời không khả dụng. Vui lòng thử lại.'), $context?->requestId() ?? 'request-unavailable');
}
