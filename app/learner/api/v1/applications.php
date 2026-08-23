<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/JsonResponder.php';
require_once dirname(__DIR__) . '/LearnerApiContext.php';
require_once dirname(__DIR__, 2) . '/data/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Data\Security\PersistentActionRateLimiter;
use TalentHub\Learner\Data\Database\DatabaseApplicationCommandRepository;
use TalentHub\Learner\Data\Service\ApplicationCommandService;

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();
    $service = new ApplicationCommandService(new DatabaseApplicationCommandRepository($context->pdo()));

    if ($request->method === 'GET') {
        $studentId = $context->studentId('internship_application.read_own');
        $applicationId = trim((string) ($request->queryParam('id') ?? ''));
        $payload = $applicationId === '' ? $service->list($studentId) : ['application' => $service->detail($studentId, $applicationId)];
        JsonResponder::sendSuccess($payload, $context->requestId());
    }

    if ($request->method === 'POST') {
        $context->mutation($request->header('x-csrf-token'));
        $raw = $request->json();
        $action = is_string($raw['action'] ?? null) ? $raw['action'] : '';
        if ($action === 'grant-consent') {
            $identity = $context->studentIdentityForPermissions(['privacy_consent.manage_own']);
            $input = $context->allowedInput($raw, ['action', 'confirmed']);
            $consent = $service->grantConsent($identity['student_id'], $identity['user_id'], $context->requestId(), ($input['confirmed'] ?? null) === true);
            JsonResponder::sendSuccess(['consent' => $consent], $context->requestId(), 201);
        }
        if ($action === 'submit') {
            $identity = $context->studentIdentityForPermissions(['internship_application.create_own']);
            $input = $context->allowedInput($raw, ['action', 'postId', 'message']);
            (new PersistentActionRateLimiter($context->pdo()))->consume(
                'learner.application',
                $identity['student_id'],
                is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : null,
            );
            $application = $service->submit($identity['student_id'], $identity['user_id'], $context->requestId(), (string) ($input['postId'] ?? ''), (string) ($input['message'] ?? ''));
            JsonResponder::sendSuccess(['application' => $application], $context->requestId(), 201);
        }
        throw new ApiException(422, 'VALIDATION_FAILED', 'Action ứng tuyển không hợp lệ.');
    }

    if ($request->method === 'PATCH') {
        $identity = $context->studentIdentityForPermissions(['internship_application.withdraw_own']);
        $context->mutation($request->header('x-csrf-token'));
        $input = $context->allowedInput($request->json(), ['action', 'applicationId', 'reason']);
        if (($input['action'] ?? null) !== 'withdraw') {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Action ứng tuyển không hợp lệ.');
        }
        $application = $service->withdraw($identity['student_id'], $identity['user_id'], $context->requestId(), (string) ($input['applicationId'] ?? ''), (string) ($input['reason'] ?? ''));
        JsonResponder::sendSuccess(['application' => $application], $context->requestId());
    }

    throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (\Throwable) {
    JsonResponder::sendError(new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ dữ liệu tạm thời không khả dụng.'), $context?->requestId() ?? 'request-unavailable');
}
