<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Contracts;

interface StudentRepository
{
    public function findById(string $studentId): ?array;
}
