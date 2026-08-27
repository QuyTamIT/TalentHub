<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Observability;

/** Explicit logging-boundary facade for callers that only need sanitization. */
final class AiMetricsSanitizer
{
    /** @param array<string,mixed> $event @return array<string,mixed> */
    public static function sanitize(array $event): array
    {
        return (new AiMetricsCollector(1))->sanitize($event);
    }
}
