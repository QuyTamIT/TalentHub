<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources;

interface StudentProfileSource
{
    /** @return array<string, mixed> */
    public function forStudent(string $studentId): array;
}
