<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources;

interface AssessmentSource
{
    /** @return list<array<string, mixed>> */
    public function forStudent(string $studentId): array;
}
