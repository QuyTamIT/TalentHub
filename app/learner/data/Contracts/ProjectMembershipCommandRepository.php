<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

use DateTimeImmutable;

interface ProjectMembershipCommandRepository
{
    /**
     * Creates or reactivates an active membership for the learner on an
     * authorized same-school project that is currently in progress.
     *
     * @return array<string,mixed> normalized membership row with a `created` flag
     */
    public function registerActiveMember(string $studentId, string $projectId, DateTimeImmutable $now): array;

    /**
     * Creates or updates a pending membership registration for the learner on an
     * authorized same-school project awaiting school review.
     *
     * @return array<string,mixed> normalized membership row with a `created` flag
     */
    public function registerPendingMember(string $studentId, string $projectId, DateTimeImmutable $now): array;
}
