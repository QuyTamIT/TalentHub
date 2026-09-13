<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/JsonResponder.php';
require dirname(__DIR__) . '/LearnerApiContext.php';

use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Data\RepositoryFactory;
use TalentHub\Learner\Data\Service\LearningTimeService;

$context = null;
try {
    $request = Request::fromGlobals();
    if ($request->method !== 'POST') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }

    $context = LearnerApiContext::fromGlobals();
    $context->mutation($request->header('x-csrf-token'));
    $identity = $context->studentIdentityForPermissions(['student_dashboard.read_own']);
    $input = $context->allowedInput($request->json(), ['sessionKey', 'pagePath']);
    $factory = new RepositoryFactory('database', $context->pdo());
    $service = new LearningTimeService($context->pdo(), $factory->badgeAwardService());

    JsonResponder::sendSuccess($service->recordHeartbeat(
        $identity['student_id'],
        (string) ($input['sessionKey'] ?? ''),
        (string) ($input['pagePath'] ?? ''),
    ), $context->requestId());
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable $exception) {
    error_log('[learning-heartbeat] ' . $exception->getMessage());
    JsonResponder::sendError(
        new ApiException(503, 'SERVICE_UNAVAILABLE', 'Không thể ghi nhận thời gian học lúc này.'),
        $context?->requestId() ?? 'request-unavailable',
    );
}
