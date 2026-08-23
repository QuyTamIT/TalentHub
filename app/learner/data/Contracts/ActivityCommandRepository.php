<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

use DateTimeImmutable;

interface ActivityCommandRepository
{
    /** @return array{registration:array<string,mixed>,promotedRegistration:null} */
    public function register(
        string $studentId,
        string $actorUserId,
        string $requestId,
        string $activityId,
        DateTimeImmutable $now,
    ): array;

    /** @return array{registration:array<string,mixed>,promotedRegistration:?array<string,mixed>} */
    public function cancel(
        string $studentId,
        string $actorUserId,
        string $requestId,
        string $registrationId,
        ?string $reason,
        DateTimeImmutable $now,
    ): array;
}
