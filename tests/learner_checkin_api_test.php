<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Contracts\CheckinRepository;
use TalentHub\Learner\Data\Service\LearnerCheckinService;

function learnerCheckinAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

final class LearnerCheckinStubRepository implements CheckinRepository
{
    public ?string $studentId = null;
    public ?string $actorUserId = null;
    public ?string $requestId = null;
    public ?string $tokenHash = null;

    public function createConfirmed(string $studentId, string $actorUserId, string $requestId, string $tokenHash): array
    {
        $this->studentId = $studentId;
        $this->actorUserId = $actorUserId;
        $this->requestId = $requestId;
        $this->tokenHash = $tokenHash;
        return ['checkinId' => 'checkin-1'];
    }

    public function history(string $studentId, int $limit, int $offset): array
    {
        return [['checkinId' => 'history-1', 'activity' => ['title' => 'Demo'], 'experience' => ['hours' => '2.50']]];
    }
}

$repository = new LearnerCheckinStubRepository();
$service = new LearnerCheckinService($repository);
$studentId = '11111111-1111-4111-8111-111111111111';
$actorId = '22222222-2222-4222-8222-222222222222';
$rawToken = 'opaque-token-123';
$result = $service->submit($studentId, $actorId, 'request-phase5', $rawToken);
learnerCheckinAssert(($result['checkinId'] ?? null) === 'checkin-1', 'service returns repository payload');
learnerCheckinAssert($repository->studentId === strtolower($studentId), 'student identity is normalized');
learnerCheckinAssert($repository->actorUserId === strtolower($actorId), 'actor identity is normalized');
learnerCheckinAssert($repository->requestId === 'request-phase5', 'request id is preserved');
learnerCheckinAssert($repository->tokenHash === hash('sha256', 'opaque-token-123'), 'token hash is SHA-256 of the opaque token');
learnerCheckinAssert($rawToken === null, 'service clears the caller token before repository work completes');

foreach (['with spaces', "\n", null] as $input) {
    try {
        $service->submit($studentId, $actorId, 'request-phase5', $input);
        learnerCheckinAssert(false, 'invalid input must fail closed');
    } catch (ApiException $exception) {
        learnerCheckinAssert($exception->errorCode === 'VALIDATION_FAILED', 'invalid input uses validation contract');
        learnerCheckinAssert($input === null, 'invalid raw token is cleared before the validation exception escapes');
    }
}

$history = $service->history($studentId, 10, 0);
learnerCheckinAssert($history[0]['activity']['title'] === 'Demo', 'history returns repository rows');

$failingRepository = new class implements CheckinRepository {
    public function createConfirmed(string $studentId, string $actorUserId, string $requestId, string $tokenHash): array
    {
        throw new RuntimeException('repository failure without token');
    }
    public function history(string $studentId, int $limit, int $offset): array { return []; }
};
$traceToken = 'raw-token-must-not-enter-trace';
try {
    (new LearnerCheckinService($failingRepository))->submit($studentId, $actorId, 'request-phase5', $traceToken);
    learnerCheckinAssert(false, 'failing repository must throw');
} catch (RuntimeException $exception) {
    learnerCheckinAssert($traceToken === null, 'repository failure still clears caller token');
    $trace = json_encode($exception->getTrace(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    learnerCheckinAssert(!str_contains($trace, 'raw-token-must-not-enter-trace'), 'exception trace contains no raw QR token');
}

echo "learner_checkin_api_test: OK\n";
