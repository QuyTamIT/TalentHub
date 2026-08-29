<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Queue;

use Throwable;

final class AiDataOutboxConsumer
{
    /** @var \Closure(string, string): string */
    private readonly \Closure $snapshotHash;

    public function __construct(
        private readonly AiDataOutboxRepository $outbox,
        private readonly AiRefreshDispatcher $dispatcher,
        callable $snapshotHash
    ) {
        $this->snapshotHash = \Closure::fromCallable($snapshotHash);
    }

    public function consume(int $limit = 100): int
    {
        $count = 0;
        $capabilities = ['roadmap', 'recommendation', 'profile_analysis'];

        foreach ($this->outbox->pending($limit) as $row) {
            try {
                $rawStudents = (string) ($row['affected_student_ids'] ?? '[]');
                $students = json_decode($rawStudents, true);
                if (!is_array($students)) {
                    $students = [];
                }
                $students = array_values(array_unique(array_filter(
                    $students,
                    static fn(mixed $id): bool => is_string($id) && trim($id) !== ''
                )));

                if ($students === []) {
                    $this->outbox->delivered((string) $row['id']);
                    $count++;
                    continue;
                }

                foreach ($students as $studentId) {
                    foreach ($capabilities as $capability) {
                        $hash = ($this->snapshotHash)($studentId, $capability);
                        $this->dispatcher->dispatch($studentId, $hash, [$capability]);
                    }
                }

                $this->outbox->delivered((string) $row['id']);
                $count++;
            } catch (Throwable) {
                // Keep the individual failed row pending and allow remaining batch to process
                continue;
            }
        }

        return $count;
    }
}
