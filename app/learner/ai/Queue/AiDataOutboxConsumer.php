<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Queue;

use Closure;
use InvalidArgumentException;
use JsonException;
use Throwable;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;

final class AiDataOutboxConsumer
{
    /** @var Closure(string, string): string */
    private readonly Closure $snapshotHash;
    private readonly Closure $studentResolver;
    /** @var Closure(list<string>): void|null */
    private readonly ?Closure $afterStudentsDispatched;

    public function __construct(
        private readonly AiDataOutboxRepository $outbox,
        private readonly AiRefreshDispatcher $dispatcher,
        callable $snapshotHash,
        callable $studentResolver,
        private readonly ?AiMetricsCollector $metrics = null,
        ?callable $afterStudentsDispatched = null,
    ) {
        $this->snapshotHash = Closure::fromCallable($snapshotHash);
        $this->studentResolver = Closure::fromCallable($studentResolver);
        $this->afterStudentsDispatched = $afterStudentsDispatched !== null ? Closure::fromCallable($afterStudentsDispatched) : null;
    }

    public function consume(int $limit = 100): int
    {
        $count = 0;
        $capabilities = ['roadmap', 'recommendation', 'profile_analysis'];

        foreach ($this->outbox->pending($limit) as $row) {
            try {
                $rawStudents = $row['affected_student_ids'] ?? null;
                if (!is_string($rawStudents) || trim($rawStudents) === '') {
                    throw new InvalidArgumentException('malformed_affected_students');
                }
                try {
                    $decoded = json_decode($rawStudents, true, 16, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new InvalidArgumentException('malformed_affected_students');
                }
                if (!is_array($decoded) || !array_is_list($decoded)) {
                    throw new InvalidArgumentException('malformed_affected_students');
                }
                $students = [];
                foreach ($decoded as $id) {
                    if (!is_string($id)) {
                        throw new InvalidArgumentException('malformed_affected_students');
                    }
                    $normalized = trim($id);
                    if ($normalized === '') {
                        continue;
                    }
                    // IDs are opaque, but reject control characters and unbounded values.
                    if (strlen($normalized) > 128 || preg_match('/[\x00-\x1F\x7F]/', $normalized) === 1) {
                        throw new InvalidArgumentException('malformed_affected_students');
                    }
                    $students[$normalized] = $normalized;
                }
                $students = array_values($students);
                if ($students === []) {
                    throw new InvalidArgumentException('malformed_affected_students');
                }

                foreach ($students as $studentId) {
                    if (!($this->studentResolver)($studentId)) {
                        throw new InvalidArgumentException('unresolvable_affected_student');
                    }
                    foreach ($capabilities as $capability) {
                        $hash = ($this->snapshotHash)($studentId, $capability);
                        $this->dispatcher->dispatch($studentId, $hash, [$capability]);
                    }
                }

                if ($this->afterStudentsDispatched !== null) {
                    ($this->afterStudentsDispatched)($students);
                }

                $this->outbox->delivered((string) $row['id']);
                $count++;
            } catch (Throwable $exception) {
                $message = strtolower($exception->getMessage());
                $isSchoolFailure = str_contains($message, 'school_refresh_dispatch_failed');
                $isPoison = str_contains($message, 'malformed_affected_students') || str_contains($message, 'unresolvable_affected_student');
                if ($isPoison) {
                    $this->outbox->failed((string) ($row['id'] ?? ''));
                }
                if ($this->metrics !== null) {
                    $category = $isPoison ? 'malformed_outbox' : ($isSchoolFailure ? 'school_refresh_dispatch_failed' : 'outbox_dispatch_failed');
                    $this->metrics->record(['queue_error' => $category]);
                }
                // Quarantine poison rows, but keep transient hash/dispatch/school failures pending for replay.
                continue;
            }
        }

        return $count;
    }
}
