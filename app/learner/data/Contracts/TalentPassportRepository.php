<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface TalentPassportRepository
{
    /** @return array<string,mixed> */
    public function aggregateForStudent(string $studentId): array;
}
