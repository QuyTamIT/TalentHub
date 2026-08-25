<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/JsonResponder.php';
require_once dirname(__DIR__) . '/LearnerApiContext.php';

use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Data\Database\DatabaseCheckinRepository;
use TalentHub\Learner\Data\Security\PersistentActionRateLimiter;
use TalentHub\Learner\Data\Service\LearnerCheckinService;

$context = null;
try {
    $request = Request::fromGlobals();
    $method = $request->method;
    $context = LearnerApiContext::fromGlobals();
    $service = new LearnerCheckinService(new DatabaseCheckinRepository($context->pdo()));

    if ($method === 'POST') {
        $context->mutation($request->header('x-csrf-token'));
        $identity = $context->studentIdentityForPermissions(['checkin.create_own']);
        (new PersistentActionRateLimiter($context->pdo()))->consume(
            'learner.checkin',
            $identity['student_id'],
            is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : null,
        );
        $input = $context->allowedInput($request->json(), ['token']);
        $rawToken = $input['token'] ?? null;
        unset($input['token'], $input, $request);
        JsonResponder::sendSuccess(
            $service->submit($identity['student_id'], $identity['user_id'], $context->requestId(), $rawToken),
            $context->requestId(),
            201,
        );
    }

    if ($method === 'GET') {
        $identity = $context->studentIdentityForPermissions(['experience_log.read_own']);
        $limit = max(1, min(100, (int) ($request->queryParam('limit') ?? 25)));
        $offset = max(0, (int) ($request->queryParam('offset') ?? 0));
        JsonResponder::sendSuccess([
            'items' => $service->history($identity['student_id'], $limit, $offset),
            'pagination' => ['limit' => $limit, 'offset' => $offset],
        ], $context->requestId());
    }

    throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phuong thuc khong duoc ho tro.');
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(
        new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dich vu check-in tam thoi khong kha dung.'),
        $context?->requestId() ?? 'request-unavailable',
    );
}
