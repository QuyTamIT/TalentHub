<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Events;

final class LearnerAiDataChanged
{
    public function __construct(public readonly string $studentId, public readonly string $sourceType, public readonly string $sourceId, public readonly int $sourceVersion, public readonly string $occurredAt, public readonly string $eventId = '') {}
}
