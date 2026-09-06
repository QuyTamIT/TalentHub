<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Sources;

interface LearnerAiExtendedSource
{
    public function sourceType(): string;

    public function schemaVersion(): string;

    public function consentScope(): string;

    /** @return list<string> */
    public function allowedFields(): array;

    public function refreshTrigger(): string;

    /** @return list<array<string,mixed>> */
    public function readForStudent(string $studentId): array;

    public function changedSince(string $studentId, ?string $versionOrTimestamp): bool;
}
