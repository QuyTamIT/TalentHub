<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Persistence;

use TalentHub\Learner\Ai\Evaluation\EvaluationQuery;
use TalentHub\Learner\Ai\Evaluation\EvaluationRecord;

interface EvaluationRepository
{
    /** @return array{record:array<string,mixed>,reused:bool} */
    public function append(EvaluationRecord $record): array;
    /** @return array<string,mixed>|null */
    public function latestByModelRun(string $studentId, string $modelRunId): ?array;
    /** @return list<array<string,mixed>> */
    public function aggregate(EvaluationQuery $query): array;
}
