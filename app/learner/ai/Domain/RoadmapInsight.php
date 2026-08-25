<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Domain;

final class RoadmapInsight
{
    private const CATEGORIES = ['strength', 'improvement', 'potential'];

    /** @var list<string> */
    private readonly array $evidenceReferenceIds;

    /** @param list<string> $evidenceReferenceIds */
    public function __construct(
        private readonly string $category,
        private readonly string $title,
        private readonly string $summary,
        array $evidenceReferenceIds,
    ) {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('Roadmap insight category is invalid.');
        }
        if (trim($title) === '' || trim($summary) === '') {
            throw new \InvalidArgumentException('Roadmap insight copy is required.');
        }
        $this->evidenceReferenceIds = self::normalizeEvidence($evidenceReferenceIds);
    }

    public function category(): string { return $this->category; }
    public function title(): string { return $this->title; }
    public function summary(): string { return $this->summary; }
    /** @return list<string> */ public function evidenceReferenceIds(): array { return $this->evidenceReferenceIds; }

    /** @return array{category:string,title:string,summary:string,evidence_ref_ids:list<string>} */
    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'summary' => $this->summary,
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
