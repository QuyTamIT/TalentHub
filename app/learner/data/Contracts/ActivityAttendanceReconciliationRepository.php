<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

use DateTimeImmutable;

interface ActivityAttendanceReconciliationRepository
{
    /**
     * @return list<array{registration_id:string,student_id:string,activity_id:string}>
     */
    public function reconcileDueNoShows(DateTimeImmutable $now, int $graceHours, int $limit): array;
}
