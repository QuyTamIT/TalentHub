<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface CheckinRepository
{
    /** @return array<string,mixed> */
    public function createConfirmed(string $studentId, string $actorUserId, string $requestId, string $tokenHash): array;

    /** @return list<array<string,mixed>> */
    public function history(string $studentId, int $limit, int $offset): array;
}
