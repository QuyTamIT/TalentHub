<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Domain;

final class RoadmapTask
{
    /** @var array<string,mixed> */
    private readonly array $action;
    /** @var list<string> */
    private readonly array $evidenceReferenceIds;

    /** @param array<string,mixed> $action @param list<string> $evidenceReferenceIds */
    public function __construct(
        private readonly int $position,
        private readonly string $title,
        private readonly string $description,
        private readonly int $estimatedMinutes,
        array $action,
        array $evidenceReferenceIds,
    ) {
        if ($position < 1 || $position > 5) {
            throw new \InvalidArgumentException('Roadmap task position is invalid.');
        }
        if (trim($title) === '' || trim($description) === '') {
            throw new \InvalidArgumentException('Roadmap task copy is required.');
        }
        if ($estimatedMinutes < 5 || $estimatedMinutes > 1440) {
            throw new \InvalidArgumentException('Roadmap task estimated minutes are invalid.');
        }
        $type = $action['type'] ?? null;
        if (!is_string($type) || !in_array($type, ['self_task', 'register_activity'], true)) {
            throw new \InvalidArgumentException('Roadmap action type is unsupported.');
        }
        $allowedFields = $type === 'self_task' ? ['type'] : ['type', 'activity_source_id'];
        $actualFields = array_keys($action);
        sort($allowedFields, SORT_STRING);
        sort($actualFields, SORT_STRING);
        if ($actualFields !== $allowedFields) {
            throw new \InvalidArgumentException('Roadmap action fields are invalid.');
        }
        if ($type === 'register_activity'
            && (!is_string($action['activity_source_id'] ?? null)
                || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $action['activity_source_id']) !== 1)) {
            throw new \InvalidArgumentException('Roadmap activity source id is invalid.');
        }
        $this->action = $action;
        $this->evidenceReferenceIds = self::normalizeEvidence($evidenceReferenceIds);
    }

    public function position(): int { return $this->position; }
    public function title(): string { return $this->title; }
    public function description(): string { return $this->description; }
    public function estimatedMinutes(): int { return $this->estimatedMinutes; }
    /** @return array<string,mixed> */ public function action(): array { return $this->action; }
    /** @return list<string> */ public function evidenceReferenceIds(): array { return $this->evidenceReferenceIds; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'title' => $this->title,
            'description' => $this->description,
            'estimated_minutes' => $this->estimatedMinutes,
            'action' => $this->action,
            'evidence_ref_ids' => $this->evidenceReferenceIds,
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
