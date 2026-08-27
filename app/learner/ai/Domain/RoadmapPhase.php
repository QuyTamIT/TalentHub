<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Domain;

final class RoadmapPhase
{
    /** @var list<string> */
    private readonly array $evidenceReferenceIds;
    /** @var list<RoadmapTask> */
    private readonly array $tasks;

    /** @param list<string> $evidenceReferenceIds @param list<RoadmapTask> $tasks */
    public function __construct(
        private readonly int $position,
        private readonly int $startDay,
        private readonly int $endDay,
        private readonly string $code,
        private readonly string $title,
        private readonly string $goal,
        private readonly string $skillFocus,
        private readonly string $deliverable,
        private readonly string $effortLabel,
        private readonly string $metricLabel,
        array $evidenceReferenceIds,
        array $tasks,
    ) {
        if ($position < 1 || $position > 3 || $startDay < 0 || $endDay > 90 || $startDay >= $endDay) {
            throw new \InvalidArgumentException('Roadmap phase range is invalid.');
        }
        if (preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $code) !== 1) {
            throw new \InvalidArgumentException('Roadmap phase code is invalid.');
        }
        foreach ([$title, $goal, $skillFocus, $deliverable, $effortLabel, $metricLabel] as $copy) {
            if (trim($copy) === '') {
                throw new \InvalidArgumentException('Roadmap phase copy is required.');
            }
        }
        foreach ($tasks as $task) {
            if (!$task instanceof RoadmapTask) {
                throw new \InvalidArgumentException('Roadmap phase tasks are invalid.');
            }
        }
        if (count($tasks) < 3 || count($tasks) > 5) {
            throw new \InvalidArgumentException('Roadmap phase requires three to five tasks.');
        }
        $positions = array_map(static fn (RoadmapTask $task): int => $task->position(), $tasks);
        if ($positions !== range(1, count($tasks))) {
            throw new \InvalidArgumentException('Roadmap task positions are invalid.');
        }
        $this->evidenceReferenceIds = self::normalizeEvidence($evidenceReferenceIds);
        $this->tasks = array_values($tasks);
    }

    public function position(): int { return $this->position; }
    public function startDay(): int { return $this->startDay; }
    public function endDay(): int { return $this->endDay; }
    public function code(): string { return $this->code; }
    public function title(): string { return $this->title; }
    public function goal(): string { return $this->goal; }
    public function skillFocus(): string { return $this->skillFocus; }
    public function deliverable(): string { return $this->deliverable; }
    public function effortLabel(): string { return $this->effortLabel; }
    public function metricLabel(): string { return $this->metricLabel; }
    /** @return list<string> */ public function evidenceReferenceIds(): array { return $this->evidenceReferenceIds; }
    /** @return list<RoadmapTask> */ public function tasks(): array { return $this->tasks; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'start_day' => $this->startDay,
            'end_day' => $this->endDay,
            'code' => $this->code,
            'title' => $this->title,
            'goal' => $this->goal,
            'skill_focus' => $this->skillFocus,
            'deliverable' => $this->deliverable,
            'effort_label' => $this->effortLabel,
            'metric_label' => $this->metricLabel,
            'evidence_ref_ids' => $this->evidenceReferenceIds,
            'tasks' => array_map(static fn (RoadmapTask $task): array => $task->toArray(), $this->tasks),
        ];
    }

    /** @param list<string> $references @return list<string> */
    private static function normalizeEvidence(array $references): array
    {
        $normalized = [];
        foreach ($references as $reference) {
            if (!is_string($reference) || trim($reference) === '') {
                throw new \InvalidArgumentException('Roadmap evidence references are invalid.');
            }
            $normalized[trim($reference)] = true;
        }
        if ($normalized === []) {
            throw new \InvalidArgumentException('Roadmap evidence references are required.');
        }
        $result = array_keys($normalized);
        sort($result, SORT_STRING);
        return $result;
    }
}
