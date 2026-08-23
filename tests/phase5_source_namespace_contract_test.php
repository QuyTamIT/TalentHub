<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Contracts\CheckinRepository;
use TalentHub\Learner\Data\Service\LearnerCheckinService;
use TalentHub\Modules\Teacher\Repository\TeacherQrSessionRepository;
use TalentHub\Modules\Teacher\Service\TeacherQrSessionService;

function phase5SourceAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

phase5SourceAssert(
    class_exists(LearnerCheckinService::class),
    'LearnerCheckinService must use its canonical namespace.'
);
phase5SourceAssert(
    class_exists(TeacherQrSessionService::class),
    'TeacherQrSessionService must use its canonical namespace.'
);
phase5SourceAssert(
    class_exists(TeacherQrSessionRepository::class),
    'TeacherQrSessionRepository must use its canonical namespace.'
);

$capturedHash = null;
$checkins = new class($capturedHash) implements CheckinRepository {
    public function __construct(private mixed &$capturedHash) {}

    public function createConfirmed(
        string $studentId,
        string $actorUserId,
        string $requestId,
        string $tokenHash,
    ): array {
        $this->capturedHash = $tokenHash;
        return ['id' => 'checkin-id'];
    }

    public function history(string $studentId, int $limit, int $offset): array
    {
        return [];
    }
};

$learnerService = new LearnerCheckinService($checkins);
$studentId = '0191316b-1000-4000-8000-000000000001';
$actorId = '0191316b-2000-4000-8000-000000000001';
$sourceToken = 'opaque-token';
$learnerService->submit($studentId, $actorId, 'request-phase5', $sourceToken);
phase5SourceAssert(
    $capturedHash === hash('sha256', 'opaque-token'),
    'LearnerCheckinService must hash the opaque token before repository use.'
);
phase5SourceAssert($sourceToken === null, 'LearnerCheckinService clears the caller token before repository work.');

try {
    $invalidToken = 'opaque token';
    $learnerService->submit($studentId, $actorId, 'request-phase5', $invalidToken);
    throw new RuntimeException('Whitespace-bearing QR tokens must be rejected.');
} catch (ApiException $exception) {
    phase5SourceAssert($exception->errorCode === 'VALIDATION_FAILED', 'Whitespace rejection must be a validation error.');
}

$teacherRepository = new TeacherQrSessionRepository(new PDO('sqlite::memory:'));
$teacherService = new TeacherQrSessionService($teacherRepository);
$validateUuid = new ReflectionMethod($teacherService, 'validateUuid');
phase5SourceAssert(
    $validateUuid->invoke($teacherService, $studentId, 'activity_id', 'invalid') === $studentId,
    'Teacher QR service must accept a canonical UUID.'
);

$validateHours = new ReflectionMethod($teacherService, 'validateHours');
phase5SourceAssert(
    $validateHours->invoke($teacherService, '24') === '24.00',
    'Teacher QR policy accepts the canonical experience-log maximum.'
);
try {
    $validateHours->invoke($teacherService, '24.01');
    throw new RuntimeException('Teacher QR policy must reject hours that experience_logs cannot store.');
} catch (ReflectionException $exception) {
    throw $exception;
} catch (Throwable $exception) {
    $cause = $exception instanceof ReflectionException ? $exception : ($exception->getPrevious() ?? $exception);
    phase5SourceAssert(
        $cause instanceof ApiException && $cause->errorCode === 'VALIDATION_FAILED',
        'Hours above 24 must use the validation error contract.'
    );
}

$repositorySource = file_get_contents(dirname(__DIR__) . '/app/learner/data/Database/DatabaseCheckinRepository.php') ?: '';
$registrationLock = strpos($repositorySource, '$registration = $this->lockRegistration');
$sessionLock = strpos($repositorySource, '$session = $this->lockSession');
$policyLock = strpos($repositorySource, '$policy = $this->lockPolicy');
phase5SourceAssert(
    is_int($registrationLock) && is_int($sessionLock) && is_int($policyLock)
        && $registrationLock < $sessionLock && $sessionLock < $policyLock,
    'Check-in lock order must remain Student -> Activity -> Registration -> QR session -> policy.'
);
phase5SourceAssert(
    !str_contains($repositorySource, "new DateTimeImmutable('now'"),
    'QR expiry must use the database clock rather than the PHP process clock.'
);

$databaseRepository = new \TalentHub\Learner\Data\Database\DatabaseCheckinRepository(new PDO('sqlite::memory:'));
$iso = new ReflectionMethod($databaseRepository, 'iso');
phase5SourceAssert(
    $iso->invoke($databaseRepository, '2026-08-22 08:15:30.123456') === '2026-08-22T08:15:30+00:00',
    'Database check-in timestamps are parsed as UTC rather than replaced with the current time.'
);

$teacherPageSource = file_get_contents(dirname(__DIR__) . '/app/teacher/checkins/index.php') ?: '';
phase5SourceAssert(
    str_contains($teacherPageSource, "'qr_session.read_managed'")
        && str_contains($teacherPageSource, "'checkin.read_managed'"),
    'Teacher page requires exact permissions for both QR sessions and managed check-in rows.'
);
$teacherRepositorySource = file_get_contents(dirname(__DIR__) . '/src/Modules/Teacher/Repository/TeacherQrSessionRepository.php') ?: '';
phase5SourceAssert(
    str_contains($teacherRepositorySource, "el.status = \\'confirmed\\'")
        && str_contains($teacherRepositorySource, "c.status = \\'confirmed\\'"),
    'Teacher managed history contains only confirmed check-ins and confirmed experiences.'
);

fwrite(STDOUT, "phase5 source namespace contract tests passed\n");
