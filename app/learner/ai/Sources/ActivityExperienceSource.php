<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources;

interface ActivityExperienceSource
{
    /** @return list<array<string, mixed>> */
    public function forStudent(string $studentId): array;
}
