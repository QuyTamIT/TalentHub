<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/JsonResponder.php';
require_once dirname(__DIR__) . '/LearnerApiContext.php';

use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Data\Database\DatabaseCertificateCommandRepository;
use TalentHub\Learner\Data\Service\CertificateCommandService;

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();
    $service = new CertificateCommandService(new DatabaseCertificateCommandRepository($context->pdo()));

    if ($request->method === 'GET') {
        $studentId = $context->studentId('certificate.read_own');
        $certificates = $service->list($studentId);
        JsonResponder::sendSuccess(['certificates' => $certificates], $context->requestId());
    }

    if ($request->method === 'POST') {
        $studentId = $context->studentId('certificate.manage_own');
        $context->mutation($request->header('x-csrf-token'));
        $input = $context->allowedInput($request->json(), [
            'title',
            'issuingOrganization',
            'issueDate',
            'expiryDate',
            'credentialId',
            'credentialUrl',
        ]);
        $certificate = $service->create($studentId, $input);
        JsonResponder::sendSuccess(['certificate' => $certificate], $context->requestId(), 201);
    }

    if ($request->method === 'PATCH') {
        $studentId = $context->studentId('certificate.manage_own');
        $context->mutation($request->header('x-csrf-token'));
        $certificateId = (string) ($request->queryParam('id') ?? $request->json()['id'] ?? '');
        $input = $context->allowedInput($request->json(), [
            'id',
            'title',
            'issuingOrganization',
            'issueDate',
            'expiryDate',
            'credentialId',
            'credentialUrl',
        ]);
        unset($input['id']);
        $certificate = $service->update($studentId, $certificateId, $input);
        JsonResponder::sendSuccess(['certificate' => $certificate], $context->requestId());
    }

    if ($request->method === 'DELETE') {
        $studentId = $context->studentId('certificate.manage_own');
        $context->mutation($request->header('x-csrf-token'));
        $certificateId = (string) ($request->queryParam('id') ?? $request->json()['id'] ?? '');
        $service->delete($studentId, $certificateId);
        JsonResponder::sendSuccess(['deleted' => true, 'id' => $certificateId], $context->requestId());
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
