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

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();
    if ($request->method !== 'GET') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }
    if (array_keys($request->queryParams()) !== []) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Query chứa field không được phép.');
    }

    $studentId = $context->studentId('badge.read_own');
    learner_configure_data([
        'source' => 'database',
        'pdo' => $context->pdo(),
        'student_id' => $studentId,
    ]);
    $data = learner_repository_factory()->schoolCredentialService()->forStudent($studentId);
    JsonResponder::sendSuccess($data, $context->requestId());
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(
        new ApiException(500, 'INTERNAL_ERROR', 'Đã xảy ra lỗi khi tải huy hiệu và chứng chỉ của trường.'),
        $context?->requestId() ?? 'request-unavailable'
    );
}
