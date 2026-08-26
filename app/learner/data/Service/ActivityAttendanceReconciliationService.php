<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Service;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use TalentHub\Learner\Data\Contracts\ActivityAttendanceReconciliationRepository;

final class ActivityAttendanceReconciliationService
{
    public function __construct(private readonly ActivityAttendanceReconciliationRepository $repository) {}

    /** @return list<array{registration_id:string,student_id:string,activity_id:string}> */
    public function run(DateTimeImmutable $now, int $graceHours = 24, int $limit = 100): array
    {
        if ($graceHours < 1) {
            throw new InvalidArgumentException('Grace hours must be a positive integer.');
        }
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Limit must be between 1 and 1000.');
        }

        return $this->repository->reconcileDueNoShows(
            $now->setTimezone(new DateTimeZone('UTC')),
            $graceHours,
            $limit
        );
    }
}
