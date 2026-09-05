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
use TalentHub\Learner\Data\Service\StatisticsService;

$context = null;
try {
    $request = Request::fromGlobals();
    $context = LearnerApiContext::fromGlobals();

    if ($request->method !== 'GET') {
        throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Phương thức không được hỗ trợ.');
    }

    $studentId = $context->studentId('student_dashboard.read_own');

    $unknownQueryFields = array_diff(array_keys($request->queryParams()), ['period']);
    if ($unknownQueryFields !== []) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Query chứa field không được phép.', array_map(
            static fn (string $field): array => [
                'field' => $field,
                'code' => 'FIELD_NOT_ALLOWED',
                'message' => 'Không được phép gửi field này.',
            ],
            array_values($unknownQueryFields)
        ));
    }

    $rawPeriod = $request->queryParam('period');
    $hasExplicitPeriod = $rawPeriod !== null && trim((string) $rawPeriod) !== '';
    $period = $hasExplicitPeriod ? strtolower(trim((string) $rawPeriod)) : 'semester';

    if (!in_array($period, StatisticsService::ALLOWED_PERIODS, true)) {
        throw new ApiException(422, 'VALIDATION_FAILED', 'Khoảng thời gian thống kê không hợp lệ.', [
            [
                'field' => 'period',
                'code' => 'INVALID_PERIOD',
                'message' => 'Khoảng thời gian phải là: ' . implode(', ', StatisticsService::ALLOWED_PERIODS),
            ],
        ]);
    }

    learner_configure_data([
        'source' => 'database',
        'pdo' => $context->pdo(),
        'student_id' => $studentId,
    ]);

    $factory = learner_repository_factory();
    $data = $factory->statisticsService()->forStudentPeriod($studentId, $period);

    if (!$hasExplicitPeriod) {
        $periodHoursSum = array_sum($data['experience']['hours'] ?? []);
        $lifetimeHours = (float) ($data['facts']['confirmed_experience_hours'] ?? 0.0);
        if ($periodHoursSum <= 0.0 && $lifetimeHours > 0.0) {
            $data = $factory->statisticsService()->forStudentPeriod($studentId, 'all');
        }
    }

    JsonResponder::sendSuccess($data, $context->requestId());
} catch (ApiException $exception) {
    JsonResponder::sendError($exception, $context?->requestId() ?? 'req-anonymous');
} catch (Throwable $exception) {
    JsonResponder::sendError(
        new ApiException(500, 'INTERNAL_ERROR', 'Đã xảy ra lỗi hệ thống khi tải thống kê.'),
        $context?->requestId() ?? 'req-anonymous'
    );
}
