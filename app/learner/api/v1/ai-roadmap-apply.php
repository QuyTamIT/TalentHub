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
    if ($request->method !== 'POST') throw new ApiException(405,'METHOD_NOT_ALLOWED','Phương thức không được hỗ trợ.');
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) throw new ApiException(413,'PAYLOAD_TOO_LARGE','Nội dung roadmap vượt quá giới hạn cho phép.');
    $studentId = $context->studentId('student_profile.update_own');
    $context->mutation($request->header('x-csrf-token'));
    $json = $request->json();
    if (strlen((string)json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > 65536) throw new ApiException(413,'PAYLOAD_TOO_LARGE','Nội dung roadmap vượt quá giới hạn cho phép.');
    $input = $context->allowedInput($json, ['roadmapId','baseVersion','source','draft','refinementId']);
    $roadmapId = is_string($input['roadmapId'] ?? null) ? trim($input['roadmapId']) : '';
    $baseVersion = $input['baseVersion'] ?? null;
    $source = is_string($input['source'] ?? null) ? trim($input['source']) : '';
    $draft = $input['draft'] ?? null;
    $refinementId = $input['refinementId'] ?? null;
    if (!Uuid::isValid($roadmapId) || !is_int($baseVersion) || $baseVersion < 1 || $baseVersion > 999999 || !in_array($source,['learner_draft','ai_refined'],true) || !is_array($draft)
        || ($refinementId !== null && (!is_string($refinementId) || !Uuid::isValid($refinementId))) || ($source === 'ai_refined' && !is_string($refinementId))) {
        throw new ApiException(422,'VALIDATION_FAILED','Dữ liệu áp dụng roadmap không hợp lệ.');
    }
    $idempotencyKey = $context->idempotencyKey($request->header('x-idempotency-key'));
    (new PersistentActionRateLimiter($context->pdo()))->consume('learner.ai',$studentId,isset($_SERVER['REMOTE_ADDR'])?(string)$_SERVER['REMOTE_ADDR']:null);
    $result = $context->roadmapCustomizationService($studentId)->apply($studentId,$roadmapId,$baseVersion,$source,$draft,is_string($refinementId)?$refinementId:null,$context->requestId(),$idempotencyKey);
    match ($result['state'] ?? null) {
        'roadmap_customized' => JsonResponder::sendSuccess($result,$context->requestId()),
        'forbidden' => throw new ApiException(403,'PERMISSION_DENIED','Bạn không có quyền chỉnh sửa roadmap này.'),
        'invalid_draft' => throw new ApiException(422,'VALIDATION_FAILED','Nội dung roadmap chưa hợp lệ.'),
        'invalid_refinement_contract' => throw new ApiException(422,'AI_REFINEMENT_INVALID','Bản tinh chỉnh AI không hợp lệ hoặc đã hết hạn.'),
        'stale_base' => throw new ApiException(409,'ROADMAP_VERSION_CONFLICT','Roadmap đã có phiên bản mới. Vui lòng tải lại.'),
        default => throw new ApiException(503,'SERVICE_UNAVAILABLE','Chưa thể áp dụng roadmap lúc này.'),
    };
} catch (ApiException $exception) {
    if ($exception->errorCode === 'AUTHENTICATION_REQUIRED') $exception = new ApiException(401,'AUTH_REQUIRED',$exception->getMessage(),$exception->details,$exception->headers);
    JsonResponder::sendError($exception,$context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(new ApiException(503,'SERVICE_UNAVAILABLE','Chưa thể áp dụng roadmap lúc này.'),$context?->requestId() ?? 'request-unavailable');
}
