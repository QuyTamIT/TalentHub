<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/JsonResponder.php';
require_once dirname(__DIR__) . '/LearnerApiContext.php';

use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Data\Service\ProfileSharingService;

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();
    $service = new ProfileSharingService($context->pdo());

    if ($request->method === 'GET') {
        $studentId = $context->studentIdForPermissions(['student_profile.share_own']);
        $shares = $service->listShares($studentId);
        JsonResponder::sendSuccess(['shares' => $shares], $context->requestId());
    }

    if ($request->method === 'POST') {
        $studentId = $context->studentIdForPermissions([
            'student_profile.share_own',
            'privacy_consent.manage_own',
        ]);
        $context->mutation($request->header('x-csrf-token'));
        $input = $context->allowedInput($request->json(), ['sharedFields', 'expiresInDays']);
        $sharedFields = is_array($input['sharedFields'] ?? null) ? $input['sharedFields'] : [];
        $expiresInDays = isset($input['expiresInDays']) && is_numeric($input['expiresInDays']) ? (int) $input['expiresInDays'] : 30;

        $share = $service->createShare($studentId, $sharedFields, $expiresInDays);
        JsonResponder::sendSuccess(['share' => $share], $context->requestId(), 201);
    }

    if ($request->method === 'DELETE') {
        $studentId = $context->studentIdForPermissions([
            'student_profile.share_own',
            'privacy_consent.manage_own',
        ]);
        $context->mutation($request->header('x-csrf-token'));
        $shareId = (string) ($request->queryParam('id') ?? $request->json()['id'] ?? '');
        $service->revokeShare($studentId, $shareId);
        JsonResponder::sendSuccess(['revoked' => true, 'id' => $shareId], $context->requestId());
    }

    throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable $e) {
    JsonResponder::sendError(
        new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ dữ liệu tạm thời không khả dụng.'),
        $context?->requestId() ?? 'request-unavailable'
    );
}
