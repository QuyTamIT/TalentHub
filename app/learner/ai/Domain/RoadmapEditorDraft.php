<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Domain;

final class RoadmapEditorDraft
{
    private const ROOT_FIELDS = ['phases'];
    private const PHASE_FIELDS = [
        'phase_id', 'position', 'start_day', 'end_day', 'code', 'title', 'goal',
        'skill_focus', 'deliverable', 'effort_label', 'metric_label', 'tasks',
    ];
    private const TASK_FIELDS = [
        'task_id', 'position', 'title', 'description', 'milestone_day', 'estimated_minutes',
    ];
    private const RANGES = [1 => [0, 30], 2 => [31, 60], 3 => [61, 90]];
    private const MIN_TASKS = 1;
    private const MAX_TASKS = 10;
    private const LIMITS = [
        'phase_title' => 120,
        'phase_goal' => 700,
        'phase_fact' => 500,
        'task_title' => 220,
        'task_description' => 900,
    ];

    /** @var array{phases:list<array<string,mixed>>} */
    private readonly array $draft;

    /** @param array{phases:list<array<string,mixed>>} $draft */
    private function __construct(array $draft)
    {
        $this->draft = $draft;
    }

    /** @param array<string,mixed> $payload @param array<string,mixed>|null $base */
    public static function fromArray(array $payload, ?array $base = null): self
    {
        $draft = self::hydrate($payload, true);
        if ($base !== null) {
            $draft->assertRetainedFrom(self::hydrate($base, false));
        }
        return $draft;
    }

    /** @param array<string,mixed> $payload */
    private static function hydrate(array $payload, bool $enforceMaximum): self
    {
        self::assertAllowedFields($payload, self::ROOT_FIELDS, 'Roadmap draft');
        $rawPhases = $payload['phases'] ?? null;
        if (!is_array($rawPhases) || !array_is_list($rawPhases) || count($rawPhases) !== 3) {
            throw new \InvalidArgumentException('Roadmap draft requires exactly three phases.');
        }

        $phases = [];
        $allTaskIds = [];
        foreach ($rawPhases as $index => $rawPhase) {
            if (!is_array($rawPhase)) {
                throw new \InvalidArgumentException('Roadmap draft phase is invalid.');
            }
            self::assertAllowedFields($rawPhase, self::PHASE_FIELDS, 'Roadmap draft phase');
            $position = self::integer($rawPhase['position'] ?? null, 'Roadmap phase position is invalid.');
            $expectedPosition = $index + 1;
            if ($position !== $expectedPosition || !isset(self::RANGES[$position])) {
                throw new \InvalidArgumentException('Roadmap phase positions are invalid.');
            }
            [$expectedStart, $expectedEnd] = self::RANGES[$position];
            $startDay = self::integer($rawPhase['start_day'] ?? null, 'Roadmap phase start day is invalid.');
            $endDay = self::integer($rawPhase['end_day'] ?? null, 'Roadmap phase end day is invalid.');
            if ($startDay !== $expectedStart || $endDay !== $expectedEnd) {
                throw new \InvalidArgumentException('Roadmap phase day range is immutable.');
            }

            $phaseId = self::uuid($rawPhase['phase_id'] ?? null, 'Roadmap phase id is invalid.');
            $code = self::copy($rawPhase['code'] ?? null, 64, 'Roadmap phase code is invalid.');
            if (preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $code) !== 1) {
                throw new \InvalidArgumentException('Roadmap phase code is invalid.');
            }

            $rawTasks = $rawPhase['tasks'] ?? null;
            if (!is_array($rawTasks) || !array_is_list($rawTasks) || count($rawTasks) < self::MIN_TASKS || ($enforceMaximum && count($rawTasks) > self::MAX_TASKS)) {
                throw new \InvalidArgumentException('Roadmap phase requires one to ten tasks.');
            }
            $tasks = [];
            $taskIds = [];
            foreach ($rawTasks as $taskIndex => $rawTask) {
                if (!is_array($rawTask)) {
                    throw new \InvalidArgumentException('Roadmap task is invalid.');
                }
                self::assertAllowedFields($rawTask, self::TASK_FIELDS, 'Roadmap task');
                $taskId = self::uuid($rawTask['task_id'] ?? null, 'Roadmap task id is invalid.');
                if (isset($taskIds[$taskId])) {
                    throw new \InvalidArgumentException('Roadmap task ids must be unique.');
                }
                if (isset($allTaskIds[$taskId])) {
                    throw new \InvalidArgumentException('Roadmap task ids must be globally unique.');
                }
                $taskIds[$taskId] = true;
                $allTaskIds[$taskId] = true;
                $milestone = self::integer($rawTask['milestone_day'] ?? null, 'Roadmap task milestone is invalid.');
                $minimumDay = $startDay === 0 ? 1 : $startDay;
                if ($milestone < $minimumDay || $milestone > $endDay) {
                    throw new \InvalidArgumentException('Roadmap task milestone must stay inside its phase.');
                }
                $minutes = self::integer($rawTask['estimated_minutes'] ?? null, 'Roadmap task duration is invalid.');
                if ($minutes < 5 || $minutes > 1440) {
                    throw new \InvalidArgumentException('Roadmap task duration must be between 5 and 1440 minutes.');
                }
                $tasks[] = [
                    'task_id' => $taskId,
                    'position' => $taskIndex + 1,
                    'title' => self::copy($rawTask['title'] ?? null, self::LIMITS['task_title'], 'Roadmap task title is invalid.'),
                    'description' => self::copy($rawTask['description'] ?? null, self::LIMITS['task_description'], 'Roadmap task description is invalid.'),
                    'milestone_day' => $milestone,
                    'estimated_minutes' => $minutes,
                ];
            }

            $phases[] = [
                'phase_id' => $phaseId,
                'position' => $position,
                'start_day' => $startDay,
                'end_day' => $endDay,
                'code' => $code,
                'title' => self::copy($rawPhase['title'] ?? null, self::LIMITS['phase_title'], 'Roadmap phase title is invalid.'),
                'goal' => self::copy($rawPhase['goal'] ?? null, self::LIMITS['phase_goal'], 'Roadmap phase goal is invalid.'),
                'skill_focus' => self::copy($rawPhase['skill_focus'] ?? null, self::LIMITS['phase_fact'], 'Roadmap phase skill focus is invalid.'),
                'deliverable' => self::copy($rawPhase['deliverable'] ?? null, self::LIMITS['phase_fact'], 'Roadmap phase deliverable is invalid.'),
                'effort_label' => self::copy($rawPhase['effort_label'] ?? null, self::LIMITS['phase_fact'], 'Roadmap phase effort is invalid.'),
                'metric_label' => self::copy($rawPhase['metric_label'] ?? null, self::LIMITS['phase_fact'], 'Roadmap phase metric is invalid.'),
                'tasks' => $tasks,
            ];
        }

        return new self(['phases' => $phases]);
    }

    /** @return array{phases:list<array<string,mixed>>} */
    public function toArray(): array
    {
        return $this->draft;
    }

    public function hash(): string
    {
        return hash('sha256', (string) json_encode($this->draft, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function assertSameStructure(self $candidate): void
    {
        if ($this->structure() !== $candidate->structure()) {
            throw new \InvalidArgumentException('AI refinement changed the roadmap structure.');
        }
    }

    public function assertRetainedFrom(self $base): void
    {
        $basePhases = $base->draft['phases'];
        $baseTaskPhases = [];
        foreach ($basePhases as $basePhase) {
            foreach ($basePhase['tasks'] as $baseTask) {
                $baseTaskPhases[$baseTask['task_id']] = $basePhase['phase_id'];
            }
        }
        foreach ($this->draft['phases'] as $index => $phase) {
            $basePhase = $basePhases[$index] ?? null;
            if (!is_array($basePhase)
                || $phase['phase_id'] !== $basePhase['phase_id']
                || $phase['position'] !== $basePhase['position']
                || $phase['start_day'] !== $basePhase['start_day']
                || $phase['end_day'] !== $basePhase['end_day']
                || $phase['code'] !== $basePhase['code']) {
                throw new \InvalidArgumentException('Roadmap draft changed an immutable phase.');
            }
            foreach ($phase['tasks'] as $task) {
                $ownerPhaseId = $baseTaskPhases[$task['task_id']] ?? null;
                if ($ownerPhaseId !== null && $ownerPhaseId !== $phase['phase_id']) {
                    throw new \InvalidArgumentException('Roadmap draft moved a retained task between phases.');
                }
            }
        }
    }

    /** @param array<string,mixed> $task */
    public function storageTitle(array $task): string
    {
        $title = self::copy($task['title'] ?? null, self::LIMITS['task_title'], 'Roadmap task title is invalid.');
        $title = trim((string) preg_replace('/\s*\(Mốc\s+\d+\s+ngày\)\s*$/iu', '', $title));
        $milestone = self::integer($task['milestone_day'] ?? null, 'Roadmap task milestone is invalid.');
        return sprintf('%s (Mốc %d ngày)', $title, $milestone);
    }

    /** @return list<array<string,mixed>> */
    private function structure(): array
    {
        return array_map(static fn (array $phase): array => [
            'phase_id' => $phase['phase_id'],
            'position' => $phase['position'],
            'start_day' => $phase['start_day'],
            'end_day' => $phase['end_day'],
            'code' => $phase['code'],
            'tasks' => array_map(static fn (array $task): array => [
                'task_id' => $task['task_id'],
                'position' => $task['position'],
                'milestone_day' => $task['milestone_day'],
                'estimated_minutes' => $task['estimated_minutes'],
            ], $phase['tasks']),
        ], $this->draft['phases']);
    }

    /** @param array<string,mixed> $payload @param list<string> $allowed */
    private static function assertAllowedFields(array $payload, array $allowed, string $label): void
    {
        foreach (array_keys($payload) as $field) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                throw new \InvalidArgumentException("{$label} contains an unknown field.");
            }
        }
    }

    private static function copy(mixed $value, int $maximum, string $message): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException($message);
        }
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);
        $normalized = (string) preg_replace('/[\t ]+/u', ' ', $normalized);
        $normalized = trim($normalized);
        if ($normalized === '' || mb_strlen($normalized) > $maximum) {
            throw new \InvalidArgumentException($message);
        }
        return $normalized;
    }

    private static function integer(mixed $value, string $message): int
    {
        if (is_int($value)) return $value;
        if (is_string($value) && preg_match('/\A\d+\z/', $value) === 1) return (int) $value;
        throw new \InvalidArgumentException($message);
    }

    private static function uuid(mixed $value, string $message): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', $value) !== 1) {
            throw new \InvalidArgumentException($message);
        }
        return $value;
    }
}
