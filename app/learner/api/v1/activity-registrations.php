<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/JsonResponder.php';
require_once dirname(__DIR__) . '/LearnerApiContext.php';

use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Data\Database\DatabaseActivityCommandRepository;
use TalentHub\Learner\Data\Service\ActivityRegistrationService;

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();
    if ($request->method !== 'POST') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }

    $context->mutation($request->header('x-csrf-token'));
    $input = $context->allowedInput(
        $request->json(),
        ['action', 'activityId', 'registrationId', 'reason'],
    );
    $action = is_string($input['action'] ?? null) ? trim($input['action']) : '';
    unset($input['action']);

    $permission = match ($action) {
        'register' => 'activity_registration.create_own',
        'cancel' => 'activity_registration.cancel_own',
        'cancel_ticket' => 'activity_registration.cancel_own',
        default => throw new ApiException(422, 'VALIDATION_FAILED', 'Action đăng ký hoạt động không hợp lệ.'),
    };
    $identity = $context->studentIdentityForPermissions([$permission]);
    $service = new ActivityRegistrationService(new DatabaseActivityCommandRepository($context->pdo()));

    if ($action === 'register') {
        // Kiểm tra xem cặp (student_id, activity_id) đã tồn tại trong bảng registrations (activity_registrations) hay chưa
        $pdo = $context->pdo();
        $stmt = $pdo->prepare('SELECT id FROM activity_registrations WHERE studentId = :studentId AND activityId = :activityId LIMIT 1');
        $stmt->execute([
            'studentId' => $identity['student_id'],
            'activityId' => $input['activityId']
        ]);
        
        if ($stmt->fetch()) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["status" => "error", "message" => "Bạn đã đăng ký hoạt động này rồi!"]);
            exit;
        }

        JsonResponder::sendSuccess(
            $service->register($identity['student_id'], $identity['user_id'], $context->requestId(), $input),
            $context->requestId(),
            201,
        );
    } elseif ($action === 'cancel_ticket') {
        $pdo = $context->pdo();
        $stmt = $pdo->prepare('DELETE FROM activity_registrations WHERE studentId = :studentId AND activityId = :activityId');
        $stmt->execute([
            'studentId' => $identity['student_id'],
            'activityId' => $input['activityId']
        ]);
        
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "success", "message" => "Hủy vé thành công"]);
        exit;
    }

    JsonResponder::sendSuccess(
        $service->cancel($identity['student_id'], $identity['user_id'], $context->requestId(), $input),
        $context->requestId(),
    );
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable) {
    JsonResponder::sendError(
        new ApiException(503, 'SERVICE_UNAVAILABLE', 'Dịch vụ dữ liệu tạm thời không khả dụng.'),
        $context?->requestId() ?? 'request-unavailable',
    );
}
