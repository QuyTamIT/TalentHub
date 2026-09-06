<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/JsonResponder.php';
require_once dirname(__DIR__) . '/LearnerApiContext.php';

use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Ai\Service\PostAssessmentAiTrigger;
use TalentHub\Support\Uuid;

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();

    if ($request->method !== 'POST') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }

    $identity = $context->studentIdentityForPermissions(['student_profile.update_own']);
    $studentId = $identity['student_id'];
    $context->mutation($request->header('x-csrf-token'));
    $idempotencyKey = $context->idempotencyKey($request->header('x-idempotency-key'));

    $input = $context->allowedInput($request->json(), ['attemptId']);

    $attemptId = trim((string) ($input['attemptId'] ?? ''));
    if (!Uuid::isValid($attemptId)) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Mã lượt làm bài không hợp lệ.', [
            ['field' => 'attemptId', 'code' => 'INVALID_UUID', 'message' => 'attemptId phải là chuỗi UUID hợp lệ.'],
        ]);
    }

    $attempt = $context->assessmentService()->ownedAttemptWithQuestions($studentId, $attemptId);
    $context->onboardingService()->assertAssessmentAccessible(
        $studentId,
        (string) ($attempt['assessment_code'] ?? ''),
    );

    $beforeOnboarding = $context->onboardingService()->progress($studentId);
    $result = $context->assessmentService()->submit($studentId, $attemptId);
    $onboarding = $context->onboardingService()->reconcile(
        $studentId,
        $identity['user_id'],
        $context->requestId(),
        isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null,
    );
    $result['onboarding'] = $onboarding;
    $result['ai_analysis'] = PostAssessmentAiTrigger::metadata($beforeOnboarding, $onboarding);
    if (($result['ai_analysis']['required'] ?? false) === true) $result['ai_analysis']['refresh']=['status'=>'pending','delivery'=>'transactional_outbox'];
    $result['next_url'] = ($result['ai_analysis']['required'] ?? false) === true
        ? '/app/learner/discover.php?onboarding=completed&ai=analyze'
        : ($onboarding['status'] === 'completed'
            ? '/app/learner/discover.php?onboarding=completed'
            : $onboarding['next_url']);
    JsonResponder::sendSuccess($result, $context->requestId(), 200);
} catch (ApiException $exception) {
    if ($exception->errorCode === 'AUTHENTICATION_REQUIRED') {
        $exception = new ApiException(401, 'AUTH_REQUIRED', $exception->getMessage(), $exception->details);
    }
    JsonResponder::sendError($exception, $context?->requestId() ?? 'request-unavailable');
} catch (Throwable $exception) {
    $msg = $exception->getMessage();
    if (str_contains($msg, 'was not found') || str_contains($msg, 'not found')) {
        JsonResponder::sendError(
            new ApiException(404, 'ASSESSMENT_ATTEMPT_NOT_FOUND', 'Không tìm thấy lượt làm bài.'),
            $context?->requestId() ?? 'request-unavailable'
        );
    } elseif (str_contains($msg, 'required assessment questions must be answered')) {
        JsonResponder::sendError(
            new ApiException(422, 'VALIDATION_FAILED', 'Chưa hoàn thành tất cả các câu hỏi bắt buộc.', [
                ['field' => 'attemptId', 'code' => 'INCOMPLETE_ANSWERS', 'message' => 'Cần trả lời đủ các câu hỏi bắt buộc trước khi nộp.'],
            ]),
            $context?->requestId() ?? 'request-unavailable'
        );
    } else {
        JsonResponder::sendError(
            new ApiException(500, 'SOURCE_FAILURE', 'Đã xảy ra lỗi khi nộp bài đánh giá.'),
            $context?->requestId() ?? 'request-unavailable'
        );
    }
}
